<?php
$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();

$config = [
	'settings' => [
		'displayErrorDetails' => getenv('API_DEBUG'),
	]
];

?>