[![Build Status](https://travis-ci.org/ManifestWebDesign/dabl-generator.svg?branch=master)](https://travis-ci.org/ManifestWebDesign/dabl-generator)

# dabl-generator
Simple MVC code generator

## Example

### Setup
```php
use Dabl\Generator\DefaultGenerator;
use Dabl\Query\DBManager;

// setup database connection
DBManager::addConnection('test', array(
    'driver' => 'sqlite',
    'dbname' => ':memory:'
));

// create databse tables
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
```

### Generate
```php
$generator = new DefaultGenerator('test');
$generator->generateModels(
    ['user', 'post'],
    './models'
);

$generator->generateViews(
    ['user', 'post'],
    './views'
);

$generator->generateControllers(
    ['user', 'post'],
    './controllers'
);
```
