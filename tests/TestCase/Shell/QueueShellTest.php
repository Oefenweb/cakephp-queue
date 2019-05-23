<?php
namespace Queue\Test\TestCase\Shell;

use Cake\Console\Shell;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Queue\Shell\QueueShell;

/**
 * QueueShell Wrapper.
 */
class QueueShellWrapper extends QueueShell
{

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
    public function err($message = null, $newlines = 1)
    {
        $this->_err[] = $message;
    }

    /**
     * Test double of `parent::out`.
     *
     * @return void
     */
    public function out($message = null, $newlines = 1, $level = Shell::NORMAL)
    {
        $this->_out[] = $message;
    }

    /**
     * Test double of `parent::_stop`.
     *
     * @return int
     */
    protected function _stop($status = 0)
    {
        return $status;
    }
}

class QueueShellTest extends TestCase
{

    /**
     *
     * @var QueueShellWrapper
     */
    public $QueueShell;

    /**
     * Fixtures to load
     *
     * @var array
     */
    public $fixtures = [
        'plugin.Queue.QueuedTasks'
    ];

    /**
     * Setup Defaults
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->QueueShell = $this->getMockBuilder(QueueShellWrapper::class)
            ->setMethods([
                'in',
                'err',
                '_stop'
            ])
            ->getMock();

        $this->QueueShell->initialize();

        Configure::write('Queue', [
            'sleepTime' => 2,
            'defaultWorkerTimeout' => 3,
            'workerMaxRuntime' => 5,
            'cleanupTimeout' => 10,
            'exitWhenNothingToDo' => false,
            'log' => false
        ]);
    }

    /**
     *
     * @return void
     */
    public function testObject()
    {
        $this->assertTrue(is_object($this->QueueShell));
        $this->assertInstanceOf(QueueShell::class, $this->QueueShell);
    }

    /**
     *
     * @return void
     */
    public function testStats()
    {
        $this->_needsConnection();

        $this->QueueShell->stats();
        $this->assertContains('Total unfinished jobs: 0', $this->out);
    }

    /**
     *
     * @return void
     */
    public function testSettings()
    {
        $this->QueueShell->settings();
        $this->assertContains('* cleanuptimeout: 10', $this->out);
    }

    /**
     *
     * @return void
     */
    public function testAddInexistent()
    {
        $this->QueueShell->args[] = 'FooBar';
        $this->QueueShell->add();
        $this->assertContains('Error: Task not found: FooBar', $this->out);
    }

    /**
     *
     * @return void
     */
    public function testAdd()
    {
        $this->QueueShell->args[] = 'Example';
        $this->QueueShell->add();

        $this->assertContains('OK, job created, now run the worker', $this->out, print_r($this->out->output, true));
    }

    /**
     *
     * @return void
     */
    public function testRetry()
    {
        $file = TMP . 'task_retry.txt';
        if (file_exists($file)) {
            unlink($file);
        }

        $this->_needsConnection();

        $this->QueueShell->args[] = 'RetryExample';
        $this->QueueShell->add();

        $expected = 'This is a very simple example of a QueueTask and how retries work';
        $this->assertContains($expected, $this->out);

        $this->QueueShell->runworker();

        $this->assertContains('Job did not finish, requeued after try 1.', $this->out);
    }

    /**
     *
     * @return void
     */
    public function testTimeNeeded()
    {
        $this->QueueShell = $this->getMockBuilder(QueueShell::class)
            ->setMethods([
                '_time'
            ])
            ->getMock();

        $first = time();
        $second = $first - HOUR + MINUTE;
        $this->QueueShell->expects($this->at(0))
            ->method('_time')
            ->will($this->returnValue($first));
        $this->QueueShell->expects($this->at(1))
            ->method('_time')
            ->will($this->returnValue($second));
        $this->QueueShell->expects($this->exactly(2))
            ->method('_time')
            ->withAnyParameters();

        $result = $this->invokeMethod($this->QueueShell, '_timeNeeded');
        $this->assertSame('3540s', $result);
    }

    /**
     *
     * @return void
     */
    public function testMemoryUsage()
    {
        $result = $this->invokeMethod($this->QueueShell, '_memoryUsage');
        $this->assertRegExp('/^\d+MB/', $result, 'Should be e.g. `17MB` or `17MB/1GB` etc.');
    }

    /**
     *
     * @return void
     */
    public function testStringToArray()
    {
        $string = 'Foo,Bar,';
        $result = $this->invokeMethod($this->QueueShell, '_stringToArray', [
            $string
        ]);

        $expected = [
            'Foo',
            'Bar'
        ];
        $this->assertSame($expected, $result);
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
