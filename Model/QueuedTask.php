<?php
App::uses('AppModel', 'Model');

/**
 * QueuedTask Model.
 *
 */
class QueuedTask extends AppModel {

/**
 * The (translation) domain to be used for extracted validation messages in models.
 *
 * @var string
 */
	public $validationDomain = 'queue';

/**
 * Adds a new Job to the queue.
 *
 * @param string $taskName A queue task name
 * @param mixed $data Any data
 * @param string $notBefore A datetime which indicates when the job may be executed
 * @return mixed On success `Model::$data` if its not empty or true, false on failure
 */
	public function createJob($taskName, $data, $notBefore = null) {
		$data = array(
			'task' => $taskName,
			'data' => serialize($data),
			'not_before' => date('Y-m-d H:i:s'),
		);

		if (!empty($notBefore)) {
			$data['not_before'] = date('Y-m-d H:i:s', strtotime($notBefore));
		}

		$this->create();

		return $this->save($data);
	}

/**
 * Looks for a new job that can be processed with the current abilities
 *
 * @param array $capabilities Available queue worker tasks.
 * @return mixed Job data or false.
 */
	public function requestJob($capabilities) {
		$idlist = array();
		$wasFetched = array();

		$this->virtualFields['age'] = 'IFNULL(TIMESTAMPDIFF(SECOND, NOW(), not_before), 0)';
		$conditions = array(
			'completed' => null,
			'OR' => array()
		);
		$fields = array(
			'id',
			'fetched',
			'age'
		);
		$order = array(
			'age' => 'ASC',
			'id' => 'ASC'
		);
		$limit = Configure::read('Queue.workers');

		// Generate the job specific conditions.
		foreach ($capabilities as $task) {
			list($plugin, $name) = pluginSplit($task['name']);
			$tmp = array(
				'task' => $name,
				'AND' => array(
					'not_before <=' => date('Y-m-d H:i:s'),
					array(
						'OR' => array(
							'fetched <' => date('Y-m-d H:i:s', time() - $task['timeout']),
							'fetched' => null
						)
					)
				),
				'failed_count <' => ($task['retries'] + 1)
			);
			$conditions['OR'][] = $tmp;
		}

		// First, find a list of a few of the oldest unfinished jobs.
		$data = $this->find('all', compact('conditions', 'fields', 'order', 'limit'));

		if (!empty($data)) {
			// Generate a list of their ids
			foreach ($data as $item) {
				$idlist[] = $item[$this->name]['id'];
				if (!empty($item[$this->name]['fetched'])) {
					$wasFetched[] = $item[$this->name]['id'];
				}
			}

			// Generate a unique identifier for the current worker thread
			$key = sha1(microtime());

			// Try to update one of the found jobs with the key of this worker.
			$this->query(
				'UPDATE ' . $this->tablePrefix . $this->table . ' SET worker_key = "' . $key .
				'", fetched = "' . date('Y-m-d H:i:s') . '" WHERE ' .
				'id IN(' . implode(',', $idlist) . ') AND ' .
				'(worker_key IS NULL OR fetched <= "' . date('Y-m-d H:i:s', time() - $task['timeout']) . '") ' .
				'ORDER BY ' . $this->virtualFields['age'] . ' ASC LIMIT 1'
			);

			// Read which one actually got updated, which is the job we are supposed to execute.
			$conditions = array('worker_key' => $key);
			$data = $this->find('first', compact('conditions'));
			if (!empty($data)) {
				// If the job had an existing fetched timestamp, increment the failure counter.
				if (in_array($data[$this->name]['id'], $wasFetched)) {
					$data[$this->name]['failed_count'] += 1;
					$data[$this->name]['failure_message'] = 'Restart after timeout';
					$this->save($data);
				}

				return $data[$this->name];
			}
		}

		return false;
	}

/**
 * Marks a job as completed, removing it from the queue.
 *
 * @param int $id A job id
 * @return mixed On success `Model::$data` if its not empty or true, false on failure
 */
	public function markJobDone($id) {
		$this->id = $id;

		return $this->saveField('completed', date('Y-m-d H:i:s'), true);
	}

/**
 * Marks a job as failed, incrementing the failed-counter and requeueing it.
 *
 * @param int $id A job id
 * @param string $failureMessage A message to append to the failure message field (optional)
 * @return bool Success
 * @todo Remove / reimplement getDataSource()->value
 */
	public function markJobFailed($id, $failureMessage = null) {
		$conditions = compact('id');
		$fields = array(
			'failed_count' => 'failed_count + 1',
			'failure_message' => $this->getDataSource()->value($failureMessage, 'failure_message')
		);

		return $this->updateAll($fields, $conditions);
	}

/**
 * Returns the number of items in the queue.
 *
 *	Either returns the number of ALL pending jobs, or the number of pending jobs of the passed task.
 *
 * @param string $taskName A task name to count
 * @return int The number of pending jobs
 */
	public function getLength($taskName = null) {
		$conditions = array('completed' => null);
		if (!empty($taskName)) {
			$conditions['task'] = $taskName;
		}

		return $this->find('count', compact('conditions'));
	}

/**
 * Return a list of all task names in the queue.
 *
 * @return array A list of task names
 */
	public function getTypes() {
		$fields = array('task');
		$group = array('task');

		return $this->find('list', compact('fields', 'group'));
	}

/**
 * Calculates some statistics for finished jobs (that are still in the database).
 *
 * @return array An array with statistics
 */
	public function getStats() {
		$fields = array(
			'task',
			'COUNT(id) AS num',
			'AVG(UNIX_TIMESTAMP(completed) - UNIX_TIMESTAMP(created)) AS alltime',
			'AVG(UNIX_TIMESTAMP(completed) - UNIX_TIMESTAMP(fetched)) AS runtime',
			'AVG(UNIX_TIMESTAMP(fetched) - IF(not_before IS NULL, UNIX_TIMESTAMP(created), UNIX_TIMESTAMP(not_before))) AS fetchdelay'
		);
		$conditions = array('NOT' => array('completed' => null));
		$group = array('task');

		return $this->find('all', compact('fields', 'conditions', 'group'));
	}

/**
 * Cleanups / delete completed jobs with given capabilities after cleanup timeout.
 *
 * @param array $capabilities Available queue worker tasks.
 * @return bool Success
 */
	public function cleanOldJobs($capabilities) {
		$conditions = array();

		// Generate the job specific conditions
		foreach ($capabilities as $task) {
			list($plugin, $name) = pluginSplit($task['name']);
			$conditions['OR'][] = array(
				'task' => $name,
				'completed <' => date('Y-m-d H:i:s', time() - $task['cleanupTimeout'])
			);
		}

		return $this->deleteAll($conditions, false);
	}

/**
 * Cleanups / delete failed jobs with given capabilities after maximum retries.
 *
 * @param array $capabilities Available queue worker tasks.
 * @return bool Success
 */
	public function cleanFailedJobs($capabilities) {
		$conditions = array();

		// Generate the job specific conditions.
		foreach ($capabilities as $task) {
			list($plugin, $name) = pluginSplit($task['name']);
			$conditions['OR'][] = array(
				'task' => $name,
				'failed_count >' => $task['retries']
			);
		}

		return $this->deleteAll($conditions, false);
	}

}
