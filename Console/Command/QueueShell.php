<?php
App::uses('QueuedTask', 'Model');
App::uses('AppShell', 'Console/Command');

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
	private $__taskConf;

/**
 * Overwrite shell initialize to dynamically load all queue related tasks.
 *
 * @return void
 */
	public function initialize() {
		// Check for tasks inside plugins and application
		$plugins = App::objects('plugin');
		$plugins[] = '';
		foreach ($plugins as $plugin) {
			if (!empty($plugin)) $plugin .= '.';
			foreach (App::objects($plugin . 'Console/Command/Task') as $task) {
				if (strpos($task, 'Queue') === 0 && substr($task, -4) === 'Task') {
					$this->Tasks->load($plugin . substr($task, 0, -4));
					$this->tasks[] = substr($task, 0, -4);
				}
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
		if (in_array($this->args[0], $this->taskNames)) {
			$this->{$this->args[0]}->add();
		}
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

		$exit = false;
		$starttime = time();
		while (!$exit) {
			if ($this->params['verbose']) {
				$this->out(__d('queue', 'Looking for a job.'));
			}
			$data = $this->QueuedTask->requestJob($this->__getTaskConf());
			if ($this->QueuedTask->exit === true) {
				$exit = true;
			} else {
				if ($data !== false) {
					$jobId = $data['id'];
					$taskname = 'Queue' . $data['task'];
					$this->out(__d('queue', 'Running job of task \'%s\' \'%d\'.', $data['task'], $jobId));

					$startTime = time();
					$return = $this->{$taskname}->run(unserialize($data['data']));
					$took = time() - $startTime;
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
					$exit = true;
				} else {
					if ($this->params['verbose']) {
						$this->out(__d('queue', 'Nothing to do, sleeping for %d second(s).', Configure::read('Queue.sleepTime')));
					}
					sleep(Configure::read('Queue.sleepTime'));
				}

				// Check if we are over the maximum runtime and end processing if so.
				if (Configure::read('Queue.workerMaxRuntime') != 0
						&& (time() - $starttime) >= Configure::read('Queue.workerMaxRuntime')
				) {
					$exit = true;
					$this->out(__d('queue',
						'Reached runtime of %s seconds (max. %s), terminating.',
						(time() - $starttime),
						Configure::read('Queue.workerMaxRuntime')
					));
				}

				if ($exit || rand(0, 100) > (100 - Configure::read('Queue.gcprop'))) {
					$this->out(__d('queue', 'Performing old job cleanup.'));
					$this->QueuedTask->cleanOldJobs();
				}
			}
		}
	}

/**
 * Triggers manual job cleanup.
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
 * Displays some statistics about finished Jobs.
 *
 * @return void
 */
	public function stats() {
		$this->out(__d('queue', 'Jobs currenty in the queue:'));

		$types = $this->QueuedTask->getTypes();
		foreach ($types as $type) {
			$this->out('      ' . str_pad($type, 20, ' ', STR_PAD_RIGHT) . ': ' . $this->QueuedTask->getLength($type));
		}

		$this->hr();
		$this->out(__d('queue', 'Total unfinished jobs      : %s', $this->QueuedTask->getLength()));
		$this->hr();
		$this->out(__d('queue', 'Finished job statistics:'));

		$data = $this->QueuedTask->getStats();
		foreach ($data as $item) {
			$this->out(__d('queue', ' %s: ', $item['QueuedTask']['task']));
			$this->out(__d('queue', '   Finished jobs in database: %s', $item[0]['num']));
			$this->out(__d('queue', '   Average job existence    : %ss', $item[0]['alltime']));
			$this->out(__d('queue', '   Average execution delay  : %ss', $item[0]['fetchdelay']));
			$this->out(__d('queue', '   Average execution time   : %ss', $item[0]['runtime']));
		}
	}

/**
 * Returns a list of available queue tasks and their individual configurations.
 *
 * @return array A list of available queue tasks and their individual configurations
 */
	private function __getTaskConf() {
		if (!is_array($this->__taskConf)) {
			$this->__taskConf = array();
			foreach ($this->tasks as $task) {
				$this->__taskConf[$task]['name'] = $task;
				if (property_exists($this->{$task}, 'timeout')) {
					$this->__taskConf[$task]['timeout'] = $this->{$task}->timeout;
				} else {
					$this->__taskConf[$task]['timeout'] = Configure::read('Queue.defaultWorkerTimeout');
				}
				if (property_exists($this->{$task}, 'retries')) {
					$this->__taskConf[$task]['retries'] = $this->{$task}->retries;
				} else {
					$this->__taskConf[$task]['retries'] = Configure::read('Queue.defaultWorkerRetries');
				}
			}
		}

		return $this->__taskConf;
	}

}
