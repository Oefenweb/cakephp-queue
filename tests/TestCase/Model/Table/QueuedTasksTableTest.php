<?php
/**
 * @author MGriesbach@gmail.com
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link http://github.com/MSeven/cakephp_queue
 */
namespace Queue\Test\TestCase\Model\Table;

use Cake\Datasource\ConnectionManager;
use Cake\I18n\FrozenTime;
use Cake\I18n\Time;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Queue\Model\Table\QueuedTasksTable;

/**
 * Queue\Model\Table\QueuedTasksTable Test Case
 */
class QueuedTasksTableTest extends TestCase
{

    /**
     *
     * @var \Queue\Model\Table\QueuedTasksTable
     */
    protected $QueuedTasks;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'plugin.Queue.QueuedTasks'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $config = TableRegistry::getTableLocator()->exists('QueuedTasks') ? [] : [
            'className' => QueuedTasksTable::class
        ];
        $this->QueuedTasks = TableRegistry::getTableLocator()->get('QueuedTasks', $config);
    }

    /**
     * Basic Instance test
     *
     * @return void
     */
    public function testQueueInstance()
    {
        $this->assertInstanceOf(QueuedTasksTable::class, $this->QueuedTasks);
    }

    /**
     * Test the basic create and length evaluation functions.
     *
     * @return void
     */
    public function testCreateAndCount()
    {
        // at first, the queue should contain 0 items.
        $this->assertSame(0, $this->QueuedTasks->getLength());

        // create a job
        $this->assertTrue((bool)$this->QueuedTasks->createJob('test1', [
            'some' => 'random',
            'test' => 'data'
        ]));

        // test if queue Length is 1 now.
        $this->assertSame(1, $this->QueuedTasks->getLength());

        // create some more jobs
        $this->assertTrue((bool)$this->QueuedTasks->createJob('test2', [
            'some' => 'random',
            'test' => 'data2'
        ]));
        $this->assertTrue((bool)$this->QueuedTasks->createJob('test2', [
            'some' => 'random',
            'test' => 'data3'
        ]));
        $this->assertTrue((bool)$this->QueuedTasks->createJob('test3', [
            'some' => 'random',
            'test' => 'data4'
        ]));

        // overall queueLength shpould now be 4
        $this->assertSame(4, $this->QueuedTasks->getLength());

        // there should be 1 task of type 'test1', one of type 'test3' and 2 of type 'test2'
        $this->assertSame(1, $this->QueuedTasks->getLength('test1'));
        $this->assertSame(2, $this->QueuedTasks->getLength('test2'));
        $this->assertSame(1, $this->QueuedTasks->getLength('test3'));
    }

    /**
     * Test the basic create and fetch functions.
     *
     * @return void
     */
    public function testCreateAndFetch()
    {
        $this->_needsConnection();

        // $capabilities is a list of tasks the worker can run.
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

        // start off empty.
        $this->assertSame([], $this->QueuedTasks->find()
            ->toArray());
        // at first, the queue should contain 0 items.
        $this->assertSame(0, $this->QueuedTasks->getLength());
        // there are no jobs, so we cant fetch any.
        $this->assertNull($this->QueuedTasks->requestJob($capabilities));
        // insert one job.
        $this->assertTrue((bool)$this->QueuedTasks->createJob('task1', $testData));

        // fetch and check the first job.
        $job = $this->QueuedTasks->requestJob($capabilities);
        $this->assertSame(1, $job->id);
        $this->assertSame('task1', $job->task);
        $this->assertSame(0, $job->failed_count);
        $this->assertNull($job->completed);
        $this->assertSame($testData, unserialize($job->data));

        // after this job has been fetched, it may not be reassigned.
        $result = $this->QueuedTasks->requestJob($capabilities);
        $this->assertNull($result);

        // queue length is still 1 since the first job did not finish.
        $this->assertSame(1, $this->QueuedTasks->getLength());

        // Now mark Task1 as done
        $this->assertTrue($this->QueuedTasks->markJobDone($job));

        // Should be 0 again.
        $this->assertSame(0, $this->QueuedTasks->getLength());
    }

    /**
     * Test the delivery of jobs in sequence, skipping fetched but not completed tasks.
     *
     * @return void
     */
    public function testSequence()
    {
        $this->_needsConnection();

        // $capabilities is a list of tasks the worker can run.
        $capabilities = [
            'task1' => [
                'name' => 'task1',
                'timeout' => 100,
                'retries' => 2
            ]
        ];
        // at first, the queue should contain 0 items.
        $this->assertSame(0, $this->QueuedTasks->getLength());
        // create some more jobs
        foreach (range(0, 9) as $num) {
            $this->assertTrue((bool)$this->QueuedTasks->createJob('task1', [
                'tasknum' => $num
            ]));
        }
        // 10 jobs in the queue.
        $this->assertSame(10, $this->QueuedTasks->getLength());

        // jobs should be fetched in the original sequence.
        $array = [];
        foreach (range(0, 4) as $num) {
            $array[$num] = $this->QueuedTasks->requestJob($capabilities);
            $jobData = unserialize($array[$num]['data']);
            $this->assertSame($num, $jobData['tasknum']);
        }
        // now mark them as done
        foreach (range(0, 4) as $num) {
            $this->assertTrue($this->QueuedTasks->markJobDone($array[$num]));
            $this->assertSame(9 - $num, $this->QueuedTasks->getLength());
        }

        // jobs should be fetched in the original sequence.
        foreach (range(5, 9) as $num) {
            $job = $this->QueuedTasks->requestJob($capabilities);
            $jobData = unserialize($job->data);
            $this->assertSame($num, $jobData['tasknum']);
            $this->assertTrue($this->QueuedTasks->markJobDone($job));
            $this->assertSame(9 - $num, $this->QueuedTasks->getLength());
        }
    }

    /**
     * Test creating Jobs to run close to a specified time, and strtotime parsing.
     * Using toUnixString() function to convert Time object to timestamp, instead of strtotime
     *
     * @return null
     */
    public function testNotBefore()
    {
        $this->assertTrue((bool)$this->QueuedTasks->createJob('task1', null, '+ 1 Min'));
        $this->assertTrue((bool)$this->QueuedTasks->createJob('task1', null, '+ 1 Day'));
        $this->assertTrue((bool)$this->QueuedTasks->createJob('task1', null, '2009-07-01 12:00:00'));
        $data = $this->QueuedTasks->find('all')->toArray();
        $this->assertWithinRange((new Time('+ 1 Min'))->toUnixString(), $data[0]['not_before']->toUnixString(), 60);
        $this->assertWithinRange((new Time('+ 1 Day'))->toUnixString(), $data[1]['not_before']->toUnixString(), 60);
        $this->assertWithinRange((new Time('2009-07-01 12:00:00'))->toUnixString(), $data[2]['not_before']->toUnixString(), 60);
    }

    /**
     * Test Job reordering depending on 'notBefore' field.
     * Jobs with an expired not_before field should be executed before any other job without specific timing info.
     *
     * @return void
     */
    public function testNotBeforeOrder()
    {
        $this->_needsConnection();

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
        $this->assertTrue((bool)$this->QueuedTasks->createJob('dummytask'));
        $this->assertTrue((bool)$this->QueuedTasks->createJob('dummytask'));
        // create a task with it's execution target some seconds in the past, so it should jump to the top of the testCreateAndFetchlist.
        $this->assertTrue((bool)$this->QueuedTasks->createJob('task1', [
            'three'
        ], '- 3 Seconds'));
        $this->assertTrue((bool)$this->QueuedTasks->createJob('task1', [
            'two'
        ], '- 5 Seconds'));
        $this->assertTrue((bool)$this->QueuedTasks->createJob('task1', [
            'one'
        ], '- 7 Seconds'));

        // when using requestJob, the jobs we just created should be delivered in this order, NOT the order in which they where created.
        $expected = [
            [
                'name' => 'task1',
                'data' => [
                    'one'
                ]
            ],
            [
                'name' => 'task1',
                'data' => [
                    'two'
                ]
            ],
            [
                'name' => 'task1',
                'data' => [
                    'three'
                ]
            ],
            [
                'name' => 'dummytask',
                'data' => null
            ],
            [
                'name' => 'dummytask',
                'data' => null
            ]
        ];

        foreach ($expected as $item) {
            $tmp = $this->QueuedTasks->requestJob($capabilities);

            $this->assertSame($item['name'], $tmp['task']);
            $this->assertEquals($item['data'], unserialize($tmp['data']));
        }
    }

    /**
     * Helper method for skipping tests that need a real connection.
     *
     * @return void
     */
    protected function _needsConnection()
    {
        $config = ConnectionManager::getConfig('test');
        $this->skipIf(strpos($config['driver'], 'Mysql') === false, 'Only Mysql is working yet for this.');
    }
}