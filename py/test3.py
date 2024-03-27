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
import openpyxl
import cgi

APIURL = "http://albatross56.xsrv.jp/hd/api/api.php"
DOCDIR = "/var/www/html/docs"
ROOT = "/var/www/html/"

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

def outPut(dt):
  print("Content-Type: application/json; charset=UTF-8\n\n")
  # print("Content-Type: text/html; charset=UTF-8;\n\n")
  print(dt)

def getDt():
  arg = cgi.FieldStorage()
  prms = {
    'a': 'fetchDocument',
    # 'hid': 'LE5MMsTF',
    # 'bid': 'p0CxjWNL',
    'hid': arg['hid'].value,
    'bid': arg['bid'].value,
    'stamp': arg['stamp'].value,
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

# fnのエクセルファイルの編集を行う
# dtは書き込みデータ
# procはプロセス名
def handleExcel(fn, dt, proc):
  wb = openpyxl.load_workbook(fn)
  aria = wb.defined_names['data']  # 名前付きのエリア取得
  ws = wb[aria.attr_text.split('!')[0]]
  cells = ws[aria.attr_text.split('!')[1]]
  i = 0
  for rowdt in dt['data']:
    j = 0
    for celldt in rowdt:
      cell = cells[i][j]
      cell.value = celldt
      j += 1
    i += 1
  wb.save(fn)

if __name__ == "__main__":
  # dbより値取得
  rtn = {}
  try:
    rtnStr = getDt()  # dbよりデータ取得
    dobj = json.loads(rtnStr)
    dobj = handleFlile(dobj) # ダウンロード用ディレクトリを作製してコピー実施
    handleExcel(dobj['dstPath'], dobj['dt'][0]['content'], '')
    rtn['result'] = True
    rtn['dstPath'] = dobj['dstPath']
    rtn['root'] = ROOT
  except Exception:
    rtn['result'] = False
  else:
    rtn['finish'] = True

  outPut(json.dumps(rtn))
