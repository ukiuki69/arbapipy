<?php
/*
 * このスクリプトは次の処理を実行します：
 * 1. `ahdschedule`テーブルから現在時刻からN分以内のレコードを選択し、
 * それらを`ahdschedule_backup`テーブルに挿入します。
 * 2. 同じ`hid`,`bid`を持つレコードがx個を超える場合、
 * 最も古いレコード（つまり最も古い`created`タイムスタンプを持つレコード）を削除します。
 */

// PHPのデフォルトタイムゾーンをJSTに設定
date_default_timezone_set('Asia/Tokyo');

$mysqli = new mysqli('mysql10077.xserver.jp', 'albatross56_ysmr', 'kteMpg5D', 'albatross56_sv1');

$output = array();
if ($mysqli->connect_error) {
  $output['error'] = '接続エラー (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error;
  echo json_encode($output);
  exit;
}

$N = 60; // N分
$X = 24;  // Xレコード上限
$sqls = array();

// `ahdschedule`から最新のN分のレコードを`ahdschedule_backup`にコピー
$sql = "
  INSERT INTO ahdschedule_backup (hid, bid, date, schedule, timestamp, created)
  SELECT hid, bid, date, schedule, timestamp, CURRENT_TIMESTAMP
  FROM ahdschedule
  WHERE timestamp > NOW() - INTERVAL ? MINUTE
";
$sqls[] = str_replace('?', $N, $sql);

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('i', $N);
if (!$stmt->execute()) {
  $output['insert_error'] = $stmt->error;
}
$stmt->close();

// `hid`, `bid`の組み合わせがX以上ある場合、最も古いレコードを削除
$sql = "
  SELECT hid, bid
  FROM ahdschedule_backup
  GROUP BY hid, bid
  HAVING COUNT(*) > ?
";
$sqls[] = str_replace('?', $X, $sql);

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('i', $X);
if ($stmt->execute()) {
  $result = $stmt->get_result();
  while ($row = $result->fetch_assoc()) {
    $hid = $row['hid'];
    $bid = $row['bid'];

    // この`hid`, `bid`組み合わせの最も古いレコードを削除
    $sql_delete = "
      DELETE FROM ahdschedule_backup
      WHERE hid = ? AND bid = ?
      ORDER BY created ASC
      LIMIT 1
    ";
    $sql_delete_prepared = str_replace('?', "'{$hid}','{$bid}'", $sql_delete);
    $sqls[] = $sql_delete_prepared;

    $stmt_delete = $mysqli->prepare($sql_delete);
    $stmt_delete->bind_param('ss', $hid, $bid);
    if (!$stmt_delete->execute()) {
      $output['delete_error'] = $stmt_delete->error;
    }
    $stmt_delete->close();
  }
} else {
  $output['select_error'] = $stmt->error;
}
$stmt->close();

$output['sqls'] = $sqls;
$output['now'] = date('Y-m-d H:i:s'); // 現在の日時を出力

echo json_encode($output, JSON_PRETTY_PRINT);

$mysqli->close();
?>
