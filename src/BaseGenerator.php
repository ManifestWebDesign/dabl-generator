<?php

/**
 * @link https://github.com/ManifestWebDesign/DABL
 * @link http://manifestwebdesign.com/redmine/projects/dabl
 * @author Manifest Web Design
 * @license    MIT License
 */

namespace Dabl\Generator;

use Dabl\Adapter\Propel\Model\Database;
use Dabl\Query\DBManager;
use DOMDocument;
use RuntimeException;

abstract class BaseGenerator {

	/**
	 * @var array
	 */
	protected $viewTemplates = array();

	/**
	 * @var string
	 */
	protected $controllerTemplate;

	/**
	 * @var array
	 */
	private $options = array(
		// prepend this to class name
		'model_prefix' => '',

		// append this to class name
		'model_suffix' => '',

		// append this to class name
		'base_model_parent_class' => 'Model',
	);

	/**
	 * @var string
	 */
	private $connectionName;

	/**
	 * @var DOMDocument
	 */
	private $dbSchema;

	/**
	 *
	 * @var Database
	 */
	private $database;

	/**
	 * @var string
	 */
	protected $baseModelTemplate;

	/**
	 * @var string
	 */
	protected $baseModelQueryTemplate;

	/**
	 * @var string
	 */
	protected $modelTemplate;

	/**
	 * @var string
	 */
	protected $modelQueryTemplate;

	/**
	 * Constructor function
	 * @param $connection_name string
	 */
	function __construct($connection_name) {
		$this->baseModelTemplate = __DIR__ . '/templates/base-model.php';
		$this->baseModelQueryTemplate = __DIR__ . '/templates/base-model-query.php';
		$this->modelTemplate = __DIR__ . '/templates/model.php';
		$this->modelQueryTemplate = __DIR__ . '/templates/model-query.php';

		$this->setConnectionName($connection_name);
		$conn = DBManager::getConnection($connection_name);
		$this->database = $conn->getDatabaseSchema();

		$dom = new DOMDocument('1.0', 'utf-8');
		$this->database->appendXml($dom);
		$dom->formatOutput = true;
		$this->setSchema($dom);
	}

	/**
	 * @param DOMDocument $schema
	 */
	function setSchema(DOMDocument $schema) {
		$this->dbSchema = $schema;
	}

	/**
	 * @return DOMDocument
	 */
	function getSchema() {
		return $this->dbSchema;
	}

	function setOptions($options) {
		$this->options = array_merge($this->options, $options);
	}

	function getOptions() {
		return $this->options;
	}

	/**
	 * Returns an array of all the table names in the XML schema
	 * @return array
	 */
	function getTableNames() {
		$table_names = array();
		foreach ($this->database->getTables() as $table)
			$table_names[] = $table->getName();
		return $table_names;
	}

	/**
	 * Returns an array of Column objects for a given table in the XML schema
	 * @param string $table_name
	 * @return array Column[]
	 */
	function getColumns($table_name) {
		$table = $this->database->getTable($table_name);
		if (empty($table)) {
			throw new RuntimeException("Unable to get structure for table '$table_name'");
		}
		return $table->getColumns();
	}

	/**
	 * @param string $table_name
	 * @return array Column[]
	 */
	function getPrimaryKeys($table_name) {
		$table = $this->database->getTable($table_name);
		if (empty($table)) {
			throw new RuntimeException("Unable to get structure for table '$table_name'");
		}
		return $table->getPrimaryKey();
	}

	/**
	 * @param string $table_name
	 * @return array
	 */
	function getForeignKeysFromTable($table_name) {
		$table = $this->database->getTable($table_name);
		if (empty($table)) {
			throw new RuntimeException("Unable to get structure for table '$table_name'");
		}
		return $table->getForeignKeys();
	}

	/**
	 * @param string $table_name
	 * @return array
	 */
	function getForeignKeysToTable($table_name) {
		$table = $this->database->getTable($table_name);
		if (empty($table)) {
			throw new RuntimeException("Unable to get structure for table '$table_name'");
		}
		return $table->getReferrers();
	}

	/**
	 * @return string
	 */
	function getDBName() {
		return DBManager::getConnection($this->getConnectionName())->getDBName();
	}

	/**
	 * @param string $conn_name
	 */
	function setConnectionName($conn_name) {
		$this->connectionName = $conn_name;
	}

	/**
	 * @return string
	 */
	function getConnectionName() {
		return $this->connectionName;
	}

