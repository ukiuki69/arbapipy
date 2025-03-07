<?php

$dbHost = 'mysql10077.xserver.jp';
$dbUser = 'albatross56_x84e';
$dbPass = 'eGR2JJtj.i7EpSN';
$dbName = 'albatross56_sv1';

// バックアップファイルの保存ディレクトリを設定
$backupDirectory = '../../../mysqlbktest/';

// エラー表示を有効にする
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ディレクトリが存在するか確認
if (!file_exists($backupDirectory)) {
    mkdir($backupDirectory, 0755, true);
    echo "バックアップディレクトリを作成しました: $backupDirectory\n";
}

// グループ定義
$groups = [
    1 => ['range' => '0-F', 'where' => "LOWER(SUBSTRING(hid, 1, 1)) BETWEEN '0' AND 'f'"],
    2 => ['range' => 'G-K', 'where' => "LOWER(SUBSTRING(hid, 1, 1)) BETWEEN 'g' AND 'k'"],
    3 => ['range' => 'L-Q', 'where' => "LOWER(SUBSTRING(hid, 1, 1)) BETWEEN 'l' AND 'q'"],
    4 => ['range' => 'R-Z', 'where' => "LOWER(SUBSTRING(hid, 1, 1)) BETWEEN 'r' AND 'z'"]
];

// ルールに基づいてバックアップファイルを削除
$files = scandir($backupDirectory);
foreach ($files as $file) {
    $filePath = $backupDirectory . $file;
    // ファイルかつ拡張子が.zipまたは.txtの場合のみ処理
    if (is_file($filePath) && (pathinfo($filePath, PATHINFO_EXTENSION) === 'zip' || pathinfo($filePath, PATHINFO_EXTENSION) === 'txt')) {
        $fileNameParts = explode('_', $file);
        $creationDate = $fileNameParts[0]; // ファイル名から作成日を取得
        $today = date('ymd');
        // 12日に作成されたファイルの場合
        if (substr($creationDate, -2) == '12' && $creationDate < $today) {
            // 130日間残す
            if (time() - filemtime($filePath) > 130 * 24 * 60 * 60) {
                echo "Deleting file: " . $filePath . PHP_EOL;
                unlink($filePath);
            }
        } else {
            // それ以外のファイルは14日間残す
            if (time() - filemtime($filePath) > 14 * 24 * 60 * 60) {
                echo "Deleting file: " . $filePath . PHP_EOL;
                unlink($filePath);
            }
        }
    }
}

// データベースに接続
$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($mysqli->connect_error) {
    die("データベース接続エラー: " . $mysqli->connect_error);
}

// ahdcompanyテーブルのレコード件数を取得
$countQuery = "SELECT COUNT(*) AS total FROM ahdcompany";
$countResult = $mysqli->query($countQuery);
if ($countResult) {
    $row = $countResult->fetch_assoc();
    $recordCount = $row['total'];
    echo "ahdcompanyテーブルのレコード件数: " . $recordCount . "\n";
} else {
    echo "ahdcompanyテーブルのレコード件数取得エラー: " . $mysqli->error . "\n";
}

// データベース内のすべてのテーブルを取得
$tablesQuery = "SHOW TABLES FROM `$dbName`";
$tablesResult = $mysqli->query($tablesQuery);

$tablesWithHid = [];
$tablesWithoutHid = [];
$ignoreTables = [
    'ahdAnyState', 'ahdLog', 'ahdSomeState', 'ahdfsexcel', 
    'ahdschedule_backup', 'ahdAttempts','ahdsenddt','ahddocdt'
];

