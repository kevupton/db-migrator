<?php

namespace Kevupton\DBMigrator\Core;

use Exception;
use Kevupton\DBMigrator\DBManager;
use ParseError;
use stdClass;

class DBParser
{
    private $variables = [];
    private $values = [];
    /** @var DBManager */
    private $manager;

    const VAR_START = '#VAR{{';
    const VAR_END = '}}';
    const PATTERN_IDENTITY = '?';

    /**
     * @var Logger
     */
    private $logger;

    public function __construct($manager, $variables, $values, $log_file = null)
    {
        $this->manager = $manager;

        if (!$log_file) {
            $this->logger = new Logger();
        }
        else {
            $this->logger = $log_file;
        }

        $this->variables = $variables;
        $this->values = $values;
    }

    public function apply($string)
    {
        $stringPieces = $this->getStringValues($string);

        foreach ($stringPieces as $stringPiece) {
            $string = $this->substituteVariables($stringPiece, $string);
        }

        return $string;
    }

    public static function regex($pattern, $output = null)
    {
        $output = $output ?: self::PATTERN_IDENTITY;
        $obj = new stdClass();
        $obj->type = 'regex';
        $obj->pattern = $pattern;
        $obj->output = $output;
        return $obj;
    }

    public function parse($string)
    {
        $stringPieces = $this->getStringValues($string);

        foreach ($stringPieces as $stringPiece) {
            $string = $this->replaceStringValue($stringPiece, $string);
        }

        return $string;
    }

    public function substituteVariables($stringPiece, $string)
    {
        foreach ($this->values as $variable => $value) {
            $result = $this->replace($this->getVarName($variable), $value, $stringPiece);

            if ($result !== $stringPiece) {
                $string = str_replace($stringPiece, $result, $string);
                $stringPiece = $result;
            }
        }

        return $string;
    }

    public function replaceStringValue($stringPiece, $string)
    {
        foreach ($this->variables as $variable => $searchTerms) {
            foreach ($searchTerms as $searchTerm) {
                $result = $this->replace($searchTerm, $this->getVarName($variable), $stringPiece);

                if ($result !== $stringPiece) {
                    if (strpos($string, $stringPiece) === false) {
                        var_dump($searchTerm, $stringPiece, $string);
                        throw new \Exception("ERROR: `$searchTerm`:");
                    }

                    $newString = $this->replaceFirstOccurrence($string, $stringPiece, $result);

                    if ($newString === $string) {
                        throw new \Exception('Unable to replace');
                    }

                    $string = $newString;
                }

                $stringPiece = $result;
            }
        }
        if (str_contains($stringPiece, '/home/livesto1')) {
            echo "STING PIECE CONTAINS: /home/liveso1\n";
            var_dump($stringPiece);
            exit;
        }

        return $string;
    }

    private function replaceFirstOccurrence($haystack, $needle, $replacement)
    {
        $pos = strpos($haystack, $needle);

        if ($pos === false) {
            return $haystack;
        }

        return substr_replace($haystack, $replacement, $pos, strlen($needle));
    }

    private function getVarName($variable)
    {
        return self::VAR_START . $variable . self::VAR_END;
    }

    private function getStringValues($query)
    {
        if (!preg_match_all('/\'\'|\'([\s\S]*?[^\\\\])\'/iu', $query, $matches)) {
            return [];
        }

        return $matches[1];
    }


    /**
     * @param string $from
     * @param string $to
     * @param string $data
     * @param bool $serialised
     * @return array|null|string|string[]
     */
    private function replace($from = '', $to = '', $data = '', $serialised = false)
    {
        try {
            if (!is_string($data)) {
                throw new Exception('$data is not a string, cannot unserialize it...');
            }

            /**
             * Without this we would receive rns because for some reason the \r\n isn't being formatted properly
             */
            $tmpData = str_replace(['\n', '\r'], ["\n", "\r"], $data);

            /* Execute the storing of the temp data */
            try {
                $data = $this->unserialize(addslashes($tmpData));
            } catch (\Exception $e) {
                try {
                    $data = $this->unserialize($tmpData);
                }
                catch(\Exception $e) {
                    try {
                        $data = $this->unserialize(stripslashes($tmpData));
                    }
                    catch (\Exception $e) {
                        if ($this->manager->isDebugging()) {
                            error_log($e->getMessage() . "\n" . $e->getTraceAsString());
                            error_log($data);
                        }

                        throw new \Exception($e->getMessage());
                    }
                }
            }

            $data = $this->replace($from, $to, $data, true);
        } catch (Exception $e) {
            if (is_array($data) || $data instanceof stdClass) {
                foreach ($data as &$value) {
                    $value = $this->replace($from, $to, $value, false);
                }
            } else if (is_string($data)) {
                $data = self::strReplace($from, $to, $data);
            }
        }

        if ($serialised) {
            return str_replace(["\n", "\r"], ['\n', '\r'], addslashes(serialize($data)));
        }

        return $data;
    }

    /**
     * @param $str
     * @return mixed
     * @throws Exception
     */
    private function unserialize($str)
    {
        if (!is_string($str)) {
            throw new Exception('Cannot unserialize value if it is not a string');
        }

        $data = @unserialize($str);
        if ($str === 'b:0;' || $data !== false) {
            return $data;
        }

        throw new Exception('string is not unserializable');
    }

    public static function strReplace($from, $to, $string)
    {
        if ($from instanceof stdClass && $from->type === 'regex') {
            $to = preg_replace('/(.*[^\\\\]|^)' . preg_quote(self::PATTERN_IDENTITY, '/') . '(.*?)/i', "$1$to$2", $from->output);
            return preg_replace($from->pattern, $to, $string);
        }

        return str_replace($from, $to, $string);
    }

    public static function strContains($haystack, $needle)
    {
        if ($needle instanceof stdClass && $needle->type === 'regex') {
            return preg_match($needle->pattern, $haystack);
        }

        return str_contains($haystack, $needle);
    }
}
