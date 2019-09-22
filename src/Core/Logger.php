<?php

namespace Kevupton\DBMigrator\Core;

class Logger
{
    /**
     * @var int
     */
    private $log_level;
    /**
     * @var null
     */
    private $log_file;


    /**
     * Logger constructor.
     * @param int $log_level
     * @param string $log_file
     */
    public function __construct($log_level = 0, $log_file = './logs/log.txt')
    {
        if ($log_file[0] === '.') {
            $this->log_file = getcwd() . '/' . $log_file;
        }

        $dir = dirname($this->log_file);

        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        if (!file_exists($this->log_file)) {
            touch($this->log_file);
        }

        $this->log_level = $log_level;
    }

    /**
     * @return int
     */
    public function getLogLevel()
    {
        return $this->log_level;
    }

    /**
     * @param int $log_level
     */
    public function setLogLevel($log_level)
    {
        $this->log_level = $log_level;
    }

    /**
     * Logs content to a file
     *
     * @param $content
     * @param int $level
     */
    public function log($content, $level = 4)
    {
        if (!is_string($content)) {
            $content = json_encode($content, JSON_PRETTY_PRINT);
        }

        file_put_contents($this->getLogFile(), $content . "\n", FILE_APPEND);
    }

    /**
     * @return string
     */
    public function getLogFile()
    {
        return $this->log_file;
    }


}
