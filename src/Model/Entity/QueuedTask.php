<?php
namespace Queue\Model\Entity;

use Cake\ORM\Entity;

/**
 * QueuedTask Entity
 *
 * @property int $id
 * @property string $task
 * @property string|null $data
 * @property \Cake\I18n\FrozenTime|null $not_before
 * @property \Cake\I18n\FrozenTime|null $fetched
 * @property \Cake\I18n\FrozenTime|null $completed
 * @property int $failed_count
 * @property string|null $failure_message
 * @property string|null $worker_key
 * @property \Cake\I18n\FrozenTime|null $created
 */
class QueuedTask extends Entity
{

    /**
     *
     * {@inheritdoc}
     *
     * @var array
     */
    protected $_accessible = [
        'task' => true,
        'data' => true,
        'not_before' => true,
        'fetched' => true,
        'completed' => true,
        'failed_count' => true,
        'failure_message' => true,
        'worker_key' => true,
        'created' => true
    ];
}