// 各テーブルがhidカラムを持つかチェック
while ($tableRow = $tablesResult->fetch_row()) {
    $tableName = $tableRow[0];
    
    // 無視するテーブルはスキップ
    if (in_array($tableName, $ignoreTables)) {
        continue;
    }
    
    // テーブルのカラム情報を取得
    $columnsQuery = "SHOW COLUMNS FROM `$tableName`";
    $columnsResult = $mysqli->query($columnsQuery);
    
    $hasHidColumn = false;
    while ($column = $columnsResult->fetch_assoc()) {
        if ($column['Field'] == 'hid') {
            $hasHidColumn = true;
            break;
        }
    }
    
    if ($hasHidColumn) {
        $tablesWithHid[] = $tableName;
    } else {
        $tablesWithoutHid[] = $tableName;
    }
}

echo "hidカラムを持つテーブル数: " . count($tablesWithHid) . "\n";
echo "hidカラムを持たないテーブル数: " . count($tablesWithoutHid) . "\n";

// hidカラムを持たないテーブルの一覧を表示
echo "\nhidカラムを持たないテーブル一覧:\n";
foreach ($tablesWithoutHid as $index => $table) {
    echo ($index + 1) . ". " . $table . "\n";
}

// hidカラムを持つテーブルの一覧も表示（必要に応じて）
echo "\nhidカラムを持つテーブル一覧:\n";
foreach ($tablesWithHid as $index => $table) {
    echo ($index + 1) . ". " . $table . "\n";
}

$mysqli->close();

// 日付に基づくファイル名のベース部分
$dateBase = date('ymd') . '_' . date('His');

// *** 新規追加: hidを持たないテーブルを別ファイルでバックアップ ***
$noHidBackupFileBasename = $dateBase . '_noHidTables.sql';
$noHidZipFileBasename = $dateBase . '_noHidTables.zip';
$noHidBackupFilePath = $backupDirectory . $noHidBackupFileBasename;
$noHidZipFilePath = $backupDirectory . $noHidZipFileBasename;

// hidを持たないテーブルをバックアップ
if (!empty($tablesWithoutHid)) {
    $tablesWithoutHidStr = implode(' ', $tablesWithoutHid);
    $noHidCommand = "mysqldump --no-tablespaces --default-character-set=binary " .
                "--host=$dbHost --user=$dbUser --password=$dbPass " .
                "$dbName $tablesWithoutHidStr > $noHidBackupFilePath 2>&1";
    echo "hidなしテーブルのバックアップを実行: $noHidCommand\n";
    $noHidOutput = [];
    exec($noHidCommand, $noHidOutput, $noHidReturnVar);
    echo "hidなしテーブルバックアップの戻り値: $noHidReturnVar\n";
    if ($noHidReturnVar !== 0) {
        echo "hidなしテーブルバックアップのエラー: " . implode("\n", $noHidOutput) . "\n";
    }

    // SQLファイルのサイズを確認
    $noHidSqlSize = filesize($noHidBackupFilePath);
    echo "hidなしテーブルのSQLファイルサイズ: $noHidSqlSize バイト\n";
    
    if ($noHidSqlSize > 0) {
        // PHP ZipArchiveを使用してZIP圧縮
        $noHidZip = new ZipArchive();
        if ($noHidZip->open($noHidZipFilePath, ZipArchive::CREATE) === TRUE) {
            // SQLファイルを追加
            if ($noHidZip->addFile($noHidBackupFilePath, $noHidBackupFileBasename)) {
                echo "ファイル {$noHidBackupFileBasename} をZIPに追加しました\n";
            } else {
                echo "ファイル {$noHidBackupFileBasename} のZIPへの追加に失敗しました\n";
            }
            
            // ZIPを閉じる
            if ($noHidZip->close()) {
                echo "hidなしテーブル用ZIPファイルを作成しました: $noHidZipFilePath\n";
            } else {
                echo "hidなしテーブル用ZIPファイルの作成に失敗しました\n";
            }
        } else {
            echo "hidなしテーブル用ZIPファイルの作成に失敗しました\n";
        }
        
        // ZIPファイルのサイズを確認
        if (file_exists($noHidZipFilePath)) {
            $noHidZipSize = filesize($noHidZipFilePath);
            echo "hidなしテーブル用ZIPファイルサイズ: $noHidZipSize バイト\n";
            
            if ($noHidZipSize > 0) {
                // 圧縮後のSQLファイルを削除
                unlink($noHidBackupFilePath);
                echo "hidなしテーブル用SQLファイルを削除しました: $noHidBackupFilePath\n";
            } else {
                echo "警告: hidなしテーブル用ZIPファイルのサイズが0です。SQLファイルは保持されます。\n";
            }
        } else {
            echo "エラー: hidなしテーブル用ZIPファイルが作成されませんでした。\n";
        }
    } else {
        echo "警告: hidなしテーブル用バックアップファイルのサイズが0です。\n";
    }
} else {
    echo "hidを持たないテーブルがありません。\n";
}

