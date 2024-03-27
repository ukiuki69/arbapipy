#!/usr/bin/python3.6
# -- coding: utf-8 --
import io
import sys
import os
import shutil
import requests
import pprint
import json
import pdb


APIURL = "http://albatross56.xsrv.jp/hd/api/api.php"
DOCDIR = "/var/www/html/docs"

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

def outPut(dt):
  print("Content-Type: application/json; charset=UTF-8\n\n")
  # print("Content-Type: text/html; charset=UTF-8;\n\n")
  print(dt)

def getDt():
  prms = {
    'a': 'fetchDocument',
    'hid': 'LE5MMsTF',
    'bid': 'p0CxjWNL',
    'stamp': 2,
  }
  rt = requests.get(APIURL, prms)
  return(rt.text)

def handleFlile(dobj):
  # コピー先ファイル名を分解
  dobj['dst'] = os.path.split(dobj['dt'][0]['dst'])
  dstdir = DOCDIR + dobj['dst'][0]
  # ディレクトリ作成
  if os.path.exists(dstdir) == False:
    os.mkdir(dstdir)
  # ファイルコピー実施
  dstPath = DOCDIR + dobj['dt'][0]['dst']
  # アトリビュートごとコピーする パーミッションを引き継ぐため
  shutil.copy2(DOCDIR + '/' + dobj['dt'][0]['template'], dstPath)
  dobj['dstPath'] = dstPath
  return(dobj)

if __name__ == "__main__":
  # dbより値取得
  rtnStr = getDt()  # dbよりデータ取得
  dobj = json.loads(rtnStr)
  dobj = handleFlile(dobj) # ダウンロード用ディレクトリを作製してコピー実施
  rtn = json.dumps(dobj)
  outPut(rtn)
