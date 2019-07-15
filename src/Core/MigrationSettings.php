<?php

namespace Kevupton\DBMigrator\Core;

use Exception;
use mysqli;

class MigrationSettings
{

    private $cache = [];

    /** @var mysqli */
    private $db;

    /** @var string */
    private $table;

    /**
     * DBManagerSettings constructor.
     * @param mysqli $db
     */
    public function __construct($db)
    {
        $this->db = $db;
        $this->table = env('DB_MANAGER_TABLE_PREFIX', '') . 'migration_settings';
    }

    public function get($key, $defaultValue = null)
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $key = $this->db->real_escape_string($key);

        $query = "SELECT `value` FROM `$this->table` WHERE `key` = '$key';";
        $result = $this->db->query($query);

        if ($result === false || $result->num_rows === 0) {
            return $defaultValue;
        }


        $value = $result->fetch_assoc()['value'];
        try {
            return $this->cache[$key] = json_decode($value);
        }
        catch (Exception $e) {
            return $defaultValue;
        }
    }

    public function delete($key)
    {
        $key = $this->db->real_escape_string($key);
        $query = "DELETE FROM $this->table WHERE `key` = '$key'";
        return $this->db->real_query($query);
    }

    public function set($key, $value)
    {
        $result = $this->update($key, $value);

        if ($result === false) {
            $result = $this->createTable();
            if (!$result) {
                throw new Exception($this->db->error);
            }
            $result = $this->update($key, $value);
        }

        if ($result === false) {
            throw new Exception($this->db->error);
        }

        return $this->cache[$key] = $value;
    }

    private function update($key, $value)
    {
        $key = $this->db->real_escape_string($key);
        $value = $this->db->real_escape_string(json_encode($value));

        $query = "INSERT INTO $this->table (`key`, `value`) VALUES ('$key', '$value') ON DUPLICATE KEY UPDATE `value` = '$value';";
        return $this->db->real_query($query);
    }

    private function createTable()
    {
        $query = <<<SQL
CREATE TABLE IF NOT EXISTS $this->table(
    `key` VARCHAR(128) NOT NULL,
    `value` TEXT,
    PRIMARY KEY (`key`)
);
SQL;
        return $this->db->real_query($query);
    }
}