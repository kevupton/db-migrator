<?php

namespace Kevupton\DBMigrator;

use Exception;
use MySQLDump;
use mysqli;
use MySQLImport;

class SnapshotManager
{
    /** @var mysqli */
    private $db;
    private $dir;
    private $current_date;
    private $current_time;
    /** @var DBManager */
    private $manager;

    /**
     * SnapshotManager constructor.
     * @param mysqli $db
     * @param DBManager $manager
     * @param string $dir
     */
    public function __construct($db, $manager, $dir)
    {
        $this->db = $db;
        $this->dir = $dir;
        $this->current_date = date('Y-m-d');
        $this->current_time = date('H:i:s');
        $this->manager = $manager;
    }

    /**
     * @throws Exception
     */
    public function takeSnapshot()
    {
        $this->increaseExecutionTime();

        $filename = $this->current_date . '_' . $this->current_time . '.sql.gz';
        $filepath = $this->getSnapshotDir($filename);

        if (file_exists($this->getTempDir($filename))) {
            throw new Exception('Tmp file already in use');
        }

        $dump = new MySQLDump($this->db);
        $dump->save($filepath);

        $this->parseSnapshot($filename);

        return $filename;
    }

    /**
     * @param null $snapshot
     * @throws Exception
     */
    public function applySnapshot($snapshot = null)
    {
        $this->increaseExecutionTime();

        $snapshot = $snapshot ?: $this->getLatestSnapshot();

        if (!$snapshot) {
            throw new Exception('No snapshots found in directory');
        }

        if (!file_exists($this->getSnapshotDir($snapshot))) {
            throw new Exception('Snapshot ' . $snapshot . ' does not exist');
        }

        $filepath = $this->createSnapshotImport($snapshot);
        $this->importSnapshot($filepath);
        unlink($filepath);
    }

    /**
     * @param $filepath
     * @throws Exception
     */
    private function importSnapshot($filepath)
    {
        $dump = new MySQLImport($this->db);
        $this->emptyDb();
        $dump->load($filepath);
    }

    private function createSnapshotImport($snapshot)
    {
        $tmp_filepath = $this->getTempDir($snapshot);
        $filepath = $this->getSnapshotDir($snapshot);

        if (file_exists($tmp_filepath)) {
            throw new Exception('Tmp file already in use');
        }

        $reading = gzopen($filepath, 'r');
        $writing = gzopen($tmp_filepath, 'w');

        while ($line = gzgets($reading)) {
            gzputs($writing, $this->manager->parser()->apply($line));
        }

        gzclose($reading);
        gzclose($writing);

        return $tmp_filepath;
    }

    /**
     * @param $filename
     * @return string
     * @throws Exception
     */
    private function parseSnapshot($filename)
    {
        $tmp_filepath = $this->getTempDir($filename);
        $filepath = $this->getSnapshotDir($filename);

        if (file_exists($tmp_filepath)) {
            throw new Exception('Tmp file already in use');
        }

        $reading = gzopen($filepath, 'r');
        $writing = gzopen($tmp_filepath, 'w');

        $replaced = false;

        while ($line = gzgets($reading)) {
            $result = $this->manager->parser()->parse($line);

            if (str_contains($result, '/home/livesto1/public_html')) {
                echo 'STRING CONTAINS LIVESTO1\n';
                var_dump($result);
                exit;
            }

            $replaced = $replaced || $line !== $result;
            gzputs($writing, $result);
        }

        gzclose($reading);
        gzclose($writing);

        // might as well not overwrite the file if we didn't replace anything
        if ($replaced) {
            rename($tmp_filepath, $filepath);
        } else {
            unlink($tmp_filepath);
        }
    }

    public function getAllSnapshots()
    {
        $snapshots = [];
        if ($handle = opendir($this->getSnapshotDir())) {

            while (false !== ($entry = readdir($handle))) {

                if ($entry != "." && $entry != "..") {
                    $snapshots[] = $entry;
                }
            }

            closedir($handle);
        }

        return $snapshots;
    }

    private function getLatestSnapshot()
    {
        $latest = null;
        if ($handle = opendir($this->getSnapshotDir())) {

            while (false !== ($entry = readdir($handle))) {

                if ($entry != "." && $entry != "..") {

                    if (!$latest || $latest < $entry) {
                        $latest = $entry;
                    }
                }
            }

            closedir($handle);
        }

        return $latest;
    }

    private function getSnapshotDir($path = '')
    {
        return $this->makeDir('snapshots', $path);
    }

    private function getTempDir($path = '')
    {
        return $this->makeDir('.tmp', $path);
    }

    private function makeDir($path, $ext)
    {
        $path = $this->dir . '/' . $path;

        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        return $path . '/' . $ext;
    }

    private function emptyDb()
    {
        $this->db->real_query('SET foreign_key_checks = 0');
        if ($result = $this->db->query("SHOW TABLES")) {
            while ($row = $result->fetch_array(MYSQLI_NUM)) {
                $this->db->query('DROP TABLE IF EXISTS ' . $row[0]);
            }
        }

        $this->db->query('SET foreign_key_checks = 1');
    }

    private function increaseExecutionTime()
    {
        ini_set('max_execution_time', env('DB_MANAGER_MAX_EXECUTION_TIME', 3600 * 2)); // TWO HOURS
    }
}