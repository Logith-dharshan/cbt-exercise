<?php

require_once 'LoanType.php';
require_once 'Customer.php';
require_once 'RetryLimit.php';
require_once 'Loan.php';

class LoanApplication
{
    use RetryLimit;

    private array $customer_master_list_data = [];

    // constructor
    public function __construct()
    {
        $customer_loader = new Customer();

        foreach ($customer_loader->loadAll() as $customer) {
            $this->customer_master_list_data[$customer->getAccountNumber()] = $customer;
        }
    }

    // Customer flow
    /**
     * Asks the user whether they are a new or existing customer.
     * Retries on invalid input up to MAX_ATTEMPTS.
     *
     * @return bool True if the user selected "Existing Customer", false if "New Customer".
     */
    private function isExistingCustomer(): bool
    {
        echo "1. New Customer\n";
        echo "2. Existing Customer\n";

        $attempts = 0;

        while (true) {
            $choice = (int) trim(readline("Enter your choice: "));

            if ($choice === 1) {
                return false;
            }

            if ($choice === 2) {
                return true;
            }

            echo "Error: Please enter 1 or 2.\n";

            $this->registerAttempt($attempts);
        }
    }

    /**
     * Asks the user for their account number and loads the matching
     * customer record. Retries on a not-found account number up to
     * MAX_ATTEMPTS.
     *
     * @return Customer The customer record matching the entered account number.
     */
    private function loadExistingCustomer(): Customer
    {
        $attempts = 0;

        while (true) {
            $account_number = trim(readline("Enter your Acc Number: "));

            if (isset($this->customer_master_list_data[$account_number])) {
                $customer = $this->customer_master_list_data[$account_number];
                echo "\nWelcome back, " . $customer->getName() . "!\n";

                return $customer;
            }
            echo "Error: No customer found with this account number.\n";
            $this->registerAttempt($attempts);
        }
    }

    /**
     * Collects personal details for a brand-new customer.
     *
     * @return Customer The newly created customer, not yet saved to storage.
     */
    private function createNewCustomer(): Customer
    {
        $customer = (new Customer())->collectCustomerDetails($this->customer_master_list_data);

        return $customer;
    }

    // Loan selection
    /**
     * Displays the loan type menu and asks the customer to choose one.
     * Rejects invalid menu choices and loan types the customer has
     * already applied for. Retries up to MAX_ATTEMPTS.
     *
     * @param Customer $_customer The customer applying for a loan.
     * @return LoanType The chosen loan type.
     */
    private function collectLoanSelection(Customer $_customer): LoanType
    {
        echo "\nSelect Loan Type:\n";
        echo "1. Personal Loan (12% p.a.)\n";
        echo "2. Home Loan (8% p.a.)\n";
        echo "3. Car Loan (9% p.a.)\n";
        echo "4. Education Loan (9.5% p.a.)\n";

        $attempts = 0;

        while (true) {
            $choice = (int) trim(readline("Enter the number corresponding to your loan type: "));

            try {
                $loan_type = LoanType::create($choice);
            } catch (Exception $e) {
                echo "Error: Invalid loan type. Please enter a number between 1 and 4.\n";
                $loan_type = null;
            }

            if ($loan_type !== null && $_customer->hasAppliedFor($loan_type->label())) {
                echo "Error: Customer has already applied for this loan type.\n";
                $loan_type = null;
            }

            if ($loan_type !== null) {
                return $loan_type;
            }

            $this->registerAttempt($attempts);
        }
    }

    /**
     * Asks the customer for a loan amount and validates it.
     * Retries on invalid input up to MAX_ATTEMPTS.
     *
     * @param Customer $_customer The customer applying for a loan, used for validation rules.
     *
     * @return float The validated loan amount.
     */
    private function collectLoanAmount(Customer $_customer): float
    {
        $attempts = 0;

        while (true) {
            $amount = (float) trim(readline("Enter the loan amount: "));

            if ($_customer->isValidLoanAmount($amount)) {
                return $amount;
            }

            $this->registerAttempt($attempts);
        }
    }

    /**
     * Asks the customer for a loan tenure in months and validates it.
     * Retries on invalid input up to MAX_ATTEMPTS.
     *
     * @param Customer $_customer The customer applying for a loan, used for validation rules.
     *
     * @return int The validated loan tenure in months.
     */
    private function collectLoanMonths(Customer $_customer): int
    {
        $attempts = 0;

        while (true) {
            $months = (int) trim(readline("Enter the loan tenure in months: "));

            if ($_customer->isValidMonths($months)) {
                return $months;
            }

            $this->registerAttempt($attempts);
        }
    }

