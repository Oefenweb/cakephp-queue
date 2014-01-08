<?php
App::uses('QueuedTask', 'Queue.Model');

/**
 * QueuedTask Test
 *
 * @property Queue.QueuedTask $QueuedTask
 */
class QueuedTaskTest extends CakeTestCase {

/**
 * Fixtures to load
 *
 * @var array
 */
	public $fixtures = array('plugin.queue.queued_task');

/**
 * setUp method
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();

		$this->QueuedTask = ClassRegistry::init('Queue.QueuedTask');
	}

/**
 * Basic Instance test
 */
	public function testQueueInstance() {
		$this->assertTrue(is_a($this->QueuedTask, 'QueuedTask'));
	}

/**
 * Test the basic create and length evaluation functions.
 */
	public function testCreateAndCount() {
		// at first, the queue should contain 0 items.
		$this->assertEqual($this->QueuedTask->getLength(), 0);

		// create a task
		$this->assertTrue((bool)$this->QueuedTask->createJob('test1', array(
			'some' => 'random',
			'test' => 'data'
		)));

		// test if queue Length is 1 now.
		$this->assertEqual($this->QueuedTask->getLength(), 1);

		//create some more tasks
		$this->assertTrue((bool)$this->QueuedTask->createJob('test2', array(
			'some' => 'random',
			'test' => 'data2'
		)));
		$this->assertTrue((bool)$this->QueuedTask->createJob('test2', array(
			'some' => 'random',
			'test' => 'data3'
		)));
		$this->assertTrue((bool)$this->QueuedTask->createJob('test3', array(
			'some' => 'random',
			'test' => 'data4'
		)));

		//overall queueLength shpould now be 4
		$this->assertEqual($this->QueuedTask->getLength(), 4);

		// there should be 1 task of type 'test1', one of type 'test3' and 2 of type 'test2'
		$this->assertEqual($this->QueuedTask->getLength('test1'), 1);
		$this->assertEqual($this->QueuedTask->getLength('test2'), 2);
		$this->assertEqual($this->QueuedTask->getLength('test3'), 1);
	}

