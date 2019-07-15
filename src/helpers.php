<?php

use Kevupton\DBMigrator\Core\DBParser;
use Kevupton\DBMigrator\DBManager;

if (!function_exists('create_db_manager')) {
    function create_db_manager($dir = __DIR__ . '/db', $ignore = [], $parsers = [], $variables = [])
    {
        return new DBManager($dir,
            env('DB_HOST'),
            env('DB_NAME'),
            env('DB_USERNAME'),
            env('DB_PASSWORD'),
            $ignore, $parsers, $variables
        );
    }
}

if (!function_exists('create_wp_db_manager')) {
    function create_wp_db_manager($dir = __DIR__ . '/db', $ignore = [], $parsers = [], $variables = [])
    {
        $parsers = array_merge([
            'SITE_URL' => [
                DBParser::regex('/https?(?:[\\:\\/\\\\]+|[%3A2F\\\\]+)(?:www\\.)?' . preg_quote(env('WP_HOST'), '/') . '/ui'),
            ],
            'WP_HOST' => [
                DBParser::regex('/(?:[^@]|^)([\\/\\:a-zA-Z_\\-0-9.%]*)' . preg_quote(env('WP_HOST'), '/') . '/ui', '$1?'),
            ],
            'WP_FILE_PATH' => [
                env('WP_FILE_PATH')
            ],
        ], $parsers);

        $variables = array_merge([
            'SITE_URL' => env('WP_PROTOCOL') . '://' . env('WP_HOST'),
            'WP_FILE_PATH' => env('WP_FILE_PATH'),
            'WP_HOST' => env('WP_HOST'),
        ], $variables);

        return new DBManager($dir,
            env('DB_HOST'),
            env('DB_NAME'),
            env('DB_USERNAME'),
            env('DB_PASSWORD'),
            $ignore, $parsers, $variables
        );
    }
}
