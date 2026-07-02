<?php

require_once __DIR__ . '/../Utils/RetryLimit.php';
require_once __DIR__ . '/Loan.php';
require_once __DIR__ . '/../Config/Database.php';
require_once __DIR__ . '/../Repositories/CustomerManagementRepository.php';

class Customer //implements JsonSerializable
{
    use RetryLimit;
    const MIN_AGE = 18;
    const MIN_ADDRESS_LEN = 10;
    const MIN_ACCOUNT_NUMBER_LEN = 10;
    const MAX_ACCOUNT_NUMBER_LEN = 16;
    const MIN_LOAN_AMOUNT = 10000;
    const MIN_MONTHS = 6;
    const MAX_MONTHS = 60;
    //    const FILE_NAME = 'customers.json';

    private int $customer_id;
    private string $account_number;
    private string $customer_name;
    private string $customer_dob;
    private string $customer_address;
    private string $customer_phone_number;
    private array $customer_loans = [];

    /**
     * Summary of getInput
     * @param string $_prompt
     * @return string
     */
    private function getInput(string $_prompt): string
    {
        return trim(fgets($_prompt));
    }

    /**
     * Helper to collect and validate a single field with retries.
     * @param string $_prompt
     * @param callable $_setter 
     */
    private function collectField(string $_prompt, callable $_setter): void
    {
        $attempts = 0;

        while (! $_setter($this->getInput($_prompt))) {
            $this->registerAttempt($attempts);
        }
    }

    /**
     * Summary of collectCustomerDetails
     * @return Customer
     */
    public function collectCustomerDetails(array $_existing_accounts): self
    {
        $this->collectField('Enter your Acc Number: ', fn($v) => $this->setAccountNumber($v, $_existing_accounts));
        $this->collectField('Enter your name: ', fn($v) => $this->setName($v));
        $this->collectField('Enter your Date of Birth (YYYY-MM-DD): ', fn($v) => $this->setDob($v));
        $this->collectField('Enter your Address: ', fn($v) => $this->setAddress($v));
        $this->collectField('Enter your Phone Number: ', fn($v) => $this->setPhone($v));

        return $this;
    }

    /**
     * Checks whether this customer already has a saved loan of the given type.
     *
     * @param string $_loan_type_label
     * @return bool
     */
    public function hasAppliedFor(string $_loan_type_label): bool
    {
        return in_array($_loan_type_label, array_map(fn(Loan $loan) => $loan->getLoanType(), $this->customer_loans));
    }

    /**
     * Adds a loan to this customer's record.
     *
     * @param Loan $_loan
     * @return void
     */
    public function addLoan(Loan $_loan): void
    {
        $this->customer_loans[] = $_loan;
    }

    /**
     * Summary of setAccountNumber
     * @param string $_account_number
     * @param array $_existing_accounts
     * @return bool
     */
    public function setAccountNumber(string $_account_number, array $_existing_accounts): bool
    {
        $length = strlen($_account_number);

        if (!preg_match("/^\d+$/", $_account_number) || $length < self::MIN_ACCOUNT_NUMBER_LEN || $length > self::MAX_ACCOUNT_NUMBER_LEN) {
            echo "Error: Account number must be " . self::MIN_ACCOUNT_NUMBER_LEN . "-" . self::MAX_ACCOUNT_NUMBER_LEN . " digits long.\n";

            return false;
        }

        if (isset($_existing_accounts[$_account_number])) {
            echo "Error: An account with this number already exists.\n";

            return false;
        }

        $this->account_number = $_account_number;

        return true;
    }

    /**
     * Summary of setName
     * @param string $_name
     * @return bool
     */
    public function setName(string $_name): bool
    {
        if (empty($_name) || !preg_match("/^[a-zA-Z\s]+$/", $_name)) {
            echo "Error: Invalid name.\n";

            return false;
        }

        $this->customer_name = $_name;

        return true;
    }

    public function setDob(string $_dob): bool
    {
        try {
            $birth_date = new DateTime($_dob);
        } catch (Exception $e) {
            echo "Error: Invalid date format. Please use YYYY-MM-DD.\n";

            return false;
        }

        $age = (new DateTime())->diff($birth_date)->y;

        if ($age < self::MIN_AGE) {
            echo "Error: You must be at least " . self::MIN_AGE . " years old to apply for a loan.\n";

            return false;
        }

        $this->customer_dob = $_dob;

        return true;
    }

    public function setAddress(string $_address): bool
    {
        if (strlen($_address) < self::MIN_ADDRESS_LEN) {
            echo "Error: Address must be at least " . self::MIN_ADDRESS_LEN . " characters long.\n";

            return false;
        }

        $this->customer_address = $_address;

        return true;
    }

    public function setPhone(string $_phone): bool
    {
        if (!preg_match("/^\d{10}$/", $_phone)) {
            echo "Error: Phone number must be a 10-digit number.\n";

            return false;
        }

        $this->customer_phone_number = $_phone;

        return true;
    }

    public function setCustomerId(int $_customer_id): void
    {
        $this->customer_id = $_customer_id;
    }

    /**
     * Summary of isValidLoanAmount
     * @param float $_amount
     * @return bool
     */
    public function isValidLoanAmount(float $_amount): bool
    {
        if ($_amount < self::MIN_LOAN_AMOUNT) {
            echo "Error: Loan amount must be greater than ₹" . number_format(self::MIN_LOAN_AMOUNT) . ".\n";

            return false;
        }

        return true;
    }

