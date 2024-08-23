<?php
/*
このスクリプトは、指定されたMySQLテーブルを一つのデータベースから別のデータベースにコピーします。
スクリプトは次の手順で処理を行います：
1. ソースデータベースとターゲットデータベースへの接続を確立します。
2. 各テーブルについて以下の操作を行います：
  a. ターゲットデータベースのテーブルから指定したhid以外のレコードを削除します。
  b. ソーステーブルからデータを取得し、ページングを使用してデータをターゲットテーブルにコピーします。ただし、指定したhidはコピーを除外します。
  c. コピー操作のログを作成します。
最後に、データベース接続を閉じます。
*/

$host = 'mysql10077.xserver.jp';
$username = 'albatross56_ysmr';
$password = 'kteMpg5D';
$db1 = 'albatross56_sv1'; // ソースデータベース
$db2 = 'albatross56_albtest'; // ターゲットデータベース
$page_size = 1000; // 一度に処理する行数

$tables = [
  // コピーするテーブル名
  'ahdAnyState',
  'ahdSomeState',
  'ahdaccount',
  'ahdbrunch',
  'ahdbrunchext',
  'ahdcalender',
  'ahdcompany',
  'ahdcontacts',
  'ahdschedule',
  'ahdusagefee',
  'ahduser',
  'ahdusersext',
  'ahdworkshift',
  'ahddailyreport',
];

$exclude_hid = ['LE5MMsTF']; // 除外するhid

// データベース接続を作成
$source_conn = new mysqli($host, $username, $password, $db1);
$target_conn = new mysqli($host, $username, $password, $db2);
$source_conn->set_charset("utf8mb4");
$target_conn->set_charset("utf8mb4");



// データベース接続をチェック
if ($source_conn->connect_error || $target_conn->connect_error) {
  die("接続に失敗しました: " . $source_conn->connect_error . $target_conn->connect_error);
}

// コピーしたテーブルの数
$copy_count = 0;

foreach ($tables as $table) {
  // テーブルが存在するかチェック
  $res = $target_conn->query("SHOW TABLES LIKE '$table'");
  if ($res->num_rows == 1) {
    // hidカラムが存在するかチェック
    $res = $target_conn->query("SHOW COLUMNS FROM `$table` LIKE 'hid'");
    if ($res->num_rows == 1) {
      // hidの除外リストを作成
      $exclude_hid_string = implode("', '", $exclude_hid);

      // ターゲットテーブルから指定したhid以外のレコードを削除
      $target_conn->query("DELETE FROM `$table` WHERE hid NOT IN ('$exclude_hid_string')");

      // ソーステーブルの行数を取得
      $res = $source_conn->query("SELECT COUNT(*) as count FROM `$table` WHERE hid NOT IN ('$exclude_hid_string')");
      $row = $res->fetch_assoc();
      $num_rows = $row['count'];

      // データをチャンクに分けてコピー
      for ($offset = 0; $offset < $num_rows; $offset += $page_size) {
        $res = $source_conn->query("SELECT * FROM `$table` WHERE hid NOT IN ('$exclude_hid_string') LIMIT $offset, $page_size");
        while ($row = $res->fetch_assoc()) {
          $keys = join('`, `', array_keys($row));
          // $values = join("', '", $row);
          $values = join("', '", array_map(function($value) use ($target_conn) {
            return $target_conn->real_escape_string($value);
          }, $row));
          $target_conn->query("INSERT INTO `$table` (`$keys`) VALUES ('$values')");
        }
      }

      // コピーしたテーブルの数を増やす
      $copy_count++;

      // // コピー操作のログを作成
      // $source_conn->query("INSERT INTO `ahddatamoved` (`tablename`) VALUES ($table)");
      // コピー操作のログを作成
      $source_conn->query("INSERT INTO `ahddatamoved` (`tablename`) VALUES ('$table')");



      echo "テーブル $table は正常にコピーされました。\n";
    } else {
      echo "テーブル $table はhidカラムを持っていません、スキップします。\n";
    }
  } else {
    echo "テーブル $table はターゲットデータベースに存在しません、スキップします。\n";
  }
}

// データベース接続を閉じる
$source_conn->close();
$target_conn->close();
?>
