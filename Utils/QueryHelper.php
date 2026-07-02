<?php

require_once __DIR__ . '/../Config/Database.php';

trait QueryHelper
{
    /**
     * Returns the shared database connection.
     */
    protected static function getConnection(): mysqli
    {
        return Database::getConnection();
    }

    /**
     * Prepare, bind and execute a query.
     */
    protected static function executeQuery(
        string $query,
        ?string $types = null,
        array $params = []
    ): mysqli_stmt {

        $statement = self::getConnection()->prepare($query); 

        if (!$statement) {
            throw new RuntimeException(
                "Failed to prepare query: " .
                self::getConnection()->error
            );
        }
 
        if ($types !== null && !empty($params)) {
            $statement->bind_param(
                $types,
                ...$params // Unpack the array into individual arguments
            );
        }

        try 
        {
            $statement->execute();
        } catch (mysqli_sql_exception $e) 
        {
            throw new RuntimeException ("Database operation failed: " . $e->getMessage(), 0, $e);
        }

        return $statement;
    }
}