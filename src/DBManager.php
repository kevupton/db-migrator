<?php

namespace Kevupton\DBMigrator;

use Exception;
use Kevupton\DBMigrator\Core\DBParser;
use Kevupton\DBMigrator\Core\MigrationManager;
use Kevupton\DBMigrator\Core\MigrationSettings;
use Kevupton\DBMigrator\Core\SnapshotManager;

class DBManager
{
    private $dir;
    private $parser;
    private $ignore = [];
    private $db_host;
    private $db_name;
    private $db_username;
    private $db_password;
    private $db;
    private $table_prefix;
    private $settings;
    private $migrations;
    private $snapshots;

    public function __construct($dir, $db_host, $db_name, $db_username, $db_password, $ignore = [], $parsers = [], $variables = [], $db_table_prefix = '__')
    {
        $this->table_prefix = env('DB_MANAGER_TABLE_PREFIX', $db_table_prefix);
        $this->dir = $dir;
        $this->ignore = array_merge([
            $this->regex('/^\s*?(?:SELECT|SHOW|DESCRIBE)\s/ui'),
        ], $ignore);
        $this->parser = new DBParser($parsers, $variables);
        $this->db_host = $db_host;
        $this->db_name = $db_name;
        $this->db_username = $db_username;
        $this->db_password = $db_password;
    }

    /**
     * @param $query
     * @throws Exception
     */
    public function recordQuery($query)
    {
        $this->migrations()->recordQuery($query);
    }

    public function settings()
    {
        if ($this->settings) {
            return $this->settings;
        }

        return $this->settings = new MigrationSettings($this->getDb());
    }

    public function migrations()
    {
        if ($this->migrations) {
            return $this->migrations;
        }

        return $this->migrations = new MigrationManager($this->getDb(), $this->ignore, $this, $this->dir, $this->table_prefix);
    }

    public function snapshots()
    {
        if ($this->snapshots) {
            return $this->snapshots;
        }

        return $this->snapshots = new SnapshotManager($this->getDb(), $this, $this->dir);
    }

    private function getDb()
    {
        if ($this->db) {
            return $this->db;
        }

        $db = mysqli_connect($this->db_host, $this->db_username, $this->db_password, $this->db_name);

        if (mysqli_connect_errno()) {
            die('Error connecting ' . mysqli_connect_error());
        }

        return $this->db = $db;
    }

    private function regex($pattern, $output = null)
    {
        return DBParser::regex($pattern, $output);
    }

    public function parser()
    {
        return $this->parser;
    }
}