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
import os

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
  sys.stdin = io.TextIOWrapper(sys.stdin.buffer, encoding='utf-8')
  content_length = int(os.environ["CONTENT_LENGTH"])
  post_data = sys.stdin.read(content_length)

  arg = cgi.parse_qs(post_data)
  prms = {
    'pname': arg['pname'][0],
    'hname': arg['hname'][0],
    'bname': arg['bname'][0],
    'pmail': arg['pmail'][0],
    'item': arg['item'][0],
    'name': arg['name'][0],
    'token': arg['token'][0],
    'title': arg.get('title', [None])[0], # Add this line to handle the optional 'title' parameter
  }
  return(prms)

def send(from_addr, to_addrs, msg):
    try:
        context = ssl.create_default_context()
        with smtplib.SMTP(SMTP_SERVER, SMTP_PORT, timeout=10) as smtpobj:
            smtpobj.set_debuglevel(1)  # Debug information output
            smtpobj.starttls(context=context)  # Add this line
            smtpobj.login(FROM_ADDRESS, PASSWD)
            smtpobj.sendmail(from_addr, to_addrs, msg.as_string())
        return None
    except Exception as e:
        return str(e)

if __name__ == '__main__':
    prms = getParams()

    to_addr = prms['pmail'].replace('%40', '@')
    bodyCommonPart = \
        '事業所からのアクセスキーの設定、ログインがされていないと情報をご確認いただくことは出来ません。\n' \
        '設定が済んでいない方は連絡帳等アクセスキーのご案内のメールをご参照の上設定をお願い致します。\n\n' \
        f'法人名:{prms["hname"]}\n事業所名:{prms["bname"]}\nご利用者さま:{prms["name"]}さま\n\n' \
        f'更新済みの項目：{prms["item"]}\n\n' \
        f'{URLBASE}?token={prms["token"]}\n' \
        'こちらのメールは送信専用となっています。\n' \
        'ご返信いただいても対応できかねますのでご了承下さい。\n\n' \
        'こちらのメールに心当たりのない場合は事業所にお問い合わせ下さい。\n\n' \
        '---\n' \
        'アルバトロスサポートチーム\n' \
        'support@rbatosmail.com\n'

    if prms["title"]:  # Check if the 'title' parameter exists
        subject = prms["title"]
    else:
        subject = '連絡帳等更新のご案内'

    body = f'{prms["pname"]} 様\n' \
           'お世話になってます。こちらは' \
           'アルバトロス for ファミリーからのシステムメールです。\n\n' \
           '事業所により情報の更新がありました。\n\n' \
           'ご確認をお願いいたします。\n\n' + bodyCommonPart

    msg = create_message(FROM_ADDRESS, to_addr, BCC, subject, body)
    error = send(FROM_ADDRESS, to_addr, msg)
    rtn = {}

    rtn['result'] = error is None
    rtn['params'] = prms  # Add this line to include the parameters in the response

    if error:
        rtn['error'] = error

    outPut(json.dumps(rtn))
