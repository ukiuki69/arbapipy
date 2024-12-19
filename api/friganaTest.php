<?php
$ch = curl_init();

$data = array("name" => "田中太郎");

curl_setopt($ch, CURLOPT_URL, "http://172.28.0.12:5000/get_furigana");  // Colabで表示されるIPアドレスを使用
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
curl_close($ch);

echo $response;

