<?php

////////////////////////////////////////////////////
//  DATABASE SETTINGS                             //
////////////////////////////////////////////////////

$DB_HOST = $DB_HOST ?? 'localhost';
$DB_USER = $DB_USER ?? 'root';
$DB_PASS = $DB_PASS ?? '';
$DB_NAME = $DB_NAME ?? '007i_gomining_tracker';

////////////////////////////////////////////////////
//  You can rename the app here                   //
////////////////////////////////////////////////////

$APP_NAME = $APP_NAME ?? "RewardLedger";

////////////////////////////////////////////////////
//  Do not edit below this line.                  //
////////////////////////////////////////////////////

$APP_VERSION = file_exists(__DIR__ . "/VERSION")
    ? trim(file_get_contents(__DIR__ . "/VERSION"))
    : "0.0.0";

?>
