<?php
header("Access-Control-Allow-Origin: http://localhost:3000");
header('Access-Control-Allow-Headers: Content-Type');
header("Access-Control-Allow-Methods: POST, GET");

// $file = $_FILES['file'];
// echo json_encode([
//   'file_name' => $file['name'],
//   'tmp_file' => $file['tmp_name'],
//   'error' => $file['error'],
// ], JSON_UNESCAPED_UNICODE);

$tempfile = $_FILES['file']['tmp_name'];
$filename = '../nagarabuil4f/' . $_FILES['file']['name'];
$rt = [];
if (is_uploaded_file($tempfile)) {
  if ( move_uploaded_file($tempfile , $filename )) {
    $rt['result']  = true;
    $rt['filename']  = $filename;
	  // echo $filename . "をアップロードしました。";
  } 
  else {
    $rt['result']  = false;
    $rt['filename']  = $filename;
  }
} 
else {
  $rt['result']  = false;
  $rt['filename']  = '';
  // echo "ファイルが選択されていません。";
}
echo json_encode($rt, JSON_UNESCAPED_UNICODE);
?>