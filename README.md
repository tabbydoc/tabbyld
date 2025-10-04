# TabbyLD

**TabbyLD** is a web-based software for semantic interpretation of spreadsheets.

TabbyLD is based on [PHP 7](https://www.php.net/releases/7.0/ru.php) and [Yii 2 Framework](http://www.yiiframework.com/).

[![Latest Stable Version](https://img.shields.io/packagist/v/yiisoft/yii2-app-basic.svg)](https://packagist.org/packages/yiisoft/yii2-app-basic)
[![Total Downloads](https://img.shields.io/packagist/dt/yiisoft/yii2-app-basic.svg)](https://packagist.org/packages/yiisoft/yii2-app-basic)
[![Build Status](https://travis-ci.org/yiisoft/yii2-app-basic.svg?branch=master)](https://travis-ci.org/yiisoft/yii2-app-basic)


### Version

1.0


### DIRECTORY STRUCTURE

      assets/             contains assets definition
      commands/           contains console commands for creation of langs by-default and table data annotation
      components/         contains core modules for semantic table interpretation (CanonicaltableAnnotator and RDFCodeGenerator)
      config/             contains application configurations
      modules/            contains single module:
          main/           contains controller, models and views
      web/                contains the entry script and Web resources


### REQUIREMENTS

The minimum requirement by this project template that your Web server supports PHP 7.0 and PostgreSQL 9.0.


### INSTALLATION

#### Install via Composer

If you do not have [Composer](http://getcomposer.org/), you may install it by following the instructions at [getcomposer.org](http://getcomposer.org/doc/00-intro.md#installation-nix).

You can then install this editor using the following command:

~~~
composer create-project tabbydoc/tabbyld
~~~


### CONFIGURATION

#### Database

Edit the file `config/db.php` with real data, for example:

```php
return [
    'class' => 'yii\db\Connection',
    'dsn' => 'pgsql:host=localhost;port=5432;dbname=tabbyld;',
    'username' => 'postgres',
    'password' => 'root',
    'charset' => 'utf8',
    'tablePrefix' => 'tabbyld_',
    'schemaMap' => [
        'pgsql'=> [
            'class'=>'yii\db\pgsql\Schema',
            'defaultSchema' => 'public'
        ]
    ],
];
```

**NOTES:**
- TabbyLD won't create the database for you, this has to be done manually before you can access it.
- Check and edit the other files in the `config/` directory to customize your application as required.

### USING

#### Commands for configuring database

Applying migrations (creating tables in database):
~~~
php yii migrate/up
~~~
Creating default locale records in database:
~~~
php yii lang/create
~~~


#### Commands for working with TabbyLD

Starting process of annotating spreadsheets:
~~~
php yii spreadsheet/annotate
~~~

Deleting all records for annotated datasets from database:
~~~
php yii annotated-dataset/remove
~~~

**NOTES:**
- Commands are entered sequentially into the console, being in the folder with the project.


### AUTHORS

* [Nikita O. Dorodnykh](mailto:tualatin32@mail.ru)
* [Aleksandr Yu. Yurin](mailto:iskander@icc.ru)