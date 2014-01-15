Octivi - Anony-Dump the anonymized database clone maker
======================================

This simple tool will allow you to make anonymized clone of your database.
It's useful for cloning your production database into anonymized one for testing environment or your developers.

Thanks to possibility of running any custom SQL queries you can:

* Truncate tables which devs should not see - e.g. some orders table
* Anonymize users personal data - e.g. change names, emails to md5()
* Change users emails to not send them any messages in testing environment

Check `sample/query-anonymize-users.sql` for real-life queries.

## Dependencies

* PHP with PDO installed
* mysqldump
* mysql

## Usage

The simplest way is to run:

    $ php anon-dump.php sample/query.sql sample/config.php --force
    
### Meaning

The script will:

1. Load config from file `sample/config.php`
2. Check if `config['clone']` database exists - if so due to `--force` parameter, it will execute `DROP DATABASE config['clone']` first
3. Create new database named `config['clone']` - `CREATE DATABASE config['clone']`
4. Dump origin database (`config['database']`) using `mysqldump` and pipe output to `mysql` - so basically clone origin database into new one
5. Run `sample/query.sql` queries on new database (`config['clone']`)
6. Dump new database to stdout


### More...

Run:

    $ php anon-dump.php

to see possible arguments.

## Configuration

### Main config file

sample/config.php:

    <?php 
    return array(
        'dsn'       => 'mysql:host=127.0.0.1', // DB DSN - check for allowed formats at http://php.net/manual/en/pdo.construct.php (e.g., mysql:host=127.0.0.1)
        'username'  => '---',                  // DB username (e.g., root)
        'password'  => '---',                  // DB password (e.g., passwd)
        'database'  => '---',                  // origin DB name, (e.g., my-database)
        'clone'     => '---_anon',             // clone DB name, (e.g., my-database-clone)
    );

### Define custom paths to executables

In `anon-dump.php` you can define custom paths to `mysqldump` and `mysql` executables:

    define('PATH_MYSQLDUMP', 'mysqldump');
    define('PATH_MYSQL', 'mysql');
