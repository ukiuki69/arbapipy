<?php
error_reporting(E_ALL);
header("Access-Control-Allow-Origin: *");
header('Access-Control-Allow-Headers: Content-Type');
header("Access-Control-Allow-Methods: POST, GET");
define("SLEEP", 0); // テスト用遅延時間

$self = basename(__FILE__);

if ($self === 'api.php') {
  // 本番用
  $allowGet = false;
  $returnSql = false;
  define("DBNAME", "albatross56_sv1"); // 本番用
  $fscgi = 'fs_cgi.py'; // 本番用
} else if ($self === 'apidev.php') {
  // テスト用
  $allowGet = true;
  $returnSql = true;
  define("DBNAME", "albatross56_albtest"); // テスト用
  // define ("DBNAME", "albatross56_sandbox"); // サンドボックス
  $fscgi = 'y_fs_cgi.py'; // 運用テスト用
} else {
  // テスト用
  $allowGet = true;
  $returnSql = true;
  // define ("DBNAME", "albatross56_sandbox"); // サンドボックス
  define("DBNAME", "albatross56_albtest"); // テスト用
  $fscgi = 'y_fs_cgi.py'; // 運用テスト用
}
if ($self === 'apixfg.php') {
  $returnSql = false;
}


define("DBNAMEREAL", "albatross56_sv1"); // 本番用

// define ("DBNAME", "albatross56_sandbox"); // サンドボックス

require './cipher.php';

$phpnow = date("Y-m-d H:i:s");
$MAX_KEYS = 5;
$secret = 'fqgjlAAS4Mjb9lYUJPRBfV6hVLZHab'; // 秘匿性の高いAPIで利用

$resetKeyLimit = 72 * 60 * 60;
$cgiAdrress = 'https://rbatosdata.com';


// キャラクターをエスケープする
function escapeChar($str)
{
  $cnv = str_replace(["\r\n", "\r", "\n", "\\n"], "<br>", $str);
  $cnv = str_replace(["%0A"], "<br>", $str);
  return $cnv;
}

function recursiveMerge(array $array1, array $array2, int $limit = 2, int $currentDepth = 1)
{
    foreach ($array2 as $key => $value) {
        // まだ再帰可能かつ両方配列なら再帰を継続
        if ($currentDepth < $limit
            && array_key_exists($key, $array1)
            && is_array($array1[$key])
            && is_array($value)
        ) {
            $array1[$key] = recursiveMerge(
                $array1[$key],
                $value,
                $limit,
                $currentDepth + 1
            );
        } else {
            // それ以外は上書きする
            $array1[$key] = $value;
        }
    }
    return $array1;
}
// 使用状況により切り替えるため
function PRMS($key, $prmsAllowGet = false)
{
  global $allowGet;
  // return($_REQUEST[$key]);
  // return($_POST[$key]);
  // $_REQUESTはクッキーまで拾うのでめんどくさい
  $localAllowGet = $allowGet;
  if ($prmsAllowGet) {
    $localAllowGet = $prmsAllowGet;
  }
  if (count($_GET) && $localAllowGet) {
    if (array_key_exists($key, $_GET)) {
      return ($_GET[$key]);
    } else return ('');
  } else {
    if (array_key_exists($key, $_POST)) {
      return ($_POST[$key]);
    } else return ('');
  }
}

function PRMS_ARRAY()
{
  // return($_REQUEST);
  // return($_POST);
  return ($_GET);
}
// 常に本番DBに接続
function connectDbPrd()
{
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
  } else {
    $mysqli->set_charset("utf8");
  }
  return ($mysqli);
}


