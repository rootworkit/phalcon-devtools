# Phalcon Devtools

A custom build of Phalcon Devtools with added features.

## Added Features

### Simple Migrations
Simple migrations are an alternative the normal Phalcon migrations that allow you to use simple SQL queries for your DB migrations.

#### Generate a simple migration class with empty up() and down() methods where you can add SQL queries to accomplish your migrations.
```sh
phalcon migration generate --table=users --simple
```

#### Result:
```php
use Phalcon\Mvc\Model\Migration;

/**
 * Class UsersMigration_100
 */
class UsersMigration_100 extends Migration
{

    /**
     * Run the migrations
     *
     * @return void
     */
    public function up()
    {
        self::$_connection->execute("ALTER TABLE users ADD COLUMN foo VARCHAR(100)");
        self::$_connection->execute("UPDATE users SET name = 'bar'");
    }

    /**
     * Reverse the migrations
     *
     * @return void
     */
    public function down()
    {
        self::$_connection->execute("ALTER TABLE users DROP COLUMN foo");
    }
}
```

#### Generate an initial migration class with a CREATE TABLE statement (works for views as well).
```sh
phalcon migration generate --table=users --simple --simple-create
```

#### Result:
```php
use Phalcon\Mvc\Model\Migration;

/**
 * Class UsersMigration_100
 */
class UsersMigration_100 extends Migration
{

    /**
     * Run the migrations
     *
     * @return void
     */
    public function up()
    {
        self::$_connection->execute("
            CREATE TABLE IF NOT EXISTS `users` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `email` varchar(128) DEFAULT NULL,
                `password` varchar(255) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `email` (`email`)
            ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    /**
     * Reverse the migrations
     *
     * @return void
     */
    public function down()
    {
    }
}
```

## What's Phalcon?

Phalcon PHP is a web framework delivered as a C extension providing high performance and lower resource consumption.

## What are Devtools?

This tools provide you useful scripts to generate code helping to develop faster and easy applications that use
with Phalcon framework.

## Requirements

* PHP >= 5.3.9
* Phalcon >= 2.0.0

## Installing via Composer

Install composer in a common location or in your project:

```bash
curl -s http://getcomposer.org/installer | php
```

Create the composer.json file as follows:

```json
{
    "require": {
        "rootwork/phalcon-devtools": "dev-custom"
    }
}
```

Run the composer installer:

```bash
php composer.phar install
```

## Build `.phar`

Install composer and box in a common location or in your project:
```bash
curl -s http://getcomposer.org/installer | php
bin/composer install
```

Build phar file `phalcon-devtools`
```bash
bin/box build -v
chmod +xr ./phalcon.phar
# Test it!
php ./phalcon.phar
```

## Installation via Git

Phalcon Devtools can be installed by using Git.

Just clone the repo and checkout the current branch:

```bash
cd ~
git clone https://github.com/rootworkit/phalcon-devtools.git
cd phalcon-devtools
```

This method requires a little bit more of setup. Probably the best way would be to symlink
the phalcon.php to a directory in your PATH, so you can issue phalcon commands in each directory
where a phalcon project resides.

```bash
ln -s ~/phalcon-devtools/phalcon.php /usr/bin/phalcon
chmod ugo+x /usr/bin/phalcon
```

## Usage

To get a list of available commands just execute following:

```bash
phalcon commands help
```

This command should display something similar to:

```sh
$ phalcon list ?

Phalcon DevTools (2.0.11)

Help:
  Lists the commands available in Phalcon devtools

Available commands:
  commands         (alias of: list, enumerate)
  controller       (alias of: create-controller)
  module           (alias of: create-module)
  model            (alias of: create-model)
  all-models       (alias of: create-all-models)
  project          (alias of: create-project)
  scaffold         (alias of: create-scaffold)
  migration        (alias of: create-migration)
  webtools         (alias of: create-webtools)
```

## Update WebTools from old version

Please remove manually directories:

* `public/css/bootstrap`
* `public/css/codemirror`
* `public/js/bootstrap`
* `public/img/bootstrap`
* `public/js/codemirror`
* `public/js/jquery`

and files:

* `public/webtools.config.php`
* `public/webtools.php`

and just run form your project root:

```bash
$ phalcon webtools --action=enable
```

## Database adapter

Should add 'adapter' parameter in your db config file (if you use not Mysql database). For PostgreSql will be

```php
$config = [
  "host"     => "localhost",
  "dbname"   => "my_db_name",
  "username" => "my_db_user",
  "password" => "my_db_user_password",
  "adapter"  => "Postgresql"
];
```

## License

Phalcon Developer Tools is open source software licensed under the [New BSD License][1].
© Phalcon Framework Team and contributors

[1]: docs/LICENSE.md
