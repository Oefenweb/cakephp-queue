<?php
namespace Queue\Model\Table;

use ArrayObject;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\I18n\FrozenTime;
use Cake\ORM\Query;
use Cake\ORM\Table;
use Queue\Model\Entity\QueuedTask;

/**
 * QueuedTasks Model
 *
 * @method \Queue\Model\Entity\QueuedTask get($primaryKey, $options = [])
 * @method \Queue\Model\Entity\QueuedTask newEntity($data = null, array $options = [])
 * @method \Queue\Model\Entity\QueuedTask[] newEntities(array $data, array $options = [])
 * @method \Queue\Model\Entity\QueuedTask|bool save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \Queue\Model\Entity\QueuedTask saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \Queue\Model\Entity\QueuedTask patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \Queue\Model\Entity\QueuedTask[] patchEntities($entities, array $data, array $options = [])
 * @method \Queue\Model\Entity\QueuedTask findOrCreate($search, callable $callback = null, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class QueuedTasksTable extends Table
{

    const DRIVER_MYSQL = 'Mysql';

    const DRIVER_POSTGRES = 'Postgres';

    const DRIVER_SQLSERVER = 'Sqlserver';

    const STATS_LIMIT = 100000;

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config)
    {
        parent::initialize($config);

        $this->setTable('queued_tasks');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
    }

    /**
     * set connection name
     *
     * @return string
     */
    public static function defaultConnectionName()
    {
        $connection = Configure::read('Queue.connection');
        if (!empty($connection)) {
            return $connection;
        }

        return parent::defaultConnectionName();
    }

    /**
     *
     * @param \Cake\Event\Event $event Model event
     * @param \ArrayObject $data The data
     * @param \ArrayObject $options The options
     * @return void
     */
    public function beforeMarshal(Event $event, ArrayObject $data, ArrayObject $options)
    {
        if (isset($data['data']) && $data['data'] === '') {
            $data['data'] = null;
        }
    }

    /**
     * Adds a new job to the queue.
     *
     * @param string $taskName Task name
     * @param array|null $data Array of data
     * @param string|null $notBefore A datetime which indicates when the job may be executed
     * @return \Queue\Model\Entity\QueuedTask Saved job entity
     */
    public function createJob($taskName, array $data = null, string $notBefore = null)
    {
        $task = [
            'task' => $taskName,
            'data' => serialize($data),
            'not_before' => $this->getDateTime()
        ];

        if (!empty($notBefore)) {
            $task['not_before'] = $this->getDateTime(strtotime($notBefore));
        }

        $queuedTask = $this->newEntity($task);

        return $this->saveOrFail($queuedTask);
    }

    /**
     * Returns the number of items in the queue.
     * Either returns the number of ALL pending jobs, or the number of pending jobs of the passed type.
     *
     * @param string|null $taskName Task type to Count
     * @return int
     */
    public function getLength($taskName = null)
    {
        $findConf = [
            'conditions' => [
                'completed IS' => null
            ]
        ];
        if ($taskName !== null) {
            $findConf['conditions']['task'] = $taskName;
        }

        return $this->find('all', $findConf)->count();
    }

    /**
     * Return a list of all task types in the Queue.
     *
     * @return \Cake\ORM\Query
     */
    public function getTypes()
    {
        $findCond = [
            'fields' => [
                'task'
            ],
            'group' => [
                'task'
            ],
            'keyField' => 'task',
            'valueField' => 'task'
        ];

        return $this->find('list', $findCond);
    }

    /**
     * Return some statistics about finished jobs still in the Database.
     * TO-DO: rewrite as virtual field
     *
     * @return \Cake\ORM\Query
     */
    public function getStats()
    {
        $driverName = $this->_getDriverName();
        $options = [
            'fields' => function (Query $query) use ($driverName): array {
                $alltime = $query->func()->avg('UNIX_TIMESTAMP(completed) - UNIX_TIMESTAMP(created)');
                $runtime = $query->func()->avg('UNIX_TIMESTAMP(completed) - UNIX_TIMESTAMP(fetched)');
                $fetchdelay = $query->func()->avg('UNIX_TIMESTAMP(fetched) - IF(not_before is NULL, UNIX_TIMESTAMP(created), UNIX_TIMESTAMP(not_before))');
                switch ($driverName) {
                    case static::DRIVER_SQLSERVER:
                        $alltime = $query->func()->avg("DATEDIFF(s, '1970-01-01 00:00:00', completed) - DATEDIFF(s, '1970-01-01 00:00:00', created)");
                        $runtime = $query->func()->avg("DATEDIFF(s, '1970-01-01 00:00:00', completed) - DATEDIFF(s, '1970-01-01 00:00:00', fetched)");
                        $fetchdelay = $query->func()->avg("DATEDIFF(s, '1970-01-01 00:00:00', fetched) - (CASE WHEN not_before IS NULL THEN DATEDIFF(s, '1970-01-01 00:00:00', created) ELSE DATEDIFF(s, '1970-01-01 00:00:00', not_before) END)");
                        break;
                }
                /**
                 *
                 * @var \Cake\ORM\Query
                 */
                return [
                    'task',
                    'num' => $query->func()->count('*'),
                    'alltime' => $alltime,
                    'runtime' => $runtime,
                    'fetchdelay' => $fetchdelay
                ];
            },
            'conditions' => [
                'completed IS NOT' => null
            ],
            'group' => [
                'task'
            ]
        ];

        return $this->find('all', $options);
    }

    /**
     * Returns [
     * 'Task' => [
     * 'YYYY-MM-DD' => INT,
     * ...
     * ]
     * ]
     *
     * @param string|null $taskName The task name
     * @return array
     */
    public function getFullStats($taskName = null)
    {
        $driverName = $this->_getDriverName();
        $fields = function (Query $query) use ($driverName): array {
            $runtime = $query->newExpr('UNIX_TIMESTAMP(completed) - UNIX_TIMESTAMP(fetched)');
            switch ($driverName) {
                case static::DRIVER_SQLSERVER:
                    $runtime = $query->newExpr("DATEDIFF(s, '1970-01-01 00:00:00', completed) - DATEDIFF(s, '1970-01-01 00:00:00', fetched)");
                    break;
            }

            return [
                'task',
                'created',
                'duration' => $runtime
            ];
        };

        $conditions = [
            'completed IS NOT' => null
        ];
        if ($taskName) {
            $conditions['task'] = $taskName;
        }

        $tasks = $this->find()
            ->select($fields)
            ->where($conditions)
            ->enableHydration(false)
            ->orderDesc('id')
            ->limit(static::STATS_LIMIT)
            ->all()
            ->toArray();

        $result = [];

        $days = [];

        foreach ($tasks as $task) {
            /** @var \DateTime $created */
            $created = $task['created'];
            $day = $created->format('Y-m-d');
            if (!isset($days[$day])) {
                $days[$day] = $day;
            }

            $result[$task['task']][$day][] = $task['duration'];
        }

        foreach ($result as $type => $tasks) {
            foreach ($tasks as $day => $durations) {
                $average = array_sum($durations) / count($durations);
                $result[$type][$day] = (int)$average;
            }

            foreach ($days as $day) {
                if (isset($result[$type][$day])) {
                    continue;
                }

                $result[$type][$day] = 0;
            }

            ksort($result[$type]);
        }

        return $result;
    }

    /**
     * Look for a new job that can be processed with the current abilities and
     * from the specified group (or any if null).
     *
     * @param array $capabilities Available QueueWorkerTasks.
     * @param array $types Request a job from these types (or exclude certain types), or any otherwise.
     * @return \Queue\Model\Entity\QueuedTask|null
     */
    public function requestJob(array $capabilities, array $types = [])
    {
        $now = $this->getDateTime();
        $nowStr = $now->toDateTimeString();
        $driverName = $this->_getDriverName();

        $query = $this->find();
        $age = $query->newExpr()->add('IFNULL(TIMESTAMPDIFF(SECOND, "' . $nowStr . '", not_before), 0)');
        switch ($driverName) {
            case static::DRIVER_SQLSERVER:
                $age = $query->newExpr()->add('ISNULL(DATEDIFF(SECOND, GETDATE(), not_before), 0)');
                break;
            case static::DRIVER_POSTGRES:
                $age = $query->newExpr()->add('COALESCE((EXTRACT(EPOCH FROM now()) - EXTRACT(EPOCH FROM not_before)), 0)');
                break;
        }
        $options = [
            'conditions' => [
                'completed IS' => null,
                'OR' => []
            ],
            'fields' => [
                'age' => $age
            ],
            'order' => [
                'age' => 'ASC',
                'id' => 'ASC'
            ]
        ];

        if ($types) {
            $options['conditions'] = $this->addFilter($options['conditions'], 'task', $types);
        }

        // Generate the task specific conditions.
        foreach ($capabilities as $task) {
            list ($plugin, $name) = pluginSplit($task['name']);
            $timeoutAt = $now->copy();
            $tmp = [
                'task' => $name,
                'AND' => [
                    [
                        'OR' => [
                            'not_before <=' => $nowStr,
                            'not_before IS' => null
                        ]
                    ],
                    [
                        'OR' => [
                            'fetched <' => $timeoutAt->subSeconds($task['timeout']),
                            'fetched IS' => null
                        ]
                    ]
                ],
                'failed_count <' => ($task['retries'] + 1)
            ];
            $options['conditions']['OR'][] = $tmp;
        }

        /** @var \Queue\Model\Entity\QueuedTask|null $task */
        $task = $this->getConnection()->transactional(function () use ($query, $options, $now): ?QueuedTask {
            $task = $query->find('all', $options)
                ->enableAutoFields(true)
                ->epilog('FOR UPDATE')
                ->first();

            if (!$task) {
                return null;
            }

            $key = sha1(microtime());
            /* @phan-suppress-next-line PhanPartialTypeMismatchArgument */
            $task = $this->patchEntity($task, [
                'worker_key' => $key,
                'fetched' => $now
            ]);

            return $this->saveOrFail($task);
        });

        if (!$task) {
            return null;
        }

        return $task;
    }

    /**
     * Mark a task as Completed, removing it from the queue.
     *
     * @param \Queue\Model\Entity\QueuedTask $task Task
     * @return bool Success
     */
    public function markJobDone(QueuedTask $task)
    {
        $fields = [
            'completed' => $this->getDateTime()
        ];
        $task = $this->patchEntity($task, $fields);

        return (bool)$this->save($task);
    }

    /**
     * Mark a job as Failed, incrementing the failed-counter and Requeueing it.
     *
     * @param \Queue\Model\Entity\QueuedTask $task Task
     * @param string|null $failureMessage Optional message to append to the failure_message field.
     * @return bool Success
     */
    public function markJobFailed(QueuedTask $task, $failureMessage = null)
    {
        $fields = [
            'failed_count' => $task->failed_count + 1,
            'failure_message' => $failureMessage
        ];
        $task = $this->patchEntity($task, $fields);

        return (bool)$this->save($task);
    }

    /**
     * Reset current jobs
     *
     * @param int|null $id ID
     *
     * @return int Success
     */
    public function reset($id = null)
    {
        $fields = [
            'completed' => null,
            'fetched' => null,
            'failed_count' => 0,
            'worker_key' => null,
            'failure_message' => null
        ];
        $conditions = [
            'completed IS' => null
        ];
        if ($id) {
            $conditions['id'] = $id;
        }

        return $this->updateAll($fields, $conditions);
    }

    /**
     *
     * @param string $taskName Task name
     *
     * @return int
     */
    public function rerun($taskName)
    {
        $fields = [
            'completed' => null,
            'fetched' => null,
            'failed_count' => 0,
            'worker_key' => null,
            'failure_message' => null
        ];
        $conditions = [
            'completed IS NOT' => null,
            'task' => $taskName
        ];

        return $this->updateAll($fields, $conditions);
    }

    /**
     * Cleanup/Delete Completed Tasks.
     *
     * @return void
     */
    public function cleanOldJobs()
    {
        if (!Configure::read('Queue.cleanuptimeout')) {
            return;
        }

        $this->deleteAll([
            'completed <' => time() - (int)Configure::read('Queue.cleanuptimeout')
        ]);
    }

    /**
     *
     * @param \Queue\Model\Entity\QueuedTask $queuedTask Queued task
     * @param array $taskConfiguration Task configuration
     * @return string
     */
    public function getFailedStatus($queuedTask, array $taskConfiguration)
    {
        $failureMessageRequeued = 'requeued';

        $queuedTaskName = 'Queue' . $queuedTask->task;
        if (empty($taskConfiguration[$queuedTaskName])) {
            return $failureMessageRequeued;
        }
        $retries = $taskConfiguration[$queuedTaskName]['retries'];
        if ($queuedTask->failed_count <= $retries) {
            return $failureMessageRequeued;
        }

        return 'aborted';
    }

    /**
     * truncate()
     *
     * @return void
     */
    public function truncate()
    {
        $sql = $this->getSchema()->truncateSql($this->_connection);
        foreach ($sql as $snippet) {
            $this->_connection->execute($snippet);
        }
    }

    /**
     * get the name of the driver
     *
     * @return string
     */
    protected function _getDriverName()
    {
        $className = explode('\\', $this->getConnection()->config()['driver']);
        $name = end($className) ?: '';

        return $name;
    }

    /**
     *
     * @param array $conditions Conditions
     * @param string $key Key
     * @param array $values Values
     * @return array
     */
    protected function addFilter(array $conditions, $key, array $values)
    {
        $include = [];
        $exclude = [];
        foreach ($values as $value) {
            if (substr($value, 0, 1) === '-') {
                $exclude[] = substr($value, 1);
            } else {
                $include[] = $value;
            }
        }

        if ($include) {
            $conditions[$key . ' IN'] = $include;
        }
        if ($exclude) {
            $conditions[$key . ' NOT IN'] = $exclude;
        }

        return $conditions;
    }

    /**
     * Returns a DateTime object from different input.
     *
     * Without argument this will be "now".
     *
     * @param int|string|\Cake\I18n\FrozenTime|\Cake\I18n\Time|null $notBefore Not before time
     *
     * @return \Cake\I18n\FrozenTime|\Cake\I18n\Time
     */
    protected function getDateTime($notBefore = null)
    {
        if (is_object($notBefore)) {
            return $notBefore;
        }

        return new FrozenTime($notBefore);
    }
}