function connectDb()
{
  if (PRMS('realdb')) {
    $dbname = DBNAMEREAL;
  } else {
    $dbname = DBNAME;
  }
  $mysqli = new mysqli(
    'mysql10077.xserver.jp',
    'albatross56_ysmr',
    'kteMpg5D',
    // 'albatross56_sv1'
    $dbname
  );
  if ($mysqli->connect_error) {
    $rt['result'] = 'false';
    // $rt['user'] = $dbid;
    $rt['msg'] = $mysqli->connect_error;
    echo json_encode($rt, JSON_UNESCAPED_UNICODE);
    exit();
  } else {
    // $mysqli->set_charset("utf8");
    $mysqli->set_charset("utf8mb4");
  }

  return ($mysqli);
}

// 汎用リストモジュール
function unvList($mysqli, $sql)
{
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
  } else {
    $rt['result'] = false;
  }
  if ($returnSql) $rt['sql'] = $sql;
  return ($rt);
  // echo json_encode($rt, JSON_UNESCAPED_UNICODE);
}

// 汎用カウントモジュール
function unvCnt($mysqli, $sql)
{
  global $returnSql;
  $sql = str_replace(array("\r\n", "\r", "\n"), ' ', $sql);
  $rt = [];
  if ($result = $mysqli->query($sql)) {
    $rt['result'] = true;
    $rt['count'] = $result->num_rows;
  } else {
    $rt['result'] = false;
  }
  if ($returnSql) $rt['sql'] = $sql;
  return ($rt);
  // echo json_encode($rt, JSON_UNESCAPED_UNICODE);
}

// 汎用修正モジュール
// update insert delete
function unvEdit($mysqli, $sql, $rtn = false)
{
  global $returnSql;
  $sql = str_replace(array("\r\n", "\r", "\n"), ' ', $sql);
  $rt = [];
  if ($result = $mysqli->query($sql)) {
    $rt['result'] = true;
    $rt['affected_rows'] = $mysqli->affected_rows;
  } else {
    $rt['result'] = false;
    $rt['error_no'] = $mysqli->connect_errno;
    $rt['error'] = $mysqli->connect_error;
  }

  if ($returnSql) $rt['sql'] = $sql;
  return ($rt);
  // echo json_encode($rt, JSON_UNESCAPED_UNICODE);
}
// 汎用マルチ修正モジュール
// update insert delete 複数sqlに対応
// 戻り値でそれぞれの結果と実施した数を返す。
// エラーが発生したらその分のsql文を返す
function unvMultiEdit($mysqli, $sql)
{
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
      if (!$mysqli->errno) {
        $rt['resulttrue']++;
      } else {
        $rt['resultfalse']++;
        array_push($rt['errsql'], $sqla[$i]);
      }
      $i++;
    } while ($mysqli->next_result());
  }
  $rt['count'] = $i;
  if ($returnSql) $rt['sql'] = $sql;
  return ($rt);
}

