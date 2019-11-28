<?php
/**
 * A Simple QueueTask example.
 *
 * @property Queue.QueuedTask $QueuedTask
 */
class QueueExampleTask extends Shell {

/**
 * An array of names of models to load.
 *
 * @var array
 */
	public $uses = ['Queue.QueuedTask'];

/**
 * Timeout for run, after which the task is reassigned to a new worker.
 *
 * @var int
 */
	public $timeout = 10;

/**
 * Timeout for cleanup, after which completed jobs are deleted (in seconds).
 *
 * @var int
 */
	public $cleanupTimeout = 600;

/**
 * Number of times a failed instance of this task should be restarted before giving up.
 *
 * @var int
 */
	public $retries = 0;

/**
 * Stores any failure messages triggered during run().
 *
 * @var string
 */
	public $failureMessage = '';

/**
 * Example add functionality.
 *
 *	Will create one example job in the queue, which later will be executed using run().
 *
 * @return void
 */
	public function add() {
		$this->out(__d('queue', 'CakePHP Queue Example task.'));
		$this->hr();
		$this->out(__d('queue', 'This is a very simple example of a queueTask.'));
		$this->out(__d('queue', 'Now adding an example Task Job into the Queue.'));
		$this->out(__d('queue', 'This task will only produce some console output on the worker that it runs on.'));
		$this->out(' ');
		$this->out(__d('queue', 'To run a Worker use:'));
		$this->out(__d('queue', '	cake queue runworker'));
		$this->out(' ');
		$this->out(__d('queue', 'You can find the sourcecode of this task in: '));
		$this->out(__FILE__);
		$this->out(' ');

		// Adding a task of type 'example' with no additionally passed data
		if ($this->QueuedTask->createJob('Example', [])) {
			$this->out(__d('queue', 'OK, job created, now run the worker'));
		} else {
			$this->err(__d('queue', 'Could not create Job'));
		}
	}

/**
 * Example run function.
 *
 *	This function is executed, when a worker is executing a task.
 *	The return parameter will determine, if the task will be marked completed, or be requeued.
 *
 * @param array $data Job data (passed on creation)
 * @return bool Success
 */
	public function run(array $data) : bool {
		$this->hr();
		$this->out(__d('queue', 'CakePHP Queue Example task.'));
		$this->hr();
		$this->out(__d('queue', ' ->Success, the Example Task was run.<-'));
		$this->out(' ');
		$this->out(' ');

		return true;
	}

}
