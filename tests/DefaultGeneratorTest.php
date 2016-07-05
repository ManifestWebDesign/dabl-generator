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
		$this->generator = new DefaultGenerator('test');

		return parent::setUp();
	}

	static function setUpBeforeClass() {
		DBManager::addConnection('test', array(
			'driver' => 'sqlite',
			'dbname' => ':memory:'
		));

		/**
		 * @var \Dabl\Adapter\DABLPDO
		 */
		$conn = DBManager::getConnection('test');
		$conn->exec('CREATE TABLE user (
			id INTEGER,
			name,
			PRIMARY KEY(id ASC)
		)');

		$conn->exec('CREATE TABLE post (
			id INTEGER,
			user_id INTEGER,
			content,
			PRIMARY KEY(id ASC),
			FOREIGN KEY(user_id) REFERENCES user(id)
		)');

		self::deleteFiles();

		foreach (['output/views', 'output/controllers', 'output/models/base'] as $dir) {
			if (!is_dir(__DIR__ . '/' . $dir)) {
				mkdir(__DIR__ . '/' . $dir, 0755, true);
			}
		}

		return parent::setUpBeforeClass();
	}

	static function deleteFiles() {
		foreach (glob(__DIR__ . '/output/*/*/*.php') as $file) {
			unlink($file);
		}
		foreach (glob(__DIR__ . '/output/*/*.php') as $file) {
			unlink($file);
		}
		foreach (glob(__DIR__ . '/output/*/*.sql') as $file) {
			unlink($file);
		}
		foreach (['models/base', 'models', 'views/users', 'views/posts', 'views', 'controllers', ''] as $dir) {
			if (is_dir(__DIR__ . '/output/' . $dir)) {
				rmdir(__DIR__ . '/output/' . $dir);
			}
		}
	}

	static function tearDownAfterClass() {
		DBManager::disconnect('test');

		self::deleteFiles();

		return parent::tearDownAfterClass();
	}

	function testGenerateModels() {

		$this->generator->generateModels(
			['user', 'post'],
			__DIR__ . '/output/models',
			__DIR__ . '/output/models/base'
		);

	}

	function testGenerateViews() {

		$this->generator->generateViews(
			['user', 'post'],
			__DIR__ . '/output/views'
		);

	}

	function testGenerateControllers() {

		$this->generator->generateControllers(
			['user', 'post'],
			__DIR__ . '/output/controllers'
		);

	}

}