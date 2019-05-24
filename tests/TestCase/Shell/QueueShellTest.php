<?php
namespace Queue\Test\TestCase\Shell;

use Cake\Console\ConsoleIo;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Queue\Shell\QueueShell;
use Tools\TestSuite\ConsoleOutput;
use Tools\TestSuite\ToolsTestTrait;

class QueueShellTest extends TestCase
{
    use ToolsTestTrait;

    /**
     *
     * @var \Queue\Shell\QueueShell|\PHPUnit_Framework_MockObject_MockObject
     */
    public $QueueShell;

    /**
     *
     * @var \Tools\TestSuite\ConsoleOutput
     */
    public $out;

    /**
     *
     * @var \Tools\TestSuite\ConsoleOutput
     */
    public $err;

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
        
        $this->out = new ConsoleOutput();
        $this->err = new ConsoleOutput();
        $io = new ConsoleIo($this->out, $this->err);
        
        $this->QueueShell = $this->getMockBuilder(QueueShell::class)
            ->setMethods([
                'in',
                'err',
                '_stop'
            ])
            ->setConstructorArgs([
                $io
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
        $this->assertContains('Total unfinished jobs: 0', $this->out->output());
    }

    /**
     *
     * @return void
     */
    public function testSettings()
    {
        $this->QueueShell->settings();
        $this->assertContains('* cleanupTimeout: 10', $this->out->output());
    }

    /**
     *
     * @return void
     */
    public function testAddInexistent()
    {
        $this->QueueShell->args[] = 'FooBar';
        $this->QueueShell->add();
        $this->assertContains('Error: Task not found: FooBar', $this->out->output());
    }

    /**
     *
     * @return void
     */
    public function testAdd()
    {
        $this->QueueShell->args[] = 'Example';
        $this->QueueShell->add();

        $this->assertContains('OK, job created, now run the worker', $this->out->output(), print_r($this->out->output(), true));
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

        $result = $this->QueueShell->timeNeeded();
        $this->assertSame('3540s', $result);
    }

    /**
     *
     * @return void
     */
    public function testMemoryUsage()
    {
        $result = $this->QueueShell->memoryUsage();
        $this->assertRegExp('/^\d+MB/', $result, 'Should be e.g. `17MB` or `17MB/1GB` etc.');
    }

    /**
     *
     * @return void
     */
    public function testStringToArray()
    {
        $string = 'Foo,Bar,';
        $result = $this->QueueShell->stringToArray($string);

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
