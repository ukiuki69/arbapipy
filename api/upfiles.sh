#!/bin/bash

# FTP接続情報
HOST="sv10767.xserver.jp"
USER="alb2024@albatross56.xsrv.jp"
PASS="fgghd$43#334w##"
REMOTEPATH="/rbatos.com/public_html/houday/api"

# 取得したファイル名の表示とアップロード
echo "取得したファイル名とアップロード:"
for FILE in "$@"; do
  echo "ファイル名: $FILE"
  curl -T "$FILE" --user "$USER:$PASS" "ftp://$HOST$REMOTEPATH/"
done

echo "すべてのファイルのアップロードが完了しました。"
