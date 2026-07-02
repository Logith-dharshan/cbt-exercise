<?php

class Router
{
	private array $routes = [];

	public function add(string $_method, string $_pattern, callable $_handler): void
	{
		$this->routes[] = [
			'method'  => strtoupper($_method),
			'pattern' => trim($_pattern, '/'),
			'handler' => $_handler,
		];
	}

	public function dispatch(string $_method, string $_path): void
	{

		$_method = strtoupper($_method);
		$_path = trim($_path, '/');

		foreach ($this->routes as $route) {

			if ($route['method'] !== $_method) {
				continue;
			}

			$params = $this->match($route['pattern'], $_path);

			if ($params !== null) {
				call_user_func_array($route['handler'], $params);

				return;
			}
		}

		http_response_code(404);

		echo json_encode([
			'status' => 404,
			'message' => 'Route not found',
			'data' => null
		]);
	}

	private function match(string $_pattern, string $_path): ?array
	{

		$pattern_parts = array_values(array_filter(explode('/', $_pattern), 'strlen'));
		$path_parts = array_values(array_filter(explode('/', $_path), 'strlen'));

		if (count($pattern_parts) !== count($path_parts)) {
			return null;
		}

		$params = [];

		foreach ($pattern_parts as $i => $part) {

			if (str_starts_with($part, '{') && str_ends_with($part, '}')) {
				$params[] = $path_parts[$i];

				continue;
			}

			if ($part !== $path_parts[$i]) {
				return null;
			}
		}

		return $params;
	}
}
