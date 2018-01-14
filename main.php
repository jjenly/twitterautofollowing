<?php

namespace App;
require __DIR__ . '/app/bootstrap.php';

$mysqliDb = new \MysqliDb (getenv('DB_HOST'), getenv('DB_USER'), getenv('DB_PASS'), getenv('DB_NAME'));
$apiTwitter = new ApiTwitter();
$follow = new AutoFollow($mysqliDb, $apiTwitter);
$follow
    ->syncFriends()
    ->goFollow();