	function getTemplateParams($table_name) {
		$class_name = $this->getModelName($table_name);
		$column_names = $PKs = array();
		$auto_increment = false;
		$columns = $this->getColumns($table_name);
		$pks = $this->getPrimaryKeys($table_name);
		$pk = null;

		foreach ($columns as &$column) {
			$column_names[] = $column->getName();
			if ($column->isPrimaryKey()) {
				$PKs[] = $column->getName();
				if ($column->isAutoIncrement()) {
					$auto_increment = true;
				}
			}
		}

		if (count($PKs) == 1) {
			$pk = $PKs[0];
		} else {
			$auto_increment = false;
		}

		return array(
			'conn' => DBManager::getConnection($this->getConnectionName()),
			'options' => $this->options,
			'auto_increment' => $auto_increment,
			'table_name' => $table_name,
			'controller_name' => $this->getControllerName($table_name),
			'model_name' => $class_name,
			'column_names' => $column_names,
			'plural' => StringFormat::pluralVariable($table_name),
			'plural_url' => StringFormat::pluralURL($table_name),
			'single' => StringFormat::variable($table_name),
			'single_url' => StringFormat::url($table_name),
			'pk' => $pk,
			'pk_var' => $pk ? StringFormat::variable($pk) : null,
			'primary_keys' => $PKs,
			'pk_method' => $pk ? StringFormat::classMethod('get' . StringFormat::titleCase($pk)) : null,
			'actions' => $this->getActions($table_name),
			'columns' => $columns
		);
	}

	/**
	 * @param string $table_name
	 * @param string $template Path to file relative to dirname(__FILE__) with leading /
	 * @param array $extra_params
	 * @return string
	 */
	function renderTemplate($table_name, $template, $extra_params = array()) {
		$params = $this->getTemplateParams($table_name);
		$params = array_merge($params, $extra_params);
		foreach ($params as $key => &$value)
			$$key = $value;

		ob_start();
		require $template;
		return ob_get_clean();
	}

	/**
	 * @return string Path to base model template file relative to dirname(__FILE__) with leading /
	 */
	function getBaseModelTemplate() {
		return $this->baseModelTemplate;
	}

	/**
	 * @return string Path to model template file relative to dirname(__FILE__) with leading /
	 */
	function getModelTemplate() {
		return $this->modelTemplate;
	}

	/**
	 * @return array Paths to view template files relative to dirname(__FILE__) with leading /
	 */
	function getViewTemplates() {
		return $this->viewTemplates;
	}

	/**
	 * @return string Path to controller template file relative to dirname(__FILE__) with leading /
	 */
	function getControllerTemplate() {
		return $this->controllerTemplate;
	}

	/**
	 * Converts a table name to a class name using the given options.  Often used
	 * to add class prefixes and/or suffixes, or to convert a class_name to a title case
	 * ClassName
	 * @param string $table_name
	 * @return string
	 */
	function getModelName($table_name) {
		$options = $this->options;
		$class_name = StringFormat::className($table_name);

		if (@$options['model_prefix']) {
			$class_name = $options['model_prefix'] . $class_name;
		}
		if (@$options['model_suffix']) {
			$class_name = $class_name . $options['model_suffix'];
		}
		return $class_name;
	}

	/**
	 * @param string $table_name
	 * @return string
	 */
	function getViewDirName($table_name) {
		return StringFormat::pluralURL($table_name);
	}

	/**
	 * @param string $table_name
	 * @return string
	 */
	function getControllerName($table_name) {
		$controller_name = StringFormat::plural($table_name);
		return StringFormat::className($controller_name) . 'Controller';
	}

	function getControllerFileName($table_name) {
		return $this->getControllerName($table_name) . '.php';
	}

	/**
	 * Generates a string with the contents of the Base class
	 * @param string $table_name
	 * @return string
	 */
	function getBaseModel($table_name) {
		return $this->renderTemplate($table_name, $this->getBaseModelTemplate());
	}

	/**
	 * Generates a string with the contents of the stub class
	 * for the table, which is used for extending the Base class.
	 * @param string $table_name
	 * @return string
	 */
	function getModel($table_name) {
		return $this->renderTemplate($table_name, $this->getModelTemplate());
	}

	function getModelQuery($table_name) {
		return $this->renderTemplate($table_name, $this->modelQueryTemplate);
	}

	function getBaseModelQuery($table_name) {
		return $this->renderTemplate($table_name, $this->baseModelQueryTemplate);
	}

	/**
	 * Returns an associative array of file contents for
	 * each view generated by this class
	 * @param string $table_name
	 * @return array
	 */
	function getViews($table_name) {
		$rendered_views = array();

		foreach ($this->getViewTemplates() as $file_name => $view_template) {
			$rendered_views[$file_name] = $this->renderTemplate($table_name, $view_template);
		}
		return $rendered_views;
	}

