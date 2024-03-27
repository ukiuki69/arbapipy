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
# print("Content-Type: text/html; charset=UTF-8;\n\n")
print("Content-Type: application/json; charset=UTF-8\n\n")


def outPut(dt):
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

if __name__ == "__main__":
  # dbより値取得
  rtn = getDt()
  obj = json.loads(rtn)
  # コピー先ファイル名を分解
  obj['dst'] = os.path.split(obj['dt'][0]['dst'])
  dstdir = DOCDIR + obj['dst'][0]
  # ディレクトリ作成
  if os.path.exists(dstdir) == False:
    os.mkdir(dstdir)
  # ファイルコピー実施
  shutil.copyfile(DOCDIR + '/' + obj['dt'][0]['template'], \
    DOCDIR + '/' + obj['dt'][0]['dst'])
  
  rtn = json.dumps(obj)
  outPut(rtn)
