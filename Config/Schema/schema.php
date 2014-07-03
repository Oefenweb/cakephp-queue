<?php
class QueueSchema extends CakeSchema {

/**
 * Before callback.
 *
 * @param array $event Schema object properties
 * @return boolean Always true
 */
	public function before($event = array()) {
		return true;
	}

/**
 * After callback.
 *
 * @param array $event Schema object properties
 * @return void
 */
	public function after($event = array()) {
	}

	public $queued_tasks = array(
		'id' => array('type' => 'integer', 'null' => false, 'default' => null, 'length' => 10, 'key' => 'primary'),
		'task' => array('type' => 'string', 'null' => false, 'default' => null, 'key' => 'index', 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
		'data' => array('type' => 'text', 'null' => true, 'default' => null, 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
		'not_before' => array('type' => 'datetime', 'null' => false, 'default' => null, 'key' => 'index'),
		'fetched' => array('type' => 'datetime', 'null' => true, 'default' => null, 'key' => 'index'),
		'completed' => array('type' => 'datetime', 'null' => true, 'default' => null, 'key' => 'index'),
		'failed_count' => array('type' => 'integer', 'null' => false, 'default' => null, 'length' => 10),
		'failure_message' => array('type' => 'text', 'null' => true, 'default' => null, 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
		'worker_key' => array('type' => 'string', 'null' => true, 'default' => null, 'length' => 40, 'key' => 'index', 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
		'created' => array('type' => 'datetime', 'null' => false, 'default' => null),
		'indexes' => array(
			'PRIMARY' => array('column' => 'id', 'unique' => 1),
			'completed' => array('column' => 'completed', 'unique' => 0),
			'not_before' => array('column' => 'not_before', 'unique' => 0),
			'fetched' => array('column' => 'fetched', 'unique' => 0),
			'worker_key' => array('column' => 'worker_key', 'unique' => 0),
			'task' => array('column' => 'task', 'unique' => 0)
		),
		'tableParameters' => array('charset' => 'utf8', 'collate' => 'utf8_general_ci', 'engine' => 'InnoDB')
	);

}
