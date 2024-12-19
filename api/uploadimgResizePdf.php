<?php
// このスクリプトは、アップロードされた画像ファイルを縮小し、サムネイルを作成するPHPスクリプトです。
// PDFファイルのアップロードもサポートし、ファイルサイズの制限を行います。
// 以下は、スクリプトの概要と各関数の説明です。

// 概要：

// クロスドメインリクエストの処理を許可するヘッダーを設定する。
// 画像を縮小するためのresizeImage関数を定義する。
// アップロードされた画像を指定されたディレクトリに保存し、縮小とサムネイルの作成を行う。
// 処理結果をJSON形式で返す。
// resizeImage関数の引数と返り値の説明：

// 引数：$source(元画像のファイルパス), $destination(出力ファイルパス), $newSize(リサイズ後のサイズ)
// 返り値：成功時にtrue、失敗時にfalse
// resizeImage関数の機能：

// 画像をリサイズして指定された出力先に保存する。
// 処理に成功した場合はtrueを返し、失敗した場合はfalseを返す。

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header("Access-Control-Allow-Origin: http://localhost:3000");
header('Access-Control-Allow-Headers: Content-Type');
header("Access-Control-Allow-Methods: POST, GET");

// 定数の定義
define('MAX_PDF_SIZE', 10 * 1024 * 1024); // 10MB

/**
 * 画像の回転を修正する関数
 */
function correctImageRotation(&$sourceImage, $source) {
    $exif = exif_read_data($source);
    if (!empty($exif['Orientation'])) {
        switch ($exif['Orientation']) {
            case 3:
                $sourceImage = imagerotate($sourceImage, 180, 0);
                break;
            case 6:
                $sourceImage = imagerotate($sourceImage, -90, 0);
                break;
            case 8:
                $sourceImage = imagerotate($sourceImage, 90, 0);
                break;
        }
    }
}

/**
 * 画像をリサイズする関数
 * 
 * @param string $source 元画像のファイルパス
 * @param string $destination 出力ファイルパス
 * @param int $newSize リサイズ後のサイズ
 * @return bool 成功時にtrue、失敗時にfalse
 */
function resizeImage($source, $destination, $newSize) {
    $imageType = exif_imagetype($source);
    if ($imageType == IMAGETYPE_JPEG) {
        $sourceImage = imagecreatefromjpeg($source);
    } elseif ($imageType == IMAGETYPE_PNG) {
        $sourceImage = imagecreatefrompng($source);
    } else {
        return false; // サポートされていない画像形式
    }

    // リサンプリング前に画像の回転を修正
    correctImageRotation($sourceImage, $source);

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

// アップロードされたファイルの情報取得
$tempfile = $_FILES['file']['tmp_name'];
$originalFilename = basename($_FILES['file']['name']); // basename関数を使用してファイル名をサニタイズ
$dirname = '../contactbookimg/' . $_REQUEST['today'] . '/' . $_REQUEST['rnddir'];
$dateDir = '../contactbookimg/' . $_REQUEST['today'];
$filename = $dirname . '/' . $originalFilename;
$newSize = 1200;
$thumbnailSize = 400;

// アップロードディレクトリの作成
if (!file_exists($dirname)){
    mkdir($dirname, 0755, true);
    chmod($dateDir, 0755);
    chmod($dirname, 0755);
}

$rt = [];

// ファイルがアップロードされたか確認
if (is_uploaded_file($tempfile)) {
    // ファイルのMIMEタイプを取得
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $tempfile);
    finfo_close($finfo);

    // サポートされているMIMEタイプを定義
    $allowedImageTypes = ['image/jpeg', 'image/png'];
    $allowedPdfType = 'application/pdf';

    if (in_array($mimeType, $allowedImageTypes)) {
        // 画像の場合の処理
        if (move_uploaded_file($tempfile, $filename)) {
            // 画像のリサイズ
            if (resizeImage($filename, $filename, $newSize)) {
                // サムネイルの作成
                $thumbnailFilename = $dirname . '/tn_' . $originalFilename;
                if (resizeImage($filename, $thumbnailFilename, $thumbnailSize)) {
                    $rt['result']  = true;
                    $rt['filename']  = $filename;
                    $rt['thumbnail'] = $thumbnailFilename;
                } else {
                    // サムネイル作成失敗
                    $rt['result']  = false;
                    $rt['filename']  = $filename;
                    $rt['message'] = "サムネイルの作成に失敗しました。";
                }
            } else {
                // リサイズ失敗
                $rt['result']  = false;
                $rt['filename']  = $filename;
                $rt['message'] = "画像のリサイズに失敗しました。";
            }
        } else {
            // ファイル移動失敗
            $rt['result']  = false;
            $rt['filename']  = $filename;
            $rt['message'] = "ファイルのアップロードに失敗しました。";
        }
    } elseif ($mimeType === $allowedPdfType) {
        // PDFの場合の処理
        $fileSize = filesize($tempfile);
        if ($fileSize > MAX_PDF_SIZE) {
            // ファイルサイズが上限を超えている場合
            $rt['result']  = false;
            $rt['filename']  = $filename;
            $rt['message'] = "ファイルサイズの上限は " . (MAX_PDF_SIZE / (1024 * 1024)) . "MB です。";
        } else {
            // ファイルサイズが許容範囲内の場合
            if (move_uploaded_file($tempfile, $filename)) {
                $rt['result']  = true;
                $rt['filename']  = $filename;
                $rt['message'] = "PDFファイルが正常にアップロードされました。";
            } else {
                // ファイル移動失敗
                $rt['result']  = false;
                $rt['filename']  = $filename;
                $rt['message'] = "ファイルのアップロードに失敗しました。";
            }
        }
    } else {
        // サポートされていないファイルタイプ
        $rt['result']  = false;
        $rt['filename']  = '';
        $rt['message'] = "サポートされていないファイル形式です。";
    }
} else {
    // ファイルがアップロードされていない場合
    $rt['result']  = false;
    $rt['filename']  = '';
    $rt['message'] = "ファイルがアップロードされていません。";
}

// JSON形式で結果を返す
echo json_encode($rt, JSON_UNESCAPED_UNICODE);
?>
