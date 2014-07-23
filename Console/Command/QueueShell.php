<?php
App::uses('Folder', 'Utility');
App::uses('QueuedTask', 'Model');
App::uses('AppShell', 'Console/Command');

declare(ticks = 1);

/**
 * Queue Shell
 *
 * @property Queue.QueuedTask $QueuedTask
 */
class QueueShell extends AppShell {

/**
 * An array of names of models to load.
 *
 * @var array
 */
	public $uses = array('Queue.QueuedTask');

/**
 * A list of available queue tasks and their individual configurations.
 *
 * @var array
 */
	protected $_taskConf;

/**
 * Indicates whether or not the worker should exit on next the iteration.
 *
 * @var boolean
 */
	private $__exit;

/**
 * Overwrite shell initialize to dynamically load all queue related tasks.
 *
 * @return void
 */
	public function initialize() {
		// Check for tasks inside plugins and application
		$paths = App::path('Console/Command/Task');

		foreach ($paths as $path) {
			$Folder = new Folder($path);
			$res = array_merge($this->tasks, $Folder->find('Queue.*\.php'));
			foreach ($res as &$r) {
				$r = basename($r, 'Task.php');
			}

			$this->tasks = $res;
		}

		$plugins = App::objects('plugin');
		foreach ($plugins as $plugin) {
			$pluginPaths = App::path('Console/Command/Task', $plugin);
			foreach ($pluginPaths as $pluginPath) {
				$Folder = new Folder($pluginPath);
				$res = $Folder->find('Queue.*Task\.php');
				foreach ($res as &$r) {
					$r = $plugin . '.' . basename($r, 'Task.php');
				}

				$this->tasks = array_merge($this->tasks, $res);
			}
		}

		$conf = Configure::read('Queue');
		if (!is_array($conf)) {
			$conf = array();
		}

		// Merge with default configuration vars.
		Configure::write('Queue', array_merge(
				array(
					'sleepTime' => 10,
					'gcprop' => 10,
					'defaultWorkerTimeout' => 2 * MINUTE,
					'defaultWorkerRetries' => 4,
					'workerMaxRuntime' => 0,
					'cleanupTimeout' => DAY,
					'exitWhenNothingToDo' => false
				),
				$conf
			)
		);

		parent::initialize();
	}

/**
 * Gets and configures the option parser.
 *
 * @return ConsoleOptionParser
 */
	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser->addSubcommand('add', array(
			'help' => __d('queue', 'Tries to call the cli `add()` function on a task.'),
			'parser' => array(
				'description' => array(
					__d('queue', 'Tries to call the cli `add()` function on a task.'),
					__d('queue', 'Tasks may or may not provide this functionality.')
				),
				'arguments' => array(
					'taskname' => array(
						'help' => __d('queue', 'Name of the task.'),
						'required' => true,
						'choices' => $this->taskNames
					)
				)
			)
		))->addSubcommand('runworker', array(
			'help' => __d('queue', 'Run a queue worker.'),
			'parser' => array(
				'description' => array(__d('queue', 'Run a queue worker, which will look for a pending task it can execute.'))
			)
		))->addSubcommand('stats', array(
			'help' => __d('queue', 'Display general statistics.'),
			'parser' => array(
				'description' => __d('queue', 'Display general statistics.'),
			)
		))->addSubcommand('clean', array(
			'help' => __d('queue', 'Manually call cleanup function to delete task data of completed tasks.'),
			'parser' => array(
				'description' => __d('queue', 'Manually call cleanup function to delete task data of completed tasks.')
			)
		))->description(__d('queue', 'CakePHP Queue Plugin.'));

		return $parser;
	}

/**
 * Looks for a queue task of the passed name and try to call add() on it.
 *
 *	A queue task may provide an add function to enable the user to create new tasks via commandline.
 *
 * @return void
 */
	public function add() {
		$name = Inflector::camelize($this->args[0]);

		if (in_array($name, $this->taskNames)) {
			$this->{$name}->add();
		} elseif (in_array('Queue' . $name, $this->taskNames)) {
			$this->{'Queue' . $name}->add();
		} else {
			$this->out(__d('queue', 'Error: Task not Found: %s', $name));
			$this->out('Available Tasks:');
			foreach ($this->taskNames as $loadedTask) {
				$this->out(' * ' . $this->_taskName($loadedTask));
			}
		}
	}

/**
 * Output the task without Queue or Task
 * example: QueueImageTask becomes Image on display
 *
 * @param string $task A task name
 * @return string Cleaned task name
 */
	protected function _taskName($task) {
		if (strpos($task, 'Queue') === 0) {
			return substr($task, 5);
		}

		return $task;
	}

