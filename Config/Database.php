<?php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', 'Root@12345');
define('DB_NAME', 'loan_management');

class Database
{
    private static ?mysqli $connection = null;

    /**
     * Returns a mysqli connection instance. If the connection is not
     * established, it will create a new one.
     *
     * @return mysqli The mysqli connection instance.
     * @throws RuntimeException If the connection fails.
     */
    public static function getConnection(): mysqli
    {
        if (!self::$connection) {
            self::$connection = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

            if (!self::$connection) {
                throw new RuntimeException("Connection failed: " . mysqli_connect_error());
            }
        }

        return self::$connection;
    }

    /**
     * Standalone connectivity check — prints "Connected successfully" if a
     * connection can be established. Run this file directly to test the DB
     * connection without going through the API or CLI app.
     *
     * Usage: php Database.php
     */
    public static function checkConnection(): void
    {
        try {
            self::getConnection();
            echo "Connected successfully\n";
        } catch (RuntimeException $e) {
            echo "Connection failed: " . $e->getMessage() . "\n";
        }
    }
}

// If this file is run directly, it performs a connectivity check.

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    Database::checkConnection();
}
