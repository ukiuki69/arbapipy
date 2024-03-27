<?php
error_reporting(E_ALL);
// header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Origin: http://localhost:3000");
header('Access-Control-Allow-Headers: Content-Type');
// header("Access-Control-Allow-Methods: POST, GET");
header("Access-Control-Allow-Methods: POST");
// header("Access-Control-Allow-Headers: Origin, X-Requested-With");
$returnSql = false;
define ("SLEEP", 0); // テスト用遅延時間

require './cipher.php';


$phpnow = date("Y-m-d H:i:s");
$MAX_KEYS = 5;
$secret = 'fqgjlAAS4Mjb9lYUJPRBfV6hVLZHab'; // 秘匿性の高いAPIで利用

$resetKeyLimit = 24 * 60 * 60;
$cgiAdrress = 'https://rbatosdata.com';

function unicode_encode($str) {
  return preg_replace_callback('/\\\\u([0-9a-zA-Z]{4})/', 
    function ($matches) {
    $char = mb_convert_encoding(pack('H*', $matches[1]), 'UTF-16', 'UTF-8');
    return $char;
    }, $str);
};

// 使用状況により切り替えるため
function PRMS($key){
  // return($_REQUEST[$key]);
  // return($_POST[$key]);
  // $_REQUESTはクッキーまで拾うのでめんどくさい
  if (count($_GET)){
    if (array_key_exists($key, $_GET)){
      return($_GET[$key]);
    }
    else return('');
  }
  else{
    if (array_key_exists($key, $_POST)){
      return($_POST[$key]);
    }
    else return('');
  }
}

function PRMS_ARRAY(){
  // return($_REQUEST);
  // return($_POST);
  return($_GET);
}


function connectDb(){
  $mysqli = new mysqli(
    'mysql10077.xserver.jp',
    'albatross56_ysmr',
    'kteMpg5D',
    'albatross56_sv1'
  );
  if ($mysqli->connect_error) {
    $rt['result'] = 'false';
    // $rt['user'] = $dbid;
    $rt['msg'] = $mysqli->connect_error;
    echo json_encode($rt, JSON_UNESCAPED_UNICODE);
    exit();
  }
  else {
    $mysqli->set_charset("utf8");
  }
  return($mysqli);
}

// 汎用リストモジュール
function unvList($mysqli, $sql){
  global $returnSql;
  $sql = str_replace(array("\r\n", "\r", "\n"), ' ', $sql);
  $rt = [];
  $dt = [];
  if ($result = $mysqli->query($sql)) {
    $rt['result'] = true;
    while ($row = $result->fetch_assoc()) {
      $dt[] = $row;
    }
    $rt['dt'] = $dt;
  }
  else{
    $rt['result'] = false;
  }
  if($returnSql) $rt['sql'] = $sql;
  return($rt);
  // echo json_encode($rt, JSON_UNESCAPED_UNICODE);
}

// 汎用カウントモジュール
function unvCnt($mysqli, $sql){
  global $returnSql;
  $sql = str_replace(array("\r\n", "\r", "\n"), ' ', $sql);
  $rt = [];
  if ($result = $mysqli->query($sql)) {
    $rt['result'] = true;
    $rt['count'] = $result->num_rows;
  }
  else{
    $rt['result'] = false;
  }
  if($returnSql) $rt['sql'] = $sql;
  return($rt);
  // echo json_encode($rt, JSON_UNESCAPED_UNICODE);
}

// 汎用修正モジュール
// update insert delete
function unvEdit($mysqli, $sql, $rtn = false){
  global $returnSql;
  $sql = str_replace(array("\r\n", "\r", "\n"), ' ', $sql);
  $rt = [];
  if ($result = $mysqli->query($sql)) {
    $rt['result'] = true;
    $rt['affected_rows'] = $mysqli->affected_rows;
  }
  else{
    $rt['result'] = false;
    $rt['error_no'] = $mysqli->connect_errno;
    $rt['error'] = $mysqli->connect_error;
  }
  if($returnSql) $rt['sql'] = $sql;
  return($rt);
  // echo json_encode($rt, JSON_UNESCAPED_UNICODE);
}
// 汎用マルチ修正モジュール
// update insert delete 複数sqlに対応
// 戻り値でそれぞれの結果と実施した数を返す。
// エラーが発生したらその分のsql文を返す
function unvMultiEdit($mysqli, $sql){
  global $returnSql;
  $sqla = explode(';', $sql);
  $rt = [];
  $rt['resulttrue'] = 0;
  $rt['resultfalse'] = 0;
  $rt['errsql'] = [];
  $i = 0;
  if ($mysqli->multi_query($sql)) {
    do {
      /* 最初の結果セットを格納します */
      // if ($result = $mysqli->store_result()) {
      if (!$mysqli->errno){
        $rt['resulttrue']++;
      }
      else{
        $rt['resultfalse']++;
        array_push($rt['errsql'], $sqla[$i]);
      }
      $i++;
    } while ($mysqli->next_result());
  }
  $rt['count'] = $i;
  if($returnSql) $rt['sql'] = $sql;
  return($rt);
}
function main(){
  $mysqli = connectDb();
  $sql = "
    delete FROM `ahdSomeState` 
    WHERE timestamp < (now() - INTERVAL 2 HOUR - INTERVAL keep DAY);
    delete FROM `ahdAnyState` 
    WHERE timestamp < (now() - INTERVAL 2 HOUR - INTERVAL keep DAY);
    delete from ahdsenddt 
    WHERE gen < (now() - INTERVAL 4 HOUR);
  ";
  $rt = unvMultiEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
}
main();
?>