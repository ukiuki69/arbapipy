<?php
error_reporting(E_ALL);
// header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Origin: http://localhost:3000");
header('Access-Control-Allow-Headers: Content-Type');
header("Access-Control-Allow-Methods: POST, GET");
// header("Access-Control-Allow-Methods: POST");
// header("Access-Control-Allow-Headers: Origin, X-Requested-With");
define ("SLEEP", 0); // テスト用遅延時間

$allowGet = true;
$returnSql = true;
define ("DBNAME", "albatross56_albtest"); // テスト用
$fscgi = 'y_fs_cgi.py'; // 運用テスト用

// $allowGet = false;
// $returnSql = false;
// define ("DBNAME", "albatross56_sv1"); // 本番用
// $fscgi = 'fs_cgi.py'; // 本番用

require './cipher.php';

$phpnow = date("Y-m-d H:i:s");
$MAX_KEYS = 5;
$secret = 'fqgjlAAS4Mjb9lYUJPRBfV6hVLZHab'; // 秘匿性の高いAPIで利用

$resetKeyLimit = 24 * 60 * 60;
$cgiAdrress = 'https://rbatosdata.com';


?>