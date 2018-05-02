# yii2-dump

Generate migration file from an existing database

> [github: https://github.com/Hzhihua/yii2-dump](https://github.com/Hzhihua/yii2-dump)  
> [oschina: http://git.oschina.net/hzhihua/yii2-dump](http://git.oschina.net/hzhihua/yii2-dump)

## Demo
![yii2-dump](https://raw.githubusercontent.com/wiki/Hzhihua/yii2-dump/yii2-dump.png)

## Test Environment

- PHP >= 5.5.0
- MySQL(5.6.36)

## Installation

```
composer require --prefer-dist "hzhihua/yii2-dump:1.0.3"
```

## Configuration

Add the following in console/config/main.php:

### Simple Configuration

```php
return [
    'controllerMap' => [
        'dump' => [
            'class' => 'hzhihua\dump\DumpController',
            'filePrefix' => '123456_654321',
            'tableOptions' => 'ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_unicode_ci', // if mysql >= 5.7, you can set “ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci”,
        ],
    ],
];
```

### Detail Configuration  [see](src/DumpController.php)

```php
return [
    'controllerMap' => [
        'dump' => [
            'class' => 'hzhihua\\dump\\DumpController',
            'db' => 'db', // Connection
            'templateFile' => '@vendor/hzhihua/yii2-dump/templates/migration.php',
            'generatePath' => '@console/migrations',
            'table' => 'table1,table2', // select which table will be dump(default filter migration table)
            'filter' => 'table3,table4', // table3 and table4 will be filtered when generating migration file
            'limit' => '0,1000', // select * from tableName limit 0,1000
            'filePrefix' => '123456_654321',
            'tableOptions' => 'ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_unicode_ci', // if mysql >= 5.7, you can set “ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci”,
            // ... ...
        ],
    ],
];
```

## Default Table Options
```tableOptions
ENGINE=InnoDB CHARACTER SET=utf8 COLLATE=utf8_unicode_ci
```
> it was defined at "@vendor/hzhihua/yii2-dump/src/Migration.php" file [see](src/Migration.php)

## Default Limit
```
0,1000
```

## Default Filter Table
> migration

## Tips
> You may neet to remove the migration file that its name is "\*_init.php" before you run "./yii migrate" commond. because it will generate "{{%user}}" table file when you run "./yii dump" commond and the file name with "\*_init.php" also create "{{%user}}" table. it will tip you "{{%user}}" has always exits.

## Simple Usage

> run `dump` command.
```
cd /path/to/your-project
./yii dump # default action: generate, it will gerate migration file
```

## Commands

> Check help
```
./yii help dump
```

> print all over the table name without table prefix
```
./yii dump/list
```

> generate all over the table migration file(default filter migration table)
```
./yii dump
or
./yii dump/generate
```

> generate all over the table migration file but it only had some data between 0 and 10
```
./yii dump -limit=10
```

> only generate table1,table2 migration file
```
./yii dump -table=table1,table2
```

> only generate table1,table2 migration file and only table1 will be dumped table data
```
./yii dump -table=table1,table2 -data=table1
```

> generate all over the migration table file without table1,table2
```
./yii dump -filter=table1,table2
```

> print all over the code of migration table file(default filter migration table)
```
./yii dump/create
```

> Display the 'createTable' code at the terminal.
```
./yii dump/create
```

> Display the 'dropTable' code at the terminal.
```
./yii dump/drop
```

> -type params
```
./yii dump -type=0/1/2/3
>>> -type=0 generate table migration file,
>>> -type=1 generate table data migration file,
>>> -type=2 generate add key migration file,
>>> -type=3 generate add foreign key migration file
```

> Useful commands (for macOS user):
```
./yii dump | pbcopy
./yii dump/drop | pbcopy
```

## Supports

- Types
- Size
- Unsigned
- NOT NULL
- DEFAULT Value
- COMMENT
- Unique key
- Foreign key
- Primary key
- ENUM type (for MySQL)
- AUTO_INCREMENT

## Not Supports 
~~AUTO_INCREMENT~~

