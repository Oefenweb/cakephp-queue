<?php
namespace Queue\Shell;

use Cake\Console\ConsoleOptionParser;
use Cake\Console\Shell;
use Cake\Core\Configure;
use Cake\I18n\Number;
use Cake\Log\Log;
use Cake\Utility\Inflector;
use Cake\Utility\Text;
use Queue\Model\Entity\QueuedTask;
use Queue\Model\QueueException;
use Queue\Queue\Config;
use Queue\Queue\TaskFinder;
use Queue\Shell\Task\AddInterface;
use Queue\Shell\Task\QueueTaskInterface;
use RuntimeException;
use Throwable;

declare(ticks=1);

/**
 * Main shell to init and run queue workers.
 *
 * @author MGriesbach@gmail.com
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link http://github.com/MSeven/cakephp_queue
 * @property \Queue\Model\Table\QueuedTasksTable $QueuedTasks
 */
class QueueShell extends Shell
{

    /**
     *
     * @var string
     */
    public $modelClass = 'Queue.QueuedTasks';

    /**
     *
     * @var array|null
     */
    protected $_taskConf;

    /**
     *
     * @var int
     */
    protected $_time = 0;

    /**
     *
     * @var bool
     */
    protected $_exit = false;

    /**
     * Overwrite shell initialize to dynamically load all Queue Related Tasks.
     *
     * @return void
     */
    public function initialize(): void
    {
        $taskFinder = new TaskFinder();
        $this->tasks = $taskFinder->allAppAndPluginTasks();

        parent::initialize();
    }

    /**
     *
     * @return void
     */
    public function startup(): void
    {
        if ($this->param('quiet')) {
            $this->interactive = false;
        }

        parent::startup();
    }

    /**
     *
     * @return string
     */
    public function getDescription(): string
    {
        $tasks = [];
        foreach ($this->taskNames as $loadedTask) {
            $tasks[] = "\t" . '* ' . $this->_taskName($loadedTask);
        }
        $tasks = implode(PHP_EOL, $tasks);

        $text = <<<TEXT
Simple and minimalistic job queue (or deferred-task) system.

Available Tasks:
$tasks
TEXT;

        return $text;
    }

    /**
     * Look for a Queue Task of hte passed name and try to call add() on it.
     * A QueueTask may provide an add function to enable the user to create new jobs via commandline.
     *
     * @return void
     */
    public function add(): void
    {
        if (count($this->args) < 1) {
            $this->out('Please call like this:');
            $this->out('       bin/cake queue add <taskname>');
            $this->_displayAvailableTasks();

            return;
        }

        $name = Inflector::camelize($this->args[0]);
        if (in_array('Queue' . $name, $this->taskNames, true)) {
            /** @var \Queue\Shell\Task\QueueTask|\Queue\Shell\Task\AddInterface $task */
            $task = $this->{'Queue' . $name};
            if (!($task instanceof AddInterface)) {
                $this->abort('This task does not support adding via CLI call');
            }
            $task->add();
        } else {
            $this->out('Error: Task not found: ' . $name);
            $this->_displayAvailableTasks();
        }
    }

    /**
     * Output the task without Queue or Task
     * example: QueueImageTask becomes Image on display
     *
     * @param string $task Task name
     * @return string Cleaned task name
     */
    protected function _taskName($task): string
    {
        if (strpos($task, 'Queue') === 0) {
            return substr($task, 5) ?: '';
        }

        return $task;
    }

