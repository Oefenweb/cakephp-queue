<?php
App::uses('ConsoleOutput', 'Console');
App::uses('ConsoleInput', 'Console');
App::uses('ShellDispatcher', 'Console');
App::uses('Shell', 'Console');
App::uses('QueueShell', 'Queue.Console/Command');
App::uses('QueuedTask', 'Queue.Model');

/**
 * QueueShell Wrapper.
 *
 */
class QueueShellWrapper extends QueueShell {

/**
 * A list with error messages.
 *
 * @var array
 */
	protected $_err = [];

/**
 * A list with out messages.
 *
 * @var array
 */
	protected $_out = [];

/**
 * Test double of `parent::err`.
 *
 * @return void
 */
	public function err($message = null, $newlines = 1) {
		$this->_err[] = $message;
	}

/**
 * Test double of `parent::out`.
 *
 * @return void
 */
	public function out($message = null, $newlines = 1, $level = Shell::NORMAL) {
		$this->_out[] = $message;
	}

/**
 * Test double of `parent::_stop`.
 *
 * @return int
 */
	protected function _stop($status = 0) {
		return $status;
	}

}

/**
 * QueueShell Test.
 *
 * @property QueueShellWrapper $QueueShell
 */
class QueueShellTest extends CakeTestCase {

/**
 * Fixtures.
 *
 * @var array
 */
	public $fixtures = ['plugin.queue.queued_task'];

/**
 * setUp method.
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();

		$out = $this->getMock('ConsoleOutput', [], [], '', false);
		$in = $this->getMock('ConsoleInput', [], [], '', false);

		$this->QueueShell = $this->getMock('QueueShellWrapper',
			['in'],
			[$out, $out, $in]
		);
		$this->QueueShell->initialize();
	}

/**
 * tearDown method.
 *
 * @return void
 */
	public function tearDown() {
		parent::tearDown();

		unset($this->QueueShell);
	}

/**
 * Tests `QueueShell` object type.
 *
 * @return void
 */
	public function testObject() {
		$this->assertTrue(is_object($this->QueueShell));
		$this->assertInstanceOf('QueueShell', $this->QueueShell);
	}

/**
 * Tests `QueueShell::stats`.
 *
 * @return void
 */
	public function testStats() {
		$result = $this->QueueShell->stats();

		$this->assertTrue(in_array(__d('queue', 'Total unfinished jobs: %s', 0), $this->QueueShell->_out));
	}

/**
 * Tests `QueueShell::add`, non-existing
 *
 * @return void
 */
	public function testAddNonExistent() {
		$this->QueueShell->args[] = 'Foo';
		$result = $this->QueueShell->add();

		$this->assertTrue(in_array(__d('queue', 'Error: Task not Found: %s', 'Foo'), $this->QueueShell->_out));
	}

/**
 * Tests `QueueShell::add`, existing
 *
 * @return void
 */
	public function testAdd() {
		$restore = Configure::read('Queue.exitWhenNothingToDo');
		Configure::write('Queue.exitWhenNothingToDo', true);

		$this->QueueShell->args[] = 'Example';
		$result = $this->QueueShell->add();

		$this->assertEmpty($this->QueueShell->_out);

		$this->QueueShell->args[] = 'QueueExample';
		$result = $this->QueueShell->add();

		$this->assertEmpty($this->QueueShell->_out);

		$this->skipIf((php_sapi_name() !== 'cli'), 'This test can only be run from console.');

		$result = $this->QueueShell->runworker();

		$this->assertTrue(in_array(__d('queue', 'Running job of task \'%s\' \'%d\'.', 'Example', 5), $this->QueueShell->_out));

		Configure::write('Queue.exitWhenNothingToDo', $restore);
	}

/**
 * Tests `QueueShell::runworker`.
 *
 * @return void
 */
	public function testRunworker() {
		$restore = Configure::read('Queue.exitWhenNothingToDo');
		Configure::write('Queue.exitWhenNothingToDo', true);

		$this->skipIf((php_sapi_name() !== 'cli'), 'This test can only be run from console.');

		$result = $this->QueueShell->runworker();

		$this->assertTrue(in_array(__d('queue', 'Looking for a job.'), $this->QueueShell->_out));

		Configure::write('Queue.exitWhenNothingToDo', $restore);
	}

/**
 * Tests `QueueShell::clean`.
 *
 * @return void
 */
	public function testClean() {
		$countBefore = $this->QueueShell->QueuedTask->find('count');
		$result = $this->QueueShell->clean();
		$expected = $countBefore - 2;
		$count = $this->QueueShell->QueuedTask->find('count');
		$this->assertEquals($expected, $count);
	}

}