/**
 * Run a queue worker loop.
 *
 *	Runs a queue worker process which will try to find unassigned tasks in the queue
 *	which it may run and try to fetch and execute them.
 *
 * @return void
 */
	public function runworker() {
		// Enable garbage collector (PHP >= 5.3)
		if (function_exists('gc_enable')) {
			gc_enable();
		}

		// Register signal handler(s)
		if (function_exists('pcntl_signal')) {
			pcntl_signal(SIGTERM, array($this, 'signalHandler'));
			pcntl_signal(SIGINT, array($this, 'signalHandler'));
		}

		$this->__exit = false;

		$workerStartTime = time();
		while (!$this->__exit) {
			$this->out(__d('queue', 'Looking for a job.'), 1, Shell::VERBOSE);

			$data = $this->QueuedTask->requestJob($this->_getTaskConf());
			if ($this->QueuedTask->exit === true) {
				$this->__exit = true;
			} else {
				if ($data !== false) {
					$jobId = $data['id'];
					$taskname = 'Queue' . $data['task'];
					$this->out(__d('queue', 'Running job of task \'%s\' \'%d\'.', $data['task'], $jobId));

					$taskStartTime = time();
					$return = $this->{$taskname}->run(unserialize($data['data']));
					$took = time() - $taskStartTime;
					if ($return) {
						$this->QueuedTask->markJobDone($jobId);
						$this->out(
							__d(
								'queue',
								'Job \'%d\' finished (took %s).',
								$jobId,
								__dn('queue', '%d second', '%d seconds', $took, $took)
							)
						);
					} else {
						$failureMessage = null;
						if (isset($this->{$taskname}->failureMessage) && !empty($this->{$taskname}->failureMessage)) {
							$failureMessage = $this->{$taskname}->failureMessage;
						}
						$this->QueuedTask->markJobFailed($jobId, $failureMessage);
						$this->out(__d('queue', 'Job \'%d\' did not finish, requeued.', $jobId));
					}
				} elseif (Configure::read('Queue.exitWhenNothingToDo')) {
					$this->out(__d('queue', 'Nothing to do, exiting.'));
					$this->__exit = true;
				} else {
					$this->out(
						__d('queue', 'Nothing to do, sleeping for %d second(s).', Configure::read('Queue.sleepTime')),
						1, Shell::VERBOSE
					);

					sleep(Configure::read('Queue.sleepTime'));
				}

				// Check if we are over the maximum runtime and end processing if so.
				if (Configure::read('Queue.workerMaxRuntime') != 0
						&& (time() - $workerStartTime) >= Configure::read('Queue.workerMaxRuntime')
				) {
					$this->__exit = true;
					$this->out(__d('queue',
						'Reached runtime of %s seconds (max. %s), terminating.',
						(time() - $workerStartTime),
						Configure::read('Queue.workerMaxRuntime')
					));
				}

				if ($this->__exit || rand(0, 100) > (100 - Configure::read('Queue.gcprop'))) {
					$this->out(__d('queue', 'Performing old job cleanup.'));
					$this->QueuedTask->cleanOldJobs();
				}
			}
		}
	}

/**
 * Triggers manual job cleanup of completed jobs.
 *
 * @return void
 */
	public function clean() {
		$this->out(__d('queue',
			'Deleting old Jobs, that have finished before %s.',
			date('Y-m-d H:i:s', time() - Configure::read('Queue.cleanupTimeout'))
		));
		$this->QueuedTask->cleanOldJobs();
	}

/**
 * Triggers manual job cleanup of failed jobs.
 *
 * @return void
 */
	public function clean_failed() {
		$this->out(__d('queue', 'Deleting failed Jobs, that have had maximum worker retries.'));
		$this->QueuedTask->cleanFailedJobs($this->_getTaskConf());
	}

/**
 * Displays some statistics about finished Jobs.
 *
 * @return void
 */
	public function stats() {
		$this->hr();
		$this->out(__d('queue', 'Jobs currenty in the queue:'));
		$this->hr();

		$types = $this->QueuedTask->getTypes();
		foreach ($types as $type) {
			$this->out(sprintf('- %s: %s', $type, $this->QueuedTask->getLength($type)));
		}
		$this->out();

		$this->hr();
		$this->out(__d('queue', 'Total unfinished jobs: %s', $this->QueuedTask->getLength()));
		$this->hr();
		$this->out();

		$this->hr();
		$this->out(__d('queue', 'Finished job statistics:'));
		$this->hr();

		$data = $this->QueuedTask->getStats();
		foreach ($data as $item) {
			$this->out(sprintf('- %s: ', $item['QueuedTask']['task']));
			$this->out(sprintf('  - %s', __d('queue', 'Finished jobs in database: %s', $item[0]['num'])));
			$this->out(sprintf('  - %s', __d('queue', 'Average job existence: %ss', $item[0]['alltime'])));
			$this->out(sprintf('  - %s', __d('queue', 'Average execution delay: %ss', $item[0]['fetchdelay'])));
			$this->out(sprintf('  - %s', __d('queue', 'Average execution time: %ss', $item[0]['runtime'])));
			$this->out();
		}
	}

/**
 * Returns a list of available queue tasks and their individual configurations.
 *
 * @return array A list of available queue tasks and their individual configurations
 */
	protected function _getTaskConf() {
		if (!is_array($this->_taskConf)) {
			$this->_taskConf = array();
			foreach ($this->tasks as $task) {
				list($pluginName, $taskName) = pluginSplit($task);

				$this->_taskConf[$taskName]['name'] = substr($taskName, 5);
				$this->_taskConf[$taskName]['plugin'] = $pluginName;

				if (property_exists($this->{$taskName}, 'timeout')) {
					$this->_taskConf[$taskName]['timeout'] = $this->{$taskName}->timeout;
				} else {
					$this->_taskConf[$taskName]['timeout'] = Configure::read('Queue.defaultWorkerTimeout');
				}
				if (property_exists($this->{$taskName}, 'retries')) {
					$this->_taskConf[$taskName]['retries'] = $this->{$taskName}->retries;
				} else {
					$this->_taskConf[$taskName]['retries'] = Configure::read('Queue.defaultWorkerRetries');
				}
			}
		}

		return $this->_taskConf;
	}

/**
 * Signal handler (for SIGTERM and SIGINT signal)
 *
 * @param int $signalNumber A signal number
 * @return void
 */
	public function signalHandler($signalNumber) {
		switch($signalNumber) {
			case SIGTERM:
				$this->out(__d('queue', 'Caught %s signal, exiting.', sprintf('SIGTERM (%d)', SIGTERM)));

				$this->__exit = true;
				break;
			case SIGINT:
				$this->out(__d('queue', 'Caught %s signal, exiting.', sprintf('SIGINT (%d)', SIGINT)));

				$this->__exit = true;
				break;
		}
	}

}
