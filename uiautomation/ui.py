import pyautogui
import re
import os
import subprocess
import sys
import time
import datetime
import pprint
import win32api
import win32gui
import win32con
import requests
import csv
from dateutil.relativedelta import relativedelta
import pdb
REV = "23"
# 取り込み送信ソフトのアップデートに対応
# 事業所指定方法変更中

# 伝送処理は原則月が変わってから前月の処理を行う 
# 当月の-5日(25日とか)から処理を行えるようにするときは-5、
# 次月の5日から処理できるようにするときは5を設定。
DATESHIFT = -60

ENDPOINT = 'http://albatross56.xsrv.jp/hd/api/api.php'
OUTPUT = 'c:\\torikomi\\csv\\'
JINO_DEFINE = OUTPUT + 'jinoList.csv' ## 事業所番号定義ファイル

SLEEP_SEC = 10  # loopのwait
MAX_LOOP = 100  # 最大ループ 終わったら終了
SLEEP_MULTIPLE = 1  # 遅いパソコンではwaitの秒数を増やす
TEST_OPERATION = True # テスト運用にするかどうか
DOWNLOAD_SLEEP = 4 # msec単位 ダウンロード一秒ごとにウエイトを入れる

CMD = "C:/torikomi/自立支援/取込送信V2/BIN/TS.exe"

def tab(n):
  for i in range(n):
    pyautogui.typewrite('\t')

def msleep(n):
  time.sleep(n / 1000)

# 指定のキーをn回連打。msのスリープを入れる
def pressRepeat(key, n, sleep=200):
  for i in range(n):
    pyautogui.press(key)
    msleep(sleep)


def oneOfficeSendDt(inputDt, jiNdx):
  # pprint.pprint(inputDt)
  time.sleep(1 * SLEEP_MULTIPLE)
  # 事業所ナンバーとパスワードを打って事業所ログイン
  # pyautogui.typewrite(inputDt['jino'])
  # 事業所の並び順に従い下矢印を押下
  pressRepeat('down', jiNdx + 1)
  tab(1)
  pyautogui.typewrite(inputDt['pswd'])
  tab(1)
  pyautogui.press('enter')

  # 事業所ダイアログ 送信モードに変更
  time.sleep(4 * SLEEP_MULTIPLE)
  tab(3)
  pyautogui.press('enter')
  # 日付
  pyautogui.typewrite(inputDt['jdate'].split('-')[0]);tab(1)
  pyautogui.typewrite(inputDt['jdate'].split('-')[1]);tab(1)
  pyautogui.typewrite(inputDt['jdate'].split('-')[2]);tab(1)
  # チェックボックス->ファイル名
  pyautogui.press('space')
  tab(1)
  pyautogui.typewrite(inputDt['fnameB'])
  tab(2)
  pyautogui.press('space')
  tab(1)
  pyautogui.typewrite(inputDt['fnameL'])
  tab(2)
  pyautogui.press('space')
  tab(1)
  pyautogui.typewrite(inputDt['fnameU'])
  tab(2)

  if TEST_OPERATION:
    pyautogui.press('down')
  
  tab(9)
  pyautogui.press('enter')
  time.sleep(2 * SLEEP_MULTIPLE)

# 未送信のリストを取得
def getUnSentList(thismonthStr, i):
  prms = {
    'a': 'listSent',
    'unsent': '',
    'date': thismonthStr,
  }
  res = requests.post(ENDPOINT, prms)
  print(i * SLEEP_SEC, 'secs waited. ***got ' , len(res.json()['dt']) ,' untransmitted datas.')
  return (res.json()['dt'])

# 未送信リストにパスワードを取得して付与
def getPas(item):
  hid = item['hid']
  bid = item['bid']
  prms = {
    'hid': hid,
    'bid': bid,
    'a': 'getTransferPass',
  }
  res = requests.post(ENDPOINT, prms)
  print('getPas', prms)
  pprint.pprint(res.json())
  if (len(res.json()['dt'])):
    rt = res.json()['dt'][0]
    item['pswd'] = rt['passwd']
    print('got encrypted passwords')
    return item
  else:
    print('hid:', item['hid'], 'bid:', item['bid'], 'の送信パスワードが見つかりません。')
    sys.exit()


