<?php
namespace Queue\Test\TestCase\Model\Table;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Queue\Model\Table\QueuedTasksTable;

/**
 * Queue\Model\Table\QueuedTasksTable Test Case
 */
class QueuedTasksTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \Queue\Model\Table\QueuedTasksTable
     */
    public $QueuedTasks;

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
        $config = TableRegistry::getTableLocator()->exists('QueuedTasks') ? [] : ['className' => QueuedTasksTable::class];
        $this->QueuedTasks = TableRegistry::getTableLocator()->get('QueuedTasks', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->QueuedTasks);

        parent::tearDown();
    }

    /**
     * Test initialize method
     *
     * @return void
     */
    public function testInitialize()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test validationDefault method
     *
     * @return void
     */
    public function testValidationDefault()
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
