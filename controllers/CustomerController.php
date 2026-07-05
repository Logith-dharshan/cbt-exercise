<?php

require_once __DIR__ . '/../Repositories/CustomerManagementRepository.php';
require_once __DIR__ . '/../Models/Loan.php';
require_once __DIR__ . '/../Models/LoanType.php';
require_once __DIR__ . '/../Models/Customer.php';

class CustomerController
{

	private function sendResponse(int $_status, string $_message, mixed $_data = null): void
	{
		http_response_code($_status);
		echo json_encode([
			'status' => $_status,
			'message' => $_message,
			'data' => $_data
		]);

		exit;
	}

	private function requireFields(array $_data, array $_required_keys): void
	{
		foreach ($_required_keys as $key) {
			if (! isset($_data[$key]) || $_data[$key] === '') {
				$this->sendResponse(400, "Missing required field: '{$key}'");

				return;
			}
		}
	}

	/**
	 * Validates the shared customer fields (name, dob, address, phone).
	 * Sends a 400 response and halts execution if anything is invalid.
	 */
	private function validateCustomerFields(array $_body): void
	{
		if (! preg_match('/^[a-zA-Z\s]+$/', $_body['customer_name'])) {
			$this->sendResponse(400, 'Customer name must contain letters and spaces only.');

			return;
		}

		try {
			$birth_date = new DateTime($_body['customer_dob']);
		} catch (Exception $e) {
			$this->sendResponse(400, 'Customer date of birth must be a valid date (YYYY-MM-DD).');

			return;
		}

		$age = (new DateTime())->diff($birth_date)->y;
		if ($age < Customer::MIN_AGE) {
			$this->sendResponse(400, 'Customer must be at least ' . Customer::MIN_AGE . ' years old.');

			return;
		}

		if (strlen($_body['customer_address']) < Customer::MIN_ADDRESS_LEN) {
			$this->sendResponse(400, 'Address must be at least ' . Customer::MIN_ADDRESS_LEN . ' characters long.');

			return;
		}

		if (! preg_match('/^\d{10}$/', (string) $_body['customer_phone_number'])) {
			$this->sendResponse(400, 'Phone number must be a 10-digit number.');

			return;
		}
	}

	/**
	 * Validates account_number format (does not check uniqueness).
	 */
	private function validateAccountNumberFormat(string $_account_number): void
	{
		$length = strlen($_account_number);

		if (
			! preg_match('/^\d+$/', $_account_number)
			|| $length < Customer::MIN_ACCOUNT_NUMBER_LEN
			|| $length > Customer::MAX_ACCOUNT_NUMBER_LEN
		) {
			$this->sendResponse(
				400,
				'Account number must be ' . Customer::MIN_ACCOUNT_NUMBER_LEN . '-' . Customer::MAX_ACCOUNT_NUMBER_LEN . ' digits long.'
			);

			return;
		}
	}

	/**
	 * Validates loan_amount / loan_tenure. Sends a 400 response and halts on failure.
	 */
	private function validateLoanAmountAndTenure(float $_amount, int $_tenure): void
	{
		if ($_amount < Customer::MIN_LOAN_AMOUNT) {
			$this->sendResponse(400, 'Loan amount must be at least ₹' . number_format(Customer::MIN_LOAN_AMOUNT) . '.');

			return;
		}

		if ($_tenure < Customer::MIN_MONTHS || $_tenure > Customer::MAX_MONTHS) {
			$this->sendResponse(400, 'Loan tenure must be between ' . Customer::MIN_MONTHS . ' and ' . Customer::MAX_MONTHS . ' months.');

			return;
		}
	}

	private function resolveLoanType(string $_loan_type): LoanType
	{
		return match ($_loan_type) {
			'Personal Loan', 'personal' => new PersonalLoan(),
			'Home Loan', 'home'         => new HomeLoan(),
			'Car Loan', 'car'           => new CarLoan(),
			'Education Loan', 'education' => new EducationLoan(),
			default => throw new InvalidArgumentException("Invalid loan type: '{$_loan_type}'"),
		};
	}

	/**
	 * Handles GET /customers and GET /customers/{account_number}
	 */
	public function handleGet(?string $_account_number = null): void
	{
		if ($_account_number !== null) {

			$customer = CustomerManagementRepository::getCustomerByAccountNumber($_account_number);

			if ($customer === null) {
				$this->sendResponse(404, 'No customer found with this account number.');

				return;
			}

			$this->sendResponse(200, 'Customer with loans received successfully.', $customer);

			return;
		}

		$customers = CustomerManagementRepository::getAllCustomers();

		$this->sendResponse(200, 'Customers with loans retrieved successfully', $customers);
	}

