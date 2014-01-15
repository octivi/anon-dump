#!/usr/bin/env php
<?php
/**
 * Copyright (c) 2014 IMAGIN Sp. z o.o.
 * http://octivi.com
 *
 * License:  This  program  is  free  software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published by
 * the  Free Software Foundation; either version 3 of the License, or (at your
 * option)  any later version. This program is distributed in the hope that it
 * will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General
 * Public License for more details.
 * 
 * @author Kamil Chłodnicki
 */
 
// Configure paths to mysqldump and mysql executables 
define('PATH_MYSQLDUMP', 'mysqldump');
define('PATH_MYSQL', 'mysql');
 

// Don't edit below this line :-)

$header = <<<HEAD
(c) 2014 IMAGIN Sp. z o.o.
Anony-Dump the anonymized database clone maker
HEAD;

define('IMAGIN_HEADER', $header);

$arguments = $argv;

if ($argc < 2 || $argc > 4) {
    fwrite(STDERR, '==================================================' . PHP_EOL);
    fwrite(STDERR, IMAGIN_HEADER . PHP_EOL);
    fwrite(STDERR, '==================================================' . PHP_EOL);
    fwrite(STDERR, "Usage:" . PHP_EOL . " {$arguments[0]} query_file [config_file] [--force]".PHP_EOL . PHP_EOL);
    fwrite(STDERR, "You must have 'mysqldump' installed.".PHP_EOL . PHP_EOL);
    fwrite(STDERR, "Arguments".PHP_EOL);
    fwrite(STDERR, " query_file\tPath to the file with query. Ex.: query.sql".PHP_EOL);
    fwrite(STDERR, " config_file\tPath to the file with config. Ex.: config.php (optional)".PHP_EOL);
    fwrite(STDERR, " --force\tOverwrite the clone if it already exists. (optional)".PHP_EOL . "\t\tMakes 'DROP DATABASE' on configured clone database.". PHP_EOL . PHP_EOL);
    fwrite(STDERR, "Sample usage:".PHP_EOL);
	fwrite(STDERR, " $ php anon-dump.php query.sql > dump.sql".PHP_EOL);
	fwrite(STDERR, '==================================================' . PHP_EOL);
    
    die(1);
}

// --force param
$force = false;
$index = array_search('--force', $arguments);
if ($index !== false)  {
    $force = true;
    unset($arguments[$index]);
    $arguments = array_values($arguments);
}

// read config
$configFile = isset($arguments[2]) ? $arguments[2] : 'config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, sprintf('Failed to load config file - "%s"' . PHP_EOL, $configFile));
    die(1);
}

$config = require_once $configFile;
$anonDump = new AnonDump($arguments[1], $config['dsn'], $config['username'], $config['password'], $config['database'], $config['clone'], $force);
$anonDump->start();

class AnonDump
{
    const DATE_FORMAT = 'Y-m-d H:i:s';

    protected $username;
    protected $password;
    protected $connection;
    protected $query;
    protected $database;
    protected $clone;
    protected $force;

    public function __construct($queryFile, $server, $username, $password, $database, $clone, $force)
    {
        if (!is_file($queryFile)) {
            fwrite(STDERR, sprintf('[%s] Failed to load query file - "%s"' . PHP_EOL, date(self::DATE_FORMAT), $queryFile));
            die(1);
        }
        
        $this->query = file_get_contents($queryFile);
        $this->username = $username;
        $this->password = $password;
		
        if ($username && $password) {
			$this->connection = new PDO($server, $username, $password);
        } else {
			$this->connection = new PDO($server);
        }
		
        $this->database = $database;
        $this->clone = $clone;
        $this->force = $force;
    }

    public function start()
    {
        $this->cloneDb($this->database, $this->clone);
        $success = $this->doQuery($this->clone);
        if (!$success) {
            die(1);
        }
        $this->dump($this->clone);

        die(0);
    }
    
    protected function cloneDb($db, $clone)
    {
        $connection = $this->connection;

        $queryExists = 'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = \'' . $clone . '\'';
        $resultExists = $connection->query($queryExists);
        $exists = $resultExists->fetch();
        
        if ($exists) {
            if ($this->force) {
                $queryDrop = 'DROP DATABASE ' . $clone;
                $connection->query($queryDrop);
            } else {
                fwrite(STDERR, 'Error: Database ' . $clone . ' already exists. Use --force to overwrite.'. PHP_EOL);
                die(1);
            }
        }
        $queryCreate = 'CREATE DATABASE ' . $clone;
        $querySuccess = $connection->query($queryCreate);

        if (false === $querySuccess) {
            $errorInfo = $connection->errorInfo();

            fwrite(STDERR, sprintf('[%s] QUERY FAILURE: `%s` - %s' . PHP_EOL, date(self::DATE_FORMAT), $db, json_encode($errorInfo)));

            return false;
        }
        
        if ($this->username && $this->password) {
            $dump = PATH_MYSQLDUMP . ' -u ' . $this->username . ' -p' . $this->password . ' ' . $db;
            $pipe = ' | ' . PATH_MYSQL . ' -u ' . $this->username . ' -p' . $this->password . ' ' . $clone;
        } else {
            $dump = PATH_MYSQLDUMP . ' ' . $db;
            $pipe = ' | ' . PATH_MYSQL . ' ' . $clone;
        }		
		
        exec($dump . $pipe);

        return true;
    }

    protected function doQuery($db)
    {
        $connection = $this->connection;

        $success = $connection->query('USE `' . $db . '`');

        if (false === $success) {
            $errorInfo = $connection->errorInfo();

            fwrite(STDERR, sprintf('[%s] QUERY FAILURE: `%s` - %s' . PHP_EOL, date(self::DATE_FORMAT), $db, json_encode($errorInfo)));

            return false;
        } else {
            $querySuccess = $connection->query($this->query);
            if (false === $querySuccess) {
                $errorInfo = $connection->errorInfo();

                fwrite(STDERR, sprintf('[%s] QUERY FAILURE: `%s` - %s' . PHP_EOL, date(self::DATE_FORMAT), $db, json_encode($errorInfo)));

                return false;
            }
        }

        return true;
    }
    
    protected function dump($db)
    {
        $dump = PATH_MYSQLDUMP . ' -u ' . $this->username . ' -p' . $this->password . ' ' . $db;
        system($dump);

        return true;
    }

}