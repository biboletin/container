<?php

use Bibo\Logger\Formatter\LineFormatter;
use Bibo\Logger\Handler\RotatingFileHandler;
use Bibo\Logger\Logger;
use Bibo\Logger\Observer\EmailLogObserver;
use Bibo\Logger\Observer\LogObserver;

include __DIR__ . '/vendor/autoload.php';

/*
$logger = new LogObserver();

// Attach observers
$logger->attach(new EmailLogObserver());

// Logging
$logger->info('User logged in', ['user_id' => 123]);
$logger->critical('Database down!');
*/


$logger = new Logger(
    new LineFormatter(),
    new RotatingFileHandler(__DIR__ . '/logs', 'app.log', 10)
);

$logger->info("User {user} logged in", ['user' => 'admin']);
$logger->error("Syntax error", ['file' => 'index.php', 'line' => 42]);

dd($logger);
