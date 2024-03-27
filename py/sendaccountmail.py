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
BCC = ''
# TO_ADDRESS = 'y.yoshimura@purestep.co.jp'
# SUBJECT = 'xserver smtpメール送信'
# BODY = 'pythonでメール送信'
# URLBASE = 'http://albatross56.xsrv.jp/hd/#/restpassword/?key='
# URLBASE = 'http://localhost:3000/hd/#/restpassword/?key='
URLBASE = 'https://houday.rbatos.com/#/restpassword/?key='


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
    'fname': arg['fname'].value,
    'lname': arg['lname'].value,
    'hname': arg['hname'].value,
    'bname': arg['bname'].value,
    'mail': arg['mail'].value,
    'resetkey': arg['resetkey'].value,
    'mode': arg['mode'].value,

    # 'fname': 'aaa',
    # 'lname': 'bbb',
    # 'hname': 'ccc',
    # 'bname': 'ddd',
    # 'mail': 'reis.jpn+aaa@gmail.com',
    # 'resetkey': '1234',
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

  to_addr = prms['mail']

  bodyCommonPart = f'{URLBASE}{prms["resetkey"]}\n\n' \
    'アドレスの有効期限は発行から２４時間以内となっています。\n' \
    '早めに処理をしていただけますようお願いいたします。\n\n' \
    '本システムの推奨ブラウザはGoogle Chromeとなっています。\n' \
    'Internet Explolerでは動作しません。\n\n' \
    'こちらのアドレスは送信専用となっています。\n' \
    'ご返信いただいても対応できかねますのでご了承下さい。\n\n' \
    'こちらのメールに心当たりのない場合は事業所にお問い合わせ下さい。\n\n' \
    '---\n' \
    'アルバトロスサポートチーム\n' \
    'support@rbatosmail.com\n'


  if prms['mode'] == 'reset':
    subject = 'パスワードリセットのご案内'
    body = f'{prms["lname"]}  {prms["fname"]} 様\n' \
    'お世話になってます。こちらは' \
    'アルバトロスからのシステムメールです。\n\n' \
    f'法人名:{prms["hname"]}\n事業所名:{prms["bname"]}\n\n' \
    '事業所よりパスワードリセットのリクエストが発行されました。' \
    '下記アドレスよりパスワードの再登録を' \
    'お願いいたします。\n\n' + bodyCommonPart
  elif prms['mode'] == 'new':
    subject = 'アカウント登録のお願い'
    body = f'{prms["lname"]}  {prms["fname"]} 様\n' \
    'お世話になります。こちらは' \
    'アルバトロスからのシステムメールです。\n\n' \
    'お客様のメールアドレスにてアカウント新規登録の手続きが発行されました。\n\n' \
    f'法人名:{prms["hname"]}\n事業所名:{prms["bname"]}\n\n' \
    '下記アドレスにアクセスしてアカウント登録処理を完了していただけますよう' \
    'お願いいたします。\n\n' + bodyCommonPart
  elif prms['mode'] == 'notNew':
    subject = 'アカウント追加登録のお知らせ'
    body = f'{prms["lname"]}  {prms["fname"]} 様\n' \
    'お世話になってます。こちらは' \
    'アルバトロスからのシステムメールです。\n\n' \
    'お客様のメールアドレスにてアカウント追加登録の手続きが発行されました。\n\n' \
    f'法人名:{prms["hname"]}\n事業所名:{prms["bname"]}\n\n' \
    '新たに上記事業所にログインできるようになりました。' \
    '今のままでもご利用いただけますが、パスワードリセットを行う場合は下記アドレスまで' \
    'お願いいたします。\n\n' + bodyCommonPart
  
  # エイリアス部分を除去したアドレスを生成
  conv_to_addr = re.sub(r'\+[a-zA-Z0-9]+@', '@', to_addr)
  msg = create_message(FROM_ADDRESS, conv_to_addr, BCC, subject, body)
  send(FROM_ADDRESS, conv_to_addr, msg)
  rtn = {}
  rtn['result'] = True
  outPut(json.dumps(rtn))