// brunchに日付の項目を追加したのでユーザーデータが複数回帰ってくる。
// それを抑制した。2021/08/09
function listUsers()
{
  $typeA = ['障害児', '重症心身障害児'];
  $serviceA = ['放課後等デイサービス', '児童発達支援'];

  $mysqli = connectDb();
  if (array_key_exists('limit', $_REQUEST)) {
    $limit = ' limit 0,' . PRMS('limit');
  } else {
    $limit = '';
  }
  if (array_key_exists('type', $_REQUEST)) {
    $str = $typeA[(int) PRMS('type')];
    $wtype = " user.type = '$str'";
  } else {
    $wtype = 'true ';
  }
  if (array_key_exists('service', $_REQUEST)) {
    $str = $serviceA[(int) PRMS('service')];
    $wservice = " user.service = '$str'";
  } else {
    $wservice = ' true';
  }
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $sql = "
    select user.*, 
      uext.ext, 
      brunch.bname, brunch.sbname, brunch.jino,  
      com.hname, com.shname
    from ahduser as user 
    join ahdbrunch as brunch using (hid, bid)
    join ahdcompany as com using (hid)
    join (
      SELECT MAX(date) mdate, date, uid,bid,hid FROM `ahduser` 
      where date <= '$date' GROUP BY uid,hid,bid    
    )
    as lastupdated 
    on lastupdated.uid = user.uid
    and lastupdated.bid = user.bid
    and lastupdated.hid = user.hid
    and lastupdated.mdate = user.date
    left join ahdusersext as uext 
    on lastupdated.uid = uext.uid
    and lastupdated.bid = uext.bid
    and lastupdated.hid = uext.hid
    where
    user.hid = '$hid' AND
    user.bid = '$bid' AND  
    (
      user.enddate = '0000-00-00' OR
      user.enddate >= '$date'
    ) 
    and user.date <= '$date'
    and $wtype 
    and $wservice
    and brunch.date = (
      SELECT MAX(date) from ahdbrunch 
      WHERE bid='$bid' and hid='$hid' and date<='$date'
    )
    $limit;
  ";
  $rt = unvList($mysqli, $sql);
  // $rt['dt'][0]['etc'] = json_decode($rt['dt'][0]['etc']);
  foreach ($rt['dt'] as &$val) {
    $val['etc'] = json_decode($val['etc']);
  }
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}
function fetchPartOfData()
{
  // ==== パラメータ取得 ====
  $table        = PRMS('table');         // 例: "ahdschedule"
  $tableKeysJson = PRMS('tableKeys');    // 例: '{"hid":"0khROPje","bid":"MwV6HuPm","date":"2023-04-01"}'
  $column       = PRMS('column');        // 例: "schedule"
  $jsonKeysJson = PRMS('jsonKeys');      // 例: '["UID19"]'

  // ==== JSONを配列やリストに変換 ====
  $tableKeys = json_decode($tableKeysJson, true);
  if (!is_array($tableKeys)) {
    // tableKeys が不正な場合
    $rt = [
      'result' => false,
      'error' => 'tableKeys が不正なJSONです。'
    ];
    echo json_encode($rt, JSON_UNESCAPED_UNICODE);
    return;
  }

  $jsonKeys = json_decode($jsonKeysJson, true);
  if (!is_array($jsonKeys)) {
    // jsonKeys が不正な場合
    $rt = [
      'result' => false,
      'error' => 'jsonKeys が不正なJSONです。'
    ];
    echo json_encode($rt, JSON_UNESCAPED_UNICODE);
    return;
  }

  // ==== DB接続 ====
  $mysqli = connectDb();

  // ==== timestamp列の存在チェック ====
  $hasTimestamp = false;
  $colCheckSql = "SHOW COLUMNS FROM `{$table}`";
  if ($resCol = $mysqli->query($colCheckSql)) {
    while ($rowCol = $resCol->fetch_assoc()) {
      if ($rowCol['Field'] === 'timestamp') {
        $hasTimestamp = true;
        break;
      }
    }
  }

  // ==== WHERE句作成 ====
  $whereArray = [];
  foreach ($tableKeys as $k => $v) {
    $escVal = $mysqli->real_escape_string($v);
    $whereArray[] = "`{$k}` = '{$escVal}'";
  }
  $whereStr = implode(' AND ', $whereArray);

  // ==== SELECT句 ====
  // 必要なカラムは JSONカラムのみでOKだが、エラー確認用に
  // hid,bid,date なども取ってもいい。しかし今回は不要なので必要最小限にします
  // ※複数取っても後で捨てるなら問題ありませんが、ここではシンプルに。
  $selectCols = "`{$column}`";
  if ($hasTimestamp) {
    // timestamp もある場合だけ取る（が今回は使用しない）
    $selectCols .= ", UNIX_TIMESTAMP(`timestamp`) * 1000 AS rowTimestamp";
  }

  $sql = "
    SELECT {$selectCols}
    FROM `{$table}`
    WHERE {$whereStr}
  ";

  // ==== クエリ実行 ====
  $rt = unvList($mysqli, $sql);
  if (!$rt['result']) {
    // SQLエラー
    $rt['error'] = 'SQLエラーが発生しました。';
    echo json_encode($rt, JSON_UNESCAPED_UNICODE);
    $mysqli->close();
    return;
  }

  // ==== レコード件数チェック ====
  $count = count($rt['dt']);
  if ($count === 0) {
    // 該当なし
    $rt['result'] = false;
    $rt['error'] = '該当レコードがありません。';
    echo json_encode($rt, JSON_UNESCAPED_UNICODE);
    $mysqli->close();
    return;
  }
  if ($count > 1) {
    // 複数あり -> エラー
    $rt['result'] = false;
    $rt['error'] = '複数のレコードが見つかったため特定できません。';
    echo json_encode($rt, JSON_UNESCAPED_UNICODE);
    $mysqli->close();
    return;
  }

  // ==== 1件のレコードを処理 ====
  $row = $rt['dt'][0];
  $jsonStr = $row[$column];

  // '[]' を '{}' に置換
  if ($jsonStr === '[]') {
    $jsonStr = '{}';
  }

  // JSONデコード
  $decodedJson = json_decode($jsonStr, true);
  if (!is_array($decodedJson)) {
    // JSON不正の場合は空オブジェクトに
    $decodedJson = [];
  }

  // ==== jsonKeys で指定された階層を辿る ====
  $filteredData = $decodedJson;
  foreach ($jsonKeys as $jk) {
    if (isset($filteredData[$jk])) {
      $filteredData = $filteredData[$jk];
    } else {
      // 存在しないキーなら空オブジェクトを返す
      $filteredData = new stdClass();
      break;
    }
  }

  // ここで $rt は不要なカラムを含むため、最低限の構造にリセットして返す。
  // 必要であれば 'result' はそのまま保持し、 'dt' だけ作り直す。
  $resultValue = true; // ここまで来れば結果はtrue
  $rt = [
    'result' => $resultValue,
    'dt' => [
      [
        // filteredData のみ返却
        'filteredData' => $filteredData,
      ]
    ],
  ];

  // ==== JSONとして返却 ====
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function sendPartOfDataWithKeys()
{
  $table = PRMS('table');
  $column = PRMS('column');
  $partOfData = PRMS('partOfData');
  
  // 従来のパラメータも取得
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  
  // tableKeysパラメータの取得と処理
  $tableKeysJson = PRMS('tableKeys');
  $tableKeys = null;
  
  if (!empty($tableKeysJson)) {
    $tableKeys = json_decode($tableKeysJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      echo '{"result":false, "error": "Invalid tableKeys JSON format"}';
      return false;
    }
  } else {
    // 従来方式のパラメータを使用
    $tableKeys = [
      'hid' => $hid,
      'bid' => $bid,
      'date' => $date
    ];
  }

  $mysqli = connectDb();
  $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

  try {
    $mysqli->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);

    // WHERE句とバインドパラメータの動的構築
    $whereClause = [];
    $bindTypes = "";
    $bindValues = [];

    foreach ($tableKeys as $key => $value) {
      $whereClause[] = "`$key` = ?";
      $bindTypes .= "s"; // 全て文字列として扱う
      $bindValues[] = $value;
    }

    $whereStr = implode(" AND ", $whereClause);

    // テーブル名とカラム名をバッククォートで囲む
    $sql = "
          SELECT `$column` FROM `$table`
          WHERE $whereStr
          FOR UPDATE
      ";

    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
      echo '{"result":false, "error": "SQL Prepare failed: ' . $mysqli->error . '"}';
      return false;
    }

    // 動的にバインドパラメータを設定
    if (!empty($bindValues)) {
      $bindParams = array_merge([$bindTypes], $bindValues);
      $bindParamsReferences = [];
      foreach ($bindParams as $key => $value) {
        $bindParamsReferences[$key] = &$bindParams[$key];
      }
      call_user_func_array([$stmt, 'bind_param'], $bindParamsReferences);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $existingData = $result->fetch_assoc()[$column] ?? null;

    if (!$existingData) {
      echo '{"result":false, "error": "Exist data not found."}';
      $mysqli->rollback();
      $stmt->close();
      $mysqli->close();
      return false;
    }

    // JSON デコードとエラーチェック
    $existingData = json_decode($existingData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      error_log("JSON Decode Error (existingData): " . json_last_error_msg());
      echo '{"result":false, "error": "Invalid existingData JSON"}';
      $mysqli->rollback();
      $stmt->close();
      $mysqli->close();
      return false;
    }

    $partialData = json_decode($partOfData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      error_log("JSON Decode Error (partOfData): " . json_last_error_msg());
      echo '{"result":false, "error": "Invalid partOfData JSON"}';
      $mysqli->rollback();
      $stmt->close();
      $mysqli->close();
      return false;
    }

    // データをマージ
    $mergedData = recursiveMerge($existingData, $partialData);

    // JSON エンコードとエラーチェック
    $finalJson = json_encode($mergedData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (json_last_error() !== JSON_ERROR_NONE) {
      error_log("JSON Encode Error: " . json_last_error_msg());
      echo '{"result":false, "error": "JSON encoding failed"}';
      $mysqli->rollback();
      $stmt->close();
      $mysqli->close();
      return false;
    }

    // INSERTクエリの動的構築
    $columns = array_keys($tableKeys);
    $columns[] = $column;
    
    $placeholders = array_fill(0, count($tableKeys), "?");
    $placeholders[] = "?";
    
    $updateParts = [];
    foreach ($columns as $col) {
      $updateParts[] = "`$col` = VALUES(`$col`)";
    }
    
    $columnsStr = "`" . implode("`, `", $columns) . "`";
    $placeholdersStr = implode(", ", $placeholders);
    $updateStr = implode(", ", $updateParts);

    // プリペアドステートメントで安全な INSERT 文
    $updateStmt = $mysqli->prepare("
          INSERT INTO `$table` ($columnsStr)
          VALUES ($placeholdersStr)
          ON DUPLICATE KEY UPDATE $updateStr
      ");
    
    if (!$updateStmt) {
      echo '{"result":false, "error": "SQL Prepare failed (update): ' . $mysqli->error . '"}';
      return false;
    }

    // UPDATE用のバインドパラメータ
    $updateBindTypes = str_repeat("s", count($tableKeys) + 1);
    $updateBindValues = array_values($tableKeys);
    $updateBindValues[] = $finalJson;
    
    // 動的にバインドパラメータを設定
    $updateBindParams = array_merge([$updateBindTypes], $updateBindValues);
    $updateBindParamsReferences = [];
    foreach ($updateBindParams as $key => $value) {
      $updateBindParamsReferences[$key] = &$updateBindParams[$key];
    }
    call_user_func_array([$updateStmt, 'bind_param'], $updateBindParamsReferences);
    
    $updateResult = $updateStmt->execute();

    if ($updateResult) {
      $mysqli->commit();
      echo '{"result":true}';
    } else {
      error_log("SQL Error (update): " . $updateStmt->error);
      $mysqli->rollback();
      echo '{"result":false, "error": "Database update failed"}';
    }

    $stmt->close();
    $updateStmt->close();
    $mysqli->close();
  } catch (Exception $e) {
    error_log("Transaction failed: " . $e->getMessage());
    $mysqli->rollback();
    $mysqli->close();
    echo '{"result":false, "error": "Transaction failed"}';
  }
}

function deletePartOfData()
{
  $table = PRMS('table');
  $column = PRMS('column');
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $keyToDelete = json_decode(PRMS('keyToDelete'), true); // JSON デコード

  if (json_last_error() !== JSON_ERROR_NONE) {
    echo '{"result":false, "error": "Invalid keyToDelete format"}';
    return false;
  }

  $mysqli = connectDb();
  $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

  try {
    $mysqli->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);

    // SELECT 文で現在のデータを取得
    $sql = "
          SELECT `$column` FROM `$table`
          WHERE hid = ? AND bid = ? AND date = ?
          FOR UPDATE
      ";
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
      echo '{"result":false, "error": "SQL Prepare failed: ' . $mysqli->error . '"}';
      return false;
    }

    $stmt->bind_param("sss", $hid, $bid, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $existingData = $result->fetch_assoc()[$column] ?? null;

    if (!$existingData) {
      echo '{"result":false, "message": "No data found for the specified record."}';
      $mysqli->rollback();
      $stmt->close();
      $mysqli->close();
      return false;
    }

    $existingData = json_decode($existingData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      error_log("JSON Decode Error: " . json_last_error_msg());
      echo '{"result":false, "error": "Invalid existingData JSON"}';
      $mysqli->rollback();
      $stmt->close();
      $mysqli->close();
      return false;
    }

    // JSON 内のキーを削除
    $current = &$existingData;
    $lastKey = array_pop($keyToDelete); // 削除対象の最終キー
    foreach ($keyToDelete as $key) {
      if (!isset($current[$key]) || !is_array($current[$key])) {
        echo '{"result":false, "message": "No matching key found"}';
        $mysqli->rollback();
        $stmt->close();
        $mysqli->close();
        return false;
      }
      $current = &$current[$key];
    }

    // 最終キーを削除
    if (isset($current[$lastKey])) {
      $deletedValue = $current[$lastKey];
      unset($current[$lastKey]);

      // 空の配列やオブジェクトを削除
      $current = array_filter($current, function ($value) {
        return !empty($value);
      });
    } else {
      echo '{"result":false, "message": "No matching key found"}';
      $mysqli->rollback();
      $stmt->close();
      $mysqli->close();
      return false;
    }

    // 更新後のデータを JSON にエンコード
    $finalJson = json_encode($existingData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (json_last_error() !== JSON_ERROR_NONE) {
      error_log("JSON Encode Error: " . json_last_error_msg());
      echo '{"result":false, "error": "JSON encoding failed"}';
      $mysqli->rollback();
      $stmt->close();
      $mysqli->close();
      return false;
    }

    // UPDATE 文でデータを更新
    $updateStmt = $mysqli->prepare("
          UPDATE `$table`
          SET `$column` = ?
          WHERE hid = ? AND bid = ? AND date = ?
      ");
    if (!$updateStmt) {
      echo '{"result":false, "error": "SQL Prepare failed (update): ' . $mysqli->error . '"}';
      return false;
    }

    $updateStmt->bind_param("ssss", $finalJson, $hid, $bid, $date);
    $updateResult = $updateStmt->execute();

    if ($updateResult) {
      $mysqli->commit();
      echo json_encode([
        "result" => true,
        "deleted" => ["key" => array_merge($keyToDelete, [$lastKey]), "value" => $deletedValue]
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
      error_log("SQL Error (update): " . $updateStmt->error);
      $mysqli->rollback();
      echo '{"result":false, "error": "Database update failed"}';
    }

    $stmt->close();
    $updateStmt->close();
    $mysqli->close();
  } catch (Exception $e) {
    error_log("Transaction failed: " . $e->getMessage());
    $mysqli->rollback();
    $mysqli->close();
    echo '{"result":false, "error": "Transaction failed"}';
  }
}



function nothing()
{
  $rt['msg'] = 'function not found.';
  $rt['request'] = $_REQUEST;
  $rt['post'] = $_POST;
  $rt['get'] = $_GET;
  $rt['headers'] = apache_request_headers();
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
}




$m = PRMS('a', true);
if ($m == 'zip')  zip();

else if ($m == 'listusers')           listUsers();
else nothing();
