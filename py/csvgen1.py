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
import csv
import zipfile


APIURL = "https://houday.rbatos.com/api/api.php"
CSVDIR = "/var/www/html/csv/"
TEMPLATEDIR = "/var/www/html/template2021"
ROOT = "/var/www/html/"

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

def outPut(dt):
  print("Content-Type: application/json; charset=UTF-8\n\n")
  # print("Content-Type: text/html; charset=UTF-8;\n\n")
  print(dt)

def getPrms():
  arg = cgi.FieldStorage()
  prms = {
    'a': 'fetchTransferData',

    # 'hid': 'LE5MMsTF',
    # 'bid': 'p0CxjWNM',
    # 'date': '2021-06-01',
    # 'rnddir': 'rndrnd',
    # 'prefix': 'aob',

    'hid': arg['hid'].value,
    'bid': arg['bid'].value,
    'date': arg['date'].value,
    'rnddir': arg['rnddir'].value,
    'prefix': arg['prefix'].value,
  }
  return prms

def fetchDt(prms):
  rt = requests.post(APIURL, prms)
  return(rt.text)

def outPutCsv(rtn, prms):
  # コピー先ファイル名を分解

  dstdir = CSVDIR + prms['rnddir']
  # ディレクトリ作成
  if os.path.exists(dstdir) == False:
    os.mkdir(dstdir)
  
  billing = rtn['dt'][0]['dt']['billing']
  useResult = rtn['dt'][0]['dt']['useResult']
  upperLimit = rtn['dt'][0]['dt']['upperLimit']
  # パラメータのプレフィクスを大文字化して3桁取得
  prefix = prms['prefix'].upper()[0:3]
  # 年月日からハイフンを取り除きyymmを得る
  monthStr = prms['date'].replace('-','')[2:6]
  # 請求データ
  outS = dstdir + '/' + prefix + monthStr + 'SEIKYUU.CSV'
  with open(outS, 'w', encoding='shift_jis') as csvfile:
    writer = csv.writer(csvfile, quotechar='"',quoting=csv.QUOTE_ALL, lineterminator='\n')
    writer.writerows(billing)
  # 利用実績
  outR = dstdir + '/' + prefix + monthStr + 'TEIKYOU.CSV'
  with open(outR, 'w', encoding='shift_jis') as csvfile:
    writer = csv.writer(csvfile, quotechar='"',quoting=csv.QUOTE_ALL, lineterminator='\n')
    writer.writerows(useResult)
  # 上限管理
  outJ = dstdir + '/' + prefix + monthStr + 'JOUGEN.CSV'
  with open(outJ, 'w', encoding='shift_jis') as csvfile:
    writer = csv.writer(csvfile, quotechar='"',quoting=csv.QUOTE_ALL, lineterminator='\n')
    writer.writerows(upperLimit)
  # zipファイルの名前。ここで決めておく
  outZ = dstdir + '/' + prefix + monthStr + 'ALL.zip'
  
  rtn = {
    'biling': outS,
    'bilingLen': len(billing),
    'useResult': outR,
    'useResultLen': len(useResult),
    'upperLimit': outJ,
    'upperLimitLen': len(upperLimit),
    'zip': outZ,
  }
  return (rtn)

def makeZip(p):
  # フルパスではなくファイルの名のみ取得しておく
  bFname = p['biling'].split('/')[-1]
  rFname = p['useResult'].split('/')[-1]
  jFname = p['upperLimit'].split('/')[-1]
  with zipfile.ZipFile(p['zip'], 'w', compression=zipfile.ZIP_STORED) \
    as new_zip:
    if p['bilingLen'] > 0:
      new_zip.write(p['biling'], arcname=bFname)
    if p['useResultLen'] > 0:
      new_zip.write(p['useResult'], arcname=rFname)
    if p['upperLimitLen'] > 0:
      new_zip.write(p['upperLimit'], arcname=jFname)

if __name__ == "__main__":
  # dbより値取得
  prms = getPrms()  # パラメータ取得
  rtn = fetchDt(prms) # dbよりデータ取得
  dobj = json.loads(rtn)
  out = outPutCsv(dobj, prms)
  makeZip(out)

  out['root'] = ROOT

  outPut(json.dumps(out))