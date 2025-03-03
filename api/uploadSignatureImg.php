<?php
  /**
   * このスクリプトは以下の機能を提供します：
   * 
   * 1. ディレクトリ配列 (`directory`) の指定に基づき、深い階層のフォルダを再帰的に作成。
   * 2. 画像ファイルを受け取り、指定のディレクトリに保存したうえで、長辺を指定ピクセルに合わせてリサイズ（JPEG/PNG 対応）。
   * 3. list パラメータを指定した場合は、アップロードを行わず、指定ディレクトリ内のファイル一覧を JSON 形式で返却。
   * 
   * 【パラメータ詳細】
   * - directory: JSON 形式の配列 (例: ["teikyuouJisseki","1310020003","20250101"]) 
   *   ・ "../signature" フォルダ配下にこれらを連結してディレクトリを作成する。
   *   ・ 例: ../signature/teikyuouJisseki/1310020003/20250101
   * - size: リサイズ後の長辺ピクセル数 (省略時は 400)
   * - list: 値が指定されている場合、アップロード処理を行わずにファイル一覧を返却
   *   ・ ディレクトリが存在しない場合はエラー返却
   * - file: アップロード対象のファイル (JPEG/PNG)
   * 
   * 【動作フロー】
   * 1) パラメータをまとめて取得し、バリデーションを実施
   * 2) list パラメータが指定されている場合は、当該ディレクトリのファイル名一覧を JSON で返却して処理終了
   * 3) list が指定されていない場合は、ディレクトリを作成 (未存在なら再帰的に作成)
   * 4) アップロードされたファイルを保存先へ移動
   * 5) 画像を指定ピクセルサイズにリサイズ (EXIF情報を考慮した自動回転あり)
   * 6) 結果を JSON 形式で返却
   * 
   */

  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);

  // クロスドメインリクエストの処理を許可するヘッダー
  header("Access-Control-Allow-Origin: *");
  // header("Access-Control-Allow-Origin: http://localhost:3000");
  header('Access-Control-Allow-Headers: Content-Type');
  header("Access-Control-Allow-Methods: POST, GET");

  /**
   * EXIF情報を読み取り、画像の回転を修正する関数
   */
  function correctImageRotation(&$sourceImage, $source) {
    $exif = @exif_read_data($source);
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
   * 画像を指定の長辺サイズにリサイズし、上書き保存する関数
   * 
   * @param string $source     元画像のファイルパス
   * @param int    $newMaxSize リサイズ後の長辺のサイズ（ピクセル）
   * @return bool  リサイズに成功すれば true、失敗なら false
   */
  function resizeImage($source, $newMaxSize) {
    // 画像タイプをチェック
    $imageType = exif_imagetype($source);
    if ($imageType == IMAGETYPE_JPEG) {
      $sourceImage = imagecreatefromjpeg($source);
    } elseif ($imageType == IMAGETYPE_PNG) {
      $sourceImage = imagecreatefrompng($source);
    } else {
      return false; // 非対応フォーマット
    }

    // EXIF情報に応じて回転修正
    correctImageRotation($sourceImage, $source);

    $width  = imagesx($sourceImage);
    $height = imagesy($sourceImage);

    // 長辺に合わせてスケールを決定
    $longSide = max($width, $height);
    $scale = $newMaxSize / $longSide;

    $newWidth  = round($width  * $scale);
    $newHeight = round($height * $scale);

    // リサイズ先の画像リソースを作成
    $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

    // PNG の場合は透過情報を維持
    if ($imageType == IMAGETYPE_PNG) {
      imagealphablending($resizedImage, false);
      imagesavealpha($resizedImage, true);
    }

    // リサンプリングしてリサイズ
    imagecopyresampled(
      $resizedImage,
      $sourceImage,
      0,
      0,
      0,
      0,
      $newWidth,
      $newHeight,
      $width,
      $height
    );

    // リサイズ後の画像を上書き保存
    if ($imageType == IMAGETYPE_JPEG) {
      imagejpeg($resizedImage, $source, 90);
    } elseif ($imageType == IMAGETYPE_PNG) {
      imagepng($resizedImage, $source);
    }

    imagedestroy($sourceImage);
    imagedestroy($resizedImage);

    return true;
  }

  // ここからメイン処理

  // 1) リクエストパラメータをまとめて取得
  $dirArrayJson = $_REQUEST['directory'] ?? '[]';
  $sizeParam    = $_REQUEST['size'] ?? '400';
  $listParam    = $_REQUEST['list'] ?? null;  // ディレクトリ一覧を取得するかどうか

  // $_FILES からファイル情報を取得
  $uploadedFile = $_FILES['file'] ?? null;

  // 2) パラメータのバリデーション
  // directory パラメータを配列に変換
  $dirArray = json_decode($dirArrayJson, true);
  if (!is_array($dirArray) || empty($dirArray)) {
    echo json_encode([
      'result'   => false,
      'error'    => 'directory parameter is invalid or empty',
      'filename' => ''
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // size パラメータは数値変換
  $newMaxSize = (int)$sizeParam;

  // ディレクトリ作成用のパスを生成
  $baseDir = '../signature';
  $subPath = implode('/', $dirArray);
  $dirname = $baseDir . '/' . $subPath;

  // 3) list パラメータが指定されている場合はディレクトリのファイルリストを返却
  // 例：list=1 などの指定を想定
  if (!empty($listParam)) {
    if (is_dir($dirname)) {
      // ディレクトリが存在する場合、ファイルリストを取得
      // scandir で . と .. は除外
      $files = array_values(
        array_diff(scandir($dirname), ['.', '..'])
      );
      echo json_encode([
        'result' => true,
        'files'  => $files
      ], JSON_UNESCAPED_UNICODE);
    } else {
      // ディレクトリが存在しない場合はエラー
      echo json_encode([
        'result' => false,
        'error'  => 'Directory not found'
      ], JSON_UNESCAPED_UNICODE);
    }
    exit;
  }

  // 4) アップロード & リサイズ処理
  // ディレクトリが存在しなければ作成
  if (!file_exists($dirname)) {
    mkdir($dirname, 0755, true);
  }

  // ファイルチェック
  if (!$uploadedFile || !isset($uploadedFile['tmp_name'])) {
    echo json_encode([
      'result'   => false,
      'error'    => 'No file uploaded',
      'filename' => ''
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $tempFile       = $uploadedFile['tmp_name'];
  $originalName   = $uploadedFile['name'];
  $destination    = $dirname . '/' . $originalName;

  $rt = [];
  if (is_uploaded_file($tempFile)) {
    if (move_uploaded_file($tempFile, $destination)) {
      // 指定の長辺サイズにリサイズ
      $resizeResult = resizeImage($destination, $newMaxSize);

      $rt['result']   = $resizeResult;
      $rt['filename'] = $destination;
    } else {
      $rt['result']   = false;
      $rt['filename'] = $destination;
      $rt['error']    = 'Failed to move uploaded file.';
    }
  } else {
    $rt['result']   = false;
    $rt['filename'] = '';
    $rt['error']    = 'File is not correctly uploaded.';
  }

  echo json_encode($rt, JSON_UNESCAPED_UNICODE);
?>
