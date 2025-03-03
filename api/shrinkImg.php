<?php
/**
 * 画像リサイズ処理スクリプト
 *
 * BASE_DIR直下のYYYYMMDD形式のディレクトリのうち、基準日から
 * DATE_RANGE日以内のディレクトリ配下を再帰的に探索し、条件に合致
 * する画像ファイル（jpg/jpeg/png）をリサイズして上書き保存します。
 *
 * 通常画像: ファイル名先頭が数字10桁、ファイルサイズ>=100KB、
 *          長辺が400px超の場合に長辺を400pxに縮小
 * サムネイル: ファイル名先頭が "tn_" + 数字10桁、ファイルサイズ>=40KB、
 *          長辺が200px超の場合に長辺を200pxに縮小
 */

// 定数定義
define('BASE_DIR', '../contactbookimg');
define('REFERENCE_DATE_OFFSET', 14);
// YYYY‐MM‐DD形式で基準日を直接設定できるようにします。
define('REFERENCE_DATE', '2024-12-31'); // 例: 2023年10月1日
// 参考: REFERENCE_DATE_OFFSETを使用して設定する場合は、以下のコードを利用できます。
// define('REFERENCE_DATE', date('Ymd', strtotime('-' . REFERENCE_DATE_OFFSET . ' days')));
define('DATE_RANGE', 30);

define('NORMAL_SIZE_THRESHOLD', 100 * 1024); // 100KB
define('THUMB_SIZE_THRESHOLD', 40 * 1024);   // 40KB
define('NORMAL_MAX_LONG', 400);
define('THUMB_MAX_LONG', 200);

// 対象とする拡張子
$validExtensions = ['jpg', 'jpeg', 'png'];

// 基準日と下限日の計算
// REFERENCE_DATEのフォーマットが「YYYY-MM-DD」形式の場合とYYYYMMDD形式の場合で処理を分岐
if (strpos(REFERENCE_DATE, '-') !== false) {
    $refDate = DateTime::createFromFormat('Y-m-d', REFERENCE_DATE);
} else {
    $refDate = DateTime::createFromFormat('Ymd', REFERENCE_DATE);
}
$lowerBound = clone $refDate;
$lowerBound->modify('-' . DATE_RANGE . ' days');

// BASE_DIR直下のディレクトリをチェック
$baseDirPath = BASE_DIR;
if (!is_dir($baseDirPath)) {
  echo "BASE_DIRが存在しません: $baseDirPath\n";
  exit(1);
}

$dirIterator = new DirectoryIterator($baseDirPath);
foreach ($dirIterator as $dirInfo) {
  if ($dirInfo->isDot() || !$dirInfo->isDir()) {
    continue;
  }
  $dirname = $dirInfo->getFilename();
  // ディレクトリ名がYYYYMMDD形式かチェック
  if (!preg_match('/^\d{8}$/', $dirname)) {
    continue;
  }
  $dirDate = DateTime::createFromFormat('Ymd', $dirname);
  if (!$dirDate) {
    continue;
  }
  // 基準日～(基準日-DATE_RANGE)の範囲内か判定
  if ($dirDate < $lowerBound || $dirDate > $refDate) {
    continue;
  }
  // 対象ディレクトリ以下を再帰的に処理
  processDirectory($dirInfo->getPathname(), $validExtensions);
}

/**
 * 指定ディレクトリ以下のファイルを再帰的に処理する
 *
 * @param string $dirPath
 * @param array  $validExtensions
 */
function processDirectory($dirPath, $validExtensions) {
  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
      $dirPath,
      RecursiveDirectoryIterator::SKIP_DOTS
    )
  );
  foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
      continue;
    }
    $filePath = $fileInfo->getPathname();
    $filename = $fileInfo->getFilename();
    $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, $validExtensions, true)) {
      continue;
    }
    // 通常画像: 数字10桁から始まる
    if (preg_match('/^\d{6}/', $filename)) {
      processImage($filePath, NORMAL_MAX_LONG, NORMAL_SIZE_THRESHOLD, 
        $ext);
    }
    // サムネイル: tn_ + 数字10桁から始まる
    elseif (preg_match('/^tn_\d{6}/', $filename)) {
      processImage($filePath, THUMB_MAX_LONG, THUMB_SIZE_THRESHOLD, 
        $ext);
    }
  }
}

/**
 * 画像ファイルをリサイズして上書き保存する
 *
 * @param string $filePath      対象ファイルパス
 * @param int    $maxLong       縮小後の長辺サイズ(px)
 * @param int    $sizeThreshold 処理対象となる最小ファイルサイズ(バイト)
 * @param string $ext           画像の拡張子(jpg, jpeg, png)
 */
function processImage($filePath, $maxLong, $sizeThreshold, $ext) {
  // ファイルサイズチェック
  if (filesize($filePath) < $sizeThreshold) {
    return;
  }
  $imgInfo = getimagesize($filePath);
  if (!$imgInfo) {
    return;
  }
  list($width, $height) = $imgInfo;
  $currentLong = max($width, $height);
  // 指定サイズ以下なら処理不要
  if ($currentLong <= $maxLong) {
    return;
  }
  // 縮小率の計算
  $scale    = $maxLong / $currentLong;
  $newWidth = (int)round($width * $scale);
  $newHeight= (int)round($height * $scale);

  // 画像の読み込み
  switch ($ext) {
    case 'jpg':
    case 'jpeg':
      $srcImg = imagecreatefromjpeg($filePath);
      break;
    case 'png':
      $srcImg = imagecreatefrompng($filePath);
      break;
    default:
      return;
  }
  if (!$srcImg) {
    return;
  }

  // 新規画像リソースの作成
  $dstImg = imagecreatetruecolor($newWidth, $newHeight);
  if ($ext === 'png') {
    // PNGの透過情報を保持
    imagealphablending($dstImg, false);
    imagesavealpha($dstImg, true);
    $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
    imagefilledrectangle($dstImg, 0, 0, $newWidth, $newHeight, 
      $transparent);
  }
  // リサンプリング
  imagecopyresampled(
    $dstImg,
    $srcImg,
    0,
    0,
    0,
    0,
    $newWidth,
    $newHeight,
    $width,
    $height
  );
  // 上書き保存
  switch ($ext) {
    case 'jpg':
    case 'jpeg':
      imagejpeg($dstImg, $filePath, 42);
      break;
    case 'png':
      imagepng($dstImg, $filePath, 3);
      break;
  }
  echo $filePath . "\n";
  imagedestroy($srcImg);
  imagedestroy($dstImg);
}
?>
