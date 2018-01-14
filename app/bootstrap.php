<?php
ini_set('display_errors', 1);
require __DIR__ . '/../vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/../.env');

