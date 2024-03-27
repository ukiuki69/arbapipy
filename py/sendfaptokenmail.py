#!/usr/bin/python3.6
# -- coding: utf-8 --

import smtplib
from email.mime.text import MIMEText
from email.utils import formatdate
import ssl
import cgi
import io
import sys
import json
import re


sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

def outPut(dt):
  print("Content-Type: application/json; charset=UTF-8\n\n")
  # print("Content-Type: text/html; charset=UTF-8;\n\n")
  print(dt)


FROM_ADDRESS = 'support@rbatosmail.com'
PASSWD = 'fWCpW$g1Vwqi-w9'
SMTP_SERVER = 'rbatos.sakura.ne.jp'
SMTP_PORT = 587
# FROM_ADDRESS = 'support@rbatos.com'
# PASSWD = 'UNka7r43zyPa'
# SMTP_SERVER = 'sv10767.xserver.jp'
# SMTP_PORT = 465
BCC = 'yukihiro.yoshimura@gmail.com'
# TO_ADDRESS = 'y.yoshimura@purestep.co.jp'
# SUBJECT = 'xserver smtpメール送信'
# BODY = 'pythonでメール送信'
# URLBASE = 'http://albatross56.xsrv.jp/hd/#/restpassword/?key='
# URLBASE = 'http://localhost:3000/hd/#/restpassword/?key='
URLBASE = 'https://family.rbatos.com/'


def create_message(from_addr, to_addr, bcc_addrs, subject, body):
  msg = MIMEText(body)
  msg['Subject'] = subject
  msg['From'] = from_addr
  msg['To'] = to_addr
  msg['Bcc'] = bcc_addrs
  msg['Date'] = formatdate()
  return msg

def getParams():
  arg = cgi.FieldStorage()
  prms = {
    'pname': arg['pname'].value,
    'hname': arg['hname'].value,
    'bname': arg['bname'].value,
    'pmail': arg['pmail'].value,
    'faptoken': arg['faptoken'].value,
    'mode': arg['mode'].value,

    # 'pname': 'aaa',
    # 'hname': 'bbb',
    # 'bname': 'ccc',
    # 'pmail': 'yukihiro.yoshimura@gmail.com',
    # 'faptoken': 'wrrt776v',
    # 'mode': 'new',


  }
  return(prms)

def send(from_addr, to_addrs, msg):
  #context = ssl.create_default_context()
  smtpobj = smtplib.SMTP(SMTP_SERVER, SMTP_PORT, timeout=10)
  smtpobj.login(FROM_ADDRESS, PASSWD)
  smtpobj.sendmail(from_addr, to_addrs, msg.as_string())
  smtpobj.close()

if __name__ == '__main__':
  prms = getParams()

  firstUrl = f'{URLBASE}/login/?token={prms["faptoken"]}&mail={prms["pmail"]}'
  secondUrl = f'{URLBASE}?token={prms["faptoken"]}'
  if prms['mode'] == 'new':
    mode = '発行'
    passSet = 'パスワードを設定のうえ'
  elif prms['mode'] == 'update':
    mode = '更新'
    passSet = ''


  to_addr = prms['pmail']
  bodyCommonPart = \
    'こちらのサービスを初めてご利用になる方はこちらからパスワード設定してログインして下さい。\n' \
    f'{firstUrl}\n\n' \
    'すでにパスワード等設定済みの場合はこちらからお願い致します。\n' \
    f'{secondUrl}\n\n' \
    'こちらのアドレスは送信専用となっています。\n' \
    'ご返信いただいても対応できかねますのでご了承下さい。\n\n' \
    'こちらのメールに心当たりのない場合は事業所にお問い合わせ下さい。\n\n' \
    '---\n' \
    'アルバトロスサポートチーム\n' \
    'support@rbatosmail.com\n'

    # 'ブックマーク、またはホーム画面に登録するときはこちらからお願いします。\n' \
    # f'{URLBASE}\n\n' \


  subject = '連絡帳等のアクセスキー新規発行のご案内'
  body = f'{prms["pname"]} 様\n' \
  'お世話になってます。こちらは' \
  'アルバトロスからのシステムメールです。\n\n' \
  '連絡帳などご家族様を事業所と繋げるためのサービス'\
  'アルバトロス for ファミリーがご利用いただけます。\n\n' \
  f'法人名:{prms["hname"]}\n事業所名:{prms["bname"]}\n\n' \
  f'上記事業所よりアクセスキーが{mode}されましたので' \
  f'下記URLにアクセスして{passSet}、ログインしてご利用いただけますよう' \
  'お願いいたします。\n\n' + bodyCommonPart
  
  # エイリアス部分を除去したアドレスを生成
  # conv_to_addr = re.sub(r'\+[a-zA-Z0-9]+@', '@', to_addr)
  msg = create_message(FROM_ADDRESS, to_addr, BCC, subject, body)
  send(FROM_ADDRESS, to_addr, msg)
  rtn = {}
  rtn['result'] = True
  outPut(json.dumps(rtn))
