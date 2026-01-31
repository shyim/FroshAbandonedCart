<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 4);
$loader = require $projectRoot . '/vendor/autoload.php';

// Register the plugin's autoload namespace
$loader->addPsr4('Frosh\\AbandonedCart\\', dirname(__DIR__) . '/src/');
$loader->addPsr4('Frosh\\AbandonedCart\\Tests\\', dirname(__DIR__) . '/tests/');