	/**
	 * Handles POST /customers — creates a customer together with their first loan.
	 */
	public function handlePost(): void
	{
		$body = json_decode(file_get_contents('php://input'), true) ?? [];

		$this->requireFields($body, [
			'account_number',
			'customer_name',
			'customer_dob',
			'customer_address',
			'customer_phone_number',
			'loan_type',
			'loan_amount',
			'loan_tenure',
		]);

		$account_number = (string) $body['account_number'];

		$this->validateAccountNumberFormat($account_number);
		$this->validateCustomerFields($body);

		if (CustomerManagementRepository::accountNumberExists($account_number)) {
			$this->sendResponse(409, 'Account number already exists.');

			return;
		}

		try {
			$loan_type = $this->resolveLoanType($body['loan_type']);
		} catch (InvalidArgumentException $e) {
			$this->sendResponse(400, $e->getMessage());

			return;
		}

		$loan_amount = (float) $body['loan_amount'];
		$loan_tenure = (int) $body['loan_tenure'];

		$this->validateLoanAmountAndTenure($loan_amount, $loan_tenure);

		$customer_id = CustomerManagementRepository::insertCustomer(
			$account_number,
			$body['customer_name'],
			$body['customer_dob'],
			$body['customer_address'],
			$body['customer_phone_number']
		);

		$emi_data = Loan::calculateEmi($loan_type, $loan_amount, $loan_tenure);

		$loan_id = CustomerManagementRepository::insertLoan(
			$customer_id,
			$loan_type->label(),
			$loan_amount,
			$loan_tenure,
			$emi_data['monthly_payment'],
			$emi_data['total_interest'],
			$emi_data['total_repayment']
		);

		$this->sendResponse(201, 'Customer created successfully.', ['customer_id' => $customer_id, 'loan_id' => $loan_id]);
	}

	/**
	 * Handles PUT /customers/{account_number} — updates customer details only.
	 * account_number can never be changed via this endpoint.
	 */
	public function handlePut(string $_account_number): void
	{
		$body = json_decode(file_get_contents('php://input'), true) ?? [];

		$this->requireFields($body, [
			'customer_name',
			'customer_dob',
			'customer_address',
			'customer_phone_number'
		]);

		if (isset($body['account_number']) && (string) $body['account_number'] !== $_account_number) {
			$this->sendResponse(400, 'Account number cannot be modified.');

			return;
		}

		if (! CustomerManagementRepository::accountNumberExists($_account_number)) {
			$this->sendResponse(404, 'No customer found with this account number.');

			return;
		}

		$this->validateCustomerFields($body);

		CustomerManagementRepository::updateCustomer(
			$_account_number,
			$body['customer_name'],
			$body['customer_dob'],
			$body['customer_address'],
			$body['customer_phone_number']
		);

		$customer = CustomerManagementRepository::getCustomerByAccountNumber($_account_number);

		$this->sendResponse(200, 'Customer updated successfully.', $customer);
	}

	/**
	 * Handles DELETE /customers/{account_number}.
	 * Refuses to delete a customer that still has one or more loans.
	 */
	public function handleDelete(string $_account_number): void
	{
		if (! CustomerManagementRepository::accountNumberExists($_account_number)) {
			$this->sendResponse(404, 'No customer found with this account number.');

			return;
		}

		if (CustomerManagementRepository::customerHasLoans($_account_number)) {
			$this->sendResponse(409, 'Customer cannot be deleted because active loans exist.');

			return;
		}

		CustomerManagementRepository::deleteCustomer($_account_number);

		$this->sendResponse(200, 'Customer deleted successfully.');
	}

	/**
	 * Handles POST /customers/{account_number}/loans — applies a new loan
	 * for an existing customer. Rejects duplicate loan types.
	 */
	public function handleApplyLoan(string $_account_number): void
	{
		$body = json_decode(file_get_contents('php://input'), true) ?? [];

		$this->requireFields($body, ['loan_type', 'loan_amount', 'loan_tenure']);

		$customer_id = CustomerManagementRepository::getCustomerIdByAccountNumber($_account_number);

		if ($customer_id === null) {
			$this->sendResponse(404, 'No customer found with this account number.');

			return;
		}

		try {
			$loan_type = $this->resolveLoanType($body['loan_type']);
		} catch (InvalidArgumentException $e) {
			$this->sendResponse(400, $e->getMessage());

			return;
		}

		if (CustomerManagementRepository::customerHasLoanType($_account_number, $loan_type->label())) {
			$this->sendResponse(409, "This customer already has an active {$loan_type->label()}.");

			return;
		}

		$loan_amount = (float) $body['loan_amount'];
		$loan_tenure = (int) $body['loan_tenure'];

		$this->validateLoanAmountAndTenure($loan_amount, $loan_tenure);

		$emi_data = Loan::calculateEmi($loan_type, $loan_amount, $loan_tenure);

		$loan_id = CustomerManagementRepository::insertLoan(
			$customer_id,
			$loan_type->label(),
			$loan_amount,
			$loan_tenure,
			$emi_data['monthly_payment'],
			$emi_data['total_interest'],
			$emi_data['total_repayment']
		);

		$customer = CustomerManagementRepository::getCustomerByAccountNumber($_account_number);

		$this->sendResponse(201, 'Loan applied successfully.', ['loan_id' => $loan_id, 'customer' => $customer]);
	}

	/**
	 * Handles DELETE /customers/{account_number}/loans
	 * Body: { "loan_ids": [1, 2, 3] }
	 */
	public function handleDeleteLoans(string $_account_number): void
	{
		$body = json_decode(file_get_contents('php://input'), true) ?? [];

		if (! CustomerManagementRepository::accountNumberExists($_account_number)) {
			$this->sendResponse(404, 'No customer found with this account number.');

			return;
		}

		$loan_ids = $body['loan_ids'] ?? [];

		if (! is_array($loan_ids) || empty($loan_ids)) {
			$this->sendResponse(400, 'Select at least one loan to delete.');

			return;
		}

		$affected = CustomerManagementRepository::deleteLoans($_account_number, $loan_ids);

		if ($affected === 0) {
			$this->sendResponse(404, 'None of the selected loans were found for this customer.');

			return;
		}

		$customer = CustomerManagementRepository::getCustomerByAccountNumber($_account_number);

		$this->sendResponse(200, $affected === 1 ? 'Loan deleted successfully.' : "{$affected} loans deleted successfully.", $customer);
	}
}