    /**
     * Summary of isValidMonths
     * @param int $_months
     * @return bool
     */
    public function isValidMonths(int $_months): bool
    {
        if ($_months < self::MIN_MONTHS || $_months > self::MAX_MONTHS) {
            echo "Error: Loan tenure must be between " . self::MIN_MONTHS . " and " . self::MAX_MONTHS . " months.\n";

            return false;
        }

        return true;
    }

    public function getCustomerId(): int
    {
        return $this->customer_id;
    }

    public function getAccountNumber(): string
    {
        return $this->account_number;
    }

    public function getName(): string
    {
        return $this->customer_name;
    }

    public function getDob(): string
    {
        return $this->customer_dob;
    }

    public function getAddress(): string
    {
        return $this->customer_address;
    }

    public function getPhoneNumber(): string
    {
        return $this->customer_phone_number;
    }

    public function getLoans(): array
    {
        return $this->customer_loans;
    }

    // /**
    //  * Summary of toArray
    //  * @return array{account_number: string, address: string, customer_id: int, dob: string, loans: array, name: string, phone: string}
    //  */
    // public function jsonSerialize(): array
    // {
    //     return [
    //         'customer_id' => $this->customer_id,
    //         'account_number' => $this->account_number,
    //         'name' => $this->customer_name,
    //         'dob' => $this->customer_dob,
    //         'address' => $this->customer_address,
    //         'phone' => $this->customer_phone_number,
    //         'loans' => $this->customer_loans
    //     ];
    // }

    // /**
    //  * Summary of fromArray
    //  * @param array $_data
    //  * @return Customer
    //  */
    // public static function fromArray(array $_data): self
    // {
    //     $customer = new self();
    //     $customer->customer_id = $_data['customer_id'];
    //     $customer->account_number = $_data['account_number'];
    //     $customer->customer_name = $_data['name'];
    //     $customer->customer_dob = $_data['dob'];
    //     $customer->customer_address = $_data['address'];
    //     $customer->customer_phone = $_data['phone'];
    //     $customer->customer_loans = array_map(fn(array $loan) => Loan::fromArray($loan), $_data['loans'] ?? []);

    //     return $customer;
    // }

    // /**
    //  * Summary of loadRaw
    //  * @return array
    //  */
    // private static function loadRaw(): array
    // {
    //     if (!file_exists(self::FILE_NAME)) {
    //         return [];
    //     }

    //     $data = file_get_contents(self::FILE_NAME);

    //     return json_decode($data, true) ?? [];
    // }

    // /**
    //  * Summary of loadAll
    //  * @return Customer[]
    //  */
    // public static function loadAll(): array
    // {
    //     return array_map(fn(array $record) => self::fromArray($record), self::loadRaw());
    // }

    // /**
    //  * Summary of generateCustomerId
    //  * @return int
    //  */
    // public static function generateCustomerId(): int
    // {
    //     $all_customers = self::loadRaw();

    //     if (empty($all_customers)) {
    //         return 1;
    //     }

    //     $ids = array_column($all_customers, 'customer_id');

    //     return max($ids) + 1;
    // }

    // /**
    //  * Summary of save
    //  * @throws RuntimeException
    //  * @return void
    //  */
    // public function save(array $_customer_master_list_data): void
    // {
    //     $_customer_master_list_data[$this->account_number] = $this->jsonSerialize();

    //     $result = file_put_contents(self::FILE_NAME, json_encode(array_values($_customer_master_list_data), JSON_PRETTY_PRINT));

    //     if ($result === false) {
    //         throw new RuntimeException("Failed to save customer data to '" . self::FILE_NAME . "'.");
    //     }
    // }

    /**
     * Summary of insertCustomer
     * @return void
     */

    public function insertCustomer(): void
    {

        $this->customer_id = CustomerManagementRepository::insertCustomer(
            $this->account_number,
            $this->customer_name,
            $this->customer_dob,
            $this->customer_address,
            $this->customer_phone_number
        );
    }
    /**
     * Summary of insertLoan
     * @param Loan $_loan
     * @return void
     */
    public function insertLoan(Loan $_loan): void
    {

        CustomerManagementRepository::insertLoan(
            $this->customer_id,
            $_loan->getLoanType(),
            $_loan->getLoanAmount(),
            $_loan->getLoanTenure(),
            $_loan->getMonthlyEmi(),
            $_loan->getTotalInterest(),
            $_loan->getTotalRepayment()
        );
    }

    /**
     * Loads every customer that has at least one loan (customers with zero
     * loans are excluded by CustomerManagementRepository::getAllCustomers()'s
     * INNER JOIN), keyed by account number.
     *
     * @return array<string, Customer>
     */
    public static function loadAll(): array
    {
        $customer_rows = CustomerManagementRepository::getAllCustomers();
        $customers = [];

        foreach ($customer_rows as $row) {
            $customer = new Customer();
            $customer->setCustomerId((int) $row['customer_id']);
            $customer->setAccountNumber($row['account_number'], []);
            $customer->setName($row['customer_name']);
            $customer->setDob($row['customer_dob']);
            $customer->setAddress($row['customer_address']);
            $customer->setPhone((string) $row['customer_phone_number']);

            foreach ($row['loans'] as $loan_row) {
                $customer->addLoan(new Loan(
                    $loan_row['loan_type'],
                    (float) $loan_row['loan_amount'],
                    (int) $loan_row['loan_tenure'],
                    (float) $loan_row['monthly_emi'],
                    (float) $loan_row['total_interest'],
                    (float) $loan_row['total_repayment']
                ));
            }

            $customers[$row['account_number']] = $customer;
        }

        return $customers;
    }
}
