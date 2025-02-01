<?php
header("Access-Control-Allow-Origin: *");

// 削除対象の文言（`text`内で検索し、これらの文言以前の内容を削除）
$textRemovePhrases = [
    "アプリが更新されます。",
    "画像はクリックすると拡大します。"
];

// 除外する画像URL（`img`内でこのリストに含まれる画像URLは削除）
$excludeImgUrls = [
    "https://rbatos.com/lp/wp-content/uploads/2024/11/d2fda9acddb72967f00bee31f70eee3f.png",
    "https://rbatos.com/lp/wp-content/uploads/2024/12/ab238924293dbf10691a31ade91d3028-1-1024x189.png"
];

// カテゴリごとのRSSフィードのURL
$feedUrls = [
    "help" => "https://rbatos.com/lp/category/help/feed/",
    "management" => "https://rbatos.com/lp/category/management/feed/",
    "update" => "https://rbatos.com/lp/category/update/feed/"
];

// 各フィードのデータを取得し、JSONに変換
$result = [];
foreach ($feedUrls as $category => $feedURL) {
    $result[$category] = fetchAndParseFeed($feedURL, $textRemovePhrases, $excludeImgUrls);
}

// JSONとして出力
header("Content-Type: application/json; charset=UTF-8");
echo json_encode($result);

// フィードを取得しJSONに変換する関数
function fetchAndParseFeed($feedURL, $textRemovePhrases, $excludeImgUrls) {
    // cURLセッションを初期化
    $ch = curl_init($feedURL);

    // cURLオプションを設定
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.82 Safari/537.36");

    // リクエストを実行してRSSコンテンツを取得
    $rssContent = curl_exec($ch);

    // エラーチェック
    if (curl_errno($ch)) {
        curl_close($ch);
        return ["error" => "Failed to fetch RSS feed: " . curl_error($ch)];
    }

    // ステータスコード確認
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpStatus != 200) {
        curl_close($ch);
        return ["error" => "Received HTTP status code " . $httpStatus];
    }

    // cURLセッションを閉じる
    curl_close($ch);

    // RSSをJSONに変換
    return rssToJson($rssContent, $textRemovePhrases, $excludeImgUrls);
}

// RSSをJSONに変換する関数
function rssToJson($rssContent, $textRemovePhrases, $excludeImgUrls) {
    $rss = simplexml_load_string($rssContent, "SimpleXMLElement", LIBXML_NOCDATA);
    if ($rss === false) {
        return ["error" => "Failed to parse RSS feed"];
    }

    $json = [];
    foreach ($rss->channel->item as $item) {
        // descriptionからテキストを抽出し、特殊文字と改行を除去
        $description = (string) $item->description;
        $text = strip_tags($description);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8'); // HTMLエンティティをデコード
        // $text = preg_replace('/[\r\n]+/', '', $text); // 改行をスペースに置換
        $text = preg_replace(['/[\r\n]+/', '/　+/'], '', $text); // 改行と全角スペースを削除

        // 削除対象の文言が見つかった場合、その文言より前のテキストを削除
        foreach ($textRemovePhrases as $phrase) {
            if (strpos($text, $phrase) !== false) {
                $text = substr($text, strpos($text, $phrase) + strlen($phrase));
                break; // 最初に見つかった文言で削除を適用
            }
        }

        // カテゴリを配列として取得
        $categories = [];
        foreach ($item->category as $category) {
            $categories[] = (string) $category;
        }

        // descriptionから画像URLを最大3つ抽出し、除外リストに基づきフィルタリング
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $description, $matches);
        $imgUrls = array_slice(array_filter($matches[1], function ($url) use ($excludeImgUrls) {
            return !in_array($url, $excludeImgUrls);
        }), 0, 3);

        $json[] = [
            "title" => (string) $item->title,
            "link" => (string) $item->link,
            "pubDate" => (string) $item->pubDate,
            "text" => $text,
            "img" => $imgUrls,
            "category" => $categories
        ];
    }
    return $json;
}
