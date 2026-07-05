<?php

require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/../Controllers/CustomerController.php';

$router = new Router();

$customer_controller = new CustomerController();

$router->add('GET', '/customers', [$customer_controller, 'handleGet']);
$router->add('GET', '/customers/{account_number}', [$customer_controller, 'handleGet']);
$router->add('POST', '/customers', [$customer_controller, 'handlePost']);
$router->add('PUT', '/customers/{account_number}', [$customer_controller, 'handlePut']);
$router->add('DELETE','/customers/{account_number}', [$customer_controller, 'handleDelete']);
$router->add('POST', '/customers/{account_number}/loans', [$customer_controller, 'handleApplyLoan']);
$router->add('DELETE', '/customers/{account_number}/loans', [$customer_controller, 'handleDeleteLoans']);