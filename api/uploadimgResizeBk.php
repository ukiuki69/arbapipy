<!-- 
このスクリプトは、アップロードされた画像ファイルを縮小し、サムネイルを作成するPHPスクリプトで
す。以下は、スクリプトの概要と各関数の説明です。

概要：

クロスドメインリクエストの処理を許可するヘッダーを設定する。
画像を縮小するためのresizeImage関数を定義する。
アップロードされた画像を指定されたディレクトリに保存し、縮小とサムネイルの作成を行う。
処理結果をJSON形式で返す。
resizeImage関数の引数と返り値の説明：

引数：$source(元画像のファイルパス), $destination(出力ファイルパス), $newSize(リサイズ後のサイズ)
返り値：成功時にtrue、失敗時にfalse
resizeImage関数の機能：

画像をリサイズして指定された出力先に保存する。
処理に成功した場合はtrueを返し、失敗した場合はfalseを返す。
-->
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Access-Control-Allow-Origin: http://localhost:3000");
header('Access-Control-Allow-Headers: Content-Type');
header("Access-Control-Allow-Methods: POST, GET");

function resizeImage($source, $destination, $newSize) {
  $imageType = exif_imagetype($source);
  if ($imageType == IMAGETYPE_JPEG) {
      $sourceImage = imagecreatefromjpeg($source);
  } elseif ($imageType == IMAGETYPE_PNG) {
      $sourceImage = imagecreatefrompng($source);
  } else {
      return false; // Unsupported image format
  }

  $width = imagesx($sourceImage);
  $height = imagesy($sourceImage);
  $ratio = $height / $width;

  if ($width > $height) {
      $newWidth = $newSize;
      $newHeight = $newSize * $ratio;
  } else {
      $newWidth = $newSize / $ratio;
      $newHeight = $newSize;
  }

  $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
  imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

  if ($imageType == IMAGETYPE_JPEG) {
      imagejpeg($resizedImage, $destination, 90);
  } elseif ($imageType == IMAGETYPE_PNG) {
      imagepng($resizedImage, $destination);
  }

  imagedestroy($sourceImage);
  imagedestroy($resizedImage);
  return true;
}

$tempfile = $_FILES['file']['tmp_name'];
$dirname = '../contactbookimg/' . $_REQUEST['today'] . '/' . $_REQUEST['rnddir'];
$dateDir = '../contactbookimg/' . $_REQUEST['today'];
$filename = $dirname . '/' . $_FILES['file']['name'];
$newSize = 1200;
$thumbnailSize = 400;

if (!file_exists($dirname)){
    mkdir($dirname, 0755, true);
    chmod($dateDir, 0755);
    chmod($dirname, 0755);
}

$rt = [];
if (is_uploaded_file($tempfile)) {
    if (move_uploaded_file($tempfile, $filename)) {
        resizeImage($filename, $filename, $newSize);
        $thumbnailFilename = $dirname . '/tn_' . $_FILES['file']['name'];
        resizeImage($filename, $thumbnailFilename, $thumbnailSize);
        $rt['result']  = true;
        $rt['filename']  = $filename;
        $rt['thumbnail'] = $thumbnailFilename;
    } else {
        $rt['result']  = false;
        $rt['filename']  = $filename;
    }
} else {
    $rt['result']  = false;
    $rt['filename']  = '';
}

echo json_encode($rt, JSON_UNESCAPED_UNICODE);
?>
