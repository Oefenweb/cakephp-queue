<?php
/**
 * QueuedTask Fixture.
 *
 */
class QueuedTaskFixture extends CakeTestFixture {

/**
 * Fields.
 *
 * @var array
 */
	public $fields = [
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

/**
 * Records.
 *
 * @var array
 */
	public $records = [
		[
			'id' => 1,
			'task' => 'Example',
			'data' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
			'not_before' => '2014-12-19 17:12:30',
			'fetched' => '2014-12-19 17:12:30',
			'completed' => '2014-12-19 17:12:30',
			'failed_count' => 1,
			'failure_message' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
			'worker_key' => 'Lorem ipsum dolor sit amet',
			'created' => '2014-12-19 17:12:30'
		],
	];

/**
 * Constructor.
 *
 *  Generates dynamic records.
 *
 * @return void
 */
	public function __construct() {
		$this->records[] = [
			'id' => 2,
			'task' => 'Example',
			'data' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
			'not_before' => '2014-12-19 17:12:30',
			'fetched' => '2014-12-19 17:12:30',
			'completed' => date('Y-m-d H:i:s', strtotime('+1 minute')),
			'failed_count' => 1,
			'failure_message' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
			'worker_key' => 'Lorem ipsum dolor sit amet',
			'created' => '2014-12-19 17:12:30'
		];
		$this->records[] = [
				'id' => 3,
				'task' => 'Example',
				'data' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
				'not_before' => '2014-12-19 17:12:30',
				'fetched' => '2014-12-19 17:12:30',
				'completed' => date('Y-m-d H:i:s', strtotime('-15 minute')),
				'failed_count' => 1,
				'failure_message' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
				'worker_key' => 'Lorem ipsum dolor sit amet',
				'created' => '2014-12-19 17:12:30'
		];
		$this->records[] = [
				'id' => 4,
				'task' => 'Example',
				'data' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
				'not_before' => '2014-12-19 17:12:30',
				'fetched' => '2014-12-19 17:12:30',
				'completed' => date('Y-m-d H:i:s', strtotime('-5 minute')),
				'failed_count' => 1,
				'failure_message' => 'Lorem ipsum dolor sit amet, aliquet feugiat. Convallis morbi fringilla gravida, phasellus feugiat dapibus velit nunc, pulvinar eget sollicitudin venenatis cum nullam, vivamus ut a sed, mollitia lectus. Nulla vestibulum massa neque ut et, id hendrerit sit, feugiat in taciti enim proin nibh, tempor dignissim, rhoncus duis vestibulum nunc mattis convallis.',
				'worker_key' => 'Lorem ipsum dolor sit amet',
				'created' => '2014-12-19 17:12:30'
		];

		parent::__construct();
	}

}
