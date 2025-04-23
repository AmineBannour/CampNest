<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Required environment variables
$required_env_vars = [
    'SMTP_HOST',
    'SMTP_USERNAME',
    'SMTP_PASSWORD',
    'SMTP_PORT',
    'MAIL_FROM_ADDRESS',
    'MAIL_FROM_NAME'
];

$dotenv->required($required_env_vars); 