<?php
class QueueSchema extends CakeSchema {

/**
 * Before callback.
 *
 * @param array $event Schema object properties
 * @return bool Always true
 */
	public function before($event = []) {
		return true;
	}

/**
 * After callback.
 *
 * @param array $event Schema object properties
 * @return void
 */
	public function after($event = []) {
	}

	public $queued_tasks = [
		'id' => ['type' => 'integer', 'null' => false, 'default' => null, 'length' => 10, 'unsigned' => true, 'key' => 'primary'],
		'task' => ['type' => 'string', 'null' => false, 'default' => null, 'key' => 'index', 'collate' => 'utf8_general_ci', 'charset' => 'utf8'],
		'data' => ['type' => 'text', 'null' => true, 'default' => null, 'collate' => 'utf8_general_ci', 'charset' => 'utf8'],
		'not_before' => ['type' => 'timestamp', 'null' => true, 'default' => null],
		'fetched' => ['type' => 'timestamp', 'null' => true, 'default' => null],
		'completed' => ['type' => 'timestamp', 'null' => true, 'default' => null, 'key' => 'index'],
		'failed_count' => ['type' => 'integer', 'null' => false, 'default' => '0', 'length' => 10, 'unsigned' => true],
		'failure_message' => ['type' => 'text', 'null' => true, 'default' => null, 'collate' => 'utf8_general_ci', 'charset' => 'utf8'],
		'worker_key' => ['type' => 'string', 'null' => true, 'default' => null, 'length' => 40, 'key' => 'index', 'collate' => 'utf8_general_ci', 'charset' => 'utf8'],
		'created' => ['type' => 'timestamp', 'null' => true, 'default' => null],
		'indexes' => [
			'PRIMARY' => ['column' => 'id', 'unique' => 1],
			'completed' => ['column' => 'completed', 'unique' => 0],
			'worker_key' => ['column' => 'worker_key', 'unique' => 0],
			'task' => ['column' => 'task', 'unique' => 0]
		],
		'tableParameters' => ['charset' => 'utf8', 'collate' => 'utf8_general_ci', 'engine' => 'InnoDB']
	];

}
