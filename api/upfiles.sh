#!/bin/bash

# FTP接続情報
HOST="sv10767.xserver.jp"
USER="albxe56tyuvh98@albatross56.xsrv.jp"
PASS="xDT-3ky*5_%DwHi"
REMOTEPATH="/rbatos.com/public_html/houday/api"

# 取得したファイル名の表示とアップロード
echo "取得したファイル名とアップロード:"
for FILE in "$@"; do
  echo "ファイル名: $FILE"
  curl -T "$FILE" --user "$USER:$PASS" "ftp://$HOST$REMOTEPATH/"
done

echo "すべてのファイルのアップロードが完了しました。"
