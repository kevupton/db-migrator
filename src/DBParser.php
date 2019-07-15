<?php

namespace Kevupton\DBMigrator;

use Exception;
use stdClass;

class DBParser
{
    private $variables = [];
    private $values = [];

    const VAR_START = '#VAR{{';
    const VAR_END = '}}';
    const PATTERN_IDENTITY = '?';

    public function __construct($variables, $values)
    {
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

    private function substituteVariables($stringPiece, $string)
    {
        foreach ($this->values as $variable => $value) {
            $result = $this->replace($this->getVarName($variable), $value, $stringPiece);

            if ($result !== $stringPiece) {
                $string = str_replace($stringPiece, $result, $string);
            }
        }

        return $string;
    }

    private function replaceStringValue($stringPiece, $string)
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

            $data = $this->replace($from, $to, $this->unserialize(stripslashes($data)), true);
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
            return addslashes(serialize($data));
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