	/**
	 * Generates a String with Controller class for MVC
	 * @param String $table_name
	 * @return String
	 */
	function getController($table_name) {
		return $this->renderTemplate($table_name, $this->getControllerTemplate());
	}

	/**
	 * Generates Table classes
	 * @param array $table_names
	 * @param string $model_query_dir
	 * @param string $base_model_query_dir
	 */
	function generateModelQueries($table_names, $model_query_dir, $base_model_query_dir) {
		if ($table_names === null) {
			$table_names = $this->getTableNames();
		}

		if (empty($table_names)) {
			return;
		}

		$model_query_dir = $this->normalizeAndCheckPath($model_query_dir);
		$base_model_query_dir = $this->normalizeAndCheckPath($base_model_query_dir);

		//Write php files for classes
		foreach ($table_names as &$table_name) {
			$class_name = $this->getModelName($table_name);

			$base_query = $this->getBaseModelQuery($table_name);
			$base_query_file = "base{$class_name}Query.php";
			$base_query_file = $base_model_query_dir . $base_query_file;

			if (!file_exists($base_query_file) || file_get_contents($base_query_file) != $base_query) {
				file_put_contents($base_query_file, $base_query);
			}

			$query_file = "{$class_name}Query.php";
			$query_file = $model_query_dir . $query_file;
			if (!file_exists($query_file)) {
				$query = $this->getModelQuery($table_name);
				file_put_contents($query_file, $query);
			}
		}
	}

	/**
	 * @param string $path
	 * @return mixed|string
	 */
	function normalizeAndCheckPath($path) {
		if (empty($path)) {
			throw new RuntimeException('Path cannot be empty');
		}

		$path = str_replace('\\', '/', $path);
		if (stripos(strrev($path), '/') !== 0) {
			$path .= '/';
		}

		if (!is_dir($path)) {
			throw new RuntimeException("Path '$path' does not exist");
		}
		return $path;
	}

	/**
	 * Generates Table classes
	 * @param array $table_names
	 * @param string $model_dir
	 * @param string $base_model_dir
	 */
	function generateModels($table_names, $model_dir, $base_model_dir = null) {
		if ($table_names === null) {
			$table_names = $this->getTableNames();
		}

		if (empty($table_names)) {
			return;
		}

		$model_dir = $this->normalizeAndCheckPath($model_dir);

		if (empty($base_model_dir)) {
			$base_model_dir = $model_dir . '/base/';
		}
		$base_model_dir = $this->normalizeAndCheckPath($base_model_dir);

		// Write PHP classes for each table
		foreach ($table_names as &$table_name) {
			$class_name = $this->getModelName($table_name);

			$base_class = $this->getBaseModel($table_name);
			$base_file = "base{$class_name}.php";
			$base_file = $base_model_dir . $base_file;

			if (!file_exists($base_file) || file_get_contents($base_file) != $base_class) {
				file_put_contents($base_file, $base_class);
			}

			$file = $model_dir . $class_name . ".php";
			if (!file_exists($file)) {
				$class = $this->getModel($table_name);
				file_put_contents($file, $class);
				unset($class);
			}
		}

		$sql = $this->database->getPlatform()->getAddTablesDDL($this->database);

		// save SQL to file
		file_put_contents($model_dir . $this->getConnectionName() . "-schema.sql", $sql);
	}

	/**
	 * Generate views
	 * @param array $table_names
	 * @param string $view_directory
	 */
	function generateViews($table_names, $view_directory) {
		if ($table_names === null) {
			$table_names = $this->getTableNames();
		}

		if (empty($table_names)) {
			return;
		}

		$view_directory = $this->normalizeAndCheckPath($view_directory);

		foreach ((array) $table_names as $table_name) {
			$target_dir = $view_directory . $this->getViewDirName($table_name) . '/';

			if (!is_dir($target_dir)) {
				mkdir($target_dir, 0755);
			}

			foreach ($this->getViews($table_name) as $file_name => $contents) {
				$file_name = $target_dir . $file_name;

				if (!file_exists($file_name)) {
					file_put_contents($file_name, $contents);
				}
			}
		}
	}

	/**
	 * Generate controllers
	 * @param array $table_names
	 * @param string $controller_directory
	 */
	function generateControllers($table_names, $controller_directory) {
		if ($table_names === null) {
			$table_names = $this->getTableNames();
		}

		if (empty($table_names)) {
			return;
		}

		$controller_directory = $this->normalizeAndCheckPath($controller_directory);

		foreach ($table_names as &$table_name) {
			$file = $this->getControllerFileName($table_name);
			$file = $controller_directory . $file;
			if (!file_exists($file)) {
				$controller = $this->getController($table_name);
				file_put_contents($file, $controller);
			}
		}
	}

}