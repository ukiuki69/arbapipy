<?php
header("Access-Control-Allow-Origin: http://localhost:3000");
header('Access-Control-Allow-Headers: Content-Type');
header("Access-Control-Allow-Methods: POST, GET");

if (array_key_exists('path', $_REQUEST) == false){
  $rt = array('result' => false, 'msg' => 'path required.');
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  return false;
}

$path = '../contactbookimg/' . $_REQUEST['path'];
$file_exist = file_exists($path);
$rt = [];
if ($file_exist){
  $result = unlink($path);
  $rt['result'] = $result;
  if (!$result){
    $rt['msg'] = 'unlink failer.';
  }
}
else{
  $rt['result'] = false;
  $rt['msg'] = 'file not found.';
}
echo json_encode($rt, JSON_UNESCAPED_UNICODE);
?>