	public function testCreateAndFetch() {
		//$capabilities is a list of tasks the worker can run.
		$capabilities = array(
			'task1' => array(
				'name' => 'task1',
				'timeout' => 100,
				'retries' => 2
			)
		);
		$testData = array(
			'x1' => 'y1',
			'x2' => 'y2',
			'x3' => 'y3',
			'x4' => 'y4'
		);
		// start off empty.

		$this->assertEqual($this->QueuedTask->find('all'), array());
		// at first, the queue should contain 0 items.
		$this->assertEqual($this->QueuedTask->getLength(), 0);
		// there are no tasks, so we cant fetch any.
		$this->assertFalse($this->QueuedTask->requestJob($capabilities));
		// insert one task.
		$this->assertTrue((bool)$this->QueuedTask->createJob('task1', $testData));

		// fetch and check the first task.
		$data = $this->QueuedTask->requestJob($capabilities);
		$this->assertEqual($data['id'], 1);
		$this->assertEqual('task1', $data['task']);
		$this->assertEqual($data['failed_count'], 0);
		$this->assertNull($data['completed']);
		$this->assertEqual(unserialize($data['data']), $testData);

		// after this task has been fetched, it may not be reassigned.
		$this->assertFalse($this->QueuedTask->requestJob($capabilities));

		// queue length is still 1 since the first task did not finish.
		$this->assertEqual($this->QueuedTask->getLength(), 1);

		// Now mark Task1 as done
		$this->assertTrue((bool)$this->QueuedTask->markJobDone(1));
		// Should be 0 again.
		$this->assertEqual($this->QueuedTask->getLength(), 0);
	}

/**
 * Test the delivery of tasks in sequence, skipping fetched but not completed tasks.
 *
 */
	public function testSequence() {
		//$capabilities is a list of tasks the worker can run.
		$capabilities = array(
			'task1' => array(
				'name' => 'task1',
				'timeout' => 100,
				'retries' => 2
			)
		);
		// at first, the queue should contain 0 items.
		$this->assertEqual($this->QueuedTask->getLength(), 0);
		// create some more tasks
		foreach (range(0, 9) as $num) {
			$this->assertTrue((bool)$this->QueuedTask->createJob('task1', array(
				'tasknum' => $num
			)));
		}
		// 10 tasks in the queue.
		$this->assertEqual($this->QueuedTask->getLength(), 10);

		// tasks should be fetched in the original sequence.
		foreach (range(0, 4) as $num) {
			$job = $this->QueuedTask->requestJob($capabilities);
			$jobData = unserialize($job['data']);
			$this->assertEqual($jobData['tasknum'], $num);
		}
		// now mark them as done
		foreach (range(0, 4) as $num) {
			$this->assertTrue((bool)$this->QueuedTask->markJobDone($num + 1));
			$this->assertEqual($this->QueuedTask->getLength(), 9 - $num);
		}

		// tasks should be fetched in the original sequence.
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
 * @return null
 */
	public function testNotBefore() {
		$this->assertTrue((bool)$this->QueuedTask->createJob('task1', null, '+ 1 Min'));
		$this->assertTrue((bool)$this->QueuedTask->createJob('task1', null, '+ 1 Day'));
		$this->assertTrue((bool)$this->QueuedTask->createJob('task1', null, '2009-07-01 12:00:00'));
		$data = $this->QueuedTask->find('all');
		$this->assertEqual($data[0]['QueuedTask']['not_before'], date('Y-m-d H:i:s', strtotime('+ 1 Min')));
		$this->assertEqual($data[1]['QueuedTask']['not_before'], date('Y-m-d H:i:s', strtotime('+ 1 Day')));
		$this->assertEqual($data[2]['QueuedTask']['not_before'], '2009-07-01 12:00:00');
	}

/**
 * Test Job reordering depending on 'notBefore' field.
 * Jobs with an expired notbefore field should be executed before any other job without specific timing info.
 * @return null
 */
	public function testNotBeforeOrder() {
		$capabilities = array(
			'task1' => array(
				'name' => 'task1',
				'timeout' => 100,
				'retries' => 2
			),
			'dummytask' => array(
				'name' => 'dummytask',
				'timeout' => 100,
				'retries' => 2
			)
		);
		$this->assertTrue((bool)$this->QueuedTask->createJob('dummytask', null));
		$this->assertTrue((bool)$this->QueuedTask->createJob('dummytask', null));
		// create a task with it's execution target some seconds in the past, so it should jump to the top of the list.
		$this->assertTrue((bool)$this->QueuedTask->createJob('task1', 'three', '- 3 Seconds'));
		$this->assertTrue((bool)$this->QueuedTask->createJob('task1', 'two', '- 4 Seconds'));
		$this->assertTrue((bool)$this->QueuedTask->createJob('task1', 'one', '- 5 Seconds'));

		// when usin requestJob, the jobs we just created should be delivered in this order, NOT the order in which they where created.
		$expected = array(
			array(
				'name' => 'task1',
				'data' => 'one'
			),
			array(
				'name' => 'task1',
				'data' => 'two'
			),
			array(
				'name' => 'task1',
				'data' => 'three'
			),
			array(
				'name' => 'dummytask',
				'data' => ''
			),
			array(
				'name' => 'dummytask',
				'data' => ''
			)
		);

		foreach ($expected as $item) {
			$tmp = $this->QueuedTask->requestJob($capabilities);
			$this->assertEqual($tmp['task'], $item['name']);
			$this->assertEqual(unserialize($tmp['data']), $item['data']);
		}
	}

	public function testRequeueAfterTimeout() {
		$capabilities = array(
			'task1' => array(
				'name' => 'task1',
				'timeout' => 1,
				'retries' => 2
			)
		);

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

	public function testMarkJobFailed() {
		$this->QueuedTask->createJob('dummytask', null);
		$id = $this->QueuedTask->id;
		$expected = 'Timeout: 100';
		$this->QueuedTask->markJobFailed($id, $expected);
		$result = $this->QueuedTask->field('failure_message');
		$this->assertEqual($result, $expected);
	}

}
