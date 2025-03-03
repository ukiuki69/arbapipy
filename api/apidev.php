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

function zip()
{
  $postal = PRMS('postal', true);
  $url = "https://zipcloud.ibsnet.co.jp/api/search?zipcode=" . $postal;
  $json = file_get_contents($url);
  $json = mb_convert_encoding($json, 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
  $ary = json_decode($json, true);
  if (
    is_null($ary['message']) && is_null($ary['results'])
    && $ary['status'] == 200
  ) {
    $subp = substr($postal, 0, 3) . '0001';
    $url = "https://zipcloud.ibsnet.co.jp/api/search?zipcode=" . $subp;
    $json = file_get_contents($url);
    $json = mb_convert_encoding($json, 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
    $ary = json_decode($json, true);
    $ary['submsg'] = '指定の郵便番号が見つからないので近くの番号で検索しました。';
    $ary['subp'] = $subp;
    $json = json_encode($ary, JSON_UNESCAPED_UNICODE);
  }
  echo $json;
}

function extData()
{
  $url = PRMS('url', true);
  $r = file_get_contents($url);
  echo $r;
}

// エクセルジェネレータ
// vpsのpythonをCGIとして起動する
// python側でdbからドキュメントのデータを拾って
// エクセルのファイルを作成する
function excelgen()
{
  global $cgiAdrress;
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $stamp = PRMS('stamp');
  $prefix = PRMS('prefix');
  // プレフィックスがある場合は、ファイル名に追加
  $pythonScript = $prefix ? $prefix . "excelgen.py" : "excelgen.py";
  $url = $cgiAdrress . "/py/" . $pythonScript . "?hid=$hid&bid=$bid&stamp=$stamp";
  // $url = $cgiAdrress . "/py/excelgen.py?hid=$hid&bid=$bid&stamp=$stamp";
  $url = htmlspecialchars_decode($url);
  // 2021/10/01追加 突然動かなくなった
  // https://qiita.com/izanari/items/f4f96e11a2b01af72846
  $options['ssl']['verify_peer'] = false;
  $options['ssl']['verify_peer_name'] = false;
  $json = file_get_contents($url, false, stream_context_create($options));
  echo $json;
}

function fsConCnvExcel()
{
  global $cgiAdrress, $fscgi;
  $jino = PRMS('jino');
  $mail = PRMS('mail');
  $date = PRMS('date');
  $a = 'cnvexcel';
  $url = $cgiAdrress . "/cgi/$fscgi?a=$a&jino=$jino&mail=$mail&date=$date";
  $url = htmlspecialchars_decode($url);
  $options['ssl']['verify_peer'] = false;
  $options['ssl']['verify_peer_name'] = false;
  // echo $url;
  $json = file_get_contents($url, false, stream_context_create($options));
  echo $json;
}

// csvジェネレータ
// vpsのpythonをCGIとして起動する
// python側でdbからドキュメントのデータを拾って
// csvのファイルを作成する
function csvgen()
{
  global $cgiAdrress;
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $rnddir = PRMS('rnddir');
  $prefix = PRMS('prefix');
  $url =
    $cgiAdrress . "/py/csvgen.py?" .
    "hid=$hid&bid=$bid&date=$date&rnddir=$rnddir&prefix=$prefix";
  $url = htmlspecialchars_decode($url);

  // 2021/10/01追加 突然動かなくなった
  // https://qiita.com/izanari/items/f4f96e11a2b01af72846
  $options['ssl']['verify_peer'] = false;
  $options['ssl']['verify_peer_name'] = false;
  $json = file_get_contents($url, false, stream_context_create($options));
  echo $json;
}

// genFKdatasのラッパー
function genFKdatas()
{
  global $cgiAdrress;
  $jino = PRMS('jino');
  $date = PRMS('date');
  $rnddir = PRMS('rnddir');
  $prefix = PRMS('prefix');
  $format = PRMS('format');
  $encode = PRMS('encode');
  $target = PRMS('target');
  $item = PRMS('item');
  $url =
    $cgiAdrress . "/py/genFKdatas.py?" .
    "jino=$jino&date=$date&item=$item&rnddir=$rnddir&prefix=$prefix" .
    "&format=$format&encode=$encode&target=$target";
  $url = htmlspecialchars_decode($url);

  $options['ssl']['verify_peer'] = false;
  $options['ssl']['verify_peer_name'] = false;
  $json = file_get_contents($url, false, stream_context_create($options));
  echo $json;
}



// 複数レコード対応用
function companybrunchM()
{
  $mysqli = connectDb();
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');

  // com.confirmPayment,
  $sql = "
    select brunch.*, 
    com.hname, com.shname, com.postal cpostal, com.city ccity,
    com.address caddress, com.tel ctel, com.fax cfax, com.etc cetc,
    com.yaku daihyouyakusyoku, com.daihyou daihyou,
    bext.ext
    from ahdbrunch brunch 
    join ahdcompany com
    using (hid)
    left join ahdbrunchext as bext
    on brunch.hid = bext.hid
    and brunch.bid = bext.bid
    where brunch.hid = '$hid'
    and brunch.bid = '$bid'
    and brunch.date = (
      SELECT MAX(date) from ahdbrunch 
      WHERE bid='$bid' and hid='$hid' and date<='$date'
    )
    ;
  ";
  $rt = unvList($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function companyAndBrunch()
{
  $mysqli = connectDb();
  $hid = PRMS('hid');
  $bid = PRMS('bid');

  $sql = "
    select brunch.*, 
    com.hname, com.shname, com.postal cpostal, com.city ccity,
    com.address caddress, com.tel ctel, com.fax cfax, com.etc cetc
    from ahdbrunch brunch 
    join ahdcompany com
    using (hid)
    where brunch.hid = '$hid'
    and brunch.bid = '$bid'
    ;
  ";
  $rt = unvList($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
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
// 廃止予定 sendBrunchに統合
function sendAddictionOfBrunch()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $addiction = PRMS('addiction');
  if ($addiction == '') {
    $adcStr = 'NULL';
  } else {
    $adcStr = "'" . $addiction . "'";
  }
  $mysqli = connectDb();
  $sql = "
    update ahdbrunch
    set addiction = $adcStr
    where hid = '$hid'
    and bid = '$bid';
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function fetchAddictionOfBrunch()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $sql = "
    select hid,bid,addiction 
    from ahdbrunch
    where hid = '$hid'
    and bid = '$bid'
  ";
  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql);
  if (count($rt['dt'])) {
    $rt['dt'][0]['addiction'] = json_decode($rt['dt'][0]['addiction']);
  }
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}



function sendCalender()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $dateList = PRMS('dateList');
  $mysqli = connectDb();
  $sql = "
    insert into ahdcalender (hid,bid,date,dateList)
    values ('$hid','$bid','$date','$dateList')
    on duplicate key update
    dateList = '$dateList'
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function fetchCalender()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $sql = "
    select hid,bid,date, dateList 
    from ahdcalender
    where hid = '$hid'
    and bid = '$bid'
    and date = '$date'
  ";
  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql);
  if (count($rt['dt'])) {
    $rt['dt'][0]['dateList'] = json_decode($rt['dt'][0]['dateList']);
  }
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function sendWorkshift()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $workshift = PRMS('workshift');
  $mysqli = connectDb();
  $sql = "
    insert into ahdworkshift (hid,bid,date,workshift)
    values ('$hid','$bid','$date','$workshift')
    on duplicate key update
    workshift = '$workshift'
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}


function sendUsersSchedule()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $uid = PRMS('uid'); // UIDxx形式
  $schedule = PRMS('schedule');

  $mysqli = connectDb();
  $mysqli->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
  $sql1 = "
    select schedule from ahdschedule 
    where hid='$hid'
    and bid='$bid'
    and date='$date'
    FOR UPDATE;
  ";
  // // $schedule = '{"D20210701":{"end":"18:30","start":"13:40","service":"放課後等デイサービス","transfer":["自宅","自宅"],"offSchool":0,"actualCost":{"おやつ":100}},"D20210703":{"end":"17:00","start":"13:30","service":"放課後等デイサービス","transfer":["学校","自宅"],"offSchool":0,"actualCost":{"おやつ":100}}}';
  // echo ('preSch<br>');
  $rt = unvList($mysqli, $sql1);
  // var_dump($rt);
  $preSch = $rt['dt'][0]['schedule'];
  // var_dump($preSch);
  // echo('<br><br>');
  $preSch = json_decode($preSch, true);
  // var_dump($preSch);

  // echo('<br><br>');
  // echo ('recieved<br>');
  $usersSch = json_decode($schedule, true);
  $sch = [];
  $sch[$uid] = $usersSch;
  // var_dump($sch);
  // echo('<br><br>');
  // echo ('merged<br>');
  if (is_array($preSch) && is_array($usersSch)) {
    $merged = array_merge($preSch, $sch);
  } else {
    $errRt['result'] = false;
    $errRt['usersSch'] = $usersSch;
    $errRt['preSch'] = $preSch;
    echo json_encode($errRt, JSON_UNESCAPED_UNICODE);
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }

  // var_dump($merged);
  // echo('<br><br>');
  // echo ('final json<br>');
  // $finalJson = jsonEncodeCharEscape($merged);
  $finalJson = json_encode($merged, JSON_UNESCAPED_UNICODE);

  // $newSch = array_merge($preSch, $sch);
  // $newSchJson = json_encode($newSch, JSON_UNESCAPED_SLASHES);
  // sleep(SLEEP);

  $sql = "
    insert into ahdschedule (hid,bid,date,schedule)
    values ('$hid','$bid','$date','$finalJson')
    on duplicate key update
    schedule = '$finalJson'
  ";

  $rt = unvEdit($mysqli, $sql);
  if ($rt['result']) {
    $mysqli->commit();
  } else {
    $mysqli->rollback();
  }
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}
// キーが与えられたスケジュール項目だけ
function sendPartOfSchedule()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $partOfSch = PRMS('partOfSch');
  $mysqli = connectDb();
  $mysqli->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
  $sql = "
    select schedule from ahdschedule 
    where hid='$hid'
    and bid='$bid'
    and date='$date'
    FOR UPDATE;
  ";
  $rt = unvList($mysqli, $sql);
  $preSch = $rt['dt'][0]['schedule'];
  if (!$preSch) {
    echo '{"result":false, "error": "not exist predata"}';
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }
  $preSch = json_decode($preSch, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    echo '{"result":false, "error": "Invalid preSch JSON"}';
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }

  $partOfSch = json_decode($partOfSch, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    echo '{"result":false, "error": "Invalid partOfSch JSON"}';
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }

  // ロック確認のために待機
  // sleep(SLEEP);
  // マージする配列があるかどうか確認
  if (is_array($preSch) && is_array($partOfSch)) {
    $merged = array_merge($preSch, $partOfSch);
  } else {
    echo '{"result":false}';
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }
  // var_dump($merged);
  // echo('<br><br>');
  // echo ('final json<br>');
  $finalJson = json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $finalJson = escapeChar($finalJson);
  $finalJson = mysqli_real_escape_string($mysqli, $finalJson);

  // var_dump($finalJson);

  // $newSch = array_merge($preSch, $sch);
  // $newSchJson = json_encode($newSch, JSON_UNESCAPED_SLASHES);
  $sql = "
    insert into ahdschedule (hid,bid,date,schedule)
    values ('$hid','$bid','$date','$finalJson')
    on duplicate key update
    schedule = '$finalJson'
  ";

  $rt = unvEdit($mysqli, $sql);
  if ($rt['result']) {
    $mysqli->commit();
  } else {
    $mysqli->rollback();
  }
  $mysqli->close();
  echo json_encode($rt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}



// Keyが与えられた項目 UIDxxx等でcontactを更新する。
// hid, bidは上6桁のマッチでokとするが該当する事業所内にメールアドレスが
// 存在しない場合はエラーとする

function sendPartOfContact()
{
  global $returnSql;
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $token = PRMS('token');
  $date = PRMS('date');
  $sqla = [];
  $partOfContact = escapeChar(PRMS('partOfContact'));
  $mysqli = connectDb();
  $mysqli->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
  $sql = "
    select hid, bid from ahduser
    where hid like '$hid%'
    and bid like '$bid%'
    and '$token' like concat('%', faptoken, '%') and faptoken != '';
  ";
  // 短いhid,bidが設定されているかどうか
  $shortHidBid = ((strlen($hid) < 8) || (strlen($bid) < 8));
  if ($shortHidBid) {
    $rt = unvList($mysqli, $sql);
    if ($returnSql) $sqla[] = $sql;
    if (!count($rt['dt'])) {
      $errobj = [
        'result' => false,
        'msg' => "mail not found",
        'sqla' => $sqla
      ];
      echo json_encode($errobj, JSON_UNESCAPED_UNICODE);
      $mysqli->rollback();
      $mysqli->close();
      return false;
    }
    $preSearch = $rt['dt'][0];
    // 6桁で来ることもあるので取り直しを行う
    $hid = $preSearch['hid'];
    $bid = $preSearch['bid'];
  }
  $state = $mysqli->real_escape_string(PRMS('partOfContact'));
  $keep = 4;
  $item = 'sendpartofcontent_log';
  $sql = "
    insert into ahdLog (hid,bid,date,state,item,keep)
    values ('$hid','$bid','$date,','$state','$item','$keep')
    on duplicate key update
    state = '$state', keep = '$keep', timestamp = CURRENT_TIMESTAMP
  ";
  $sendLogRt = unvEdit($mysqli, $sql);

  $sql = "
    select contacts from ahdcontacts 
    where hid='$hid'
    and bid='$bid'
    and date='$date'
    FOR UPDATE;
  ";
  $rt = unvList($mysqli, $sql);
  if (count($rt['dt'])) {
    $preCon = $rt['dt'][0]['contacts'];
  } else {
    $preCon = '{}'; // 検索できなかったらエラーにせずに空白オブジェクト
  }
  if ($returnSql) $sqla[] = $sql;
  // $preCon = json_decode(escapeChar($preCon), true);

  // if ($preCon == NULL)  $preCon = [];
  // $partOfContact = json_decode($partOfContact, true);

  // 事前に存在するjsonとパラメータから与えられたjsonのチェックを追加
  $preCon = json_decode(escapeChar($preCon), true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    $errobj = [
      'result' => false,
      'msg' => "Invalid contacts on DB JSON " . json_last_error_msg(),
      'sendLogRt' => $sendLogRt,
      'sqla' => $sqla
    ];
    echo json_encode($errobj, JSON_UNESCAPED_UNICODE);
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }

  $partOfContact = json_decode($partOfContact, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    $errobj = [
      'result' => false,
      'msg' => "Invalid partOfContact JSON " . json_last_error_msg(),
      'sendLogRt' => $sendLogRt,
      'sqla' => $sqla
    ];
    echo json_encode($errobj, JSON_UNESCAPED_UNICODE);
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }

  // マージする配列があるかどうか確認
  if (is_array($preCon) && is_array($partOfContact)) {
    $merged = array_merge($preCon, $partOfContact);
  } else {
    $errobj = [
      'result' => false,
      'msg' => "array marge error",
      'sqla' => $sqla,
      'preCon' => $preCon,
      'sendLogRt' => $sendLogRt,
      'partOfContact' => $partOfContact,

    ];
    echo json_encode($errobj, JSON_UNESCAPED_UNICODE);
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }

  $finalJson = json_encode($merged, JSON_UNESCAPED_UNICODE);
  $finalJson = escapeChar($finalJson);
  $finalJson = mysqli_real_escape_string($mysqli, $finalJson);


  // $newSch = array_merge($preSch, $sch);
  // $newSchJson = json_encode($newSch, JSON_UNESCAPED_SLASHES);
  $sql = "
    insert into ahdcontacts (hid,bid,date,contacts)
    values ('$hid','$bid','$date','$finalJson')
    on duplicate key update
    contacts = '$finalJson'
  ";
  if ($returnSql) $sqla[] = $sql;
  $rt = unvEdit($mysqli, $sql);
  if ($returnSql) $rt['sqla'] = $sqla;
  if ($rt['result']) {
    $mysqli->commit();
  } else {
    $mysqli->rollback();
  }
  $mysqli->close();
  $rt['sendLogRt'] = $sendLogRt;
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
}

function sendOneMessageOfContact()
{
  global $returnSql;
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $token = PRMS('token');
  $date = PRMS('date');
  $uid = PRMS('uid');
  $did = PRMS('did');
  $msgIndex = (int)PRMS('msgIndex');
  $message = PRMS('message');

  // パラメータのエラーチェック
  if (empty($hid)) {
    echo json_encode(['result' => false, 'error' => 'hid is missing or empty'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return false;
  }

  if (empty($bid)) {
    echo json_encode(['result' => false, 'error' => 'bid is missing or empty'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return false;
  }

  if ($hid === $bid) {
    echo json_encode(['result' => false, 'error' => 'hid and bid must not be the same'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return false;
  }

  // Parse message if it's a JSON string
  $decodedMessage = json_decode($message, true);
  if (json_last_error() === JSON_ERROR_NONE) {
    $message = $decodedMessage;
  }

  $sqla = [];
  $mysqli = connectDb();
  $mysqli->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);

  $sql = "
    select hid, bid from ahduser
    where hid like '$hid%'
    and bid like '$bid%'
    and '$token' like concat('%', faptoken, '%') and faptoken != '';
  ";

  // 短いhid,bidが設定されているかどうか
  $shortHidBid = ((strlen($hid) < 8) || (strlen($bid) < 8));
  if ($shortHidBid) {
    $rt = unvList($mysqli, $sql);
    if ($returnSql) $sqla[] = $sql;
    if (!count($rt['dt'])) {
      $errobj = [
        'result' => false,
        'msg' => "mail not found",
        'sqla' => $sqla
      ];
      echo json_encode($errobj, JSON_UNESCAPED_UNICODE);
      $mysqli->rollback();
      $mysqli->close();
      return false;
    }
    $preSearch = $rt['dt'][0];
    $hid = $preSearch['hid'];
    $bid = $preSearch['bid'];
  }

  $state = $mysqli->real_escape_string(PRMS('partOfContact'));
  $keep = 4;
  $item = 'sendpartofcontent_log';
  $sql = "
    insert into ahdLog (hid,bid,date,state,item,keep)
    values ('$hid','$bid','$date','$state','$item','$keep')
    on duplicate key update
    state = '$state', keep = '$keep', timestamp = CURRENT_TIMESTAMP
  ";
  $sendLogRt = unvEdit($mysqli, $sql);

  $sql = "
    select contacts from ahdcontacts 
    where hid='$hid'
    and bid='$bid'
    and date='$date'
    FOR UPDATE;
  ";
  $rt = unvList($mysqli, $sql);
  if (count($rt['dt'])) {
    $preCon = $rt['dt'][0]['contacts'];
  } else {
    $preCon = '{}'; // 検索できなかったらエラーにせずに空白オブジェクト
  }
  if ($returnSql) $sqla[] = $sql;

  $preCon = json_decode(escapeChar($preCon), true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    $errobj = [
      'result' => false,
      'msg' => "Invalid contacts on DB JSON " . json_last_error_msg(),
      'sendLogRt' => $sendLogRt,
      'sqla' => $sqla
    ];
    echo json_encode($errobj, JSON_UNESCAPED_UNICODE);
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }

  // マージする配列があるかどうか確認
  if (!isset($preCon[$uid])) {
    $preCon[$uid] = [];
  }
  if (!isset($preCon[$uid][$did])) {
    $preCon[$uid][$did] = [
      ['content' => ''],
      ['content' => ''],
      ['content' => '']
    ];
  }

  // インデックスにメッセージを設定
  $preCon[$uid][$did][$msgIndex] = $message;


  $finalJson = json_encode($preCon, JSON_UNESCAPED_UNICODE);
  $finalJson = escapeChar($finalJson);
  $finalJson = $mysqli->real_escape_string($finalJson);

  $sql = "
    insert into ahdcontacts (hid,bid,date,contacts)
    values ('$hid','$bid','$date','$finalJson')
    on duplicate key update
    contacts = '$finalJson'
  ";
  if ($returnSql) $sqla[] = $sql;
  $rt = unvEdit($mysqli, $sql);
  if ($returnSql) $rt['sqla'] = $sqla;
  if ($rt['result']) {
    $mysqli->commit();
  } else {
    $mysqli->rollback();
  }
  $mysqli->close();
  $rt['sendLogRt'] = $sendLogRt;
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
}

function sendDtUnderUidOfContact()
{
  global $returnSql;
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $token = PRMS('token');
  $date = PRMS('date');
  $uid = PRMS('uid');
  $content = PRMS('content');

  // パラメータのエラーチェック
  if (empty($hid)) {
    echo json_encode(['result' => false, 'error' => 'hid is missing or empty'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return false;
  }

  if (empty($bid)) {
    echo json_encode(['result' => false, 'error' => 'bid is missing or empty'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return false;
  }

  if ($hid === $bid) {
    echo json_encode(['result' => false, 'error' => 'hid and bid must not be the same'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return false;
  }

  // Parse content if it's a JSON string
  $decodedContent = json_decode($content, true);
  if (json_last_error() === JSON_ERROR_NONE) {
    $content = $decodedContent;
  }

  $sqla = [];
  $mysqli = connectDb();
  $mysqli->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);

  $sql = "
    select hid, bid from ahduser
    where hid like '$hid%'
    and bid like '$bid%'
    and '$token' like concat('%', faptoken, '%') and faptoken != '';
  ";

  // 短いhid,bidが設定されているかどうか
  $shortHidBid = ((strlen($hid) < 8) || (strlen($bid) < 8));
  if ($shortHidBid) {
    $rt = unvList($mysqli, $sql);
    if ($returnSql) $sqla[] = $sql;
    if (!count($rt['dt'])) {
      $errobj = [
        'result' => false,
        'msg' => "mail not found",
        'sqla' => $sqla
      ];
      echo json_encode($errobj, JSON_UNESCAPED_UNICODE);
      $mysqli->rollback();
      $mysqli->close();
      return false;
    }
    $preSearch = $rt['dt'][0];
    $hid = $preSearch['hid'];
    $bid = $preSearch['bid'];
  }

  $state = $mysqli->real_escape_string(PRMS('partOfContact'));
  $keep = 4;
  $item = 'sendpartofcontent_log';
  $sql = "
    insert into ahdLog (hid,bid,date,state,item,keep)
    values ('$hid','$bid','$date','$state','$item','$keep')
    on duplicate key update
    state = '$state', keep = '$keep', timestamp = CURRENT_TIMESTAMP
  ";
  $sendLogRt = unvEdit($mysqli, $sql);

  $sql = "
    select contacts from ahdcontacts 
    where hid='$hid'
    and bid='$bid'
    and date='$date'
    FOR UPDATE;
  ";
  $rt = unvList($mysqli, $sql);
  if (count($rt['dt'])) {
    $preCon = $rt['dt'][0]['contacts'];
  } else {
    $preCon = '{}'; // 検索できなかったらエラーにせずに空白オブジェクト
  }
  if ($returnSql) $sqla[] = $sql;

  $preCon = json_decode(escapeChar($preCon), true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    $errobj = [
      'result' => false,
      'msg' => "Invalid contacts on DB JSON " . json_last_error_msg(),
      'sendLogRt' => $sendLogRt,
      'sqla' => $sqla
    ];
    echo json_encode($errobj, JSON_UNESCAPED_UNICODE);
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }

  // UID配下にデータオブジェクトを追加
  if (!isset($preCon[$uid])) {
    $preCon[$uid] = new stdClass();
  }
  $preCon[$uid] = array_merge((array)$preCon[$uid], $content);

  $finalJson = json_encode($preCon, JSON_UNESCAPED_UNICODE);
  $finalJson = escapeChar($finalJson);
  $finalJson = $mysqli->real_escape_string($finalJson);

  $sql = "
    insert into ahdcontacts (hid,bid,date,contacts)
    values ('$hid','$bid','$date','$finalJson')
    on duplicate key update
    contacts = '$finalJson'
  ";
  if ($returnSql) $sqla[] = $sql;
  $rt = unvEdit($mysqli, $sql);
  if ($returnSql) $rt['sqla'] = $sqla;
  if ($rt['result']) {
    $mysqli->commit();
  } else {
    $mysqli->rollback();
  }
  $mysqli->close();
  $rt['sendLogRt'] = $sendLogRt;
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
}


function sendContactForFap()
{
  global $returnSql;
  $hid = PRMS('hid', true);
  $bid = PRMS('bid', true);
  $token = PRMS('token', true);
  $date = PRMS('date', true);
  $did = PRMS('did', true); // DYYYYMMDD形式
  $uid = PRMS('uid', true); // UIDnnn形式 -> どちらでも可
  $pos = intval(PRMS('pos', true)); // 配列の中のポジション
  $content = PRMS('content', true); // コンテント
  $sqla = [];
  if (!$pos) $pos = 1;
  if (strpos($uid, 'UID') === false) $uid = 'UID' . $uid;
  $mysqli = connectDb();
  $mysqli->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
  $sql = "
    select hid, bid from ahduser
    where hid like '$hid%'
    and bid like '$bid%'
    and '$token' like concat('%', faptoken, '%') and faptoken != '';
  ";
  // 短いhid,bidが設定されているかどうか
  $shortHidBid = ((strlen($hid) < 8) || (strlen($bid) < 8));
  if ($shortHidBid) {
    $rt = unvList($mysqli, $sql);
    if ($returnSql) $sqla[] = $sql;

    if (!count($rt['dt'])) {
      $errobj = [
        'result' => false,
        'msg' => "token not found",
        'sqla' => $sqla
      ];
      echo json_encode($errobj, JSON_UNESCAPED_UNICODE);
      $mysqli->rollback();
      $mysqli->close();
      return false;
    }
    $preSearch = $rt['dt'][0];
    // 6桁で来ることもあるので取り直しを行う
    $hid = $preSearch['hid'];
    $bid = $preSearch['bid'];
  }
  // 既存のahdcontactsを取得
  $sql = "
    select contacts from ahdcontacts 
    where hid='$hid'
    and bid='$bid'
    and date='$date'
    FOR UPDATE;
  ";
  $rt = unvList($mysqli, $sql);
  if (count($rt['dt'])) {
    $t = $rt['dt'][0]['contacts'];
  } else {
    $t = '{}'; // 検索できなかったらエラーにせずに空白オブジェクト
  }
  if ($returnSql) $sqla[] = $sql;
  $preCon = json_decode(escapeChar($t), true);
  // $preCon = json_decode($t, true);
  // var_dump($t);
  $keyMatch = false;
  if (array_key_exists($uid, $preCon)) {
    if (array_key_exists($did, $preCon[$uid])) {
      $keyMatch = true;
    }
  }
  if (!$keyMatch) {
    $errobj = [
      'result' => false,
      'msg' => "content key not found",
      'sqla' => $sqla
    ];
    echo json_encode($errobj, JSON_UNESCAPED_UNICODE);
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }
  $preCon[$uid][$did][$pos] = json_decode(escapeChar($content), true);
  $newContent = $preCon;
  $finalJson = json_encode($newContent, JSON_UNESCAPED_UNICODE);
  $finalJson = escapeChar($finalJson);

  // $newSch = array_merge($preSch, $sch);
  // $newSchJson = json_encode($newSch, JSON_UNESCAPED_SLASHES);
  $sql = "
    insert into ahdcontacts (hid,bid,date,contacts)
    values ('$hid','$bid','$date','$finalJson')
    on duplicate key update
    contacts = '$finalJson'
  ";
  if ($returnSql) $sqla[] = $sql;
  $rt = unvEdit($mysqli, $sql);
  if ($returnSql) $rt['sqla'] = $sqla;
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  if ($rt['result']) {
    $mysqli->commit();
  } else {
    $mysqli->rollback();
  }
  $mysqli->close();
}

function fetchContacts()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $sql = "
    select hid,bid,date, contacts 
    from ahdcontacts
    where hid = '$hid'
    and bid = '$bid'
    and date = '$date'
  ";
  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql);
  if (count($rt['dt'])) {
    $rt['dt'][0]['contacts'] = json_decode($rt['dt'][0]['contacts']);
  }
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function fetchWorkshift()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $sql = "
    select hid,bid,date,workshift 
    from ahdworkshift
    where hid = '$hid'
    and bid = '$bid'
    and date = '$date'
  ";
  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql);
  if (count($rt['dt'])) {
    $rt['dt'][0]['workshift'] = json_decode($rt['dt'][0]['workshift']);
  }
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

// 事業所番号によるcontentの送信を行う
// faptokenとctokenによる認証を行う
function sendPartOfContactJino()
{
  global $returnSql;
  $jino = PRMS('jino');
  $mail = PRMS('mail');
  $faptoken = PRMS('faptaken');
  $uid = PRMS('uid');
  $date = PRMS('date');
  $ctoken = PRMS('ctoken');
  $content = PRMS('content');
  $hid = '';
  $bid = '';
  $sqla = [];
  if ($mail) {
    $mailWhere = "pmail = '$mail'";
  } else {
    $mailWhere = " true";
  }

  // 事業所番号よりhid, bidを得る
  $mysqli = connectDb();
  $mysqli->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
  
  // 同一jinoで異なるhid,bidのペアが複数存在するかチェック
  $sql = "
    SELECT COUNT(DISTINCT CONCAT(hid, '-', bid)) AS count_pairs
    FROM ahdbrunch
    WHERE jino = '$jino';
  ";
  $rt = unvList($mysqli, $sql);
  $sqla[] = $sql;

  // 異なるhid,bidのペアが複数存在する場合はエラー
  if ($rt['dt'][0]['count_pairs'] > 1) {
    $errobj = [
      'result' => false,
      'msg' => "複数の施設が同一の事業所番号で登録されているためデータを特定できません。",
      'sqla' => $sqla
    ];
    echo json_encode($errobj, JSON_UNESCAPED_UNICODE);
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }

  // 同一jinoで同一のhid,bidペアが存在する場合は最新の日付のものを取得
  $sql = "
    SELECT hid, bid, jino, date
    FROM ahdbrunch
    WHERE jino = '$jino'
    ORDER BY date DESC
    LIMIT 1;
  ";
  $rt = unvList($mysqli, $sql);
  $sqla[] = $sql;

  if (count($rt['dt']) === 0) {
    $errobj = [
      'result' => false,
      'msg' => "指定された事業所番号が見つかりません。",
      'sqla' => $sqla
    ];
    echo json_encode($errobj, JSON_UNESCAPED_UNICODE);
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }

  $hid = $rt['dt'][0]['hid'];
  $bid = $rt['dt'][0]['bid'];

  // mail, uid, faptalkenの認証を行う
  $sql = "
    SELECT a.hid, a.bid, a.uid, a.date, a.faptoken, a.pmail
    FROM ahduser a
    INNER JOIN (
      SELECT hid, bid, uid, MAX(date) as max_date
      FROM ahduser
      WHERE date <= '$date'
      GROUP BY hid, bid, uid
    ) b
    ON a.hid = b.hid AND a.bid = b.bid AND a.uid = b.uid AND a.date = b.max_date
    where a.faptoken = '$faptoken' and $mailWhere;
  ";
  $rt = unvList($mysqli, $sql);
  $sqla[] = $sql;
  if (count($rt['dt']) === 0) {
    $errobj = [
      'result' => false,
      'msg' => "faptoken authentication failed.",
      'sqla' => $sqla
    ];
    echo json_encode($errobj, JSON_UNESCAPED_UNICODE);
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }
  // 既存のcontactsを取得する
  $sql = "
    select contacts from ahdcontacts 
    where hid='$hid'
    and bid='$bid'
    and date='$date'
    FOR UPDATE;
  ";
  $rt = unvList($mysqli, $sql);
  if (count($rt['dt'])) {
    $t = $rt['dt'][0]['contacts'];
  } else {
    $errobj = [
      'result' => false,
      'msg' => "Invalid data. No existing contact data.",
      'sqla' => $sqla
    ];
    echo json_encode($errobj, JSON_UNESCAPED_UNICODE);
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }
  $sqla[] = $sql;
  $preCon = json_decode(escapeChar($t), true);
  $UID = 'UID' . $uid;
  // echo "UID = $UID<br>";
  // var_dump($preCon);
  // echo "<br>preCon[UID]<br>";
  // var_dump($preCon[$UID]);
  if (isset($preCon[$UID]) && is_array($preCon[$UID]) && isset($preCon[$UID]['ctoken'])) {
    $thisCToken = $preCon[$UID]['ctoken'];
  } else {
    $thisCToken = -1;
  }
  if ($ctoken !== $thisCToken) {
    $errobj = [
      'result' => false,
      'msg' => "ctoken authentication failed.",
      'sqla' => $sqla
    ];
    echo json_encode($errobj, JSON_UNESCAPED_UNICODE);
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }
  // コンテントの書き換えを行う
  $preCon[$UID] = json_decode(escapeChar($content), true);
  // echo "preconuid<br>";
  // var_dump($preCon[$UID]);
  $preCon[$UID]['ctoken'] = $ctoken;
  $newContent = $preCon;
  $finalJson = json_encode($newContent, JSON_UNESCAPED_UNICODE);
  $finalJson = escapeChar($finalJson);
  $finalJson = mysqli_real_escape_string($mysqli, $finalJson);

  $sql = "
    insert into ahdcontacts (hid,bid,date,contacts)
    values ('$hid','$bid','$date','$finalJson')
    on duplicate key update
    contacts = '$finalJson'
  ";
  $sqla[] = $sql;
  $rt = unvEdit($mysqli, $sql);
  if ($returnSql) $rt['sqla'] = $sqla;
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  if ($rt['result']) {
    $mysqli->commit();
  } else {
    $mysqli->rollback();
  }
  $mysqli->close();
}

// 事業所番号によるcontentの取得を行う
// faptokenとctokenによる認証を行う
function fetchPartOfContactJino()
{
  global $returnSql;
  $jino = PRMS('jino');
  $mail = PRMS('mail');
  $faptoken = PRMS('faptaken');
  $uid = PRMS('uid');
  $date = PRMS('date');
  $ctoken = PRMS('ctoken');
  $hid = '';
  $bid = '';
  $sqla = [];
  if ($mail) {
    $mailWhere = "pmail = '$mail'";
  } else {
    $mailWhere = " true";
  }

  $mysqli = connectDb();
  $mysqli->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);

  // 同一jinoで異なるhid,bidのペアが複数存在するかチェック
  $sql = "
    SELECT COUNT(DISTINCT CONCAT(hid, '-', bid)) AS count_pairs
    FROM ahdbrunch
    WHERE jino = '$jino';
  ";
  $rt = unvList($mysqli, $sql);
  $sqla[] = $sql;

  // 異なるhid,bidのペアが複数存在する場合はエラー
  if ($rt['dt'][0]['count_pairs'] > 1) {
    $errobj = [
      'result' => false,
      'msg' => "複数の施設が同一の事業所番号で登録されているためデータを特定できません。",
      'sqla' => $sqla
    ];
    echo json_encode($errobj, JSON_UNESCAPED_UNICODE);
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }

  // 事業所番号よりhid, bidを得る
  $sql = "
    SELECT hid, bid, jino, date
    FROM ahdbrunch
    WHERE jino = '$jino'
    ORDER BY date DESC
    LIMIT 1;
  ";
  $rt = unvList($mysqli, $sql);
  $sqla[] = $sql;
  if (count($rt['dt']) === 1) {
    $hid = $rt['dt'][0]['hid'];
    $bid = $rt['dt'][0]['bid'];
  }
  if (count($rt['dt']) > 1) {
    $errobj = [
      'result' => false,
      'msg' => "Data cannot be identified because there are multiple offices.",
      'sqla' => $sqla
    ];
    echo json_encode($errobj, JSON_UNESCAPED_UNICODE);
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }
  if (count($rt['dt']) === 0) {
    $errobj = [
      'result' => false,
      'msg' => "Can not found jino.",
      'sqla' => $sqla
    ];
    echo json_encode($errobj, JSON_UNESCAPED_UNICODE);
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }

  // mail, uid, faptalkenの認証を行う
  $sql = "
    SELECT a.hid, a.bid, a.uid, a.date, a.faptoken, a.pmail
    FROM ahduser a
    INNER JOIN (
      SELECT hid, bid, uid, MAX(date) as max_date
      FROM ahduser
      WHERE date <= '$date'
      GROUP BY hid, bid, uid
    ) b
    ON a.hid = b.hid AND a.bid = b.bid AND a.uid = b.uid AND a.date = b.max_date
    where a.faptoken = '$faptoken' and $mailWhere ;
  ";
  $rt = unvList($mysqli, $sql);
  $sqla[] = $sql;
  if (count($rt['dt']) === 0) {
    $errobj = [
      'result' => false,
      'msg' => "faptoken authentication failed.",
      'sqla' => $sqla
    ];
    echo json_encode($errobj, JSON_UNESCAPED_UNICODE);
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }
  if (count($rt['dt']) === 0) {
    $errobj = [
      'result' => false,
      'msg' => "not found user. by fap token",
      'sqla' => $sqla
    ];
    echo json_encode($errobj, JSON_UNESCAPED_UNICODE);
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }

  // contactsを取得する
  $sql = "
    select contacts from ahdcontacts 
    where hid='$hid'
    and bid='$bid'
    and date='$date'
    FOR UPDATE;
  ";
  $rt = unvList($mysqli, $sql);
  $sqla[] = $sql;
  if (count($rt['dt'])) {
    $t = $rt['dt'][0]['contacts'];
  } else {
    $errobj = [
      'result' => false,
      'msg' => "No existing contact data.",
      'sqla' => $sqla
    ];
    echo json_encode($errobj, JSON_UNESCAPED_UNICODE);
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }
  $preCon = json_decode(escapeChar($t), true);
  $UID = 'UID' . $uid;
  if (isset($preCon[$UID]) && is_array($preCon[$UID]) && isset($preCon[$UID]['ctoken'])) {
    $thisCToken = $preCon[$UID]['ctoken'];
  } else {
    $thisCToken = -1;
  }
  if ($ctoken !== $thisCToken) {
    $errobj = [
      'result' => false,
      'msg' => "ctoken authentication failed.",
      'sqla' => $sqla
    ];
    echo json_encode($errobj, JSON_UNESCAPED_UNICODE);
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }
  $rtDt = [];
  $rtDt['dt'] = $preCon[$UID];
  $rtDt['result'] = $rt['result'];
  if ($returnSql) $rt['sqla'] = $sqla;
  echo json_encode($rtDt, JSON_UNESCAPED_UNICODE);
  if ($rt['result']) {
    $mysqli->commit();
  } else {
    $mysqli->rollback();
  }
  $mysqli->close();
}



function fetchSchedule()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $sql = "
    select hid,bid,date, schedule, UNIX_TIMESTAMP(timestamp) * 1000 AS timestamp
    from ahdschedule
    where hid = '$hid'
    and bid = '$bid'
    and date = '$date'
  ";
  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql);
  if (count($rt['dt'])) {
    // 空白の配列でのエラー回避
    if ($rt['dt'][0]['schedule'] == '[]') $rt['dt'][0]['schedule'] = '{}';
    $rt['dt'][0]['schedule'] = json_decode($rt['dt'][0]['schedule']);
    // オブジェクトに新しいプロパティを追加
    $rt['dt'][0]['schedule']->timestamp = $rt['dt'][0]['timestamp'];
  }
  $jsn = json_encode(escapeChar($rt), JSON_UNESCAPED_UNICODE);
  echo $jsn;
  $mysqli->close();
}



function sendTransferData()
{
  global $phpnow;
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $dt = PRMS('dt');
  // $dt = "{}";
  // これだけは常に本番DB接続
  $mysqli = connectDbPrd();
  $sql = "
    insert into ahdsenddt (hid,bid,date,gen,dt)
    values ('$hid','$bid','$date','$phpnow', '$dt')
    on duplicate key update 
      gen = '$phpnow',
      dt = '$dt'
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}
// function sendTransferData() {
//   global $phpnow;
//   $hid = PRMS('hid');
//   $bid = PRMS('bid');
//   $date = PRMS('date');
//   $dt = PRMS('dt');

//   // Base64デコードとGzip解凍
//   $decodedData = base64_decode($dt); // Base64デコード
//   if ($decodedData === false) {
//       echo json_encode(["error" => "Failed to decode Base64"], JSON_UNESCAPED_UNICODE);
//       return;
//   }
//   $uncompressedData = gzdecode($decodedData); // Gzip解凍
//   if ($uncompressedData === false) {
//       echo json_encode(["error" => "Failed to decompress data"], JSON_UNESCAPED_UNICODE);
//       return;
//   }

//   // これだけは常に本番DB接続
//   $mysqli = connectDbPrd();
//   $sql = "
//       insert into ahdsenddt (hid, bid, date, gen, dt)
//       values ('$hid', '$bid', '$date', '$phpnow', '$uncompressedData')
//       on duplicate key update 
//           gen = '$phpnow',
//           dt = '$uncompressedData'
//   ";
//   $rt = unvEdit($mysqli, $sql);
//   echo json_encode($rt, JSON_UNESCAPED_UNICODE);
//   $mysqli->close();
// }



// 伝送データに送信済みの日次を付加する
// unvEditを使うがこれで良いのかｗ
// clear で送信日付をリセットできる
function putSentToTransfer()
{
  global $phpnow;
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  if (array_key_exists('clear', $_REQUEST)) {
    $sent = '0';
  } else {
    $sent = $phpnow;
  }
  $mysqli = connectDb();
  $sql = "
    insert into ahdsenddt (hid,bid,date,sent)
    values ('$hid','$bid','$date','$phpnow')
    on duplicate key update 
      sent = '$sent'
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

// オプションshortまたはsを付けることによりdtフィールドの出力を抑制し
// トラフィックを減らす
function fetchTransferData()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $short = false;
  if (array_key_exists('s', $_REQUEST) || array_key_exists('short', $_REQUEST)) {
    $short = true;
  }
  if (!$short) $addiction = ", dt";
  else $addiction = "";
  $sql = "
    select hid,bid,date,gen,sent $addiction
    from ahdsenddt
    where hid = '$hid'
    and bid = '$bid'
    and date = '$date'
  ";
  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql);
  if (!$short)
    $rt['dt'][0]['dt'] = json_decode($rt['dt'][0]['dt']);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

// 送信済みまたは未送信のリストを表示する
// sentとunsentのオプションは両立しない
// 今の所、登録順でソートされて出力される
function listSent()
{
  $where_date = "true";
  if (array_key_exists('date', $_REQUEST)) {
    $where_date = "date = '" . PRMS('date') . "'";
  }
  $where_unsent = "true";
  if (array_key_exists('unsent', $_REQUEST)) {
    $where_unsent = "gen not like '0000-00-00%' and sent like '0000-00-00%'";
  }
  $where_sent = "true";
  if (array_key_exists('sent', $_REQUEST)) {
    $where_sent = "gen not like '0000-00-00%' and sent not like '0000-00-00%'";
  }
  $where_reg = "true";
  if (array_key_exists('hid', $_REQUEST)) {
    $where_reg = "gen not like '0000-00-00%'";
  }

  $where_hid = "true";
  if (array_key_exists('hid', $_REQUEST)) {
    $where_hid = "hid = '" . PRMS('hid') . "'";
  }
  $where_bid = "true";
  if (array_key_exists('bid', $_REQUEST)) {
    $where_bid = "bid = '" . PRMS('bid') . "'";
  }

  $sql = "
    select hid,bid,date,gen,sent
    from ahdsenddt
    where $where_date
    and $where_sent
    and $where_unsent
    and $where_reg
    and $where_bid
    and $where_hid
    order by gen
  ";
  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function getTransferPass()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $sql = "
    SELECT * FROM ahdsendpw
    where hid = '$hid' and bid = '$bid';
  ";
  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql);
  if (count($rt['dt']) > 0) {
    $rt['dt'][0]['passwd'] = decodeCph($rt['dt'][0]['passwd']);
  }
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function putTransferPass()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $passwd = encodeCph(PRMS('passwd'));
  $mysqli = connectDb();
  $sql = "
    insert into ahdsendpw (hid,bid,passwd)
    values ('$hid','$bid','$passwd')
    on duplicate key update 
    passwd = '$passwd'
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}
// パスワードの変更のみ行われる
// 使わないかも
function putAccount()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $mail = PRMS('mail');
  // $s = json_encode(PRMS('s'), JSON_UNESCAPED_UNICODE);
  $passwd = encodeCph(PRMS('passwd'));
  $mysqli = connectDb();
  $sql = "
    insert into ahdaccount (hid,bid,passwd,mail)
    values ('$hid','$bid','$passwd','$mail')
    on duplicate key update 
    passwd = '$passwd';
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

// アカウントの修正
// 名前とパーミッションのみ変更可能
function editAccount()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $mail = PRMS('mail');
  $lname = PRMS('lname');
  $fname = PRMS('fname');
  $permission = PRMS('permission');
  $mysqli = connectDb();
  $sql = "
    update ahdaccount
    set 
      lname = '$lname', fname = '$fname', permission = '$permission'
    where
      hid = '$hid' and bid = '$bid' and mail = '$mail';
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

// パスワードの全変更
// 同一メールアドレスは同一アカウントとして扱いパスワードも全て同時に変更する
// セッション保持用の配列も破棄する
function updatePasswordsAll()
{
  $mail = PRMS('mail');
  $resetkey = PRMS('resetkey');
  $passwd = encodeCph(PRMS('passwd'));
  // セッション保持の破棄
  $skeystr = json_encode([], JSON_UNESCAPED_UNICODE);

  // リセットキーとパスワードが一致するかどうか確認
  $sql = "
    select mail from ahdaccount
    where mail = '$mail' and resetkey = '$resetkey'
    and TIMEDIFF(CURRENT_TIMESTAMP, resetkeyts) < '72:00:00'
  ";
  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql);
  if (count($rt['dt']) == 0) {
    $rt['msg'] = 'account key not found';
    $rt['result'] = false;
    echo json_encode($rt, JSON_UNESCAPED_UNICODE);
    $mysqli->close();
    return false;
  }
  $rtPre = $rt;
  // $sql = "
  //   update ahdaccount
  //   set passwd = '$passwd', skey = '$skeystr'
  //   where mail = '$mail'
  //   and ( 
  //     resetkey = '$resetkey'
  //     or resetkey = ''
  //   )
  //   and TIMEDIFF(CURRENT_TIMESTAMP, resetkeyts) < '24:00:00'
  // ";

  // 2022/04/21
  // キーとメールとタイムスタンプは確認済みなので
  // 同一のメアドのパスワードを一斉リセットする
  $sql = "
    update ahdaccount
    set passwd = '$passwd', skey = '$skeystr'
    where mail = '$mail'
  ";

  $rt = unvEdit($mysqli, $sql);
  $rt['pre'] = $rtPre;
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

// 新しいキーを送信する
// パスワード認証直後に行う
function sendNewKey()
{
  global $MAX_KEYS;
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $mail = PRMS('mail');
  $key = PRMS('key');
  $mysqli = connectDb();
  // 最初に既存のキーを取得
  $sql1 = "
    select skey from ahdaccount
    where hid = '$hid'
    and bid = '$bid'
    and mail = '$mail';
  ";
  $rt = unvList($mysqli, $sql1);
  // 蓄積されているキーを配列化
  $keys = json_decode($rt['dt'][0]['skey'], true);
  if ($keys === NULL) {
    $keys = [];
  }
  // 新しいキーを追加
  if (array_search($key, $keys) === FALSE)
    array_unshift($keys, $key);
  // 一定以上のキーは削除
  array_splice($keys, $MAX_KEYS);
  $keyStr = json_encode($keys, JSON_UNESCAPED_UNICODE);
  // dbに格納
  $sql = "
    insert into ahdaccount (hid,bid,mail,skey)
    values ('$hid','$bid','$mail','$keyStr')
    on duplicate key update 
    skey = '$keyStr';
  ";
  $rt = unvEdit($mysqli, $sql);
  $rt['mail'] = $mail;
  $rt['key'] = $key;
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

// クッキー認証をして新しいキーを返す
function sertificatAndNew()
{
  $mail = PRMS('mail');
  $key = PRMS('key');
  $mysqli = connectDb();
  $sql1 = "
    select * from ahdaccount
    where mail = '$mail'
    ORDER BY bid ASC
  ";
  $rt = unvList($mysqli, $sql1);
  // 認証不可のレスポンスを用意しておく
  $eres = array('result' => false);
  // レスポンス件数が0件
  if (count($rt['dt']) === 0) {
    $eres['massage'] = 'mail address not found.';
    echo json_encode($eres, JSON_UNESCAPED_UNICODE);
    return false;
  }
  $hid = '';
  $bid = '';
  foreach ($rt['dt'] as $v) {
    $keys = json_decode($v['skey'], true);
    // echo($v['bid'] . $v['skey'] . '<br>');
    $s = FALSE;
    if ($keys != NULL) {
      $s = array_search($key, $keys); // キー検索結果
    }
    if ($s !== FALSE) {
      $hid = $v['hid'];
      $bid = $v['bid'];
      break;
    }
  }
  if ($s === FALSE) {
    $eres['massage'] = 'key not found.';
    echo json_encode($eres, JSON_UNESCAPED_UNICODE);
    return false;
  }
  // 新しいキー作成
  $l = strlen($key);
  $newkey = substr(
    str_shuffle('1234567890abcdefghijklmnopqrstuvwxyz'),
    0,
    $l
  );
  // 既存のキーを削除して新しい配列にする
  unset($keys[$key]);
  array_unshift($keys, $newkey);
  $nkeysJson = json_encode($keys, JSON_UNESCAPED_UNICODE);
  $sql = "
    update ahdaccount
    set skey = '$nkeysJson'
    where hid = '$hid'
    and bid = '$bid'
    and mail = '$mail';
  ";
  $rt = unvEdit($mysqli, $sql);
  $rt['key'] = $newkey;
  $rt['hid'] = $hid;
  $rt['bid'] = $bid;
  $rt['mail'] = $mail;
  // $rt['sql1'] = $sql1;
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
}
// hidによるアカウントの取得。
// 一旦、セッションキーを確認してから
// リストを返す
function fetchAccountsByBid()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $key = PRMS('key');
  $mail = PRMS('mail');
  // $mail = PRMS('mail');
  $mysqli = connectDb();
  $sql = "
    select account.*
    from ahdaccount as account
    where hid = '$hid'
    and bid = '$bid'
    and mail = '$mail'
    and skey like '%\"$key\"%'
  ";
  $rt = unvList($mysqli, $sql);
  if (count($rt['dt']) === 0) {
    $rt = unvList($mysqli, $sql);
    echo json_encode($rt, JSON_UNESCAPED_UNICODE);
    $mysqli->close();
    return false;
  }
  $sql = "
    select account.*
    from ahdaccount as account
    where hid = '$hid'
    and bid = '$bid'
    ;";
  $rt = unvList($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}


function getAccount()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $mail = PRMS('mail');
  $mysqli = connectDb();
  global $returnSql;
  $sql = "
    select account.*,
      brunch.bname, brunch.sbname, brunch.jino,  
      com.hname, com.shname
    from ahdaccount as account
    join ahdbrunch brunch using (hid, bid)
    join ahdcompany com using (hid)
    where hid = '$hid'
    and bid = '$bid'
    and mail = '$mail';
  ";
  $rt = unvList($mysqli, $sql);
  if (count($rt['dt'])) {
    $rt['dt'][0]['passwd'] = decodeCph($rt['dt'][0]['passwd']);
  }
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

// ログインなどの失敗回数をカウントする
function getCountAttempts()
{
  $ip_address = $_SERVER['REMOTE_ADDR']; // クライアントのIPアドレスを取得
  $interval_minutes = PRMS('interval_minutes');
  $interval_minutes = ($interval_minutes === '') ? 10 : $interval_minutes;  
  $mysqli = connectDb();
  $sql = "
    SELECT attempt_count count FROM ahdAttempts 
    WHERE ip_address = '$ip_address' 
    AND attempt_time >= NOW() - INTERVAL $interval_minutes MINUTE;
  ";
  $rt = unvList($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

// メールアドレスとパスワードのみでアカウントを検索する
// 複数の事業所が返ってくることを想定
function getAccountByPw()
{
  $mail = PRMS('mail');
  $interval_minutes = PRMS('interval_minutes');
  $interval_minutes = ($interval_minutes === '') ? 10 : $interval_minutes;
  $pass = encodeCph(PRMS('passwd'));
  $mysqli = connectDb();
  $ip_address = $_SERVER['REMOTE_ADDR']; // クライアントのIPアドレスを取得


  $sql = "
    select 
      account.mail, account.lname,account.fname ,
      account.hid, account.bid,
      account.permission,
      brunch.bname, brunch.sbname, brunch.jino,
      brunch.date,
      com.hname, com.shname
    from ahdaccount as account
    join (
      select hid,bid,max(`date`) as `date` from ahdbrunch group by hid,bid
    ) as tmp using (hid, bid)
    join ahdbrunch as brunch
    on brunch.hid = tmp.hid and brunch.bid = tmp.bid and brunch.date = tmp.date 
    join ahdcompany com on com.hid = tmp.hid
    where passwd = '$pass'
    and mail = '$mail';
  ";

  $rt = unvList($mysqli, $sql);

  if (count($rt['dt']) === 0) {
    // 古い試行記録を削除
    $sql_delete_old_attempts = "
      DELETE FROM ahdAttempts 
      WHERE attempt_time < NOW() - INTERVAL $interval_minutes MINUTE;
    ";
    $mysqli->query($sql_delete_old_attempts);

    // 試行回数を記録
    $sql_attempt = "
      INSERT INTO ahdAttempts (ip_address, attempt_count)
      VALUES ('$ip_address', 1)
      ON DUPLICATE KEY UPDATE attempt_count = attempt_count + 1;
    ";
    $mysqli->query($sql_attempt);

    // 認証失敗時の処理
    $sql_count = "
      SELECT attempt_count 
      FROM ahdAttempts 
      WHERE ip_address = '$ip_address'
      AND attempt_time >= NOW() - INTERVAL $interval_minutes MINUTE;
    ";
    $result = $mysqli->query($sql_count);
    $row = $result->fetch_assoc();
    $attempt_count = $row['attempt_count'];

    $rt['attempt_count'] = $attempt_count;
    echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  } else {
    // 認証成功時の処理
    $rt['attempt_count'] = 0;
    
    // 該当レコードを削除
    $sql_delete_attempts = "
        DELETE FROM ahdAttempts 
        WHERE ip_address = '$ip_address';
    ";
    $mysqli->query($sql_delete_attempts);

    echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  }

  $mysqli->close();
}

// キーでアカウントを検索する。
function getAccountByKey()
{
  // sertificatAndNew からのコピペ
  $mail = PRMS('mail');
  $key = PRMS('key');
  $date = PRMS('date');
  $mysqli = connectDb();
  global $returnSql;
  $sql1 = "
    select 
      account.*,
      brunch.bname, brunch.sbname, brunch.jino,  
      com.hname, com.shname
    from ahdaccount as account
    join (
      select hid,bid,bname,sbname,jino,max(`date`) as `date` 
      from ahdbrunch group by hid,bid
    ) as brunch using (hid, bid)
    join ahdcompany com using (hid)
    where mail = '$mail'

  ";
  // $sql1 = "
  //   select 
  //     account.*,
  //     brunch.bname, brunch.sbname, brunch.jino,  
  //     com.hname, com.shname
  //   from ahdaccount as account
  //   join ahdbrunch brunch using (hid, bid)
  //   join ahdcompany com using (hid)
  //   where mail = '$mail'

  // ";

  $rt = unvList($mysqli, $sql1);
  $accountList = $rt; // アカウントリストを退避
  // 認証不可のレスポンスを用意しておく
  $eres = array('result' => false);
  // レスポンス件数が0件
  if (count($rt['dt']) === 0) {
    $eres['massage'] = 'mail address not found.';
    if ($returnSql) $eres['sql'] = $sql1;
    echo json_encode($eres, JSON_UNESCAPED_UNICODE);
    return false;
  }
  $hid = '';
  $bid = '';

  foreach ($rt['dt'] as $v) {
    $keys = json_decode($v['skey'], true);
    $s = FALSE;
    if ($keys != NULL) {
      $s = array_search($key, $keys); // キー検索結果
    }
    if ($s !== FALSE) {
      $hid = $v['hid'];
      $bid = $v['bid'];
      break;
    }
  }
  if ($s === FALSE) {
    $eres['massage'] = 'key not found.';
    echo json_encode($eres, JSON_UNESCAPED_UNICODE);
    return false;
  }
  // コピペここまで
  // 法人事業所情報を指定月に合わせた情報を取得するよう変更 2021/08/09
  $sql = "
    select 
      account.mail, account.lname,account.fname ,
      account.hid, account.bid,
      account.permission,
      brunch.bname, brunch.sbname, brunch.jino,
      com.hname, com.shname
    from ahdaccount as account
    join ahdbrunch brunch using (hid, bid)
    join ahdcompany com using (hid)
    where hid = '$hid'
    and bid = '$bid'
    and mail = '$mail'
    and brunch.date = (
      SELECT MAX(date) from ahdbrunch 
      WHERE bid='$bid' and hid='$hid' and date<='$date'
    )
  ";
  $rt = unvList($mysqli, $sql);
  $rt['accountlist'] = $accountList;
  if ($returnSql) $rt['sql1'] = $sql;
  // $rt[dt][0]['passwd'] = decodeCph($rt[dt][0]['passwd']);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}
// on duplicate key updateを使っているが 運用上はありえない
// update文に変更
// 最新の指定月以内最新レコードのみ変更する
// sql複数変更するのでトランザクション追加
function sendUserEtc()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $uid = PRMS('uid');
  $date = PRMS('date');
  $etc = PRMS('etc');
  $mysqli = connectDb();
  $mysqli->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
  // $sql = "
  //   insert into ahduser (hid,bid,uid,date,etc)
  //   values ('$hid','$bid','$uid', '$date', '$etc')
  //   on duplicate key update
  //   etc = '$etc'
  // ";
  $sql = "
    set @a = (
      SELECT MAX(date) from ahduser
      WHERE bid='$bid' and hid='$hid' and uid='$uid' and date<='$date'
    );
    update ahduser set etc = '$etc'
    where hid = '$hid'
    and bid = '$bid'
    and uid = '$uid'
    and date = @a;
  ";
  $rt = unvMultiEdit($mysqli, $sql);
  if ($rt['resultfalse'] == 0) {
    $mysqli->commit();
  } else {
    $mysqli->rollback();
  }
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function removeUser()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $uid = PRMS('uid');
  $date = PRMS('date');

  $mysqli = connectDb();
  $sql = "
    delete from ahduser
    where hid = '$hid'
    and bid = '$bid'
    and uid = '$uid'
    and date >= '$date';
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function fetchCompany()
{
  global $secret;
  if (PRMS('secret') != $secret) {
    $rt['msg'] = 'this is secret api.';
    echo json_encode($rt, JSON_UNESCAPED_UNICODE);
    return false;
  }

  $sort = PRMS('sort');
  if (!$sort) $sort = 'postal';
  $sql = "
    select * from ahdcompany
    order by $sort;
  ";
  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function fetchComExtAll()
{
  global $secret;
  if (PRMS('secret') != $secret) {
    $rt['msg'] = 'this is secret api.';
    echo json_encode($rt, JSON_UNESCAPED_UNICODE);
    return false;
  }

  $sql = "
    select * from ahdbrunchext
  ";
  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}


function fetchBrunch()
{
  global $secret;
  if (PRMS('secret') != $secret) {
    $rt['msg'] = 'this is secret api.';
    echo json_encode($rt, JSON_UNESCAPED_UNICODE);
    return false;
  }
  $sql = "
    select hid, bid from ahdbrunch GROUP BY hid, bid;
  ";
  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}


function sendCompany()
{
  $hid = PRMS('hid');
  $hname = PRMS('hname');
  $shname = PRMS('shname');
  $postal = PRMS('postal');
  $city = PRMS('city');
  $address = PRMS('address');
  $tel = PRMS('tel');
  $fax = PRMS('fax');
  $yaku = PRMS('yaku');
  $daihyou = PRMS('daihyou');
  $etc = PRMS('etc');

  $mysqli = connectDb();

  $sql = "
    insert into ahdcompany (
      hid, hname, shname, 
      postal, city, address, tel, fax, yaku,
      daihyou, etc
    )
    values(
      '$hid', '$hname', '$shname', 
      '$postal', '$city', '$address', '$tel', '$fax', '$yaku',
      '$daihyou', '$etc'

    )
    on duplicate key update
      hid = '$hid',
      hname = '$hname',
      shname = '$shname',
      postal = '$postal',
      city = '$city',
      address = '$address',
      tel = '$tel',
      fax = '$fax',
      yaku = '$yaku',
      daihyou = '$daihyou',
      etc = '$etc'
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

// 事業所情報の送信
// json項目は項目があったら更新する
// json項目は更新のみ。新規の場合、jsonは無視される
// json項目は別途sendAddictionOfBrunchがあるがあっちは一項目だけの
// 更新ということで
// 2021/08/09 変更
// sendAddictionOfBrunchを廃止してこちらに統合する
// etcとaddictionは必ず指定するようにする
function sendBrunch()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $bname = PRMS('bname');
  $sbname = PRMS('sbname');
  $jino = PRMS('jino');
  $kanri = PRMS('kanri');
  $postal = PRMS('postal');
  $city = PRMS('city');
  $address = PRMS('address');
  $tel = PRMS('tel');
  $fax = PRMS('fax');
  $fprefix = PRMS('fprefix');
  $etc = PRMS('etc');
  $addiction = PRMS('addiction');

  // $addictionStr = '';  $etcStr = '';
  // if (isset($_REQUEST['addiction'])){
  //   $addiction = $_REQUEST['addiction'];
  //   $addictionStr = "addiction = '$addiction',";
  // }
  // else{
  //   $addiction = '';
  // }
  // if (isset($_REQUEST['etc'])){
  //   $etc = $_REQUEST['etc'];
  //   $etcStr = "etc = '$etc',";
  // }
  // else{
  //   $etc = '';
  // }

  $mysqli = connectDb();

  $sql = "
    insert into ahdbrunch (
      hid, bid, date, bname, sbname, jino, kanri, postal, city, address, tel, fax, fprefix,
      addiction, etc
    )
    values(
      '$hid', '$bid', '$date', '$bname', '$sbname', '$jino', '$kanri', 
      '$postal', '$city', '$address', '$tel', '$fax', '$fprefix',
      '$addiction', '$etc'

    )
    on duplicate key update
      hid = '$hid',
      bid = '$bid',
      date = '$date',
      bname = '$bname',
      sbname = '$sbname',
      jino = '$jino',
      kanri = '$kanri',
      postal = '$postal',
      city = '$city',
      address = '$address',
      tel = '$tel',
      fax = '$fax',
      fprefix = '$fprefix',
      etc = '$etc',
      addiction = '$addiction'
  ";
  // $sql = trim($sql . $addictionStr . $etcStr);
  // $sql = substr($sql, 0, -1); // 末尾の一文字を削除
  $rt = unvEdit($mysqli, $sql);
  // dispatch後に使うのでパラメータを組み込んでおく
  $rt['bname'] = $bname;
  $rt['sbname'] = $sbname;
  $rt['jino'] = $jino;
  $rt['kanri'] = $kanri;
  $rt['postal'] = $postal;
  $rt['city'] = $city;
  $rt['address'] = $address;
  $rt['tel'] = $tel;
  $rt['fax'] = $fax;
  $rt['etc'] = $etc;
  $rt['fprefix'] = $fprefix;
  $rt['addiction'] = $addiction;

  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}
// ユーザー情報の書き込み
// 書き込み完了後、該当ユーザーのuidも一緒に返す
// jsonのetcは扱わない
// 互換性のために残すが今後はsendUserWithEtc()を使うようにする
// 日付を追加する必要が発生したので変更を行う
// オートインクリメント属性を外してスキーを変更してある
// いちどDBに問い合わせをして付与すべきIDを取得しているこの時にトランザクションを
// 開始すべきだがIDは必ずしもuniqである必要がないのでトランザクション処理は行わない
// やっぱりトランザクションする
function sendUser()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $uid = PRMS('uid');
  $hno = PRMS('hno');
  $date = PRMS('date');
  $type = PRMS('type');
  $service = PRMS('service');
  $classroom = PRMS('classroom');
  $volume = PRMS('volume');
  $priceLimit = PRMS('priceLimit');
  $name = PRMS('name');
  $kana = PRMS('kana');
  $scity = PRMS('scity');
  $scity_no = PRMS('scity_no');
  $birthday = PRMS('birthday');
  $kanri_type = PRMS('kanri_type');
  $startDate = PRMS('startDate');
  $contractDate = PRMS('contractDate');
  $lineNo = PRMS('lineNo');
  $endDate = PRMS('endDate');
  $contractEnd = PRMS('contractEnd');
  $postal = PRMS('postal');
  $city = PRMS('city');
  $address = PRMS('address');
  $pname = PRMS('pname');
  $pkana = PRMS('pkana');
  $pmail = PRMS('pmail');
  $pphone = PRMS('pphone');
  $pphone1 = PRMS('pphone1');
  $belongs1 = PRMS('belongs1');
  $belongs2 = PRMS('belongs2');
  $belongs2 = PRMS('belongs2');
  $brosIndex = PRMS('brosIndex');
  $sindex = PRMS('sindex');

  $mysqli = connectDb();
  $mysqli->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
  // uid 送信されない場合は新規データとして解釈 テーブルのデータから作成する
  if ($uid == '') {
    $sqlpre = "SELECT max(uid) uid FROM `ahduser` where hid='$hid';";
    $rtpre = unvList($mysqli, $sqlpre);
    $uid = intval($rtpre['dt'][0]['uid']) + 1;
  }

  $sql = "
    insert into ahduser (
      hid, bid, uid, hno, date, type, service, classroom, 
      volume, priceLimit, name, kana, 
      scity, scity_no, birthday, kanri_type, 
      startDate, endDate, contractDate, lineNo, postal, city, address, 
      pname, pkana, pmail, pphone, pphone1, 
      belongs1, belongs2, brosIndex, sindex, contractEnd
    )
    values (
      '$hid', '$bid', '$uid', '$hno', '$date', '$type', '$service', '$classroom', 
      '$volume', '$priceLimit', '$name', '$kana', 
      '$scity', '$scity_no', '$birthday', '$kanri_type', 
      '$startDate', '$endDate', '$contractDate', '$lineNo', 
      '$postal', '$city', '$address', 
      '$pname', '$pkana', '$pmail', '$pphone', '$pphone1', 
      '$belongs1', '$belongs2', '$brosIndex', '$sindex', '$contractEnd'
    ) 
    on duplicate key update
      hno = '$hno',
      date = '$date',
      type = '$type',
      service = '$service',
      classroom = '$classroom',
      volume = '$volume',
      priceLimit = '$priceLimit',
      name = '$name',
      kana = '$kana',
      scity = '$scity',
      scity_no = '$scity_no',
      birthday = '$birthday',
      kanri_type = '$kanri_type',
      startDate = '$startDate',
      endDate = '$endDate',
      contractDate = '$contractDate',
      contractEnd = '$contractEnd',
      lineNo = '$lineNo',
      postal = '$postal',
      city = '$city',
      address = '$address',
      pname = '$pname',
      pkana = '$pkana',
      pmail = '$pmail',
      pphone = '$pphone',
      pphone1 = '$pphone1',
      belongs1 = '$belongs1',
      belongs2 = '$belongs2',
      brosIndex = '$brosIndex',
      sindex = '$sindex';
  ";
  $rt = unvEdit($mysqli, $sql);
  $sql1 = "
      select hid, bid, hno, uid from ahduser
      where 
        hno = '$hno' and
        hid = '$hid' and
        date = '$date' and
        bid = '$bid';
  ";

  if ($rt['result']) {
    $mysqli->commit();
  } else {
    $mysqli->rollback();
  }
  $rt0 = unvList($mysqli, $sql1);
  $rt['uid'] = $rt0['dt'][0]['uid'];
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

// ユーザー情報の書き込み
// etcの項目も入れる
// 書き込み完了後、該当ユーザーのuidも一緒に返す
// 日付を追加する必要が発生したので変更を行う
// uid オートインクリメント属性を外してスキーを変更してある
// いちどDBに問い合わせをして付与すべきIDを取得しているこの時にトランザクションを
// 開始すべきだがIDは必ずしもuniqである必要がないのでトランザクション処理は行わない
// やっぱトランザクションする
function sendUserWithEtc()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $uid = PRMS('uid');
  $hno = PRMS('hno');
  $date = PRMS('date');
  $type = PRMS('type');
  $service = PRMS('service');
  $classroom = PRMS('classroom');
  $volume = PRMS('volume');
  $priceLimit = PRMS('priceLimit');
  $name = PRMS('name');
  $kana = PRMS('kana');
  $scity = PRMS('scity');
  $scity_no = PRMS('scity_no');
  $birthday = PRMS('birthday');
  $kanri_type = PRMS('kanri_type');
  $startDate = PRMS('startDate');
  $contractDate = PRMS('contractDate');
  $lineNo = PRMS('lineNo');
  $endDate = PRMS('endDate');
  $postal = PRMS('postal');
  $city = PRMS('city');
  $address = PRMS('address');
  $pname = PRMS('pname');
  $pkana = PRMS('pkana');
  $pmail = PRMS('pmail');
  $pphone = PRMS('pphone');
  $pphone1 = PRMS('pphone1');
  $belongs1 = PRMS('belongs1');
  $belongs2 = PRMS('belongs2');
  $belongs2 = PRMS('belongs2');
  $sindex = PRMS('sindex');
  $brosIndex = PRMS('brosIndex');
  $etc = PRMS('etc');
  $faptoken = PRMS('faptoken');
  $contractEnd = PRMS('contractEnd');


  $mysqli = connectDb();
  $mysqli->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);

  // 最初に既存のユーザーがいないか受給者証番号で検索する
  if ($uid == '') {
    $sqlpre = "
      select uid from `ahduser`
      where hid='$hid' and bid='$bid' and hno='$hno'
    ";
    $rtpre0 = unvList($mysqli, $sqlpre);
    if (array_key_exists('dt', $rtpre0) && count($rtpre0['dt'])) {
      $uid = $rtpre0['dt'][0]['uid'];
    }
  }
  // uid 送信されない場合は新規データとして解釈 テーブルのデータから作成する
  if ($uid == '') {
    // 素数配列からランダムに数値を選び
    $primeNums = [11, 13, 17, 19, 47, 53, 59];
    $nextInc = mt_rand(0, count($primeNums) - 1);
    $sqlpre = "SELECT max(uid) uid FROM `ahduser` where hid='$hid';";
    $rtpre = unvList($mysqli, $sqlpre);
    $uid = intval($rtpre['dt'][0]['uid']) + $primeNums[$nextInc];
  }
  $sql = "
    insert into ahduser (
      hid, bid, uid, hno, date, type, service, classroom, 
      volume, priceLimit, name, kana, 
      scity, scity_no, birthday, kanri_type, 
      startDate, endDate, contractDate, lineNo, postal, city, address, 
      pname, pkana, pmail, pphone, pphone1, 
      belongs1, belongs2, sindex, brosIndex, etc, faptoken, contractEnd
    )
    values (
      '$hid', '$bid', '$uid', '$hno', '$date', '$type', '$service', '$classroom', 
      '$volume', '$priceLimit', '$name', '$kana', 
      '$scity', '$scity_no', '$birthday', '$kanri_type', 
      '$startDate', '$endDate', '$contractDate', '$lineNo', 
      '$postal', '$city', '$address', 
      '$pname', '$pkana', '$pmail', '$pphone', '$pphone1', 
      '$belongs1', '$belongs2', '$sindex', '$brosIndex', '$etc', '$faptoken', 
      '$contractEnd'
    ) 
    on duplicate key update
      hno = '$hno',
      type = '$type',
      service = '$service',
      classroom = '$classroom',
      volume = '$volume',
      priceLimit = '$priceLimit',
      name = '$name',
      kana = '$kana',
      scity = '$scity',
      scity_no = '$scity_no',
      birthday = '$birthday',
      kanri_type = '$kanri_type',
      startDate = '$startDate',
      endDate = '$endDate',
      contractDate = '$contractDate',
      contractEnd = '$contractEnd',
      lineNo = '$lineNo',
      postal = '$postal',
      city = '$city',
      address = '$address',
      pname = '$pname',
      pkana = '$pkana',
      pmail = '$pmail',
      pphone = '$pphone',
      pphone1 = '$pphone1',
      belongs1 = '$belongs1',
      belongs2 = '$belongs2',
      sindex = '$sindex',
      brosIndex = '$brosIndex',
      etc = '$etc',
      faptoken = '$faptoken';
  ";
  $rt = unvEdit($mysqli, $sql);
  $sql1 = "
      select hid, bid, hno, uid from ahduser
      where 
        hno = '$hno' and
        hid = '$hid' and
        date = '$date' and
        bid = '$bid';
  ";
  if ($rt['result']) {
    $mysqli->commit();
  } else {
    $mysqli->rollback();
  }
  $rt0 = unvList($mysqli, $sql1);
  $rt['uid'] = $rt0['dt'][0]['uid'];
  if (isset($rtpre)) {
    $rt['rtpre'] = $rtpre;
  }
  if (isset($rtpre0)) {
    $rt['rtpre0'] = $rtpre0;
  }
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}


// ドキュメント作成用データ送信
// 常に本番DBを使う
function sendDocument()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $stamp = PRMS('stamp');
  $template = PRMS('template');
  $dst = PRMS('dst');
  // $content = PRMS('content');
  $mysqli = connectDbPrd();
  $content = $mysqli->real_escape_string(PRMS('content'));
  $sql = "
    INSERT INTO ahddocdt (
        hid, bid, stamp, template, dst, content
    ) VALUES (
        '$hid', '$bid', '$stamp', '$template', '$dst', '$content'
    )
    ON DUPLICATE KEY UPDATE
        hid = VALUES(hid),
        bid = VALUES(bid),
        stamp = VALUES(stamp),
        template = VALUES(template),
        dst = VALUES(dst),
        content = VALUES(content);
  ";
  $rt = unvEdit($mysqli, $sql);
  // 古いデータを削除する
  $sql1 = "
    delete FROM ahddocdt WHERE gen < DATE_SUB(CURDATE(), INTERVAL 1 DAY);
  ";
  $rt1 = unvEdit($mysqli, $sql1);
  $rt['deleted_rows'] = $rt1['affected_rows'];
  sleep(SLEEP); // timeout確認
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function fetchDocument()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $stamp = PRMS('stamp');
  $sql = "
    select * from ahddocdt
    where hid = '$hid'
    and bid = '$bid'
    and stamp = '$stamp';
  ";
  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql);
  if (count($rt['dt'])) {
    $rt['dt'][0]['content'] = json_decode($rt['dt'][0]['content']);
  }
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

// ユーザーのjson部分を複数書き換える
// etcsは[{uid:xxx,etc:{...}}]の配列
// 2022/01/02 変数付きsqlに変更
// トランザクション追加
// この更新はstdDateを更新しないよ
function sendUserEtcMulti()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $json = PRMS('etcs');
  $ary = json_decode($json, true);
  $mysqli = connectDb();
  $mysqli->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);

  $sql = '';
  // var_dump($ary);
  foreach ($ary as $v) {
    $uid = $v['uid'];
    $etc = $v['etc'];
    // $etc = json_encode($v['etc'], JSON_UNESCAPED_UNICODE);
    $oneSql = "
      set @a = (
        SELECT MAX(date) from ahduser
        WHERE bid='$bid' and hid='$hid' and uid='$uid' and date<='$date'
      );
      update ahduser set etc = '$etc'
      where hid = '$hid'
      and bid = '$bid'
      and uid = '$uid'
      and date = @a;
    ";
    $sql .= $oneSql;
  }

  $rt = unvMultiEdit($mysqli, $sql);
  if ($rt['resultfalse'] == 0) {
    $mysqli->commit();
  } else {
    $mysqli->rollback();
  }
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function replaceUsersCity()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $scity = PRMS('scity');
  $scity_no = PRMS('scity_no');
  $mysqli = connectDb();

  $sql = "
    update ahduser set scity = '$scity'
    where hid = '$hid'
    and bid = '$bid'
    and scity_no = '$scity_no';
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

// 名前で市区町村番号を変更する
function replaceUsersCityNoByName()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $scity = PRMS('scity');
  $scity_no = PRMS('scity_no');
  $mysqli = connectDb();

  $sql = "
    update ahduser set scity_no = '$scity_no'
    where hid = '$hid'
    and bid = '$bid'
    and scity = '$scity';
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}


// ユーザーソート用のインデックスをまとめて書き込む
// indexsetは[[uid, sinde], [uid, sinde]...]の二次元配列
function sendUsersIndex()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $j = PRMS('indexset');
  $ary = json_decode($j);
  $sql = '';
  foreach ($ary as $v) {
    $sindex = $v[1];
    $uid = $v[0];
    $oneSql = "
      update ahduser set sindex = '$sindex'
      where hid = '$hid'
      and bid = '$bid'
      and uid = '$uid';
    ";
    $sql .= $oneSql;
  }
  $mysqli = connectDb();
  $rt = unvMultiEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
}
// アカウントの追加 メールアドレスとリセットキーのセット
function addAccount()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $mail = PRMS('mail');
  $resetkey = PRMS('resetkey');
  $lname = PRMS('lname');
  $fname = PRMS('fname');
  $permission = PRMS('permission');
  // まずはメールだけでアカウント検索
  $sql = "select * from ahdaccount where mail = '$mail'";
  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql);
  // 最初のレコードからパスワードを取得する。
  // 同じメールアドレスは同じパスワードが設定されているという前提にする
  if (count($rt['dt'])) {
    $passwd = $rt['dt'][0]['passwd'];
    $mailCount = count($rt['dt']);
  } else {
    $passwd = '';
    $mailCount = 0;
  }

  // 新規ユーザー追加
  // タイムスタンプはmysql側でレコード更新時に上書きされるのでここでは指定しない
  // パスワードはこの場合暗号化されたまま処理される
  // 追加できなかった場合reslt: falseが帰る
  $sql = "insert into ahdaccount (
      mail, lname, fname, hid, bid, passwd, resetkey, permission, resetkeyts
    )
    values (
      '$mail', '$lname', '$fname', '$hid', '$bid',
      '$passwd', '$resetkey', '$permission', 
      CURRENT_TIMESTAMP
    );
  ";
  $rt = unvEdit($mysqli, $sql);
  $rt['mailCount'] = $mailCount;
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  // -------
  // ここから下にメール送信cgiへのコードを書く予定
  // 
  // CGI_URL = 'http://153.127.61.191/py/sendaccountmail.py'

}
// アカウントのパスワードリセット
function resetAccountPass()
{
  $mail = PRMS('mail');
  $resetkey = PRMS('resetkey');
  $sql = "
    update ahdaccount
      set resetkey = '$resetkey', resetkeyts = CURRENT_TIMESTAMP
    where
      mail = '$mail';
  ";
  $rt = unvEdit(connectDb(), $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
}

// アカウントメール送信のラッパー
function sendAccountMail()
{
  global $cgiAdrress;
  $fname = PRMS('fname');
  $lname = PRMS('lname');
  $mail = urlencode(PRMS('mail'));
  $resetkey = PRMS('resetkey');
  $mode = PRMS('mode');
  $hname = PRMS('hname');
  $bname = PRMS('bname');
  $url =
    $cgiAdrress . "/py/sendaccountmail.py?" .
    "fname=$fname&lname=$lname&mail=$mail&resetkey=$resetkey&mode=$mode&" .
    "hname=$hname&bname=$bname";
  // $url = htmlspecialchars($url);
  $url = str_replace(' ', "%20", $url);

  // 2021/10/01追加 突然動かなくなった
  // https://qiita.com/izanari/items/f4f96e11a2b01af72846
  $options['ssl']['verify_peer'] = false;
  $options['ssl']['verify_peer_name'] = false;
  $json = file_get_contents($url, false, stream_context_create($options));
  echo $json;
}

// アカウントメール送信のラッパー
function sendFapTokenMail()
{
  global $cgiAdrress;
  $pname = PRMS('pname');
  $pmail = urlencode(PRMS('pmail'));
  $faptoken = PRMS('faptoken');
  $mode = PRMS('mode');
  $hname = PRMS('hname');
  $bname = PRMS('bname');
  $url =
    $cgiAdrress . "/py/sendfaptokenmail.py?" .
    "pname=$pname&pmail=$pmail&faptoken=$faptoken&mode=$mode&" .
    "hname=$hname&bname=$bname";
  $url = str_replace(' ', "%20", $url);

  $options['ssl']['verify_peer'] = false;
  $options['ssl']['verify_peer_name'] = false;
  $json = file_get_contents($url, false, stream_context_create($options));
  echo $json;
}

function sendFapNoticeMail()
{
  global $cgiAdrress;
  $pname = PRMS('pname');
  $pmail = urlencode(PRMS('pmail'));
  $item = PRMS('item');
  $hname = PRMS('hname');
  $bname = PRMS('bname');
  $name = PRMS('name');
  $token = PRMS('token');

  $url =
    $cgiAdrress . "/py/sendfapnoticemail.py?" .
    "pname=$pname&pmail=$pmail&item=$item&name=$name&" .
    "hname=$hname&bname=$bname&token=$token";
  $url = str_replace(' ', "%20", $url);

  $options['ssl']['verify_peer'] = false;
  $options['ssl']['verify_peer_name'] = false;
  $json = file_get_contents($url, false, stream_context_create($options));
  echo $json;
}

function sendFapNoticeMailPost()
{
  global $cgiAdrress;
  $params = array(
    'pname' => trim(PRMS('pname')),
    'pmail' => trim(PRMS('pmail')),
    'item' => trim(PRMS('item')),
    'hname' => trim(PRMS('hname')),
    'bname' => trim(PRMS('bname')),
    'name' => trim(PRMS('name')),
    'bcc' => trim(PRMS('bcc')),
    'token' => trim(PRMS('token')),
    'content' => trim(PRMS('content')),
    'title' => trim(PRMS('title')),
    'html' => trim(PRMS('html')),
  );

  $data = array();
  foreach ($params as $key => $value) {
    if (!empty($value)) {
      // if($key === 'pmail') {
      //   $value = urlencode($value);
      // }

      $data[$key] = $value;
    }
  }

  if (empty($data)) {
    header('Content-Type: application/json');
    echo json_encode(array('result' => false));
    return;
  }
  // var_dump($data);
  $url = $cgiAdrress . "/py/sendfapnoticemailpost.py";
  $options = array(
    'http' => array(
      'method' => 'POST',
      'header' => 'Content-type: application/x-www-form-urlencoded',
      'content' => http_build_query($data)
    ),
    'ssl' => array(
      'verify_peer' => false,
      'verify_peer_name' => false
    )
  );
  $context = stream_context_create($options);
  $json = file_get_contents($url, false, $context);
  // $json = json_encode($data);;
  if ($json === false) {
    echo 'An error occurred while sending the request.';
  } else {
    echo $json;
  }
}
function sendHtmlMail()
{
  global $cgiAdrress;
  $params = array(
    'pmail' => trim(PRMS('pmail')),
    'bcc' => trim(PRMS('bcc')),
    'content' => trim(PRMS('content')),
    'title' => trim(PRMS('title')),
    'replyto' => trim(PRMS('replyto')),
  );

  $data = array();
  foreach ($params as $key => $value) {
    if (!empty($value)) {
      // if($key === 'pmail') {
      //   $value = urlencode($value);
      // }
      $data[$key] = $value;
    }
  }

  if (empty($data)) {
    header('Content-Type: application/json');
    echo json_encode(array('result' => false));
    return;
  }
  // var_dump($data);
  $url = $cgiAdrress . "/py/sendhtmlmail.py";
  $options = array(
    'http' => array(
      'method' => 'POST',
      'header' => 'Content-type: application/x-www-form-urlencoded',
      'content' => http_build_query($data)
    ),
    'ssl' => array(
      'verify_peer' => false,
      'verify_peer_name' => false
    )
  );
  $context = stream_context_create($options);
  $json = file_get_contents($url, false, $context);
  // $json = json_encode($data);;
  if ($json === false) {
    echo 'An error occurred while sending the request.';
  } else {
    echo $json;
  }
}


// function sendFapNoticeMail(){
//   global $cgiAdrress;
//   $pname = PRMS('pname');
//   $pmail = urlencode(PRMS('pmail'));
//   $item = PRMS('item');
//   $hname = PRMS('hname');
//   $bname = PRMS('bname');
//   $name = PRMS('name');
//   $token = PRMS('token');

//   // curlセッションを初期化
//   $ch = curl_init();

//   // URLを設定
//   $url = $cgiAdrress . "/py/sendfapnoticemail.py";
//   curl_setopt($ch, CURLOPT_URL, $url);

//   // POSTメソッドを使用
//   curl_setopt($ch, CURLOPT_POST, true);

//   // パラメータを設定
//   $params = array(
//     'pname' => $pname,
//     'pmail' => $pmail,
//     'item' => $item,
//     'hname' => $hname,
//     'bname' => $bname,
//     'name' => $name,
//     'token' => $token
//   );
//   curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

//   // SSL設定を無効にする
//   curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//   curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

//   // 結果を文字列で取得
//   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

//   // セッションを実行
//   $result = curl_exec($ch);

//   // curlセッションを終了
//   curl_close($ch);

//   echo $result;
// }


// 自動送信のメールを作成
function createAutoMsg()
{
  $date = PRMS('date');
  $sql = "
    select user.*, 
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
    where
    user.mail != '' AND
    user.faptoken != '' AND  
    (
      user.enddate = '0000-00-00' OR
      user.enddate >= '$date'
    ) 
    and user.date <= '$date'
    and brunch.date = (
      SELECT MAX(date) from ahdbrunch 
      WHERE bid=user.bid and hid=user.hid and date<='$date'
    );
  ";
  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql);
  // $rt['dt'][0]['etc'] = json_decode($rt['dt'][0]['etc']);
  foreach ($rt['dt'] as &$val) {
    $val['etc'] = json_decode($val['etc']);
  }
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}


// リセットキーによるアカウントの取得
function getAccountByRestkey()
{
  $resetkey = PRMS('resetkey');
  $sql = "
    select * from ahdaccount
    where resetkey = '$resetkey'
    and TIMESTAMPDIFF(HOUR, resetkeyts, CURRENT_TIMESTAMP) < 72;
  ";
  $rt = unvList(connectDb(), $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
}

function listAccount()
{
  global $secret;
  if (PRMS('secret') != $secret) {
    $rt['msg'] = 'this is secret api.';
    echo json_encode($rt, JSON_UNESCAPED_UNICODE);
    return false;
  }
  $limitFraise = '';
  $limit = PRMS('limit');
  $offset = PRMS('offset');
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $mail = PRMS('mail');
  if ($offset == '')  $offset = 0;
  if ($limit != '') {
    $limitFraise = " limit $offset , $limit";
  }
  $whereFraise = "where TRUE ";
  if ($hid != '') {
    $whereFraise .= "and account.hid='$hid' ";
  }
  if ($bid != '') {
    $whereFraise .= "and account.bid='$bid' ";
  }
  if ($mail != '') {
    $whereFraise .= "and account.mail like '%$mail%' ";
  }
  $sql = "
    select  account.lname,account.fname,account.mail,
            account.passwd,account.permission,
            account.hid, account.bid,
            brunch.bname,com.hname,
            brunch.jino,brunch.fprefix
    from    ahdaccount account
    join    (
      select 
      hid,bid,bname,jino,fprefix,date from ahdbrunch 
      join (select hid,bid,max(date) date  from ahdbrunch GROUP by hid,bid) as b
      using (hid, bid, date)
    ) brunch 
    using   (hid, bid)
    join    ahdcompany com using (hid)
    $whereFraise
    order by mail,hid,bid
    $limitFraise;
  ";
  $rt = unvList(connectDb(), $sql);
  foreach ($rt['dt'] as &$val) {
    $val['passwd'] = decodeCph($val['passwd']);
  }
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
}

function sendFsExcelImg()
{
  $jino = PRMS('jino');
  $date = PRMS('date');
  $grid = PRMS('grid');

  $mysqli = connectDb();
  $sql = "
    insert into ahdfsexcel (jino,date,grid)
    values ('$jino','$date','$grid')
    on duplicate key update
    grid = '$grid',
    done = 0
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function fetchFsExcelImg()
{
  $count = PRMS('count');
  $all = PRMS('all');
  $mysqli = connectDb();
  if ($all) {
    $whereStr = "true";
  } else {
    $whereStr = "done = 0";
  }
  $sql = "
    select * from ahdfsexcel
    where $whereStr
    order by timestamp
    limit 0, $count
  ";
  $rt = unvList($mysqli, $sql);
  if ($rt['result'] == true) {
    // foreach($rt['dt'] as $d){
    //   $d['grid'] = json_decode($d['grid'], true);
    // }
    for ($i = 0; $i < count($rt['dt']); $i++) {
      $rt['dt'][$i]['grid'] = json_decode($rt['dt'][$i]['grid']);
    }
  }
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function setDoneFsExcelImg()
{
  $ts = PRMS('ts');
  $mysqli = connectDb();
  $sql = "
    update ahdfsexcel
    set done=1
    where timestamp<='$ts';
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
};

function sendSomeState()
{
  $mysqli = connectDb();
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $item = PRMS('item');
  $jino = PRMS('jino');
  // $state = PRMS('state');
  $state = $mysqli->real_escape_string(PRMS('state'));
  $keep = PRMS('keep');
  if (!$keep)  $keep = 0;
  $sql = "
    insert into ahdSomeState (hid,bid,date,jino,state,item,keep)
    values ('$hid','$bid','$date,','$jino','$state','$item','$keep')
    on duplicate key update
    state = '$state', keep = '$keep', timestamp = CURRENT_TIMESTAMP
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
};

function fetchSomeState()
{
  $mysqli = connectDb();
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $item = PRMS('item');
  $jino = PRMS('jino');

  // hidとbidのセットを指定するかjinoで指定するか
  if ($hid) {
    $whereStr = "hid='$hid' and bid='$bid' ";
  } else {
    $whereStr = "jino='$jino' ";
  }
  if ($date) {
    $whereStr .= "and date='$date' ";
  }
  if ($item) {
    $whereStr .= "and item='$item' ";
  }
  $sql = "
    select * from ahdSomeState
    where $whereStr
  ";
  $rt = unvList($mysqli, $sql);
  for ($i = 0; $i < count($rt['dt']); $i++) {
    $rt['dt'][$i]['state'] = json_decode($rt['dt'][$i]['state'], true);
    // if (json_last_error() !== JSON_ERROR_NONE) {
    //   echo 'json_decode error: ' . json_last_error_msg();
    //   // you might want to handle errors differently in your code
    // }
  }

  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
};

function sendAnyState()
{
  $mysqli = connectDb();
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $item = PRMS('item');
  // $state = PRMS('state');
  $state = $mysqli->real_escape_string(PRMS('state'));
  $keep = PRMS('keep');
  if (!$keep)  $keep = 0;
  $sql = "
    insert into ahdAnyState (hid,bid,date,state,item,keep)
    values ('$hid','$bid','$date,','$state','$item','$keep')
    on duplicate key update
    state = '$state', keep = '$keep', timestamp = CURRENT_TIMESTAMP
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
};

function fetchAnyState()
{
  $mysqli = connectDb();
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $item = PRMS('item');

  $whereStr = "hid='$hid' and bid='$bid' ";
  if ($date) {
    $whereStr .= "and date='$date' ";
  }
  if ($item) {
    $whereStr .= "and item='$item' ";
  }
  $sql = "
    select * from ahdAnyState
    where $whereStr
  ";
  $rt = unvList($mysqli, $sql);
  for ($i = 0; $i < count($rt['dt']); $i++) {
    $rt['dt'][$i]['state'] = json_decode($rt['dt'][$i]['state'], true);
    // if (json_last_error() !== JSON_ERROR_NONE) {
    //   echo 'json_decode error: ' . json_last_error_msg();
    //   // you might want to handle errors differently in your code
    // }
  }

  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
};


function deleteSomeState()
{
  $mysqli = connectDb();
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $timestamp = PRMS('timestamp');
  $item = PRMS('item');
  $jino = PRMS('jino');
  $date = PRMS('date');

  // hidとbidのセットを指定するかjinoで指定するか
  if ($hid) {
    $whereStr = "hid='$hid' and bid='$bid' ";
  } else {
    $whereStr = "jino='$jino' ";
  }
  if ($timestamp) {
    $whereStr .= "and timestamp<='$timestamp' ";
  } else {
    $whereStr .= "and date<='$date' ";
  }
  $whereStr .= "and item='$item' ";
  $sql = "
    delete from ahdSomeState
    where $whereStr
  ";
  $rt = unvEdit($mysqli, $sql);

  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
};

function getDbname()
{
  echo json_encode(DBNAME, JSON_UNESCAPED_UNICODE);
}


// ワムネットスクレイピング対応
function sendOfficeis()
{
  $jino = PRMS('jino');
  $pref = PRMS('pref');
  $bname = PRMS('bname');
  $hname = PRMS('hname');
  $service = PRMS('service');
  $others = PRMS('others');
  $lupdate = date("Y-m-d H:i:s");

  // CREATE TABLE IF NOT EXISTS `ahdofficeis` (
  //   `jino` varchar(12) NOT NULL COMMENT '事業所番号',
  //   `service` varchar(25) NOT NULL COMMENT 'サービス種別',
  //   `pref` varchar(4) NOT NULL COMMENT '県番号',
  //   `bname` varchar(60) NOT NULL COMMENT '事業所名',
  //   `hname` varchar(60) NOT NULL COMMENT '法人名',
  //   `others` mediumtext NOT NULL COMMENT 'その他',
  //   `lupdate` datetime NOT NULL COMMENT '更新日時'
  // ) ENGINE=InnoDB DEFAULT CHARSET=utf8;


  $mysqli = connectDb();

  $sql = "
    insert into ahdofficeis (
      jino, pref, bname, hname, service, 
      others, lupdate
    )
    values(
      '$jino', '$pref', '$bname', '$hname', '$service', 
      '$others', '$lupdate'
    )
    on duplicate key update
      jino = '$jino',
      pref = '$pref',
      bname = '$bname',
      hname = '$hname',
      service = '$service',
      others = '$others',
      lupdate = '$lupdate'
  ";
  $rt = unvEdit($mysqli, $sql);

  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

// ワムネットスクレイピング対応
function fetchOfficeis()
{
  $pref = PRMS('pref');
  $jino = PRMS('jino');
  $newoffice = PRMS('newoffice');
  $whereStr = "";

  if ($pref) {
    $whereStr = " where pref = '$pref'";
  } else if ($jino) {
    $whereStr = " where jino = '$jino'";
  } else if ($newoffice) {
    $whereStr = " 
    where DATEDIFF(CURDATE(), 
    STR_TO_DATE(REPLACE(JSON_EXTRACT(others, '$.\"事業の開始(予定)年月日\"'), '\"', ''), '%Y/%m/%d')) <= $newoffice";
  } else {
    $whereStr = " false";
  }

  $sql = "
    select * from ahdofficeis
  " . $whereStr;
  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql);
  if (is_array($rt['dt']) && count($rt['dt'])) {
    foreach ($rt['dt'] as $key => $value) {
      $rt['dt'][$key]['others'] = json_decode($value['others']);
    }
  }
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}


// 事業所情報とusersから最小の月を取得
// スケジュールから最大の月を取得
function getMinMaxOfMonnth()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $sql = "
    select MIN(date) min from ahdbrunch 
    where bid='$bid' and hid='$hid' GROUP BY bid,hid
  ";
  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql);
  $rt0 = $rt;
  $min = '';
  if (count($rt['dt'])) {
    $min = $rt['dt'][0]['min'];
  }
  $sql = "
    select MIN(date) min from ahduser
    where bid='$bid' and hid='$hid' GROUP BY bid,hid
  ";
  $rt = unvList($mysqli, $sql);
  $rt1 = $rt;
  $minu = '';
  if (count($rt['dt'])) {
    $minu = $rt['dt'][0]['min'];
  }
  if ($minu > $min) $min = $minu;
  $sql = "
    select MAX(date) max from ahdschedule 
    where bid='$bid' and hid='$hid' GROUP BY bid,hid
  ";
  $rt = unvList($mysqli, $sql);
  $rt2 = $rt;
  $max = date('Y-m-d');
  if (count($rt['dt'])) {
    $max = $rt['dt'][0]['max'];
  }
  $rt = [];
  $rt['rt0'] = $rt0;
  $rt['rt1'] = $rt1;
  $rt['rt2'] = $rt2;
  $rt['min'] = $min;
  $rt['max'] = $max;
  $rt['result'] = $rt0['result'] && $rt1['result'];
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}
// ユーザーの次の変更履歴を取得する
function fetchNextUserInfo()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');

  $sql = "
    select uid, name, MIN(date) next from ahduser
    where hid='$hid' and bid='$bid' and date>'$date'
    group by hid,bid,uid
  ";
  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

// 該当のユーザーを全て削除する
// allnewをつけると以降全て
function deleteAllUser()
{
  $mysqli = connectDb();
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $allnew = PRMS('allnew');

  if ($allnew)  $ope = '>=';
  else          $ope = '=';

  $sql = "
    delete from ahduser
    where hid='$hid' and bid='$bid' and date $ope '$date'
  ";
  $rt = unvEdit($mysqli, $sql);

  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
};

// 該当のスケジュールを削除する
// allnewをつけると以降全て
function deleteSchedule()
{
  $mysqli = connectDb();
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $allnew = PRMS('allnew');

  if ($allnew)  $ope = '>=';
  else          $ope = '=';

  $sql = "
    delete from ahdschedule
    where hid='$hid' and bid='$bid' and date $ope '$date'
  ";
  $rt = unvEdit($mysqli, $sql);

  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
};

function deleteCalender()
{
  $mysqli = connectDb();
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $allnew = PRMS('allnew');

  if ($allnew)  $ope = '>=';
  else          $ope = '=';

  $sql = "
    delete from ahdcalender
    where hid='$hid' and bid='$bid' and date $ope '$date'
  ";
  $rt = unvEdit($mysqli, $sql);

  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
};

// アカウント削除
function deleteAccount()
{
  $mysqli = connectDb();
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $mail = PRMS('mail');
  $sql = "
    delete from ahdaccount
    where hid='$hid' and bid='$bid' and mail='$mail'
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}


// 該当のブランチを削除
// allnewをつけると以降全て
function deleteBrunch()
{
  $mysqli = connectDb();
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $allnew = PRMS('allnew');

  if ($allnew)  $ope = '>=';
  else          $ope = '=';

  $sql = "
    delete from ahdbrunch
    where hid='$hid' and bid='$bid' and date $ope '$date'
  ";
  $rt = unvEdit($mysqli, $sql);

  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
};



// ユーザーの次の変更履歴を取得する
function fetchNextComInfo()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');

  $sql = "
    select MIN(date) next from ahdbrunch
    where hid='$hid' and bid='$bid' and date>'$date'
    group by hid,bid
  ";
  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function copySchedule()
{
  $mysqli = connectDb();
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $dst = PRMS('dst');

  $sql = "
    INSERT into ahdschedule select hid, bid, '$dst', schedule 
    from ahdschedule src
    where hid= '$hid' and bid='$bid' and date='$date'
    on duplicate key update
    hid = '$hid',
    bid = '$bid',
    date = '$dst',
    schedule = src.schedule;
  ";
  $rt = unvEdit($mysqli, $sql);

  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}


function sendUsageFee()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $fee = PRMS('fee');
  $hide = PRMS('hide');
  $adjust = PRMS('adjust');
  $mysqli = connectDb();
  $sql = "
  insert into ahdusagefee (hid,bid,date,fee,hide,adjust)
  values ('$hid','$bid','$date','$fee','$hide', '$adjust')
  on duplicate key update
  fee = '$fee', hide = '$hide', adjust = '$adjust' ;
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function fetchUsageFee()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  if ($bid) {
    $whereStr = " and bid = '$bid' ";
  } else {
    $whereStr = "";
  }
  $mysqli = connectDb();
  if ($bid) {
    $sql = "
      select ahdusagefee.*, brunch.bname, com.hname, com.shname, brunch.sbname,
      brunch.bdate
      from ahdusagefee
      join ahdcompany com using (hid)
      join (
        SELECT date bdate, hid,bid,bname,sbname from ahdbrunch 
      ) as brunch
      using (hid, bid)
      where hid = '$hid' $whereStr
      and date = (
        SELECT MAX(date) from ahdusagefee 
        WHERE hid='$hid' $whereStr and date<='$date'
      )
      and bdate = (
        SELECT MAX(date) from ahdbrunch 
        WHERE hid='$hid' and bid='$bid' and date<='$date'
      );
    ";
  } else {
    $sql = "
      select uf.date, uf.hid, uf.bid, uf.fee, uf.adjust, uf.hide,
      com.hname, com.shname,
      brunch.bname, brunch.sbname, brunch.bdate
      from ahdusagefee uf
      join ahdcompany com using (hid)
      join (
        SELECT date bdate, hid,bid,bname,sbname from ahdbrunch 
      ) as brunch
      using (hid, bid)
      join (
        select max(date) maxdate, hid, bid from ahdusagefee
        where date <= '$date' GROUP BY hid, bid 
      ) as last
      on last.hid = uf.hid
      and last.bid = uf.bid
      and last.maxdate = uf.date
      where uf.hid = '$hid' 
      and bdate = (
        SELECT MAX(date) from ahdbrunch 
        WHERE hid='$hid' and bid=uf.bid and date<='$date'
      );
    ";
  }

  // "join (
  //   SELECT MAX(date) mdate, date, uid,bid,hid FROM `ahduser` 
  //   where date <= '$date' GROUP BY uid,hid,bid    
  // )
  // as lastupdated 
  // on lastupdated.uid = user.uid
  // and lastupdated.bid = user.bid
  // and lastupdated.hid = user.hid
  // and lastupdated.mdate = user.date

  $rt = unvList($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function removeUsageFee()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  if ($bid) {
    $whereStr = " and bid = '$bid' ";
  } else {
    $whereStr = "";
  }
  $mysqli = connectDb();
  $sql = "
    delete from ahdusagefee
    where hid = '$hid' and date= '$date' $whereStr;
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function fetchContactsForFAP()
{
  // mailとtokenでahduserを検索 listを作成
  // hid,bidが指定されていたらlistから絞り込み
  // 指定されていなかったらlisの0番目をuserとして特定
  // comとbrunchをジョイン 最新のレコードセットを取得
  // 事業所を特定
  // afpConfigを取得 rtに追加 見つからなかったら初期値を追加
  // comとして事業所情報をrtにセット
  // ussrsの最新のレコードセットを取得
  // usersより該当の利用者情報を取得rtにセット
  // usersより兄弟情報を取得
  // 兄弟情報をusers.bros配下にセット
  // scheduleを取得
  // rtにセット
  // 兄弟のスケジュールを取得してrtにセット
  // threadを取得してセット
  // 取得だけなのでトランザクションは使わない
  global $returnSql;
  $frt = []; // 最終のリターン
  $date = PRMS('date', true);
  $mail = PRMS('mail', true);
  $token = PRMS('token', true);
  $hid = PRMS('hid', true);
  $bid = PRMS('bid', true);
  $uid = PRMS('uid', true);
  // mailとtokenでahduserを検索 listを作成
  $sql = "
    select user.*, 
      brunch.bname, brunch.sbname, brunch.jino,  
      com.hname, com.shname
    from ahduser as user 
    join ahdbrunch as brunch using (hid, bid)
    join ahdcompany as com using (hid)" .
    "join (
      SELECT MAX(date) mdate, date, uid,bid,hid FROM `ahduser` 
      where date <= '$date' and pmail = '$mail' 
      GROUP BY uid,hid,bid    
    )
    as lastupdated 
    on lastupdated.uid = user.uid
    and lastupdated.bid = user.bid
    and lastupdated.hid = user.hid
    and lastupdated.mdate = user.date
    join (
      SELECT
        MAX(date) mdate, hid, bid
      from
        ahdbrunch
      WHERE
        date <= '$date'
      GROUP BY
        hid,
        bid
    ) as lastbrunch
    on lastbrunch.bid = user.bid
    and lastbrunch.hid = user.hid
    and lastbrunch.mdate = brunch.date
    where
    (
      user.enddate = '0000-00-00' OR
      user.enddate >= '$date'
    ) 
    and user.date <= '$date';
  ";
  $mysqli = connectDb();

  $rt = unvList($mysqli, $sql);
  if ($rt['result']) {
    $users = $rt['dt'];
  } else {
    $frt['users'] = null;
    $frt['result'] = false;
    $frt['msg'] = 'user fetch error.';
    echo json_encode($frt, JSON_UNESCAPED_UNICODE);
    $mysqli->close();
    return false;
  }
  // hid bid を上6桁を作成する
  $i = 0;
  foreach ($users as $v) {
    $users[$i]['hids'] = substr($v['hid'], 0, 6);
    $users[$i]['bids'] = substr($v['bid'], 0, 6);
    $i++;
  }
  $fUsers = [];
  // usersをtokenで絞り込み
  // tokenは複数が連結されて送信されてくるので部分一致で返す
  foreach ($users as $v) {
    if (!$v['faptoken']) continue;
    if (strpos($token, $v['faptoken']) !== false) {
      $fUsers[] = $v;
    }
  }
  // hid,bidが指定されていたらlistから絞り込み
  $users = $fUsers;
  $frt['users'] = $fUsers;

  $fUsers = [];
  foreach ($users as $v) {
    if ($hid) $hidMatch = strpos($v['hid'], $hid) !== false;
    else $hidMatch = false;
    if ($bid) $bidMatch = strpos($v['bid'], $bid) !== false;
    else $bidMatch = false;
    // echo('prms ' . $hid . $bid . ' dbrow ' . $v['hid'] . $v['bid'] . '<br>');
    // hidのみ指定済み
    if ($hid && $hidMatch && !$bid) {
      $fUsers[] = $v;
    }
    // bid hid 指定済み
    else if ($hidMatch && $bidMatch) {
      $fUsers[] = $v;
    }
    // 指定がない 全部追加
    else if (!$hid && !$bid) {
      $fUsers[] = $v;
    }
  }
  if ($hid) {
    $frt['filtered_users'] = $fUsers;
  }

  // $frt['users'] = $fUsers;
  // $frt['usersOrg'] = $usersOrg;
  if ($returnSql) $frt['sql'][] = $rt['sql'];
  // 該当ユーザーの特定 絞り込みの結果件数が0だと hid bidで絞り込み前のユーザーから
  // ユーザー特定を行う それも見つからなかったらエラーにする
  $matched = false;
  if (count($fUsers) > 0 && $uid) {
    foreach ($fUsers as $v) {
      if ($v['uid'] === $uid) {
        $userInfo = $v;
        $matched = true;
        break;
      }
    }
  } else if (count($fUsers) > 0) {
    $userInfo = $fUsers[0];
    $matched = true;
  }
  // ユーザーが特定できない場合
  if (!$matched) {
    $frt['userInfo'] = null;
    $frt['result'] = false;
    $frt['msg'] = 'user not found.';
    echo json_encode($frt, JSON_UNESCAPED_UNICODE);
    $mysqli->close();
    return false;
  }
  // hid, bid取得し直し。引数から与えられていないこともある
  // comとbrunchをジョイン 最新のレコードセットを取得
  $hid = $userInfo['hid'];
  $bid = $userInfo['bid'];
  $frt['userInfo'] = $userInfo;
  $sql = "
    select brunch.*, 
    com.hname, com.shname, com.postal cpostal, com.city ccity,
    com.address caddress, com.tel ctel, com.fax cfax, com.etc cetc,
    bext.ext
    from ahdbrunch brunch 
    join ahdcompany com
    using (hid)
    left join ahdbrunchext as bext
    on brunch.hid = bext.hid
    and brunch.bid = bext.bid
    where brunch.hid = '$hid'
    and brunch.bid = '$bid'
    and brunch.date = (
      SELECT MAX(date) from ahdbrunch 
      WHERE bid='$bid' and hid='$hid' and date<='$date'
    );
  ";
  $rt = unvList($mysqli, $sql);
  // afpConfigを取得 rtに追加 見つからなかったら初期値を追加
  $config = false;
  $messagetoall = '';
  if ($rt['result']) {
    $frt['com'] = $rt['dt'][0];
    // コンフィグの存在確認
    if (array_key_exists('etc', $rt['dt'][0])) {
      $comEtc = json_decode($rt['dt'][0]['etc'], true);
      if (array_key_exists('settingContactBook', $comEtc)) {
        $config = $comEtc['settingContactBook'];
      }
    }
    if (!$config) {
      $config = array(
        'dummy' => 'dummy'
      );
    }
    $frt['config'] = $config;
    // messagetoallの確認
    if (array_key_exists('ext', $rt['dt'][0])) {
      $ext = json_decode($rt['dt'][0]['ext'], true);
      if ($ext) {
        if (array_key_exists('messagetoall', $ext)) {
          $messagetoall = $ext['messagetoall'];
        }
      }
    }
  } else {
    $frt['com'] = null;
    $frt['result'] = false;
    $frt['msg'] = 'com fetch error.';
    echo json_encode($frt, JSON_UNESCAPED_UNICODE);
    $mysqli->close();
    return false;
  }
  $frt['messagetoall'] = $messagetoall;

  if ($returnSql) $frt['sql'][] = $rt['sql'];
  // usersより兄弟情報を取得
  // 兄弟情報をusers.bros配下にセット
  $pname = $userInfo['pname'];
  $pphone = $userInfo['pphone'];
  $brosIndex = $userInfo['brosIndex'];
  $bros = [];
  $brosUID = [];
  foreach ($users as $v) {
    // 電話番号と保護者の名前が同じユーザーを取得
    // 兄弟順位が一緒なら本人なのでスキップ
    if ($v['pname'] !== $pname) continue;
    if ($v['pphone'] !== $pphone) continue;
    if ($v['hid'] !== $hid) continue;
    if ($v['bid'] !== $bid) continue;
    if ($v['brosIndex'] === $brosIndex) continue;
    $bros[] = $v;
    $brosUID[] = $v['uid'];
  }
  $frt['userInfo']['bros'] = $bros;
  // scheduleを取得
  $sql = "
    select hid,bid,date, schedule 
    from ahdschedule
    where hid = '$hid'
    and bid = '$bid'
    and date = '$date'
  ";
  $rt = unvList($mysqli, $sql);
  // userinfoよりschedule等を特定するuidを取得
  $UID = 'UID' . $userInfo['uid'];
  $schedule = [];
  $schedule['bros'] = [];
  $t = false;
  if ($rt['result']) {
    if (count($rt['dt'])) {
      $t = json_decode($rt['dt'][0]['schedule'], true);
      if (array_key_exists($UID, $t)) {
        $schedule = $t[$UID];
      }
    }
    // 兄弟のスケジュールを追加
    foreach ($brosUID as $v) {
      if (!$t) continue;
      if (array_key_exists('UID' . $v, $t)) {
        $schedule['bros']['UID' . $v] = $t['UID' . $v];
      }
    }
  } else {
    $frt['schedule'] = null;
    $frt['result'] = false;
    $frt['msg'] = 'scedule fetch error.';
    echo json_encode($frt, JSON_UNESCAPED_UNICODE);
    $mysqli->close();
    return false;
  }
  $frt['schedule'] = $schedule;
  if ($returnSql) $frt['sql'][] = $rt['sql'];
  // threadを取得してセット
  $sql = "
    select hid,bid,date,contacts 
    from ahdcontacts
    where hid = '$hid'
    and bid = '$bid'
    and date = '$date'
  ";
  $rt = unvList($mysqli, $sql);
  $contacts = [];
  if ($rt['result']) {
    if (count($rt['dt'])) {
      $t = json_decode($rt['dt'][0]['contacts'], true);
      if (array_key_exists($UID, $t)) {
        $contacts = $t[$UID];
      }
    }
  } else {
    $frt['contacts'] = null;
    $frt['result'] = false;
    $frt['msg'] = 'contacts fetch error.';
    echo json_encode($frt, JSON_UNESCAPED_UNICODE);
    $mysqli->close();
    return false;
  }

  foreach ($contacts as $key => &$value) {
    if (preg_match('/^D2[0-9]+/', $key)) {
      if (array_key_exists('draft', $value[2])) {
        if ($value[2]['draft']) {
          $value[2]['content'] = '';
          unset($value[2]['photos']);
          unset($value[2]['vital']);
        }
      }
      if (array_key_exists('draft', $value[0])) {
        if ($value[0]['draft']) {
          $value[0]['content'] = '';
          unset($value[2]['photos']);
          unset($value[2]['vital']);
        }
      }
    }
  }
  $frt['contacts'] = $contacts;
  if ($returnSql) $frt['sql'][] = $rt['sql'];
  $frt['result'] = true;
  echo json_encode($frt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}
// カレンダーファールが正しいかどうか確認を行う
function checkCalender()
{
  $sql = "
    select * from ahdcalender;
  ";
  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql);
  $errList = [];
  if (count($rt['dt'])) {
    foreach ($rt['dt'] as $e) {
      $date = $e['date'];
      $dateList = json_decode($e['dateList'], true);
      if (count($dateList)) {
        if ($date != $dateList[0]['date']) {
          $errList[] = array(
            'hid' => $e['hid'],
            'bid' => $e['bid'],
            'date' => $date,
          );
        }
      }
    }
  }
  $rtnDt = array(
    'count' => count($rt['dt']),
    'errList' => $errList,
    'rt' => $rt,
  );
  echo json_encode($rtnDt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}
// スケジュールデータが存在する月を列挙する
function fetchScheduleAria()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $sql = "
    select date from ahdschedule
    where hid = '$hid'
    and bid = '$bid'
  ";
  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function sendUsersExt()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $uid = PRMS('uid');
  $ext = PRMS('ext');
  $mysqli = connectDb();
  $sql = "
    insert into ahdusersext (hid,bid,uid,ext)
    values ('$hid','$bid','$uid','$ext')
    on duplicate key update
    ext = '$ext'
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function fetchUsersExt()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $uid = PRMS('uid');
  $mysqli = connectDb();
  $sql = "
    select * from ahdusersext
    where hid = '$hid' and bid = '$bid' and uid = '$uid';
  ";
  $rt = unvList($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}


function sendComExt()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $ext = PRMS('ext');
  $mysqli = connectDb();
  $sql = "
    insert into ahdbrunchext (hid,bid,ext)
    values ('$hid','$bid','$ext')
    on duplicate key update
    ext = '$ext'
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function fetchUsersHist()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $uid = PRMS('uid');
  $mysqli = connectDb();
  $sql = "
    select * from ahduser
    where hid = '$hid' and bid = '$bid' and uid = '$uid';
  ";
  $rt = unvList($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function fetchAllHnoFromUsers()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $mysqli = connectDb();
  $sql = "
    select hno from ahduser
    where hid = '$hid' and bid = '$bid'
  ";
  $rt = unvList($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function sendAlfamiSmsCode()
{
  global $cgiAdrress;
  $phone_number = PRMS('phone_number');

  $url =
    $cgiAdrress . "/py/alfami_smscode.py?" .
    "phone_number=$phone_number";

  $options['ssl']['verify_peer'] = false;
  $options['ssl']['verify_peer_name'] = false;
  $json = file_get_contents($url, false, stream_context_create($options));
  echo $json;
}


function getHolidays()
{
  $year = PRMS('year');
  $v = @file_get_contents("https://holidays-jp.github.io/api/v1/$year/date.json");

  if ($v === FALSE) {
    // stdClass()は空のJSONオブジェクトを生成します
    echo json_encode(['result' => false, 'dt' => new stdClass()]);
    return;
  }

  echo json_encode(['result' => true, 'dt' => json_decode($v)]);
}
function getUsersTel()
{
  $phone = PRMS('phone');
  $phone = preg_replace("/[^0-9]/", "", $phone); // 数字以外の文字を除去
  if (!$phone) {
    $rt['result'] = false;
    $rt['msg'] = 'parameter phone required.';
    echo json_encode($rt, JSON_UNESCAPED_UNICODE);
    return false;
  }
  $mysqli = connectDb();
  $sql = "
    SELECT 
      c.hname, d.bname,
      a.hid, a.bid, a.uid, a.name, a.pname, a.birthday, 
      a.pphone, a.pphone1, a.date, a.faptoken,
      e.ext,
      a.hno, d.sbname, d.bname
    FROM
      ahduser a
    INNER JOIN (
      SELECT hid, bid, uid, MAX(date) as max_date
      FROM ahduser
      GROUP BY hid, bid, uid
    ) umax
    ON a.hid = umax.hid AND a.bid = umax.bid 
    AND a.uid = umax.uid AND a.date = umax.max_date
    INNER JOIN (
      SELECT hid, bid, MAX(date) as max_date
      FROM ahdbrunch
      GROUP BY hid, bid
    ) dmax
    ON a.hid = dmax.hid AND a.bid = dmax.bid
    INNER JOIN ahdcompany c
    ON a.hid = c.hid
    INNER JOIN ahdbrunch d
    ON a.hid = d.hid AND a.bid = d.bid AND d.date = dmax.max_date
    INNER JOIN ahdbrunchext e
    ON a.hid = e.hid AND a.bid = e.bid
  ";

  $rt = unvList($mysqli, $sql);
  $filteredResults = [];

  foreach ($rt['dt'] as $row) {
    $pphone = preg_replace("/[^0-9]/", "", $row['pphone']);
    $pphone1 = preg_replace("/[^0-9]/", "", $row['pphone1']);

    if ($pphone == $phone || $pphone1 == $phone) {
      $filteredResults[] = $row;
    }
  }

  $rt['dt'] = $filteredResults;
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function getOfiiceTelForSms()
{
  $phone = PRMS('phone');
  // 現在の日付から
  $d = new DateTime();
  $yearMonth = $d->format('Y-m');
  $date = $yearMonth . "-01";
  $phone = preg_replace("/[^0-9]/", "", $phone); // 数字以外の文字を除去
  if (!$phone) {
    $rt['result'] = false;
    $rt['msg'] = 'parameter phone required.';
    echo json_encode($rt, JSON_UNESCAPED_UNICODE);
    return false;
  }
  $mysqli = connectDb();
  $sql = "
    select brunch.*, 
    com.hname, com.shname, com.postal cpostal, com.city ccity,
    com.address caddress, com.tel ctel, com.fax cfax, com.etc cetc,
    bext.ext
    from ahdbrunch brunch 
    join ahdcompany com
    using (hid)
    join ahdbrunchext as bext
      on brunch.hid = bext.hid
      and brunch.bid = bext.bid
    where brunch.date = (
      SELECT MAX(date) from ahdbrunch 
      WHERE date<='$date'
    )
    ;
  ";

  $rt = unvList($mysqli, $sql);
  $filteredResults = [];

  foreach ($rt['dt'] as $row) {
    $ext = json_decode($row['ext'], true);

    // smsPhoneが存在するか確認
    if (isset($ext['smsPhone'])) {
      $pphone = preg_replace("/[^0-9]/", "", $ext['smsPhone']);
      $row['smsPhone'] = $ext['smsPhone'];
      if ($pphone == $phone) {
        unset($row['ext']);
        unset($row['addiction']);
        unset($row['etc']);
        $filteredResults[] = $row;
      }
    }
  }
  $rt['dt'] = $filteredResults;
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}


// function getUsersTel(){
//   $mysqli = connectDb();
//   $phone = PRMS('phone');
//   $phone = preg_replace("/[^0-9]/", "", $phone); // 数字以外の文字を除去

//   $sql = "
//     SELECT 
//       c.hname, d.bname,
//       a.hid, a.bid, a.uid, a.name, a.pname, a.birthday, 
//       a.pphone, a.pphone1, a.date, a.faptoken
//     FROM
//       ahduser a
//     INNER JOIN (
//       SELECT hid, bid, uid, MAX(date) as max_date
//       FROM ahduser
//       GROUP BY hid, bid, uid
//     ) umax
//     ON a.hid = umax.hid AND a.bid = umax.bid 
//     AND a.uid = umax.uid AND a.date = umax.max_date
//     INNER JOIN (
//       SELECT hid, bid, MAX(date) as max_date
//       FROM ahdbrunch
//       GROUP BY hid, bid
//     ) dmax
//     ON a.hid = dmax.hid AND a.bid = dmax.bid
//     INNER JOIN ahdcompany c
//     ON a.hid = c.hid
//     INNER JOIN ahdbrunch d
//     ON a.hid = d.hid AND a.bid = d.bid AND d.date = dmax.max_date
//   ";

//   $result = $mysqli->query($sql);
//   $filteredResults = [];

//   while ($row = $result->fetch_assoc()) {
//     $pphone = preg_replace("/[^0-9]/", "", $row['pphone']);
//     $pphone1 = preg_replace("/[^0-9]/", "", $row['pphone1']);

//     if ($pphone == $phone || $pphone1 == $phone) {
//       $filteredResults[] = $row;
//     }
//   }

//   echo json_encode($filteredResults, JSON_UNESCAPED_UNICODE);
//   $mysqli->close();
// }


function fetchSchBackupList()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $mysqli = connectDb();
  $sql = "
    select date,timestamp,created from ahdschedule_backup
    where hid = '$hid' and bid = '$bid'
  ";
  $rt = unvList($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}
function fetchSchBackup()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $created = PRMS('created');
  $date = PRMS('date');
  $mysqli = connectDb();
  $sql = "
    select * from ahdschedule_backup
    where hid = '$hid' and bid = '$bid'
    and created = '$created' and date = '$date';
  ";
  $rt = unvList($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function fetchDailyReport()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $sql = "
    select hid,bid,date,dailyreport, UNIX_TIMESTAMP(timestamp) * 1000 AS timestamp
    from ahddailyreport
    where hid = '$hid'
    and bid = '$bid'
    and date = '$date'
  ";
  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql);
  if (count($rt['dt'])) {
    $rt['dt'][0]['dailyreport'] = json_decode($rt['dt'][0]['dailyreport']);
    // オブジェクトに新しいプロパティを追加
    $rt['dt'][0]['dailyreport']->timestamp = $rt['dt'][0]['timestamp'];
  }
  $jsn = json_encode(escapeChar($rt), JSON_UNESCAPED_UNICODE);
  echo $jsn;
  $mysqli->close();
}

function sendPartOfDailyReport()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $partOfSch = PRMS('partOfRpt');
  $mysqli = connectDb();
  $mysqli->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
  $sql = "
    select dailyreport from ahddailyreport 
    where hid='$hid'
    and bid='$bid'
    and date='$date'
    FOR UPDATE;
  ";
  // // $schedule = '{"D20210701":{"end":"18:30","start":"13:40","service":"放課後等デイサービス","transfer":["自宅","自宅"],"offSchool":0,"actualCost":{"おやつ":100}},"D20210703":{"end":"17:00","start":"13:30","service":"放課後等デイサービス","transfer":["学校","自宅"],"offSchool":0,"actualCost":{"おやつ":100}}}';
  // echo $sql;
  $rt = unvList($mysqli, $sql);

  if (count($rt['dt'])) {
    $preSch = $rt['dt'][0]['dailyreport'];
  } else {
    $preSch = '{}'; // 検索できなかったらエラーにせずに空白オブジェクト
  }

  if (!$preSch) {
    echo '{"result":false}';
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }
  // echo ($preSch);
  $preSch = mb_convert_encoding($preSch, 'UTF-8');
  $preSch = json_decode($preSch, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_last_error_msg();
    echo '{"result":false, "error": "Invalid preSch JSON"}';
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }

  $partOfSch = json_decode($partOfSch, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    echo '{"result":false, "error": "Invalid partOfSch JSON"}';
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }

  // ロック確認のために待機
  // sleep(SLEEP);
  // マージする配列があるかどうか確認
  if (is_array($preSch) && is_array($partOfSch)) {
    $merged = array_merge($preSch, $partOfSch);
  } else {
    echo '{"result":false}';
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }
  // var_dump($merged);
  // echo('<br><br>');
  // echo ('final json<br>');
  $finalJson = json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $finalJson = escapeChar($finalJson);
  $finalJson = mysqli_real_escape_string($mysqli, $finalJson);

  // var_dump($finalJson);

  // $newSch = array_merge($preSch, $sch);
  // $newSchJson = json_encode($newSch, JSON_UNESCAPED_SLASHES);
  $sql = "
    insert into ahddailyreport (hid,bid,date,dailyreport)
    values ('$hid','$bid','$date','$finalJson')
    on duplicate key update
    dailyreport = '$finalJson'
  ";

  $rt = unvEdit($mysqli, $sql);
  if ($rt['result']) {
    $mysqli->commit();
  } else {
    $mysqli->rollback();
  }
  $mysqli->close();
  echo json_encode($rt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function sendPartOfDailyReportWithKey()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $partOfRpt = PRMS('partOfRpt');
  $key1 = PRMS('key1');

  // パラメータのエラーチェック
  if (empty($hid)) {
    echo json_encode(['result' => false, 'error' => 'hid is missing or empty'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return false;
  }

  if (empty($bid)) {
    echo json_encode(['result' => false, 'error' => 'bid is missing or empty'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return false;
  }

  if ($hid === $bid) {
    echo json_encode(['result' => false, 'error' => 'hid and bid must not be the same'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return false;
  }

  $mysqli = connectDb();
  $mysqli->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);

  $sql = "
    SELECT dailyreport FROM ahddailyreport 
    WHERE hid='$hid' AND bid='$bid' AND date='$date' FOR UPDATE;
  ";

  $rt = unvList($mysqli, $sql);

  if (count($rt['dt'])) {
    $preSch = $rt['dt'][0]['dailyreport'];
  } else {
    $preSch = '{}'; // 検索できなかったらエラーにせずに空白オブジェクト
  }

  if (!$preSch) {
    echo '{"result":false}';
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }

  $preSch = mb_convert_encoding($preSch, 'UTF-8');
  $preSch = json_decode($preSch, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_last_error_msg();
    echo '{"result":false, "error": "Invalid preSch JSON"}';
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }

  $partOfRpt = json_decode($partOfRpt, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    echo '{"result":false, "error": "Invalid partOfRpt JSON"}';
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }

  // $key1の値を指定されたUID配下に追加または置換
  if (isset($preSch[$key1]) && is_array($preSch[$key1])) {
    foreach ($partOfRpt as $key => $value) {
      $preSch[$key1][$key] = $value;
    }
  } else {
    $preSch[$key1] = $partOfRpt;
  }

  $finalJson = json_encode($preSch, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $finalJson = escapeChar($finalJson);
  $finalJson = mysqli_real_escape_string($mysqli, $finalJson);

  $sql = "
    INSERT INTO ahddailyreport (hid, bid, date, dailyreport)
    VALUES ('$hid', '$bid', '$date', '$finalJson')
    ON DUPLICATE KEY UPDATE
    dailyreport = '$finalJson'
  ";

  $rt = unvEdit($mysqli, $sql);
  if ($rt['result']) {
    $mysqli->commit();
  } else {
    $mysqli->rollback();
  }
  $mysqli->close();
  echo json_encode($rt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function sendPartOfDailyReportWith2Key()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $key1 = PRMS('key1');
  $key2 = PRMS('key2');
  $partOfRpt = PRMS('partOfRpt');

  // パラメータのエラーチェック
  if (empty($hid)) {
    echo json_encode(['result' => false, 'error' => 'hid is missing or empty'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return false;
  }

  if (empty($bid)) {
    echo json_encode(['result' => false, 'error' => 'bid is missing or empty'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return false;
  }

  if ($hid === $bid) {
    echo json_encode(['result' => false, 'error' => 'hid and bid must not be the same'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return false;
  }

  $mysqli = connectDb();
  $mysqli->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);

  $sql = "
    SELECT dailyreport FROM ahddailyreport 
    WHERE hid='$hid' AND bid='$bid' AND date='$date' FOR UPDATE;
  ";

  $rt = unvList($mysqli, $sql);

  if (count($rt['dt'])) {
    $preSch = $rt['dt'][0]['dailyreport'];
  } else {
    $preSch = '{}'; // 検索できなかったらエラーにせずに空白オブジェクト
  }

  if (!$preSch) {
    echo '{"result":false}';
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }

  $preSch = mb_convert_encoding($preSch, 'UTF-8');
  $preSch = json_decode($preSch, false); // オブジェクトとしてデコード
  if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_last_error_msg();
    echo '{"result":false, "error": "Invalid preSch JSON"}';
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }

  $partOfRpt = json_decode($partOfRpt, false); // オブジェクトとしてデコード
  if (json_last_error() !== JSON_ERROR_NONE) {
    echo '{"result":false, "error": "Invalid partOfRpt JSON"}';
    $mysqli->rollback();
    $mysqli->close();
    return false;
  }

  // $key1の配下に$key2を追加または置換
  if (!isset($preSch->$key1) || !is_object($preSch->$key1)) {
    $preSch->$key1 = new stdClass();
  }

  if (!isset($preSch->$key1->$key2) || !is_object($preSch->$key1->$key2)) {
    $preSch->$key1->$key2 = new stdClass();
  }

  // $key2の配下にpartOfRptを追加または置換
  foreach ($partOfRpt as $key => $value) {
    $preSch->$key1->$key2->$key = $value;
  }

  $finalJson = json_encode($preSch, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $finalJson = escapeChar($finalJson);
  $finalJson = mysqli_real_escape_string($mysqli, $finalJson);

  $sql = "
    INSERT INTO ahddailyreport (hid, bid, date, dailyreport)
    VALUES ('$hid', '$bid', '$date', '$finalJson')
    ON DUPLICATE KEY UPDATE
    dailyreport = '$finalJson'
  ";

  $rt = unvEdit($mysqli, $sql);
  if ($rt['result']) {
    $mysqli->commit();
  } else {
    $mysqli->rollback();
  }
  $mysqli->close();
  echo json_encode($rt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function fetchScheduleTimeStamp()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $mysqli = connectDb();

  $sql = "
    select UNIX_TIMESTAMP(timestamp) * 1000 AS timestamp, timestamp as timestampStr
    FROM ahdschedule
    where hid = '$hid'
    and bid = '$bid'
    and date = '$date'
  ";
  $rt = unvList($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function fetchDailyReportTimeStamp()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $mysqli = connectDb();

  $sql = "
    select UNIX_TIMESTAMP(timestamp) * 1000 AS timestamp, timestamp as timestampStr
    FROM ahddailyreport
    where hid = '$hid'
    and bid = '$bid'
    and date = '$date'
  ";
  $rt = unvList($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function fetchHidBidByJino()
{
  $jino = PRMS('jino');
  $date = PRMS('date');
  $mysqli = connectDb();

  $sql = "
    SELECT hid, bid, date FROM ahdbrunch 
    WHERE jino = '$jino' and date <= '$date'
    ORDER BY date DESC LIMIT 1;
  ";
  $rt = unvList($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function fetchTimeStamps()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $mysqli = connectDb();

  $sql = "
    select UNIX_TIMESTAMP(timestamp) * 1000 AS timestamp, timestamp as timestampStr
    FROM ahddailyreport
    where hid = '$hid'
    and bid = '$bid'
    and date = '$date'
  ";
  $t = unvList($mysqli, $sql);
  $rt = $t;
  if (isset($t['dt']) && isset($t['dt'][0])) {
    $rt['dt'][0]['ahddailyreport'] = $t['dt'][0]['timestamp'];
    $rt['dt'][0]['ahddailyreportStr'] = $t['dt'][0]['timestampStr'];
  };

  if (isset($t['sql'])) {
    $sqls[] = $t['sql'];
  }
  $sql = "
    select UNIX_TIMESTAMP(timestamp) * 1000 AS timestamp, timestamp as timestampStr
    FROM ahdschedule
    where hid = '$hid'
    and bid = '$bid'
    and date = '$date'
  ";
  $t = unvList($mysqli, $sql);
  if (isset($t['dt']) && isset($t['dt'][0])) {
    $rt['dt'][0]['ahdschedule'] = $t['dt'][0]['timestamp'];
    $rt['dt'][0]['ahdscheduleStr'] = $t['dt'][0]['timestampStr'];
  }
  if (isset($t['sql'])) {
    $sqls[] = $t['sql'];
  }
  $sql = "
    select UNIX_TIMESTAMP(timestamp) * 1000 AS timestamp, timestamp as timestampStr
    FROM ahdcontacts
    where hid = '$hid'
    and bid = '$bid'
    and date = '$date'
  ";
  $t = unvList($mysqli, $sql);
  if (isset($t['dt']) && isset($t['dt'][0])) {
    $rt['dt'][0]['ahdcontacts'] = $t['dt'][0]['timestamp'];
    $rt['dt'][0]['ahdcontactsStr'] = $t['dt'][0]['timestampStr'];
  }
  if (isset($t['sql'])) {
    $sqls[] = $t['sql'];
  }
  if (isset($sqls)) {
    $rt['sqls'] = $sqls;
  }
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function fetchUniqBids()
{
  $mysqli = connectDb();

  $sql = "
    SELECT DISTINCT bid FROM ahduser;
  ";
  $rt = unvList($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function fetchUsersPlan()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $uid = PRMS('uid');
  $created = PRMS('created');
  $item = PRMS('item');
  $month = PRMS('month');
  $lastmonth = PRMS('lastmonth');
  $limit = PRMS('limit');

  // 基本クエリ
  $sql = "SELECT hid, bid, uid, created, item, content, timestamp
          FROM ahdUsersPlan 
          WHERE hid='{$hid}' AND bid='{$bid}'";

  // オプショナルパラメータ
  if (!empty($uid)) {
    $sql .= " AND uid='{$uid}'";
  }
  if (!empty($created)) {
    $sql .= " AND created='{$created}'";
  }
  if (!empty($item)) {
    $sql .= " AND item='{$item}'";
  }
  if (!empty($month)) {
    $sql .= " AND created LIKE '{$month}%'";
  }
  if (!empty($lastmonth)) {
    $sql .= " AND created <= LAST_DAY('{$lastmonth}-01')";
    $sql .= " AND created >= LAST_DAY('{$lastmonth}-01') - INTERVAL 1 YEAR - INTERVAL 1 MONTH";
  }
  $sql .= ' ORDER BY `created` DESC';
  if (!empty($limit)) {
    $sql .= " limit 0, {$limit}";
  }
  // lastmanthを指定するときはlimitを指定しない
  if (empty($lastmonth) && empty($limit)) {
    $sql .= " limit 0, 10";
  }

  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql); // unvList関数にSQLクエリを直接渡す
  if (isset($rt['dt']) && count($rt['dt'])) {
    foreach ($rt['dt'] as $key => $value) {
      if (isset($value['content'])) {
        $rt['dt'][$key]['content'] = json_decode($value['content'], true);
      }
    }
  }
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function sendUsersPlan()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $uid = PRMS('uid');
  $created = PRMS('created');
  $item = PRMS('item');
  $content = PRMS('content');
  $mysqli = connectDb();
  $sql = "
    insert into ahdUsersPlan (hid,bid,uid,created,item,content)
    values ('$hid','$bid','$uid','$created','$item', '$content')
    on duplicate key update
    content = '$content'
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function deleteUsersPlan()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $uid = PRMS('uid');
  $item = PRMS('item');
  $created = PRMS('created');
  $mysqli = connectDb();
  $sql = "
    DELETE FROM ahdUsersPlan
    WHERE hid = '$hid'
    AND bid = '$bid'
    AND uid = '$uid'
    AND created = '$created'
  ";

  // itemが指定されている場合は条件に追加
  if ($item !== '') {
    $sql .= " AND item = '$item'";
  }

  $sql .= ";";

  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function sendLog()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $uid = PRMS('uid');
  $date = PRMS('date');
  $item = PRMS('item');
  $state = escapeChar(PRMS('state')); // 追加
  $keep = PRMS('keep');
  $mysqli = connectDb();
  $state = mysqli_real_escape_string($mysqli, $state);
  $sql = "
    insert into ahdLog (hid,bid,uid,date,item,state,keep)
    values ('$hid','$bid','$uid','$date','$item', '$state', '$keep')
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

// function fetchUsersIdByLineId(){
//   $lineid = PRMS('lineid');
//   $sql = "
//     select hid,bid,uid
//     from ahdusersext
//     where ext like '%$lineid%'
//   ";
//   $mysqli = connectDb();
//   $rt = unvList($mysqli, $sql);
//   $jsn = json_encode(escapeChar($rt), JSON_UNESCAPED_UNICODE);
//   echo $jsn;
//   $mysqli->close();
// }

function fetchUsersIdByLineId()
{
  $lineid = PRMS('lineid');
  $date = PRMS('date'); // 必要に応じて日付を設定してください

  $sql = "
    select user.*, 
      uext.ext, 
      brunch.bname, brunch.sbname, brunch.jino,  
      com.hname, com.shname
    from ahduser as user 
    join ahdbrunch as brunch using (hid, bid)
    join ahdcompany as com using (hid)
    join (
      SELECT MAX(date) mdate, date, uid, bid, hid 
      FROM ahduser 
      where date <= '$date' 
      GROUP BY uid, hid, bid    
    ) as lastupdated 
    on lastupdated.uid = user.uid
    and lastupdated.bid = user.bid
    and lastupdated.hid = user.hid
    and lastupdated.mdate = user.date
    left join ahdusersext as uext 
    on lastupdated.uid = uext.uid
    and lastupdated.bid = uext.bid
    and lastupdated.hid = uext.hid
    join (
      select hid, bid, uid
      from ahdusersext
      where ext like '%$lineid%'
    ) as filtered
    on user.hid = filtered.hid
    and user.bid = filtered.bid
    and user.uid = filtered.uid
    where
    (
      user.enddate = '0000-00-00' OR
      user.enddate >= '$date'
    ) 
    and user.date <= '$date'
    and brunch.date = (
      SELECT MAX(date) from ahdbrunch 
      WHERE bid=filtered.bid and hid=filtered.hid and date<='$date'
    )
  ";

  $mysqli = connectDb();
  $rt = unvList($mysqli, $sql);
  $jsn = json_encode(escapeChar($rt), JSON_UNESCAPED_UNICODE);
  echo $jsn;
  $mysqli->close();
}

function fetchWaitingMsg()
{
  $date = PRMS('date');
  $after = PRMS('after');
  $sql = "
    select hid, bid, date, contacts from ahdcontacts
    where date = '$date'
  ";
  $mysqli = connectDb();
  $result = unvList($mysqli, $sql);

  // unvListの結果がfalseの場合、そのまま返す
  if (!$result['result']) {
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    $mysqli->close();
    return;
  }

  // 今日の日付をJSTで取得
  $today = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
  $todayStr = $today->format('Ymd');

  // after日付の設定
  $afterDateStr = null;
  if ($after) {
    $afterDate = new DateTime($after, new DateTimeZone('Asia/Tokyo'));
    $afterDateStr = $afterDate->format('Ymd');
  }

  $filteredData = [];

  // フェッチされたデータをループ
  foreach ($result['dt'] as $row) {
    $contacts = json_decode($row['contacts'], true);
    $filteredEntry = [
      'hid' => $row['hid'],
      'bid' => $row['bid']
    ];

    // contactsをループ
    foreach ($contacts as $uidKey => $uidValue) {
      if (strpos($uidKey, 'UID') !== 0) continue;

      foreach ($uidValue as $dateKey => $dateArray) {
        if (strpos($dateKey, 'D') !== 0) continue;

        $contactDate = substr($dateKey, 1);
        if ($contactDate > $todayStr) continue;
        if ($afterDateStr && $contactDate < $afterDateStr) continue;

        $includeDateArray = false;

        foreach ($dateArray as $contact) {
          $sent = isset($contact['sent']) ? $contact['sent'] : true;
          $draft = isset($contact['draft']) ? $contact['draft'] : true;

          if (!$sent && !$draft) {
            $includeDateArray = true;
            break;
          }
        }

        if ($includeDateArray) {
          $filteredEntry[$uidKey][$dateKey] = $dateArray;
        }
      }
    }

    if (count($filteredEntry) > 2) { // 'hid' と 'bid' 以外の要素があるかを確認
      $filteredData[] = $filteredEntry;
    }
  }

  $result['dt'] = $filteredData;
  echo json_encode($result, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function sendPartOfData()
{
  $table = PRMS('table');
  $column = PRMS('column');
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $partOfData = PRMS('partOfData');

  $mysqli = connectDb();
  $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

  try {
    $mysqli->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);

    // テーブル名とカラム名をバッククォートで囲む
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

    // プリペアドステートメントで安全な INSERT 文
    $updateStmt = $mysqli->prepare("
          INSERT INTO `$table` (hid, bid, date, `$column`)
          VALUES (?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE `$column` = VALUES(`$column`)
      ");
    if (!$updateStmt) {
      echo '{"result":false, "error": "SQL Prepare failed (update): ' . $mysqli->error . '"}';
      return false;
    }

    $updateStmt->bind_param("ssss", $hid, $bid, $date, $finalJson);
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


function fetchLineNames() {
  $mysqli = connectDb();

  // すべてのカラムを含めて GROUP BY したレコード件数を取得
  $groupByAllSQL = "
      SELECT lineName, displayName, accessToken, lineID
      FROM ahdOfficialLineAccount
      GROUP BY lineName, displayName, accessToken, lineID;
  ";

  // lineName だけで GROUP BY したレコード件数を取得
  $groupByLineNameSQL = "
      SELECT lineName
      FROM ahdOfficialLineAccount
      GROUP BY lineName;
  ";

  // クエリ実行
  $groupByAllResult = unvList($mysqli, $groupByAllSQL);
  $groupByLineNameResult = unvList($mysqli, $groupByLineNameSQL);

  // 件数を比較
  $totalAll = count($groupByAllResult['dt']);
  $totalLineName = count($groupByLineNameResult['dt']);

  // 件数が一致しない場合はエラー
  if ($totalAll != $totalLineName) {
      echo json_encode(["result" => false, "error" => "Duplicate data found."]);
      $mysqli->close();
      return;
  }

  // 重複がなければ lineName と displayName を取得
  $fetchSQL = "
      SELECT DISTINCT lineName, displayName
      FROM ahdOfficialLineAccount;
  ";

  $dataResult = unvList($mysqli, $fetchSQL);

  echo json_encode($dataResult, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function fetchLineAccount() {
  $hid = PRMS('hid');
  $bid = PRMS('bid');

  $mysqli = connectDb();

  // 対応するレコードを取得
  $fetchSQL = "
      SELECT lineName, displayName, accessToken, lineID
      FROM ahdOfficialLineAccount
      WHERE hid = '$hid' AND bid = '$bid';
  ";

  $dataResult = unvList($mysqli, $fetchSQL);

  // 結果をそのまま返す
  echo json_encode($dataResult, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function fetchLineNameList() {
  $lineName = PRMS('lineName');

  $mysqli = connectDb();

  // 特定の lineName を持つリストを取得
  $fetchSQL = "
      SELECT hid, bid, lineName, displayName
      FROM ahdOfficialLineAccount
      WHERE lineName = '$lineName';
  ";

  $dataResult = unvList($mysqli, $fetchSQL);

  // 結果をそのまま返す
  echo json_encode($dataResult, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function sendNewBrunchForLineAccount() {
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $lineName = PRMS('lineName');

  $mysqli = connectDb();

  // hid, bid が既存かチェック
  $checkExistSQL = "
      SELECT COUNT(*) as cnt
      FROM ahdOfficialLineAccount
      WHERE hid = '$hid' AND bid = '$bid';
  ";

  $existResult = unvList($mysqli, $checkExistSQL);

  if ($existResult['result'] && $existResult['dt'][0]['cnt'] > 0) {
      echo json_encode(["result" => false, "error" => "Specified hid and bid already exist."]);
      $mysqli->close();
      return;
  }

  // lineName が既存かチェックして displayName, accessToken, lineID を取得
  $checkSQL = "
      SELECT displayName, accessToken, lineID
      FROM ahdOfficialLineAccount
      WHERE lineName = '$lineName';
  ";

  $checkResult = unvList($mysqli, $checkSQL);

  if (!$checkResult['result'] || count($checkResult['dt']) == 0) {
      echo json_encode(["result" => false, "error" => "Specified lineName does not exist."]);
      $mysqli->close();
      return;
  }

  // displayName, accessToken, lineID を取得
  $displayName = $checkResult['dt'][0]['displayName'];
  $accessToken = $checkResult['dt'][0]['accessToken'];
  $lineID = $checkResult['dt'][0]['lineID'];

  // 新しいレコードを追加（unvEditを使用）
  $insertSQL = "
      INSERT INTO ahdOfficialLineAccount (hid, bid, lineName, displayName, accessToken, lineID)
      VALUES ('$hid', '$bid', '$lineName', '$displayName', '$accessToken', '$lineID');
  ";

  // unvEditの結果をそのまま返す
  $rt = unvEdit($mysqli, $insertSQL);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}




/**
 * テーブル「ahdschedule」などのJSONカラムから特定キーの階層を抽出し返却する
 * パラメータは PRMS() 経由で取得する
 * 
 * 事前に定義されている前提の関数:
 *  - PRMS($key, $prmsAllowGet = false)
 *  - connectDb()  // DB接続を返す
 *  - unvList($mysqli, $sql)  // クエリ実行して { result, dt } を返す
 *
 * リクエストパラメータ例:
 *  - a=fetchPartOfData
 *  - table=ahdschedule
 *  - tableKeys={"hid":"0khROPje","bid":"MwV6HuPm","date":"2023-04-01"}
 *  - column=schedule
 *  - jsonKeys=["UID19"]
 */
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




function fetchLineIdList() {
  $lineID = urldecode(PRMS('lineID'));

  $mysqli = connectDb();

  // lineID に合致するリストを取得
  $fetchSQL = "
      SELECT hid, bid, lineName, displayName, accessToken, lineID
      FROM ahdOfficialLineAccount
      WHERE lineID = '$lineID';
  ";

  $dataResult = unvList($mysqli, $fetchSQL);

  // 結果をそのまま返す
  echo json_encode($dataResult, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

// 銀行情報取得API
// 銀行コードだけだったら銀行名を返す
// 支店コードもあったら支店名を返す
function fetchBankInfo(): void
{
  $bankcode = PRMS('bankcode', true);
  $branchcode = PRMS('branchcode', true);

  if ($branchcode) {
    $url = "https://bank.teraren.com/banks/$bankcode/branches/$branchcode.json";
  } else {
    $url = "https://bank.teraren.com/banks/$bankcode.json";
  }

  $json = file_get_contents($url);
  $json = mb_convert_encoding($json, 'UTF8', 'ASCII,JIS,UTF-8,EUC-JP,SJIS-WIN');
  $ary = json_decode($json, true);

  // Add 'result: true' to the response
  if (is_array($ary)) {
    $ary['result'] = true;
    echo json_encode($ary, JSON_UNESCAPED_UNICODE);
  } else {
    // If decoding fails or the response is not an array, return the original JSON
    echo $json;
  }
}

function sendScheduleOld()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $schedule = PRMS('schedule');
  $mysqli = connectDb();
  $sql = "
    insert into ahdschedule (hid,bid,date,schedule)
    values ('$hid','$bid','$date','$schedule')
    on duplicate key update
    schedule = '$schedule'
  ";
  $rt = unvEdit($mysqli, $sql);
  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  $mysqli->close();
}

function sendSchedule()
{
  $hid = PRMS('hid');
  $bid = PRMS('bid');
  $date = PRMS('date');
  $schedule = PRMS('schedule');
  $mysqli = connectDb();

  // トランザクションを開始
  $mysqli->begin_transaction(MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);

  try {
    // 既存のスケジュールをフェッチ
    $sqlFetch = "
      select schedule from ahdschedule 
      where hid='$hid' and bid='$bid' and date='$date'
      FOR UPDATE;
    ";
    $result = unvList($mysqli, $sqlFetch);
    $existingSchedule = $result['dt'][0]['schedule'] ?? '{}';
    $existingSchedule = json_decode($existingSchedule, true);

    // 新しいスケジュールをデコード
    $newSchedule = json_decode($schedule, true);

    // 既存のスケジュールと新しいスケジュールをマージ
    if (is_array($existingSchedule) && is_array($newSchedule)) {
      $mergedSchedule = array_merge($existingSchedule, $newSchedule);
    } else {
      throw new Exception('Invalid schedule data');
    }

    // マージしたスケジュールをエンコード
    $finalSchedule = json_encode($mergedSchedule, JSON_UNESCAPED_UNICODE);

    // スケジュールを更新
    $sql = "
      insert into ahdschedule (hid,bid,date,schedule)
      values ('$hid','$bid','$date','$finalSchedule')
      on duplicate key update
      schedule = '$finalSchedule'
    ";
    $rt = unvEdit($mysqli, $sql);

    // トランザクションをコミット
    $mysqli->commit();
    echo json_encode($rt, JSON_UNESCAPED_UNICODE);
  } catch (Exception $e) {
    // エラーが発生した場合はロールバック
    $mysqli->rollback();
    echo json_encode(['result' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
  }

  $mysqli->close();
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
else if ($m == 'lu')                  listUsers();
// ユーザー一覧の取得
else if ($m == 'companybrunch')             companyAndBrunch();
else if ($m == 'cb')                        companyAndBrunch();
// companybrunchMは複数レコード対応用
else if ($m == 'companybrunchM')             companybrunchM();
// 法人、事業所情報の取得
else if ($m == 'sendCalender')              sendCalender();
else if ($m == 'fetchCalender')             fetchCalender();
else if ($m == 'sendSchedule')              sendSchedule();
else if ($m == 'fetchSchedule')             fetchSchedule();
else if ($m == 'sendTransferData')          sendTransferData();
else if ($m == 'fetchTransferData')         fetchTransferData();
// 送信済みリストの取得
else if ($m == 'listSent')                  listSent();
else if ($m == 'putTransferPass')           putTransferPass();
else if ($m == 'getTransferPass')           getTransferPass();
else if ($m == 'putSentToTransfer')         putSentToTransfer();
// アカウント関連
else if ($m == 'editAccount')               editAccount();
else if ($m == 'putAccount')                putAccount();
else if ($m == 'getAccount')                getAccount();
else if ($m == 'getAccountByPw')            getAccountByPw();
else if ($m == 'getCountAttempts')          getCountAttempts();
else if ($m == 'sendNewKey')                sendNewKey();
else if ($m == 'sertificatAndNew')          sertificatAndNew();
else if ($m == 'replaceKey')                sertificatAndNew();  //エイリアス
else if ($m == 'getAccountByKey')           getAccountByKey();
else if ($m == 'sendAddictionOfBrunch')     sendAddictionOfBrunch();
else if ($m == 'fetchAddictionOfBrunch')    fetchAddictionOfBrunch();
else if ($m == 'sendUserEtc')               sendUserEtc();
else if ($m == 'sendUserEtcMulti')          sendUserEtcMulti();
else if ($m == 'sendUser')                  sendUser();
else if ($m == 'sendUserWithEtc')           sendUserWithEtc();
// ユーザーの市区町村を書き換える
else if ($m == 'replaceUsersCityNoByName')  replaceUsersCityNoByName();
else if ($m == 'replaceUsersCity')          replaceUsersCity();
else if ($m == 'removeUser')                removeUser();
// 事業所情報の書き込み。sendAddictionOfBrunchとちょいかぶり
else if ($m == 'sendBrunch')                sendBrunch();
else if ($m == 'sendDocument')              sendDocument();
else if ($m == 'fetchDocument')             fetchDocument();
else if ($m == 'excelgen')                  excelgen();
else if ($m == 'csvgen')                    csvgen();
else if ($m == 'csvgen')                    csvgen();
else if ($m == 'genFKdatas')                genFKdatas();
else if ($m == 'sendUsersIndex')            sendUsersIndex();
// アカウントの追加 メールアドレスとリセットキーのセット
else if ($m == 'addAccount')                addAccount();
// アカウントのパスワードリセット発行
else if ($m == 'resetAccountPass')          resetAccountPass();
// メール送信cgi起動
else if ($m == 'sendAccountMail')           sendAccountMail();
// パスワードリセットキーによるアカウントの取得
else if ($m == 'getAccountByRestkey')       getAccountByRestkey();
// 同じメアドのパスワードを一斉変更
else if ($m == 'updatePasswordsAll')        updatePasswordsAll();
// ユーザーごとのスケジュール更新
else if ($m == 'sendUsersSchedule')         sendUsersSchedule();
// スケジュールの一部更新
else if ($m == 'sendPartOfSchedule')        sendPartOfSchedule();
// アカウントの一覧
else if ($m == 'listAccount')         listAccount();
// エクセルイメージの送受信
else if ($m === 'sendExcelImg') sendFsExcelImg();
else if ($m === 'sendFsExcelImg') sendFsExcelImg();
else if ($m === 'fetchFsExcelImg') fetchFsExcelImg();
else if ($m === 'setDoneFsExcelImg') setDoneFsExcelImg();
// stateの送受信、っていうかなんにでも使える
else if ($m === 'sendSomeState') sendSomeState();
else if ($m === 'fetchSomeState') fetchSomeState();
else if ($m === 'deleteSomeState') deleteSomeState();
else if ($m === 'sendAnyState') sendAnyState();
else if ($m === 'fetchAnyState') fetchAnyState();
// fscon
else if ($m === 'fsConCnvExcel') fsConCnvExcel();
else if ($m === 'sendCompany') sendCompany();
else if ($m === 'fetchCompany') fetchCompany();
else if ($m === 'fetchComExtAll') fetchComExtAll();
else if ($m === 'fetchBrunch') fetchBrunch();
else if ($m === 'getDbname') getDbname();
// ワムネットスクレイピング
else if ($m === 'fetchOfficeis') fetchOfficeis();
else if ($m === 'sendOfficeis') sendOfficeis();
// 最小月と最大月
else if ($m === 'getMinMaxOfMonnth') getMinMaxOfMonnth();
// ユーザーと事業所の次の変更履歴を取得
else if ($m === 'fetchNextUserInfo') fetchNextUserInfo();
else if ($m === 'fetchNextComInfo') fetchNextComInfo();
// bidによるアカウントの取得
else if ($m === 'fetchAccountsByBid') fetchAccountsByBid();
// アカウント削除
else if ($m === 'deleteAccount') deleteAccount();
// 削除関連
else if ($m === 'deleteBrunch') deleteBrunch();
else if ($m === 'deleteAllUser') deleteAllUser();
else if ($m === 'deleteSchedule') deleteSchedule();
else if ($m === 'deleteCalender') deleteCalender();
// スケジュールの複製
else if ($m === 'copySchedule') copySchedule();
// 外部データの取得
else if ($m === 'extData') extData();
// 利用料金
else if ($m === 'sendUsageFee') sendUsageFee();
else if ($m === 'fetchUsageFee') fetchUsageFee();
else if ($m === 'removeUsageFee') removeUsageFee();
// 連絡帳
else if ($m == 'fetchContactsForFAP') fetchContactsForFAP();
else if ($m == 'sendPartOfContact') sendPartOfContact();
else if ($m == 'sendOneMessageOfContact') sendOneMessageOfContact();
else if ($m == 'sendDtUnderUidOfContact') sendDtUnderUidOfContact();
else if ($m == 'sendContactForFap') sendContactForFap();
else if ($m == 'sendPartOfContactJino') sendPartOfContactJino();
else if ($m == 'fetchPartOfContactJino') fetchPartOfContactJino();
else if ($m == 'fetchContacts') fetchContacts();
else if ($m == 'sendFapTokenMail') sendFapTokenMail();
else if ($m == 'sendFapNoticeMailPost') sendFapNoticeMailPost();
else if ($m == 'sendFapNoticeMail') sendFapNoticeMail();
else if ($m == 'sendHtmlMail') sendHtmlMail();
else if ($m == 'createAutoMsg') createAutoMsg();
else if ($m == 'fetchUsersIdByLineId') fetchUsersIdByLineId();
else if ($m == 'fetchWaitingMsg') fetchWaitingMsg();
// 拡張項目　月のデータを持たない法人情報とユーザー情報
else if ($m == 'sendUsersExt') sendUsersExt();
else if ($m == 'fetchUsersExt') fetchUsersExt();
else if ($m == 'sendComExt') sendComExt();
// シフト作成支援
else if ($m == 'sendWorkshift') sendWorkshift();
else if ($m == 'fetchWorkshift') fetchWorkshift();
// 全ての受給者証番号を調べる
else if ($m == 'fetchAllHnoFromUsers') fetchAllHnoFromUsers();

// カレンダーの日付整合性チェック
else if ($m == 'checkCalender') checkCalender();
// スケジュールデータが存在する年月を取得
else if ($m == 'fetchScheduleAria') fetchScheduleAria();
// ユーザー履歴取得
else if ($m == 'fetchUsersHist') fetchUsersHist();
// sms送信
else if ($m == 'sendAlfamiSmsCode') sendAlfamiSmsCode();
// 祭日取得
else if ($m == 'getHolidays') getHolidays();
// 電話番号によるユーザーの検索
else if ($m == 'getUsersTel') getUsersTel();
else if ($m == 'getOfiiceTelForSms') getOfiiceTelForSms();
// スケジュールのバックアップ
else if ($m == 'fetchSchBackupList') fetchSchBackupList();
else if ($m == 'fetchSchBackup') fetchSchBackup();
// 日報
else if ($m == 'fetchDailyReport') fetchDailyReport();
else if ($m == 'sendPartOfDailyReport') sendPartOfDailyReport();
else if ($m == 'sendPartOfDailyReportWithKey') sendPartOfDailyReportWithKey();
else if ($m == 'sendPartOfDailyReportWith2Key') sendPartOfDailyReportWith2Key();

// タイムスタンプの取得
else if ($m == 'fetchScheduleTimeStamp') fetchScheduleTimeStamp();
else if ($m == 'fetchDailyReportTimeStamp') fetchDailyReportTimeStamp();
// jinoによるhid bid取得
else if ($m == 'fetchHidBidByJino') fetchHidBidByJino();
// schedule,contacts,dayrepoのタイムスタンプを得る
else if ($m == 'fetchTimeStamps') fetchTimeStamps();
// ユニークなbidの取得
else if ($m == 'fetchUniqBids') fetchUniqBids();

// 個別支援計画
else if ($m == 'fetchUsersPlan') fetchUsersPlan();
else if ($m == 'sendUsersPlan') sendUsersPlan();
else if ($m == 'deleteUsersPlan') deleteUsersPlan();
else if ($m == 'sendLog') sendLog();
// 汎用JSOM更新
else if ($m == 'sendPartOfData') sendPartOfData();
else if ($m == 'sendPartOfDataWithKeys') sendPartOfDataWithKeys();
else if ($m == 'deletePartOfData') deletePartOfData();

// 汎用JSON取得
else if ($m == 'fetchPartOfData') fetchPartOfData();

// lineアカウント関連
else if ($m == 'fetchLineNames') fetchLineNames();
else if ($m == 'fetchLineAccount') fetchLineAccount();
else if ($m == 'fetchLineNameList') fetchLineNameList();
else if ($m == 'sendNewBrunchForLineAccount') sendNewBrunchForLineAccount();
else if ($m == 'fetchLineIdList') fetchLineIdList();

// 銀行情報
else if ($m == 'fetchBankInfo') fetchBankInfo();


else nothing();
