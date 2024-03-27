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
BCC = 'yukihiro.yoshimura@gmail.com'
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
    'item': arg['item'].value,
    'name': arg['name'].value,
    'token': arg['token'].value,
  }
  return(prms)

def send(from_addr, to_addrs, msg,):
  #context = ssl.create_default_context()
  smtpobj = smtplib.SMTP(SMTP_SERVER, SMTP_PORT, timeout=10)
  smtpobj.login(FROM_ADDRESS, PASSWD)
  smtpobj.sendmail(from_addr, to_addrs, msg.as_string())
  smtpobj.close()

if __name__ == '__main__':
  prms = getParams()

  to_addr = prms['pmail']
  bodyCommonPart = \
    f'法人名:{prms["hname"]}\n事業所名:{prms["bname"]}\nご利用者さま:{prms["name"]}さま\n\n' \
    f'更新済みの項目：{prms["item"]}\n\n' \
    f'{URLBASE}?token={prms["token"]}\n' \
    'こちらのメールは送信専用となっています。\n' \
    'ご返信いただいても対応できかねますのでご了承下さい。\n\n' \
    'こちらのメールに心当たりのない場合は事業所にお問い合わせ下さい。\n\n' \
    '---\n' \
    'アルバトロスサポートチーム\n' \
    'support@rbatosmail.com\n'

  subject = '連絡帳等更新のご案内'
  body = f'{prms["pname"]} 様\n' \
  'お世話になってます。こちらは' \
  'アルバトロス for ファミリーからのシステムメールです。\n\n' \
  '事業所により情報の更新がありました。\n\n' \
  'ご確認をお願いいたします。\n\n' + bodyCommonPart
  
  # エイリアス部分を除去したアドレスを生成
  # conv_to_addr = re.sub(r'\+[a-zA-Z0-9]+@', '@', to_addr)
  msg = create_message(FROM_ADDRESS, to_addr, BCC, subject, body)
  send(FROM_ADDRESS, to_addr, msg)
  rtn = {}
  rtn['result'] = True
  rtn['prms'] = prms
  outPut(json.dumps(rtn))
