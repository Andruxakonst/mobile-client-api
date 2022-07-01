<?php
include_once __DIR__ . '/../vendor/autoload.php';
include_once __DIR__ . '/../../../classes/db/dbconfig.php';
include_once __DIR__ . '/../../../classes/LocalMemcached.php';
include_once __DIR__ . '/config.php';

$app = new \Slim\App($config);

$container = $app->getContainer();

include_once __DIR__ . '/dependencies.php';

include_once __DIR__ . '/routes.php';

// Run app
$app->run();