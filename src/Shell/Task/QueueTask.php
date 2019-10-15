<?php
/**
 * @author Andy Carter
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 */
namespace Queue\Shell\Task;

use Cake\Console\ConsoleIo;
use Cake\Console\Shell;
use InvalidArgumentException;

/**
 * Queue Task.
 *
 * Common Queue plugin tasks properties and methods to be extended by custom
 * tasks.
 */
abstract class QueueTask extends Shell implements QueueTaskInterface
{

    /**
     *
     * @var string
     */
    public $queueModelClass = 'Queue.QueuedTasks';

    /**
     *
     * @var \Queue\Model\Table\QueuedTasksTable
     */
    public $QueuedTasks;

    /**
     *
     * @param \Cake\Console\ConsoleIo|null $io IO
     */
    public function __construct(ConsoleIo $io = null)
    {
        parent::__construct($io);

        $this->loadModel($this->queueModelClass);
    }

    /**
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function queueTaskName(): string
    {
        $class = get_class($this);

        preg_match('#\\\\Queue(.+)Task$#', $class, $matches);
        if (!$matches) {
            throw new InvalidArgumentException('Invalid class name: ' . $class);
        }

        return $matches[1];
    }
}