// 各グループでバックアップを作成（hidを持つテーブルのみ）
foreach ($groups as $groupNum => $groupInfo) {
    // ファイル名にはパスを含めない（ベース名のみ）
    $backupFileBasename = $dateBase . '_group' . $groupNum . '_' . $groupInfo['range'] . '.sql';
    $zipFileBasename = $dateBase . '_group' . $groupNum . '_' . $groupInfo['range'] . '.zip';
    
    // パスを含むフルパス名
    $backupFilePath = $backupDirectory . $backupFileBasename;
    $zipFilePath = $backupDirectory . $zipFileBasename;
    
    // 空のSQLファイルを作成
    file_put_contents($backupFilePath, "");
    echo "空のSQLファイルを作成しました: $backupFilePath\n";
    
    // hidを持つテーブルを条件付きでバックアップ
    foreach ($tablesWithHid as $table) {
        $command2 = "mysqldump --no-tablespaces --default-character-set=binary " .
                  "--host=$dbHost --user=$dbUser --password=$dbPass " .
                  "--where=\"" . $groupInfo['where'] . "\" " .
                  "$dbName $table >> $backupFilePath 2>&1";
        echo "テーブル $table のバックアップ中...\n";
        $output2 = [];
        exec($command2, $output2, $return_var2);
        if ($return_var2 !== 0) {
            echo "テーブル $table のバックアップエラー: " . implode("\n", $output2) . "\n";
        }
    }
    
    // 最終的なSQLファイルのサイズを確認
    $finalSqlSize = filesize($backupFilePath);
    echo "最終SQLファイルサイズ: $finalSqlSize バイト\n";
    
    if ($finalSqlSize == 0) {
        echo "警告: バックアップファイルのサイズが0です。\n";
        continue;
    }
    
    // PHP ZipArchiveを使用してZIP圧縮
    $zip = new ZipArchive();
    if ($zip->open($zipFilePath, ZipArchive::CREATE) === TRUE) {
        // SQLファイルを追加
        if ($zip->addFile($backupFilePath, $backupFileBasename)) {
            echo "ファイル {$backupFileBasename} をZIPに追加しました\n";
        } else {
            echo "ファイル {$backupFileBasename} のZIPへの追加に失敗しました\n";
        }
        
        // ZIPを閉じる
        if ($zip->close()) {
            echo "ZIPファイルを作成しました: $zipFilePath\n";
        } else {
            echo "ZIPファイルの作成に失敗しました\n";
        }
    } else {
        echo "ZIPファイルの作成に失敗しました\n";
    }
    
    // ZIPファイルのサイズを確認
    if (file_exists($zipFilePath)) {
        $zipSize = filesize($zipFilePath);
        echo "ZIPファイルサイズ: $zipSize バイト\n";
        
        if ($zipSize > 0) {
            // 圧縮後のSQLファイルを削除
            unlink($backupFilePath);
            echo "SQLファイルを削除しました: $backupFilePath\n";
        } else {
            echo "警告: ZIPファイルのサイズが0です。SQLファイルは保持されます。\n";
        }
    } else {
        echo "エラー: ZIPファイルが作成されませんでした。\n";
    }
    
    echo "グループ{$groupNum}（" . $groupInfo['range'] . "）のバックアップが完了しました\n";
}

echo "バックアッププロセスが完了しました。\n";
?>