    // Loan calculation
    /**
     * Calculates the monthly EMI, total interest, and total repayment
     * using the standard reducing-balance bank formula:
     * EMI = P × r × (1+r)^n / ((1+r)^n - 1)
     * Where: P = principal, r = monthly interest rate, n = number of months.
     *
     * @param LoanType $_loan_type The loan type, used to determine the annual interest rate.
     * @param float $_principal The loan amount.
     * @param int $_months The loan tenure in months.
     *
     * @return array{monthly_payment: float, total_repayment: float, total_interest: float}
     */
    private function calculateEmi(LoanType $_loan_type, float $_principal, int $_months): array
    {
        $monthly_rate = $_loan_type->rate() / 12;

        $monthly_payment = $_principal * $monthly_rate * pow(1 + $monthly_rate, $_months)
            / (pow(1 + $monthly_rate, $_months) - 1);

        $total_repayment = $monthly_payment * $_months;
        $total_interest = $total_repayment - $_principal;

        return [
            'monthly_payment' => $monthly_payment,
            'total_repayment' => $total_repayment,
            'total_interest' => $total_interest,
        ];
    }

    // Output
    /**
     * Prints the loan summary for the loan just applied for, including
     * the customer's details and the calculated EMI figures.
     *
     * @param Customer $_customer The customer who applied for the loan.
     * @param LoanType $_loan_type The loan type that was selected.
     * @param Loan $_loan The loan that was created, carrying the amount, tenure, and EMI figures.
     *
     * @return void
     */
    private function printLoanSummary(
        Customer $_customer,
        LoanType $_loan_type,
        Loan $_loan
    ): void {
        echo "\n--- Loan Summary ---\n";
        echo "Customer ID: " . $_customer->getCustomerId() . "\n";
        echo "Account Number: " . $_customer->getAccountNumber() . "\n";
        echo "Name: " . $_customer->getName() . "\n";
        echo "Phone Number: " . $_customer->getPhoneNumber() . "\n";
        echo "Loan Type: " . $_loan_type->label() . " (" . ($_loan_type->rate() * 100) . "% p.a.)\n";
        echo "Loan Amount: " . $this->formatCurrency($_loan->getLoanAmount()) . "\n";
        echo "Loan Tenure(months): " . $_loan->getLoanTenure() . " months\n";
        echo "Monthly EMI: " . $this->formatCurrency($_loan->getMonthlyEmi()) . "\n";
        echo "Total Interest: " . $this->formatCurrency($_loan->getTotalInterest()) . "\n";
        echo "Total Repayment: " . $this->formatCurrency($_loan->getTotalRepayment()) . "\n";
    }

    /**
     * Runs the full loan application flow: determines whether the
     * customer is new or existing, collects loan details, calculates
     * the EMI, saves the customer record, and prints the summary.
     *
     * @return void
     */
    public function run(): void
    {
        $is_existing = $this->isExistingCustomer();
        $customer = $is_existing
            ? $this->loadExistingCustomer()
            : $this->createNewCustomer();

        $loan_type = $this->collectLoanSelection($customer);
        $loan_amount = $this->collectLoanAmount($customer);
        $months = $this->collectLoanMonths($customer);

        $emi_details = $this->calculateEmi($loan_type, $loan_amount, $months);

        $loan = new Loan(
            $loan_type->label(),
            $loan_amount,
            $months,
            $emi_details['monthly_payment'],
            $emi_details['total_interest'],
            $emi_details['total_repayment']
        );

        if (! $is_existing) {
            $customer->insertCustomer();
        }

        $customer->addLoan($loan);
        $customer->insertLoan($loan);

        $this->printLoanSummary($customer, $loan_type, $loan);
    }
    /**
     * Formats a numeric amount as an Indian Rupee currency string.
     *
     * @param float $_amount The raw numeric amount to format.
     *
     * @return string The formatted currency string (e.g. "₹5,00,000.00").
     */
    private function formatCurrency(float $_amount): string
    {
        $formatter = new NumberFormatter('en_IN', NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($_amount, 'INR');
    }
}

(new LoanApplication())->run();
