<?php

require_once 'Database.php';
require_once 'LoanManagementRepository.php';

header('Content-Type: application/json');

/**
 * Sends a uniform JSON response: status, message, and (optional) data.
 *
 * @param int    $_status  HTTP status code.
 * @param string $_message Human-readable result message.
 * @param mixed  $_data    Payload, or null if there's nothing to return.
 *
 * @return void
 */                                                                                                         
function sendResponse(int $_status, string $_message, mixed $_data = null): void
{
    http_response_code($_status);
    echo json_encode([
        'status'  => $_status,
        'message' => $_message,
        'data'    => $_data,
    ]);

    exit;

}

/**
 * Validates that every required key is present and non-empty in $_data.
 * Sends a 400 JSON error and stops execution if any field is missing.
 *
 * @param array    $_data Decoded request body to check.
 * @param string[] $_required_keys Keys that must be present and non-empty.
 *
 * @return void
 */
function requireFields(array $_data, array $_required_keys): void
{
    foreach ($_required_keys as $key) {
        if (!isset($_data[$key]) || $_data[$key] === '') {
            sendResponse(400, "Missing required field: '{$key}'");
        }
    }
}

// Parse the request 

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim($path, '/');           // "customers/784512" or "customers" or ""
$segments = $path === '' ? [] : explode('/', $path);

// Expected shapes: ["customers"] or ["customers", "{account_number}"]
$resource = $segments[0] ?? null;
$account_number = $segments[1] ?? null;

if ($resource !== 'customers') {
    sendResponse(404, "Not found. Use /customers or /customers/{account_number}.");
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

//  Route

switch ($method) {

    case 'GET':

        if ($account_number !== null){
            $customer = LoanManagementRepository::getCustomerByAccountNumber($account_number);

            if ($customer === null){
                sendResponse(404, "No customer found with this customer or they have no loans");
            }

            sendResponse(200,"Customer loans received successfully.", $customer);
            
        }

        $rows = LoanManagementRepository::getAllCustomers();
        sendResponse(200, 'Customers with loans retrieved successfully', $rows);

        break;

    case 'POST':
        requireFields($body, [
            'account_number',
            'customer_name',
            'customer_dob',
            'customer_address',
            'customer_phone_number',
        ]);

        $customer_id = LoanManagementRepository::insertCustomer(
            $body['account_number'],
            $body['customer_name'],
            $body['customer_dob'],
            $body['customer_address'],
            $body['customer_phone_number']
        );

        sendResponse(201, 'Customer inserted successfully', [
            'customer_id' => $customer_id,
        ]);
        break;

    case 'PUT':
        if ($account_number === null) {
            sendResponse(400, 'Account number is required in the URL: /customers/{account_number}');
        }

        requireFields($body, [
            'customer_name',
            'customer_dob',
            'customer_address',
            'customer_phone_number',
        ]);

        $affected = LoanManagementRepository::updateCustomer(
            $account_number,
            $body['customer_name'],
            $body['customer_dob'],
            $body['customer_address'],
            $body['customer_phone_number']
        );

        if ($affected === 0) {
            sendResponse(404, 'No customer found with this account number, or no changes made');
        }

        $customer = LoanManagementRepository::getCustomerByAccountNumber($account_number);
        sendResponse(200, 'Customer updated successfully', $customer);
        break;

    case 'DELETE':
        if ($account_number === null) {
            sendResponse(400, 'Account number is required in the URL: /customers/{account_number}');
        }

        $affected = LoanManagementRepository::deleteCustomer($account_number);

        if ($affected === 0) {
            sendResponse(404, 'No customer found with this account number');
        }

        sendResponse(200, 'Customer deleted successfully');
        break;

    default:
        sendResponse(405, 'Method not allowed');
}