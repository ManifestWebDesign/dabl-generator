<?php

use Dabl\Generator\DefaultGenerator;
use Dabl\Query\DBManager;

/**
 * Created by IntelliJ IDEA.
 * User: dan
 * Date: 7/4/16
 * Time: 11:20 AM
 */
class DefaultGeneratorTest extends PHPUnit_Framework_TestCase {

	/**
	 * @var DefaultGenerator
	 */
	protected $generator;

	function setUp() {
		DBManager::addConnection('test', array(
			'driver' => 'sqlite',
			'dbname' => ':memory:'
		));

		/**
		 * @var \Dabl\Adapter\DABLPDO
		 */
		$conn = DBManager::getConnection('test');
		$conn->exec('CREATE TABLE my_table (
			id INTEGER,
			name,
			date,
			PRIMARY KEY(id ASC)
		)');

		$this->generator = new DefaultGenerator('test');
		return parent::setUp();
	}

	function tearDown() {
		DBManager::disconnect('test');

		return parent::tearDown();
	}

	function testGenerateModels() {
		$this->generator->generateModels(
			array('my_table'),
			__DIR__ . '/output',
			__DIR__ . '/output/base'
		);
	}

}