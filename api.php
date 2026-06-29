<?php

require_once 'Database.php';

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        $customres = (new Database())->loadAllCustomers();
        echo json_encode($customres);

        break;
    
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $customers = Database::insertCustomer(
            $data['account_number'],
            $data['customer_name'],
            $data['customer_dob'],
            $data['customer_address'],
            $data['customer_phone_number']
        );
        echo json_encode([
            'message' => 'Customer inserted successfully',
            'customer_id' => $customers
        ]);

        break;

    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = Database::getConnection()->prepare("
            UPDATE customers 
            SET customer_name = ?, customer_dob = ?, customer_address = ?, customer_phone_number = ? 
            WHERE account_number = ?");
        $stmt->bind_param(
            "sssis",
            $data['customer_name'],
            $data['customer_dob'],
            $data['customer_address'],
            $data['customer_phone_number'],
            $data['account_number']
        );

        $stmt->execute();
        echo json_encode([
            'message' => 'Customer updated successfully'
        ]);

        break;

        case 'DELETE':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = Database::getConnection()->prepare("DELETE FROM customers WHERE account_number = ?");
            $stmt->bind_param("s", $data['account_number']);
            $stmt->execute();
            echo json_encode([
                'message' => 'Customer deleted successfully'
            ]);
    
            break;

        default:
            
            echo json_encode([
                'message' => 'Method not allowed'
            ]);
}