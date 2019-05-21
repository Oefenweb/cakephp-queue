<?php
/**
 * All Queue plugin tests.
 */
class AllQueueTest extends CakeTestCase {

/**
 * Defines the tests for this plugin.
 *
 * @return void
 */
	public static function suite() {
		$suite = new CakeTestSuite('All Queue test');

		$path = CakePlugin::path('Queue') . 'Test' . DS . 'Case' . DS;
		$suite->addTestDirectoryRecursive($path);

		return $suite;
	}

}
