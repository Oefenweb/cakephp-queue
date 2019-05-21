<?php
App::uses('QueuedTask', 'Queue.Model');

/**
 * QueuedTask Test.
 *
 * @property Queue.QueuedTask $QueuedTask
 */
class QueuedTaskTest extends CakeTestCase {

/**
 * Fixtures to load.
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

		$this->QueuedTask = ClassRegistry::init('Queue.QueuedTask');
	}

/**
 * tearDown method.
 *
 * @return void
 */
	public function tearDown() {
		parent::tearDown();

		unset($this->QueuedTask);
	}

/**
 * Tests `QueuedTask` object type.
 *
 * @return void
 */
	public function testQueueInstance() {
		$this->assertTrue(is_a($this->QueuedTask, 'QueuedTask'));
	}

/**
 * Tests the basic create and length evaluation functions.
 *
 * @return void
 */
	public function testCreateAndCount() {
		// At first, the queue should contain 0 items
		$this->assertEqual($this->QueuedTask->getLength(), 0);

		// Create a task
		$this->assertTrue((bool)$this->QueuedTask->createJob('test1', [
			'some' => 'random',
			'test' => 'data'
		]));

		// Test if queue Length is 1 now
		$this->assertEqual($this->QueuedTask->getLength(), 1);

		// Create some more tasks
		$this->assertTrue((bool)$this->QueuedTask->createJob('test2', ['some' => 'random', 'test' => 'data2']));
		$this->assertTrue((bool)$this->QueuedTask->createJob('test2', ['some' => 'random', 'test' => 'data3']));
		$this->assertTrue((bool)$this->QueuedTask->createJob('test3', ['some' => 'random', 'test' => 'data4']));

		// Overall queueLength shpould now be 4
		$this->assertEqual($this->QueuedTask->getLength(), 4);

		// There should be 1 task of type 'test1', one of type 'test3' and 2 of type 'test2'
		$this->assertEqual($this->QueuedTask->getLength('test1'), 1);
		$this->assertEqual($this->QueuedTask->getLength('test2'), 2);
		$this->assertEqual($this->QueuedTask->getLength('test3'), 1);
	}

/**
 * Tests creation and fetching of tasks.
 *
 * @return void
 */
	public function testCreateAndFetch() {
		// Capabilities is a list of tasks the worker can run
		$capabilities = [
			'task1' => [
				'name' => 'task1',
				'timeout' => 100,
				'retries' => 2
			]
		];
		$testData = [
			'x1' => 'y1',
			'x2' => 'y2',
			'x3' => 'y3',
			'x4' => 'y4'
		];

		// Start off empty
		$this->QueuedTask->deleteAll(['1 = 1']);

		$this->assertEqual($this->QueuedTask->find('all'), []);
		// At first, the queue should contain 0 items
		$this->assertEqual($this->QueuedTask->getLength(), 0);
		// There are no tasks, so we cant fetch any
		$this->assertFalse($this->QueuedTask->requestJob($capabilities));
		// Insert one task
		$this->assertTrue((bool)$this->QueuedTask->createJob('task1', $testData));

		// Fetch and check the first task
		$data = $this->QueuedTask->requestJob($capabilities);
		$this->assertEqual($data['id'], 5);
		$this->assertEqual('task1', $data['task']);
		$this->assertEqual($data['failed_count'], 0);
		$this->assertNull($data['completed']);
		$this->assertEqual(unserialize($data['data']), $testData);

		// After this task has been fetched, it may not be reassigned
		$this->assertFalse($this->QueuedTask->requestJob($capabilities));

		// Queue length is still 1 since the first task did not finish
		$this->assertEqual($this->QueuedTask->getLength(), 1);

		// Now mark Task 5 as done
		$this->assertTrue((bool)$this->QueuedTask->markJobDone(5));
		// Should be 0 again
		$this->assertEqual($this->QueuedTask->getLength(), 0);
	}

/**
 * Test the delivery of tasks in sequence, skipping fetched but not completed tasks.
 *
 * @return void
 */
	public function testSequence() {
		// Capabilities is a list of tasks the worker can run
		$capabilities = [
			'task1' => [
				'name' => 'task1',
				'timeout' => 100,
				'retries' => 2
			]
		];
		// At first, the queue should contain 0 items
		$this->assertEqual($this->QueuedTask->getLength(), 0);
		// Create some more tasks
		foreach (range(0, 9) as $num) {
			$this->assertTrue((bool)$this->QueuedTask->createJob('task1', [
				'tasknum' => $num
			]));
		}
		// 10 tasks in the queue
		$this->assertEqual($this->QueuedTask->getLength(), 10);

		// Tasks should be fetched in the original sequence
		foreach (range(0, 4) as $num) {
			$job = $this->QueuedTask->requestJob($capabilities);
			$jobData = unserialize($job['data']);
			$this->assertEqual($jobData['tasknum'], $num);
		}
		// Now mark them as done
		foreach (range(0, 4) as $num) {
			$this->assertTrue((bool)$this->QueuedTask->markJobDone($num + 5));
			$this->assertEqual($this->QueuedTask->getLength(), 9 - $num);
		}

		// Tasks should be fetched in the original sequence
		foreach (range(5, 9) as $num) {
			$job = $this->QueuedTask->requestJob($capabilities);
			$jobData = unserialize($job['data']);
			$this->assertEqual($jobData['tasknum'], $num);
			$this->assertTrue((bool)$this->QueuedTask->markJobDone($job['id']));
			$this->assertEqual($this->QueuedTask->getLength(), 9 - $num);
		}
	}

/**
 * Test creating Tasks to run close to a specified time, and strtotime parsing.
 *
 * @return void
 */
	public function testNotBefore() {
		$this->assertTrue((bool)$this->QueuedTask->createJob('task1', null, '+ 1 Min'));
		$this->assertTrue((bool)$this->QueuedTask->createJob('task1', null, '+ 1 Day'));
		$this->assertTrue((bool)$this->QueuedTask->createJob('task1', null, '2009-07-01 12:00:00'));
		$data = $this->QueuedTask->find('all');
		$this->assertEqual($data[4]['QueuedTask']['not_before'], date('Y-m-d H:i:s', strtotime('+ 1 Min')));
		$this->assertEqual($data[5]['QueuedTask']['not_before'], date('Y-m-d H:i:s', strtotime('+ 1 Day')));
		$this->assertEqual($data[6]['QueuedTask']['not_before'], '2009-07-01 12:00:00');
	}

/**
 * Test Job reordering depending on 'notBefore' field.
 *
 *  Jobs with an expired notbefore field should be executed before any other job without specific timing info.
 *
 * @return void
 */
	public function testNotBeforeOrder() {
		$capabilities = [
			'task1' => [
				'name' => 'task1',
				'timeout' => 100,
				'retries' => 2
			],
			'dummytask' => [
				'name' => 'dummytask',
				'timeout' => 100,
				'retries' => 2
			]
		];
		$this->assertTrue((bool)$this->QueuedTask->createJob('dummytask', null));
		$this->assertTrue((bool)$this->QueuedTask->createJob('dummytask', null));
		// Create a task with it's execution target some seconds in the past, so it should jump to the top of the list
		$this->assertTrue((bool)$this->QueuedTask->createJob('task1', 'three', '- 3 Seconds'));
		$this->assertTrue((bool)$this->QueuedTask->createJob('task1', 'two', '- 4 Seconds'));
		$this->assertTrue((bool)$this->QueuedTask->createJob('task1', 'one', '- 5 Seconds'));

		// When usin requestJob, the jobs we just created should be delivered in this order,
		// NOT the order in which they where created
		$expected = [
			[
				'name' => 'task1',
				'data' => 'one'
			],
			[
				'name' => 'task1',
				'data' => 'two'
			],
			[
				'name' => 'task1',
				'data' => 'three'
			],
			[
				'name' => 'dummytask',
				'data' => ''
			],
			[
				'name' => 'dummytask',
				'data' => ''
			]
		];

		foreach ($expected as $item) {
			$tmp = $this->QueuedTask->requestJob($capabilities);
			$this->assertEqual($tmp['task'], $item['name']);
			$this->assertEqual(unserialize($tmp['data']), $item['data']);
		}
	}

/**
 * Tests requeueing after timeout is reached.
 *
 * @return void
 */
	public function testRequeueAfterTimeout() {
		$capabilities = [
			'task1' => [
				'name' => 'task1',
				'timeout' => 1,
				'retries' => 2
			]
		];

		$this->assertTrue((bool)$this->QueuedTask->createJob('task1', '1'));
		$tmp = $this->QueuedTask->requestJob($capabilities);
		$this->assertEqual($tmp['task'], 'task1');
		$this->assertEqual(unserialize($tmp['data']), '1');
		$this->assertEqual($tmp['failed_count'], '0');
		sleep(2);
		$tmp = $this->QueuedTask->requestJob($capabilities);
		$this->assertEqual($tmp['task'], 'task1');
		$this->assertEqual(unserialize($tmp['data']), '1');
		$this->assertEqual($tmp['failed_count'], '1');
		$this->assertEqual($tmp['failure_message'], 'Restart after timeout');
	}

/**
 * Tests `QueuedTask::markJobFailed`.
 *
 * @return void
 */
	public function testMarkJobFailed() {
		$this->QueuedTask->createJob('dummytask', null);
		$id = $this->QueuedTask->id;
		$expected = 'Timeout: 100';
		$this->QueuedTask->markJobFailed($id, $expected);
		$result = $this->QueuedTask->field('failure_message');
		$this->assertEqual($result, $expected);
	}

}
