<?php
/**
 * Executes a Local command on the server.
 *
 * @property Queue.QueuedTask $QueuedTask
 */
class QueueExecuteTask extends Shell {

/**
 * An array of names of models to load.
 *
 * @var array
 */
	public $uses = array('Queue.QueuedTask');

/**
 * Timeout for run, after which the task is reassigned to a new worker.
 *
 * @var integer
 */
	public $timeout = 0;

/**
 * Number of times a failed instance of this task should be restarted before giving up.
 *
 * @var integer
 */
	public $retries = 0;

/**
 * Stores any failure messages triggered during run().
 *
 * @var string
 */
	public $failureMessage = '';

/**
 * Add functionality.
 *
 * 	Will create one example task job in the queue,
 * 	which later will be executed using run().
 *
 * @return void
 */
	public function add() {
		$this->out(__d('queue', 'CakePHP Queue Execute task.'));
		$this->hr();
		if (count($this->args) < 2) {
			$this->out(__d('queue', 'This will run an shell command on the Server.'));
			$this->out(__d('queue', 'The task is mainly intended to serve as a kind of buffer for programm calls from a CakePHP application.'));
			$this->out(' ');
			$this->out(__d('queue', 'Call like this:'));
			$this->out(__d('queue', '  cake queue add execute *command* *param1* *param2* ...'));
			$this->out(' ');
		} else {
			$data = array(
				'command' => $this->args[1],
				'params' => array_slice($this->args, 2)
			);
			if ($this->QueuedTask->createJob('execute', $data)) {
				$this->out(__d('queue', 'Job created'));
			} else {
				$this->err(__d('queue', 'Could not create Job'));
			}
		}
	}

/**
 * Run function.
 *
 * 	This function is executed, when a worker is executing a task.
 * 	The return parameter will determine, if the task will be marked completed, or be requeued.
 *
 * @param array $data An array with job data (passed on creation)
 * @return boolean Success
 */
	public function run($data) {
		$command = escapeshellcmd($data['command']) . ' ' . implode(' ', $data['params']);
		$this->out(__d('queue', 'Executing: %s', $command));
		exec($command, $output, $status);
		$this->out(' ');
		$this->out($output);

		return (!$status);
	}

}
