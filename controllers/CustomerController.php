<?php

require_once __DIR__ . '/../Repositories/CustomerManagementRepository.php';

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

    public function handleGet(?string $_account_number = null): void
    {
        if ($_account_number !== null) {

            $customer = CustomerManagementRepository::getCustomerByAccountNumber($_account_number);

            if ($customer === null) {
                $this->sendResponse(404, 'No customer found with this account number or they have no loans');

                return;
            }

            $this->sendResponse(200, 'Customer with loans received successfully.', $customer);

            return;
        }

        $customers = CustomerManagementRepository::getAllCustomers();

        $this->sendResponse(200, 'Customers with loans retrieved successfully', $customers);
    }

    public function handlePost()
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $this->requireFields($body, [
            'account_number',
            'customer_name',
            'customer_dob',
            'customer_address',
            'customer_phone_number',
        ]);

        $customer_id = CustomerManagementRepository::insertCustomer(
            $body['account_number'],
            $body['customer_name'],
            $body['customer_dob'],
            $body['customer_address'],
            $body['customer_phone_number']
        );

        $this->sendResponse(201, 'Customer inserted successfully', ['customer_id' => $customer_id]);
    }

    /**
     * Handles PUT /customers/{account_number}
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

        $affected = CustomerManagementRepository::updateCustomer(
            $_account_number,
            $body['customer_name'],
            $body['customer_dob'],
            $body['customer_address'],
            $body['customer_phone_number']
        );

        if ($affected === 0) {
            $this->sendResponse(404, 'No customer found with this account number, or no changes made');

            return;
        }

        $customer = CustomerManagementRepository::getCustomerByAccountNumber($_account_number);

        $this->sendResponse(200, 'Customer updated successfully', $customer);
    }

    /**
     * Handles DELETE /customers/{account_number}
     */
    public function handleDelete(string $_account_number): void
    {
        $affected = CustomerManagementRepository::deleteCustomer($_account_number);
        if ($affected === 0) {
            $this->sendResponse(404, 'No customer found with this account number');

            return;
        }

        $this->sendResponse(200, 'Customer deleted successfully');
    }
}
