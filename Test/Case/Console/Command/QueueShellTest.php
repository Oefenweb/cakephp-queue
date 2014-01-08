<?php
App::uses('QueueShell', 'Queue.Console/Command');

class TestQueueShell extends QueueShell {

	public $out = array();

	public function out($message = null, $newlines = 1, $level = Shell::NORMAL) {
		$this->out[] = $message;
	}

	protected function _getTaskConf() {
		parent::_getTaskConf();
		foreach ($this->_taskConf as &$conf) {
			$conf['timeout'] = 5;
			$conf['retries'] = 1;
		}

		return $this->_taskConf;
	}

}

class QueueShellTest extends CakeTestCase {

	public $QueueShell;

/**
 * Fixtures to load
 *
 * @var array
 */
	public $fixtures = array(
		'app.school', 'app.school_geo_code', 'app.user', 'app.video_source',
		'plugin.queue.queued_task'
	);

	public function setUp() {
		parent::setUp();

		$this->QueueShell = new TestQueueShell();
		$this->QueueShell->initialize();
		$this->QueueShell->loadTasks();

		Configure::write('Queue', array(
			'sleeptime' => 2,
			'gcprop' => 10,
			'defaultworkertimeout' => 3,
			'defaultworkerretries' => 1,
			'workermaxruntime' => 5,
			'cleanuptimeout' => 10,
			'exitwhennothingtodo' => false,
			'pidfilepath' => TMP . 'queue' . DS,
			'log' => false,
		));
	}

/**
 * QueueShellTest::testObject()
 *
 * @return void
 */
	public function testObject() {
		$this->assertTrue(is_object($this->QueueShell));
		$this->assertInstanceOf('QueueShell', $this->QueueShell);
	}

/**
 * QueueShellTest::testStats()
 *
 * @return void
 */
	public function testStats() {
		$result = $this->QueueShell->stats();

		$this->assertTrue(in_array(__d('queue', 'Total unfinished jobs: %s', 0), $this->QueueShell->out));
	}

/**
 * QueueShellTest::testRunworker()
 *
 * @return void
 */
	public function testRunworker() {
		$restore = Configure::read('Queue.exitWhenNothingToDo');
		Configure::write('Queue.exitWhenNothingToDo', true);
		$this->skipIf((php_sapi_name() !== 'cli'), 'This test can only be run from console.');

		$result = $this->QueueShell->runworker();

		$this->assertTrue(in_array(__d('queue', 'Looking for a job.'), $this->QueueShell->out));

		Configure::write('Queue.exitWhenNothingToDo', $restore);
	}

/**
 * QueueShellTest::testAddInexistent()
 *
 * @return void
 */
	public function testAddInexistent() {
		$this->QueueShell->args[] = 'Foo';
		$result = $this->QueueShell->add();
		$this->assertTrue(in_array(__d('queue', 'Error: Task not Found: %s', 'Foo'), $this->QueueShell->out));
	}

/**
 * QueueShellTest::testAdd()
 *
 * @return void
 */
	public function testAdd() {
		$restore = Configure::read('Queue.exitWhenNothingToDo');
		Configure::write('Queue.exitWhenNothingToDo', true);

		$this->QueueShell->args[] = 'Example';
		$result = $this->QueueShell->add();
		$this->assertEmpty($this->QueueShell->out);

		$this->QueueShell->args[] = 'QueueExample';
		$result = $this->QueueShell->add();
		$this->assertEmpty($this->QueueShell->out);

		$this->skipIf((php_sapi_name() !== 'cli'), 'This test can only be run from console.');

		$result = $this->QueueShell->runworker();
		$this->assertTrue(in_array(__d('queue', 'Running job of task \'%s\' \'%d\'.', 'Example', 1), $this->QueueShell->out));

		Configure::write('Queue.exitWhenNothingToDo', $restore);
	}

}
