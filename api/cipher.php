<?php
$key = '?Y;7{[Sx%|7.U]>T8P<k]d}{*[v}M,o$Qd*x!]Y$';
// $plain_text = 'abcasfdd';

// //openssl
// $c_t = openssl_encrypt($plain_text, 'AES-128-ECB', $key);
// $p_t = openssl_decrypt($c_t, 'AES-128-ECB', $key);
// echo('plain/encryption/composite<br>');
// var_dump($plain_text, $c_t, $p_t);

function encodeCph($text){
  global $key;
  return openssl_encrypt($text, 'AES-128-ECB', $key);
}

function decodeCph($text){
  global $key;
  return openssl_decrypt($text, 'AES-128-ECB', $key);
}
?>