# 未送信アイテムを取得してファイル作成。
# ファイル名や事業所コードなどの情報を付加して
# 返す。
def putFiles(item, thismonthStr):
  def downloadSleep(n):
    msleep(n * DOWNLOAD_SLEEP)
  def findKey(key, dic):
    if (key in dic):
      return(dic[key])
    else:
      return(False)
  
  hid = item['hid']
  bid = item['bid']
  prms = {
    'hid': hid,
    'bid': bid,
    'date':thismonthStr,
    'a': 'fetchTransferData',
  }
  res = requests.post(ENDPOINT, prms)
  rt = res.json()['dt'][0]['dt']
  item['jino'] = rt['jino']
  # billingDt = rt['billing']
  billingDt = findKey('billing', rt)
  useResult = findKey('useResult', rt)
  upperLimit = findKey('upperLimit', rt)
  if (billingDt == False or useResult == False or upperLimit == False):
    print(item['jino'] + ' has no data. skipped.')
    return False
  # フォルダの作成
  os.makedirs(OUTPUT + item['jino'], exist_ok=True)
  thisMonthShort = thismonthStr.replace('-', '')[:6]
  # ファイル名の準備と書き込み
  # 請求ファイル
  item['fnameB'] = OUTPUT + item['jino'] + '\\' + thisMonthShort + 'B.csv'
  f = open(item['fnameB'], 'w')
  writer = csv.writer(f, quotechar='"',quoting=csv.QUOTE_ALL, lineterminator='\n')
  writer.writerows(billingDt)
  f.close()
  downloadSleep(len(billingDt))
  print('=>billingdata', item['fnameB'])
  # 利用実績
  item['fnameU'] = OUTPUT + item['jino'] + '\\' + thisMonthShort + 'U.csv'
  f = open(item['fnameU'], 'w')
  writer = csv.writer(f, quotechar='"',quoting=csv.QUOTE_ALL, lineterminator='\n')
  writer.writerows(useResult)
  downloadSleep(len(useResult))
  f.close()
  print('=>use result data', item['fnameU'])
  # 上限管理
  item['fnameL'] = OUTPUT + item['jino'] + '\\' + thisMonthShort + 'L.csv'
  f = open(item['fnameL'], 'w')
  writer = csv.writer(f, quotechar='"',quoting=csv.QUOTE_ALL, lineterminator='\n')
  writer.writerows(upperLimit)
  f.close()
  downloadSleep(len(upperLimit))
  print('=>upper limit data', item['fnameU'])
  # 和暦を取得
  td = datetime.date.today()
  item['jdate'] = str(td.year-2018)+'-'+str(td.month)+'-'+str(td.day)

def sentFlugUpdate(lst, thismonthStr):
  prms = {
    'hid':lst['hid'],
    'bid':lst['bid'],
    'date' : thismonthStr,
    'a': 'putSentToTransfer',
  }
  rt = requests.post(ENDPOINT, prms)
  print('Set sent flag. rows', rt.json()['affected_rows'], '-',lst['jino'])

# 定義された事業所番号を配列にして返す
def readCsvOfJino():
  fl = open(JINO_DEFINE, 'r', encoding='utf_8', errors="", newline="")
  v = csv.reader(fl)
  rt = []
  for row in v:
    print(row[0], ' ', row[1])
    rt.append(row[0])
  rt.sort()
  print(rt)  
  return rt

def calledMainloop(thismonthStr, i):
  jinoDefined = readCsvOfJino()

  # 未送信リストの取得
  unsentList = getUnSentList(thismonthStr, i)
  # パスワードの取得
  for v in unsentList:
    getPas(v)
  # pprint.pprint(unsentList)
  # ファイルのダウンロード
  for v in unsentList:
    putFiles(v, thismonthStr)
  # pprint.pprint(unsentList)
  # 取込アプリオープンの判断
  open = False
  if (len(unsentList) > 0):
    open = True
    subprocess.Popen(CMD, shell=True)
  
  pprint.pprint(unsentList)
  # 取込アプリを操作して送信の実施
  for v in unsentList:
    # 定義済み事業所番号リストを確認。なかったらスキップする
    if v['jino'] not in jinoDefined:
      print(v['jino'], ' not defined.')
      continue
    # 事業所が何番目に定義されているか
    jiNdx = jinoDefined.index(v['jino'])
    oneOfficeSendDt(v, jiNdx)
  # 送信済みフラグをセット
  # for v in unsentList:
  #   sentFlugUpdate(v, thismonthStr)
  
  # window閉じる
  if (open):
    tab(4)
    pyautogui.press('enter')

# start
print('-----------------------------------------')
print('Albatross Systems.')
print('Import Transmission Oeprator r.' + REV)
print('-----------------------------------------')

# ------------- main Process ----------------
thismonth = datetime.datetime.today()
thismonth = thismonth + datetime.timedelta(days=DATESHIFT)
thismonth = thismonth + relativedelta(months=-1)
# 月初を取得
thismonth = datetime.datetime(thismonth.year, thismonth.month, 1)
thismonthStr = thismonth.strftime('%Y-%m-%d')
print('this month = ', thismonth)

for i in range(MAX_LOOP):
  calledMainloop(thismonthStr, i)
  time.sleep(SLEEP_SEC)
