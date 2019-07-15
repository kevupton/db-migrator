<?php

namespace Kevupton\DBMigrator\Core;

use Exception;
use Kevupton\DBMigrator\DBManager;
use mysqli;

class MigrationManager
{

    /** @var string */
    private $current_date;

    /** @var string */
    private $current_time;

    /** @var mysqli */
    private $db;

    private $dir;

    private $table;

    private $created = false;

    /** @var DBManager */
    private $manager;
    private $ignore;

    /**
     * MigrationManager constructor.
     * @param mysqli $db
     * @param $ignore
     * @param DBManager $manager
     * @param string $dir
     * @param string $table_prefix
     */
    public function __construct($db, $ignore, $manager, $dir, $table_prefix = '')
    {
        $this->db = $db;
        $this->current_date = date('Y-m-d');
        $this->current_time = date('H:i:s');
        $this->dir = $dir;
        $this->table = $table_prefix . 'migrations';
        $this->manager = $manager;
        $this->ignore = $ignore;
    }

    /**
     * @throws Exception
     */
    public function runMigrations()
    {
        $migrations = $this->getAllMigrations();

        foreach ($migrations as $migration) {
            $this->runMigrationsForFile($migration);
        }
    }

    /**
     * @param $query
     * @throws Exception
     */
    public function recordQuery($query)
    {

        if (!env('DB_MANAGER_RECORD', false) && !$this->manager->settings()->get('recording', false)) {
            return;
        }

        // Dont save the select queries.
        foreach ($this->ignore as $toIgnore) {
            if (DBParser::strContains($query, $toIgnore)) {
                return;
            }
        }

        $query = $this->manager->parser()->parse($query); // TODO fix this... it doesnt replace values properly (if they are serialized)

        list($id, $env) = $this->saveLineToMigrationsFile($query);
        $this->saveMigrationToDB($id, $env, "$this->current_date $this->current_time");
    }

    /**
     * @param $line
     * @return mixed
     * @throws Exception
     */
    private function parseMigrationLine($line)
    {
        if (!preg_match('/^([0-9]{2}:[0-9]{2}:[0-9]{2}):(.*?):([a-zA-Z_\\-]+): (.*?)$/', $line, $matches)) {
            throw new Exception('Migration has invalid format');
        }
        array_shift($matches);
        $matches[3] = $this->manager->parser()->apply($matches[3]); // reformat the query
        return $matches;
    }

    /**
     * @param $migration
     * @throws Exception
     */
    private function runMigrationsForFile($migration)
    {
        $filepath = $this->getMigrationsDir($migration);

        if (!preg_match('/^([0-9]{4}-[0-9]{2}-[0-9]{2})\.sql$/ui', $migration, $matches)) {
            throw new Exception('Invalid migration format. Expected {date}.sql, received: ' . $migration);
        }

        $date = $matches[1];

        $reading = fopen($filepath, 'r');

        while ($line = fgets($reading)) {
            list($time, $id, $env, $sql) = $this->parseMigrationLine($line);

            if ($this->hasMigration($id, $env)) {
                continue;
            }

            $result = $this->db->real_query($sql);

            if (!$result) {
                throw new Exception("Error running migration: $id, $env. " . $this->db->error);
            }

            $this->saveMigrationToDB($id, $env, "$this->current_date $this->current_time");
        }

        fclose($reading);
    }

    /**
     * Gets all available migrations to run
     *
     * @return array
     */
    private function getAllMigrations()
    {
        $migrations = [];

        if ($handle = opendir($this->getMigrationsDir())) {

            while (false !== ($entry = readdir($handle))) {

                if ($entry != "." && $entry != "..") {
                    $migrations[] = $entry;
                }
            }

            closedir($handle);
        }

        return $migrations;
    }

    private function saveLineToMigrationsFile($line)
    {
        $id = uniqid();
        $env = env('ENV');

        $line = str_replace("\n", '\n', $line);
        $line = $this->current_time . ':' . $id . ':' . $env . ': ' . $line . "\n";

        file_put_contents($this->getMigrationsDir($this->current_date . '.sql'), $line, FILE_APPEND);

        return [$id, $env];
    }

    /**
     * @param $id
     * @param $env
     * @param $datetime
     * @return bool
     * @throws Exception
     */
    private function saveMigrationToDB($id, $env, $datetime)
    {
        $this->createTable();

        $id = $this->db->real_escape_string($id);
        $env = $this->db->real_escape_string($env);
        $datetime = $this->db->real_escape_string($datetime);

        $query = "INSERT IGNORE INTO $this->table (`id`, `env`, `run_at`) VALUES ('$id', '$env', '$datetime');";
        return $this->db->real_query($query);
    }

    /**
     * @param $id
     * @param $env
     * @return bool
     * @throws Exception
     */
    private function hasMigration($id, $env)
    {
        $id = $this->db->real_escape_string($id);
        $env = $this->db->real_escape_string($env);

        $query = "SELECT * FROM $this->table WHERE `id` = '$id' AND `env` = '$env'";
        $result = $this->db->query($query);

        if ($result === false) {
            $this->createTable();
            return false;
        }
        else {
            // it means that the table exists
            $this->created = true;
        }

        return $result->num_rows > 0;
    }


    private function createTable()
    {
        if ($this->created) {
            return true;
        }

        $query = <<<SQL
CREATE TABLE IF NOT EXISTS $this->table(
    `id` CHAR(13) NOT NULL,
    `env` VARCHAR(16) NOT NULL,
    `run_at` DATETIME,
    PRIMARY KEY (`id`, `env`)
);
SQL;
        $result = $this->db->real_query($query);

        if (!$result) {
            throw new Exception($this->db->error);
        }

        return $this->created = $result;
    }

    private function getMigrationsDir($path = '')
    {
        return $this->makeDir('migrations', $path);
    }

    private function makeDir($path, $ext)
    {
        $path = $this->dir . '/' . $path;

        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        return $path . '/' . $ext;
    }
}