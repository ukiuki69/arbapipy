#!/usr/bin/python3.6
# -- coding: utf-8 --
import io
from pickle import FALSE, TRUE
import sys
import os
import requests
import pprint
import json
import pdb
import cgi
import csv
import ftplib
import logging

APIURL = "https://houday.rbatos.com/api/api.php"
# APIURL = "https://houday.rbatos.com/api/apidev.php"
CSVDIR = "/var/www/html/csv/"
ROOT = "/var/www/html/"
FTPDSTDIR = '/rbatos.com/public_html/houday/csv/'

def ftp_upload(
  hostname, username, password, port, upload_src_path, upload_dst_path, timeout, rnddir
):
  with ftplib.FTP() as ftp:
    try:
      ftp.connect(host=hostname, port=port, timeout=timeout)
      # パッシブモード設定
      ftp.set_pasv("true")
      # FTPサーバログイン
      ftp.login(username, password)
      ftp.mkd(FTPDSTDIR + rnddir)
      with open(upload_src_path, 'rb') as fp:
        ftp.storbinary(upload_dst_path, fp)
      return 'true'
    except ftplib.all_errors as e:
      return 'false'


# # logの設定
# logger = logging.getLogger(__name__)
# formatter = '%(asctime)s:%(name)s:%(levelname)s:%(message)s'
# logging.basicConfig(
#     filename='./ftp_logger.log',
#     level=logging.DEBUG,
#     format=formatter
# )
# logger.setLevel(logging.INFO)

# 接続先サーバーのホスト名

def upLoadthis(upload_src_path, rnddir, upload_dst_path):
  hostname = "sv10767.xserver.jp" 
  # アップロードするファイルパス
  # upload_src_path = "./test.jpg" 
  # アップロード先のファイルパス（STORはファイルをアップロードするためのFTPコマンドなので必要です。）
  upload_dst_path = "STOR " + FTPDSTDIR + rnddir + '/' + upload_dst_path 
  # サーバーのユーザー名
  username = "alb2024@albatross56.xsrv.jp" 
  # サーバーのログインパスワード
  password = "fgghd$43#334w##" 
  # FTPサーバポート
  port = 21 
  timeout = 50

  # logger.info("===START FTP===")
  ftprt = ftp_upload(
    hostname, username, password, port, upload_src_path, upload_dst_path, timeout, rnddir
  )
  return ftprt
  # logger.info("===FINISH FTP===")


sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

def outPut(dt):
  print("Content-Type: application/json; charset=UTF-8\n\n")
  # print("Content-Type: text/html; charset=UTF-8;\n\n")
  print(dt)

def getPrms():
  arg = cgi.FieldStorage()
  prms = {
    'a': 'fetchSomeState',

    # 'jino': '1451000374',
    # 'date': '2022-03-01',
    # 'item': 'KF',
    # 'encode': 'shift_jis',
    # 'rnddir': 'ntc9tg7yjrqk6di7kwou',
    # 'prefix': 'tstuk',
    # 'format': 'fixed',
    # 'target': 'hamagin',
    
    # 'jino': '1453200212',
    # 'date': '2022-02-01',
    # 'item': 'KF',
    # 'encode': 'utf-8',
    # 'rnddir': 'rjp0blgqn7e3m4u3lrpk',
    # 'prefix': 'asahi',
    # 'format': 'csv',
    # 'target': 'saninFact',

    'item': arg['item'].value,
    'jino': arg['jino'].value,
    'date': arg['date'].value,
    'rnddir': arg['rnddir'].value,
    'format': arg['format'].value,
    'encode': arg['encode'].value,
    'target': arg['target'].value,
    'prefix': arg['prefix'].value,
    
  }
  return prms

def fetchDt(prms):
  rt = requests.post(APIURL, prms)
  return(rt.text)

def outPutFile(rtn, prms):
  # コピー先ファイル名を分解

  dstdir = CSVDIR + prms['rnddir']
  # ディレクトリ作成
  if os.path.exists(dstdir) == False:
    os.mkdir(dstdir)
  
  outputDt = rtn['dt'][0]['state']
  # パラメータのプレフィクスを大文字化して3桁取得
  prefix = prms['prefix'].upper()[0:3]
  # 年月日からハイフンを取り除きyymmを得る
  monthStr = prms['date'].replace('-','')[2:6]
  # 金融機関ごとの識別子より文字列を得る
  fnameBody = prms['target'].upper()[0:6]
  format = prms['format']
  # フォーマットにより識別子を決める
  if format == 'csv':
    ext = '.csv'
    quotingVal = csv.QUOTE_ALL
  elif format == 'fixed':
    ext = '.txt'
    quotingVal = csv.QUOTE_NONE
  else:
    ext = ''
  # 出力エンコーディング
  encode = prms['encode']
  # 出力ファイル
  outFile = dstdir + '/' + prefix + monthStr + fnameBody + ext
  
  if format == 'csv':
    with open(outFile, 'w', encoding=encode) as csvfile:
      writer = csv.writer(csvfile, quotechar='"',quoting=quotingVal, lineterminator='\n')
      writer.writerows(outputDt)
  elif format == 'fixed':
    with open(outFile, 'w', encoding=encode, newline='\r\n') as f:
      for l in outputDt:
        f.write("%s\n" % l)
  
  rtn = {
    'fname': outFile,
    'len': len(outputDt),
  }
  return (rtn)


if __name__ == "__main__":
  # dbより値取得
  prms = getPrms()  # パラメータ取得
  rtn = fetchDt(prms) # dbよりデータ取得
  dobj = json.loads(rtn)
  out = outPutFile(dobj, prms)
  out['root'] = ROOT
  fbody = out['fname'].split('/')[-1]
  out['ftpresult'] = upLoadthis(out['fname'], prms['rnddir'], fbody)
  outPut(json.dumps(out))