<?php

if (PHP_SAPI === 'cli') {
    require_once __DIR__ . '/LoanApplication.php';

    (new LoanApplication())->run();

    return;
}

require_once __DIR__ . '/Router/Routes.php';

/** @var Router $router Defined by Router/Routes.php */
$router->dispatch($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/'
); 