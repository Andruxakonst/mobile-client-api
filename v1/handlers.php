<?php
$container['notFoundHandler'] = function ($c) {
	return function ($request, $response) use ($c) {
		$answer = [
			'status' => 'error',
			'id' => -6,
			'message' => 'Page not found'
		];
		return $response->withJson($answer, 404)
			->withHeader('Content-type', 'application/vnd.api+json');
	};
};
$container['notAllowedHandler'] = function ($c) {
	return function ($request, $response, $methods) use ($c) {
		$answer = [
			'status' => 'error',
			'id' => -8,
			'message' => 'Method must be one of: ' . implode(', ', $methods)
		];
		return $response->withJson($answer, 405)
			->withHeader('Allow', implode(', ', $methods))
			->withHeader('Content-type', 'application/vnd.api+json');
	};
};