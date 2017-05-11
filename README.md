# Tarantool Mapper
[![License](https://poser.pugx.org/tarantool/mapper/license.png)](https://packagist.org/packages/tarantool/mapper)
[![Build Status](https://travis-ci.org/tarantool-php/mapper.svg?branch=master)](https://travis-ci.org/tarantool-php/mapper)
[![Latest Version](https://img.shields.io/github/release/tarantool-php/mapper.svg?style=flat-square)](https://github.com/tarantool-php/mapper/releases)
[![Total Downloads](https://img.shields.io/packagist/dt/tarantool/mapper.svg?style=flat-square)](https://packagist.org/packages/tarantool/mapper)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/tarantool-php/mapper/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/tarantool-php/mapper/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/tarantool-php/mapper/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/tarantool-php/mapper/?branch=master)

- [Installation](#installation)
- [Instantiate mapper](#instantiate-mapper)
- [Logging](#logging)
- [Existing types](#existing-types)
- [Describe entities](#describe-entities)
- [Use migrations](#use-migrations)
- [Use fluent api](#use-fluent-api)
- [Working with the data](#working-with-the-data)
- [Indexes](#indexes)
- [Array properties](#array-properties)
- [Sequence plugin](#sequence-plugin)
- [User-defined classes plugin](#user-defined-classes-plugin)
- [Annotation plugin](#annotation-plugin)
- [Internals](#internals)

## Installation
The recommended way to install the library is through [Composer](http://getcomposer.org):
```
$ composer require tarantool/mapper
```

## Instantiate mapper
Usually, you manage dependencies in your service provider.
To get started you should instantiate connection, packer, client and mapper itself.
In this example we use PurePacker and StreamConnection. It means you don't need any pecl extensions. To see other implementations please check [client documentation](https://github.com/tarantool-php/client#usage)

```php
use Tarantool\Client\Client;
use Tarantool\Client\Connection\StreamConnection;
use Tarantool\Client\Packer\PurePacker;
use Tarantool\Mapper\Mapper;

$connection = new StreamConnection();
$client = new Client($connection, new PurePacker());
$mapper = new Mapper($client);
```

## Logging
By default, client does not logs tarantool requests, you can use mapper\client that supports logging.
```php
use Tarantool\Client\Connection\StreamConnection;
use Tarantool\Client\Packer\PurePacker;
use Tarantool\Mapper\Mapper;
use Tarantool\Mapper\Client;

$connection = new StreamConnection();
$client = new Client($connection, new PurePacker());
$mapper = new Mapper($client);

$result = $client->ping();

$log = $client->getLog();
```

## Existing types
You can start with your current configuration.
Please, note - all instances are mapped to key-value objects.
```php
$globalSpace = $mapper->find('_space', ['name' => '_space']);
echo $globalSpace->id; // 280

$indexes = $mapper->find('_index', ['id' => $globalSpace->id]);
var_dump($indexes); // indexes on _index space
echo $indexes[0]->name;  // primary index
echo $indexes[0]->type; // tree

$guest = $mapper->find('_user', ['name' => 'guest']);
echo $guest->id; // 0
echo $guest->type; // user

```

## Describe entities
To get started you should describe your types and fields using meta object.
```php

$person = $mapper->getSchema()->createSpace('person');

// add properties
$person->addProperty('id', 'unsigned');
$person->addProperty('name', 'str');
$person->addProperty('birthday', 'unsigned');
$person->addProperty('gender', 'str');

// add multiple properties
$person->addProperties([
  'telegram' => 'str',
  'vk' => 'str',
  'facebook' => 'str',
]);

// add indexes
// first index is primary
$person->createIndex([
    'type' => 'hash', // define type
    'fields' => ['id'],
]);

// create unique indexes using property or array of properties as parameter
$person->createIndex('name');

// create not unique indexes
$person->createIndex([
    'fields' => 'birthday',
    'unique' => false
]);

// if you wish - you can specify index name
$person->createIndex([
    'fields' => ['name', 'birthday'],
    'type' => 'hash',
    'name' => 'name_with_birthday',
]);
```

## Use migrations

```php
use Tarantool\Mapper\Mapper;
use Tarantool\Mapper\Migration;

class InitTesterSchema implements Migration
{
  public function migrate(Mapper $mapper)
  {
    $tester = $mapper->getSchema()->createSpace('tester');
    $tester->addProperty('id', 'unsigned');
    $tester->addProperty('name', 'str');
    $tester->createIndex('id');
  }
}

$mapper->getBootstrap()->register(InitTesterSchema::class);
// or register instance $mapper->getBootstrap()->register(new InitTesterSchema());

$mapper->getBootstrap()->migrate();

```

## Use fluent api

```php
use Tarantool\Mapper\Mapper;
use Tarantool\Mapper\Migration;

class InitTesterSchema implements Migration
{
  public function migrate(Mapper $mapper)
  {
    $mapper->getSchema()->createSpace('person')
      ->addProperty('id', 'unsigned')
      ->addProperty('name', 'str')
      ->addIndex('id');
  }
}

```

## Working with the data
Now you can store and retreive data from tarantool storage using mapper instance.
```php
// get repository instance
$persons = $mapper->getRepository('person');

// create new entity
$dmitry = $persons->create([
  'id' => 1,
  'name' => 'Dmitry'
]);

// save
$mapper->save($dmitry);

// you can create entities using mapper wrapper.
// this way entity will be created and saved in the tarantool
$vasily = $mapper->create('person', [
  'id' => 2,
  'name' => 'Vasily'
]);

// you can retreive entites by id from repository
$helloWorld = $mapper->getRepository('post')->find(3);

// or using mapper wrapper
$helloWorld = $mapper->find('post', 3);

// updates are easy
$helloWorld->title = "Hello World!";
$mapper->save($helloWorld);
```
## Indexes
```php
$note = $mapper->getSchema()->createSpace('note');
$note->addProperty('slug', 'str');
$note->addProperty('title', 'str',
$note->addProperty('status', 'str');

$note->addIndex('slug');
$note->addIndex([
  'fields' => 'status',
  'unique' => false
]);

// find using repository
$mapper->getRepository('note')->find(['status' => 'active']);
// find using shortcut
$mapper->find('note', ['status' => 'active']);

// find first
$mapper->getRepository('note')->findOne(['slug' => 'my-secret-note']);

// composite indexes can be used partial
$person = $mapper->getSchema()->createSpace('person');
$person->addProperty('id', 'unsigned');
$person->addProperty('client', 'unsigned');
$person->addProperty('sector', 'unsigned');
$person->addProperty('name', 'unsigned');

$person->addIndex('id');
$person->addIndex([
  'fields' => ['client', 'sector'],
  'unique' => false
]);

// using index parts
$mapper->find('person', ['client' => 2]);
$mapper->find('person', ['client' => 2, 'sector' => 27]);
```

## Array properties
You can store arrays as property without any serialization to string.
```php
$pattern = $mapper->getSchema()->createSpace('shift_pattern');
$pattern->addProperty('id', 'unsigned');
$pattern->addProperty('title', 'str');
$pattern->addProperty('pattern', '*');

$pattern->addIndex('id');

$mapper->create('shift_pattern', [
  'id' => 1,
  'title' => '5 days week',
  'pattern' => [
    ['work' => true],
    ['work' => true],
    ['work' => true],
    ['work' => true],
    ['work' => true],
    ['work' => false],
    ['work' => false],
  ]
]);

$mapper->get('shift_pattern', 1)->pattern[5]; // read element with index 5 from pattern array
```

## Sequence plugin
If you want you can use sequence plugin that generates next value based on sequence space.
Or you can implement id generator using any other source, for example with raft protocol.
```php
$mapper->getSchema()->createSpace('post')
  ->addProperty('id', 'unsigned')
  ->addProperty('title', 'str')
  ->addProperty('body', 'str')
  ->addIndex('id');

$mapper->addPlugin(Tarantool\Mapper\Plugins\Sequence::class);

$entity = $mapper->create('post', [
  'title' => 'Autoincrement implemented',
  'body' => 'You can use Sequence plugin to track and fill your entity id'
]);

echo $entity->id; // will be set when you create an instance
```

## User-defined classes plugin
If you want you can specify classes to use for repository and entity instances.
Entity and repository class implementation are ommited, but you should just extend base classes.
```php
$userClasses = $mapper->addPlugin(Tarantool\Mapper\Plugins\UserClasses::class);
$userClasses->mapEntity('person', Application\Models\Person::class);
$userClasses->mapRepository('person', Application\Repositories\Person::class);

$nekufa = $mapper->create('person', [
  'email' => 'nekufa@gmail.com'
]);

get_class($nekufa); // Application\Models\Person;

$mapper->getSchema()->getSpace('person')->getRepository(); // will be instance of Application\Repositories\Person
```

## Annotation plugin
You can describe your entities using dobclock. Mapper will create space, format and indexes for you.

```php
namespace Entities;

use Tarantool\Mapper\Entity;

class Person extends Entity
{
    /**
     * @var integer
     */
    public $id;

    /**
     * @var string
     */
    public $name;
}

class Post extends Entity
{
    /**
     * @var integer
     */
    public $id;

    /**
     * @var string
     */
    public $slug;

    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $body;

    /**
     * @var Person
     */
    public $author;
}
```
If you want to index fields, extend repository and define indexes property
```php
namespace Repositories;

use Tarantool\Mapper\Repository;

class Post extends Repository
{
    public $indexes = [
        ['id'],
        ['slug'],
        // extended definition (similar to Space::addIndex params)
        // [
        //  'fields' => ['year', 'month', 'day'],
        //  'unique' => true
        // ],
    ];
}
```
Register plugin and all your classes:
```php
$mapper->addPlugin(Tarantool\Mapper\Plugins\Sequence::class); // just not to fill id manually
$mapper->addPlugin(Tarantool\Mapper\Plugins\Annotation::class)
  ->register(Entities\Person::class)
  ->register(Entities\Post::class)
  ->register(Repositories\Person::class)
  ->migrate(); // sync database schema with code

$nekufa = $mapper->create('person', ['name' => 'dmitry']);

$post = $mapper->create('post', [
  'author' => $nekufa,
  'slug' => 'hello-world',
  'title' => 'Hello world',
  'body' => 'Now you can use mapper better way'
]);

```

## Internals
Mapper uses IdentityMap and query caching
```php
$dmitry = $mapper->getRepository('person')->findOne(['name' => 'Dmitry']); // person with id 1
echo $dmitry == $mapper->findOne('person', 1); // true

// query result are cached until you create new entites
$mapper->getRepository('person')->findOne(['name' => 'Dmitry']);

// you can flush cache manually
$mapper->getRepository('person')->flushCache();
```