    /**
     * Run a QueueWorker loop.
     * Runs a Queue Worker process which will try to find unassigned jobs in the queue
     * which it may run and try to fetch and execute them.
     *
     * @return void
     */
    public function runworker(): void
    {
        // Enable Garbage Collector (PHP >= 5.3)
        if (function_exists('gc_enable')) {
            gc_enable();
        }
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [
                &$this,
                '_exit',
            ]);
            pcntl_signal(SIGINT, [
                &$this,
                '_exit',
            ]);
            pcntl_signal(SIGTSTP, [
                &$this,
                '_exit',
            ]);
            pcntl_signal(SIGQUIT, [
                &$this,
                '_exit',
            ]);
        }
        $this->_exit = false;

        $startTime = time();

        $typesParam = $this->param('type');
        $types = is_string($typesParam) ? $this->_stringToArray($typesParam) : [];

        while (!$this->_exit) {
            $this->out(__d('queue', 'Looking for a job.'), 1, Shell::VERBOSE);

            $queuedTask = $this->QueuedTasks->requestJob($this->_getTaskConf(), $types);

            if ($queuedTask) {
                $this->runJob($queuedTask);
            } elseif (Configure::read('Queue.exitWhenNothingToDo')) {
                $this->out(__d('queue', 'nothing to do, exiting.'));
                $this->_exit = true;
            } else {
                $this->out(__d('queue', 'nothing to do, sleeping.'));
                sleep(Config::sleepTime());
            }

            // check if we are over the maximum runtime and end processing if so.
            if (Configure::readOrFail('Queue.workerMaxRuntime') && (time() - $startTime) >= Configure::readOrFail('Queue.workerMaxRuntime')) {
                $this->_exit = true;
                $this->out(__d('queue', 'Reached runtime of ' . (time() - $startTime) . ' Seconds (Max ' . Configure::readOrFail('Queue.workerMaxRuntime') . '), terminating.'));
            }
            if (mt_rand(0, 100) > (100 - Config::gcprob())) {
                $this->out(__d('queue', 'Performing old job cleanup.'));
                $this->QueuedTasks->cleanOldJobs($this->_getTaskConf());
            }
            $this->hr();
        }

        if ($this->param('verbose')) {
            $this->_log('endworker');
        }
    }

    /**
     *
     * @param \Queue\Model\Entity\QueuedTask $queuedTask Queued task
     * @return void
     */
    protected function runJob(QueuedTask $queuedTask): void
    {
        $this->out('Running Job of type "' . $queuedTask->task . '"');
        $this->_log('job ' . $queuedTask->task . ', id ' . $queuedTask->id, null, false);
        $taskName = 'Queue' . $queuedTask->task;

        try {
            $this->_time = time();

            $data = unserialize($queuedTask->data);
            /** @var \Queue\Shell\Task\QueueTask $task */
            $task = $this->{$taskName};
            if (!$task instanceof QueueTaskInterface) {
                throw new RuntimeException('Task must implement ' . QueueTaskInterface::class);
            }

            /* @phan-suppress-next-line PhanTypeVoidAssignment */
            $return = $task->run((array)$data, $queuedTask->id);
            if ($return !== null) {
                trigger_error('run() should be void and throw exception in error case now.', E_USER_DEPRECATED);
            }
            $failureMessage = $taskName . ' failed';
        } catch (Throwable $e) {
            $return = false;

            $failureMessage = get_class($e) . ': ' . $e->getMessage();
            if (!($e instanceof QueueException)) {
                $failureMessage .= "\n" . $e->getTraceAsString();
            }

            $this->_logError($taskName . ' (job ' . $queuedTask->id . ')' . "\n" . $failureMessage);
        }

        if ($return === false) {
            $this->QueuedTasks->markJobFailed($queuedTask, $failureMessage);
            $failedStatus = $this->QueuedTasks->getFailedStatus($queuedTask, $this->_getTaskConf());
            $this->_log('job ' . $queuedTask->task . ', id ' . $queuedTask->id . ' failed and ' . $failedStatus);
            $this->out('Job did not finish, ' . $failedStatus . ' after try ' . $queuedTask->failed_count . '.');

            return;
        }

        $this->QueuedTasks->markJobDone($queuedTask);
        $this->out('Job Finished.');
    }

    /**
     * Manually trigger a Finished job cleanup.
     *
     * @return void
     */
    public function clean(): void
    {
        $this->out(__d('queue', 'Deleting old completed jobs, that have had cleanup timeout.'));
        $this->QueuedTasks->cleanOldJobs($this->_getTaskConf());
    }

    /**
     * Manually trigger a Failed job cleanup.
     *
     * @return void
     */
    //@codingStandardsIgnoreLine
    public function clean_failed(): void
    {
        $this->out(__d('queue', 'Deleting failed jobs, that have had maximum worker retries.'));
        $this->QueuedTasks->cleanFailedJobs($this->_getTaskConf());
    }

    /**
     * Display current settings
     *
     * @return void
     */
    public function settings(): void
    {
        $this->out('Current Settings:');
        $conf = (array)Configure::read('Queue');
        foreach ($conf as $key => $val) {
            if ($val === false) {
                $val = 'no';
            }
            if ($val === true) {
                $val = 'yes';
            }
            $this->out('* ' . $key . ': ' . print_r($val, true));
        }

        $this->out();
    }

    /**
     * Display some statistics about Finished Jobs.
     *
     * @return void
     */
    public function stats(): void
    {
        $this->out('Jobs currently in the queue:');

        $types = $this->QueuedTasks->getTypes()->toArray();
        foreach ($types as $type) {
            $this->out('      ' . str_pad($type, 20, ' ', STR_PAD_RIGHT) . ': ' . $this->QueuedTasks->getLength($type));
        }
        $this->hr();
        $this->out('Total unfinished jobs: ' . $this->QueuedTasks->getLength());
        $this->hr();
        $this->out('Finished job statistics:');
        $data = $this->QueuedTasks->getStats();
        foreach ($data as $item) {
            $this->out(' ' . $item['task'] . ': ');
            $this->out('   Finished Jobs in Database: ' . $item['num']);
            $this->out('   Average Job existence    : ' . str_pad(Number::precision($item['alltime']), 8, ' ', STR_PAD_LEFT) . 's');
            $this->out('   Average Execution delay  : ' . str_pad(Number::precision($item['fetchdelay']), 8, ' ', STR_PAD_LEFT) . 's');
            $this->out('   Average Execution time   : ' . str_pad(Number::precision($item['runtime']), 8, ' ', STR_PAD_LEFT) . 's');
        }
    }

    /**
     * Get option parser method to parse commandline options
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function getOptionParser(): ConsoleOptionParser
    {
        $subcommandParser = [
            'options' => [
                /*
                 * 'dry-run'=> array(
                 * 'short' => 'd',
                 * 'help' => 'Dry run the update, no jobs will actually be added.',
                 * 'boolean' => true
                 * ),
                 */
            ],
        ];
        $subcommandParserFull = $subcommandParser;
        $subcommandParserFull['options']['type'] = [
            'short' => 't',
            'help' => 'Type (comma separated list possible)',
            'default' => null,
        ];

        return parent::getOptionParser()->setDescription($this->getDescription())
            ->addSubcommand('clean', [
                'help' => 'Remove old jobs (cleanup)',
                'parser' => $subcommandParser,
            ])
            ->addSubcommand('clean_failed', [
                'help' => 'Remove old failed jobs (cleanup)',
                'parser' => $subcommandParser,
            ])
            ->addSubcommand('add', [
                'help' => 'Add Job',
                'parser' => $subcommandParser,
            ])
            ->addSubcommand('stats', [
                'help' => 'Stats',
                'parser' => $subcommandParserFull,
            ])
            ->addSubcommand('settings', [
                'help' => 'Settings',
                'parser' => $subcommandParserFull,
            ])
            ->addSubcommand('runworker', [
                'help' => 'Run Worker',
                'parser' => $subcommandParserFull,
            ]);
    }

    /**
     * Timestamped log.
     *
     * @param string $message Log type
     * @param string|null $pid PID of the process
     * @param bool $addDetails Details
     * @return void
     */
    protected function _log($message, $pid = null, $addDetails = true): void
    {
        if (!Configure::read('Queue.log')) {
            return;
        }

        if ($addDetails) {
            $timeNeeded = $this->_timeNeeded();
            $memoryUsage = $this->_memoryUsage();
            $message .= ' [' . $timeNeeded . ', ' . $memoryUsage . ']';
        }

        if ($pid) {
            $message .= ' (pid ' . $pid . ')';
        }
        Log::write('info', $message, [
            'scope' => 'queue',
        ]);
    }

    /**
     *
     * @param string $message Message
     * @param string|null $pid PID of the process
     * @return void
     */
    protected function _logError($message, $pid = null): void
    {
        $timeNeeded = $this->_timeNeeded();
        $memoryUsage = $this->_memoryUsage();
        $message .= ' [' . $timeNeeded . ', ' . $memoryUsage . ']';

        if ($pid) {
            $message .= ' (pid ' . $pid . ')';
        }

        Log::write('error', $message);
    }

    /**
     * Returns a List of available QueueTasks and their individual configurations.
     *
     * @return array
     */
    protected function _getTaskConf(): array
    {
        if (!is_array($this->_taskConf)) {
            $this->_taskConf = [];
            foreach ($this->tasks as $task) {
                list ($pluginName, $taskName) = pluginSplit($task);

                $this->_taskConf[$taskName]['name'] = substr($taskName, 5);
                $this->_taskConf[$taskName]['plugin'] = $pluginName;
                if (property_exists($this->{$taskName}, 'timeout')) {
                    $this->_taskConf[$taskName]['timeout'] = $this->{$taskName}->timeout;
                } else {
                    $this->_taskConf[$taskName]['timeout'] = Config::defaultWorkerTimeout();
                }
                if (property_exists($this->{$taskName}, 'retries')) {
                    $this->_taskConf[$taskName]['retries'] = $this->{$taskName}->retries;
                } else {
                    $this->_taskConf[$taskName]['retries'] = Config::defaultWorkerRetries();
                }
                if (property_exists($this->{$taskName}, 'cleanupTimeout')) {
                    $this->_taskConf[$taskName]['cleanupTimeout'] = $this->{$taskName}->cleanupTimeout;
                } else {
                    $this->_taskConf[$taskName]['cleanupTimeout'] = Config::cleanupTimeout();
                }
            }
        }

        return $this->_taskConf;
    }

    /**
     * Signal handling to queue worker for clean shutdown
     *
     * @param int $signal The signal
     * @return void
     */
    protected function _exit($signal): void
    {
        $this->out(__d('queue', 'Caught signal {0}, exiting.', [$signal]));
        $this->_exit = true;
    }

    /**
     *
     * @return void
     */
    protected function _displayAvailableTasks(): void
    {
        $this->out('Available Tasks:');
        foreach ($this->taskNames as $loadedTask) {
            $this->out("\t" . '* ' . $this->_taskName($loadedTask));
        }
    }

    /**
     *
     * @return string Memory usage in MB.
     */
    protected function _memoryUsage(): string
    {
        $limit = ini_get('memory_limit');

        $used = number_format(memory_get_peak_usage(true) / (1024 * 1024), 0) . 'MB';
        if ($limit !== '-1') {
            $used .= '/' . $limit;
        }

        return $used;
    }

    /**
     *
     * @return string
     */
    protected function _timeNeeded(): string
    {
        $diff = $this->_time() - $this->_time($this->_time);
        $seconds = max($diff, 1);

        return $seconds . 's';
    }

    /**
     *
     * @param int|null $providedTime Provided time
     *
     * @return int
     */
    protected function _time($providedTime = null): int
    {
        if ($providedTime !== null) {
            return $providedTime;
        }

        return time();
    }

    /**
     *
     * @param string|null $param String to convert
     * @return array
     */
    protected function _stringToArray($param): array
    {
        if (!$param) {
            return [];
        }

        $array = Text::tokenize($param);
        if (is_string($array)) {
            return [$array];
        }

        return array_filter($array);
    }
}
