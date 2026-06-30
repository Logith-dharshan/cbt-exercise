<?php

require_once 'Database.php';
require_once 'QueryHelper.php';

/**
 * LoanManagementRepository
 *
 * Holds every SQL query used by the API. Database.php only supplies
 * the connection (Database::getConnection()); this class is where
 * prepare() / bind_param() / execute() actually happen. CustomerEndpoint
 * (the HTTP layer) calls these methods and never writes SQL itself.
 */
class LoanManagementRepository
{
    use QueryHelper;
    /**
     * Fetches all customers joined with their loans.
     * (Used by both the CLI's Customer::loadAll() and the API's GET handler.)
     *
     * @return array  List of customer rows (each row may include loan columns).
     */
    public static function getAllCustomers(): array
    {

        $stmt = self::executeQuery(
            "
            SELECT
                customers.id AS customer_id,
                customers.account_number,
                customers.customer_name,
                customers.customer_dob,
                customers.customer_address,
                customers.customer_phone_number,
                loans.loan_type,
                loans.loan_amount,
                loans.loan_tenure,
                loans.monthly_emi,
                loans.total_interest,
                loans.total_repayment
            FROM customers
            LEFT JOIN loans ON customers.id = loans.customer_id
            ORDER BY customers.id
        ");

        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    /**
     * Fetches a single customer (joined with their loans) by account number.
     *
     * @param string $_account_number
     * @return array  Matching rows (empty if no customer found).
     */
    public static function getCustomerByAccountNumber(string $_account_number): array
    {

        $stmt = self::executeQuery(
            "
            SELECT
                customers.id AS customer_id,
                customers.account_number,
                customers.customer_name,
                customers.customer_dob,
                customers.customer_address,
                customers.customer_phone_number,
                loans.loan_type,
                loans.loan_amount,
                loans.loan_tenure,
                loans.monthly_emi,
                loans.total_interest,
                loans.total_repayment
            FROM customers
            LEFT JOIN loans ON customers.id = loans.customer_id
            WHERE customers.account_number = ?
            ORDER BY customers.id
        ",
        "s", 
        [$_account_number]);
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    /**
     * Inserts a new customer row.
     *
     * @param string $_account_number
     * @param string $_customer_name
     * @param string $_customer_dob
     * @param string $_customer_address
     * @param string $_customer_phone_number
     * @return int  The auto-increment ID of the new row.
     */
    public static function insertCustomer(
        string $_account_number,
        string $_customer_name,
        string $_customer_dob,
        string $_customer_address,
        string $_customer_phone_number
    ): int {

        $stmt = self::executeQuery(
            "
            INSERT INTO customers
                (account_number, customer_name, customer_dob, customer_address, customer_phone_number)
            VALUES (?, ?, ?, ?, ?)
            ",
            "sssss",
            [
                $_account_number,
                $_customer_name, 
                $_customer_dob, 
                $_customer_address,
                $_customer_phone_number
            ]);

        $inserted_id = self::getConnection()->insert_id;
        $stmt->close();

        return $inserted_id;
    }

    /**
     * Inserts a new loan row linked to an existing customer.
     *
     * @param int    $_customer_id
     * @param string $_loan_type
     * @param float  $_loan_amount
     * @param int    $_loan_tenure
     * @param float  $_monthly_emi
     * @param float  $_total_interest
     * @param float  $_total_repayment
     * @return int  The auto-increment ID of the new row.
     */
    public static function insertLoan(
        int    $_customer_id,
        string $_loan_type,
        float  $_loan_amount,
        int    $_loan_tenure,
        float  $_monthly_emi,
        float  $_total_interest,
        float  $_total_repayment
    ): int {

        $stmt = self::executeQuery(
            "
            INSERT INTO loans
                (customer_id, loan_type, loan_amount, loan_tenure, monthly_emi, total_interest, total_repayment)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ",
        "isdiddd",
        [
            $_customer_id,
            $_loan_type,
            $_loan_amount,
            $_loan_tenure,
            $_monthly_emi,
            $_total_interest,
            $_total_repayment
        ]);

        $inserted_id = self::getConnection()->insert_id;
        $stmt->close();

        return $inserted_id;
    }

    /**
     * Updates an existing customer, identified by account_number.
     * account_number itself is never changed.
     *
     * @param string $_account_number
     * @param string $_customer_name
     * @param string $_customer_dob
     * @param string $_customer_address
     * @param string    $_customer_phone_number
     * @return int  Number of affected rows (0 if no match or no change).
     */
    public static function updateCustomer(
        string $_account_number,
        string $_customer_name,
        string $_customer_dob,
        string $_customer_address,
        string $_customer_phone_number
    ): int {

        $stmt = self::executeQuery(
            "
            UPDATE customers
            SET customer_name = ?, customer_dob = ?, customer_address = ?, customer_phone_number = ?
            WHERE account_number = ?
        ",
        "sssss",
        [
            $_customer_name,
            $_customer_dob,
            $_customer_address,
            $_customer_phone_number,
            $_account_number
        ]);

        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected;
    }

    /**
     * Deletes a customer, identified by account_number.
     *
     * @param string $_account_number
     * @return int  Number of affected rows (0 if no match).
     */
    public static function deleteCustomer(string $_account_number): int
    {

        $stmt = self::executeQuery(
            "
            DELETE FROM customers
            WHERE account_number = ?
        ",
        "s",
        [$_account_number]);

        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected;
    }
}
