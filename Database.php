<?php
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASSWORD', 'Root@12345');
define('DB_NAME', 'loan_management');
class Database {
    private static ?mysqli $connection = null;

    /**
     * Returns a mysqli connection instance. If the connection is not established, it will create a new one.
     * @return mysqli The mysqli connection instance.
     * @throws RuntimeException If the connection fails.
     */
    public static function getConnection(): mysqli {

        if (!self::$connection) {
            self::$connection = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
            if (!self::$connection) {
                throw new RuntimeException("Connection failed: " . mysqli_connect_error());
            }
        }
        return self::$connection;
    }

    public static function insertCustomer(string $_account_number, string $_customer_name, string $_customer_dob, string $_customer_address, int $_customer_phone_number) {
        $conn = self::getConnection();
        $stmt = $conn->prepare("INSERT INTO customers (account_number, customer_name, customer_dob, customer_address, customer_phone_number) VALUES (?, ?, ?, ?, ?)"); 
        $stmt->bind_param("ssssi", $_account_number, $_customer_name, $_customer_dob, $_customer_address, $_customer_phone_number);
        if ($stmt->execute()) {
            echo "New customer inserted successfully.\n";
        } else {
            echo "Error: " . $stmt->error . "\n";
        }

        return $conn->insert_id; // Return the ID of the newly inserted customer
    }

    public static function insertLoan(int $_customer_id, string $_loan_type, float $_loan_amount, int $_loan_tenure, float $_monthly_emi, float $_total_interest, float $_total_repayment) {
        $conn = self::getConnection();
        $stmt = $conn->prepare("INSERT INTO loans (customer_id, loan_type, loan_amount, loan_tenure, monthly_emi, total_interest, total_repayment) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isdiddd", $_customer_id, $_loan_type, $_loan_amount, $_loan_tenure, $_monthly_emi, $_total_interest, $_total_repayment);
        if ($stmt->execute()) {
            echo "New loan inserted successfully.\n";
        } else {
            echo "Error: " . $stmt->error . "\n";
        }   

        return $conn->insert_id; // Return the ID of the newly inserted loan
    }

    public function loadAllCustomers() {
        $conn = self::getConnection();
        $stmt = $conn->prepare("
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
            LEFT JOIN loans
            ON customers.id = loans.customer_id
            ORDER BY customers.id
        ");
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}



