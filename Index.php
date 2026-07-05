<?php

if (PHP_SAPI === 'cli') {
	require_once __DIR__ . '/LoanApplication.php';

	(new LoanApplication())->run();

	return;
}

// Serve static files when running with PHP built-in server

if (PHP_SAPI === 'cli-server') {
	$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

	$file = __DIR__ . $path;

	if (
		$path !== '/' &&
		file_exists($file) &&
		!is_dir($file)
	) {
		return false;
	}
}

require_once __DIR__ . '/Router/Routes.php';

/** @var Router $router Defined by Router/Routes.php */
$router->dispatch(
	$_SERVER['REQUEST_METHOD'],
	parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/'
);