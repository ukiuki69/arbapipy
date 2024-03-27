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
import urllib


sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')


FROM_ADDRESS = 'support@rbatosmail.com'
PASSWD = 'fWCpW$g1Vwqi-w9'
SMTP_SERVER = 'rbatos.sakura.ne.jp'
SMTP_PORT = 587
DEFAULT_BCC = 'yukihiro.yoshimura@gmail.com,hk.purestep@gmail.com'
URLBASE = 'https://family.rbatos.com/'


def create_message(from_addr, to_addr, bcc_addrs, subject, body):
    msg = MIMEText(body)
    msg['Subject'] = subject
    msg['From'] = from_addr
    msg['To'] = to_addr

    # bcc_addrsが存在すればDEFAULT_BCCと結合してbcc_addrsにする
    if 'bcc' in prms and DEFAULT_BCC:
        bcc_addrs += ',' + DEFAULT_BCC if bcc_addrs else DEFAULT_BCC

    msg['Date'] = formatdate()
    return msg, bcc_addrs.split(',')

def getParams():
    post_data = sys.stdin.read()
    post_data = urllib.parse.unquote(post_data) # URLデコードする
    prms = cgi.parse_qs(post_data)
    # 配列から値に変換
    for key in prms:
        prms[key] = prms[key][0]
    return prms


def send(from_addr, to_addrs, bcc_addrs, msg):
    all_recipients = [to_addrs] + bcc_addrs
    smtpobj = smtplib.SMTP(SMTP_SERVER, SMTP_PORT, timeout=10)
    try:
        smtpobj.login(FROM_ADDRESS, PASSWD)
        smtpobj.sendmail(from_addr, all_recipients, msg.as_string())
        return True
    except:
        return False
    finally:
        smtpobj.close()

# contentに含まれる改行コードを正規化する関数を定義
def normalize_newline(text):
    return re.sub(r'(?:<br\s*/?>|<BR\s*/?>|\r\n|\r|\n)', '\n', text)


if __name__ == '__main__':
    prms = getParams()
    for key, value in prms.items():
        prms[key] = urllib.parse.unquote(value)

    to_addr = prms['pmail']
    content = prms.get('content', '')  # contentが存在しなければ空文字列を返す
    content = normalize_newline(content)

    # bccの値を取得する
    bcc_addrs = prms.get('bcc', '')

    # DEFAULT_BCCの値が存在する場合は、カンマで区切った形でbcc_addrsに結合します。
    # bcc_addrsが空である場合は、DEFAULT_BCCの値をそのまま代入します。
    if DEFAULT_BCC:
        bcc_addrs = bcc_addrs + ',' + DEFAULT_BCC if bcc_addrs else DEFAULT_BCC
    bodyCommonPart = \
        f'法人名:{prms["hname"]}\n事業所名:{prms["bname"]}\nご利用者さま:{prms["name"]}さま\n\n' \
        f'更新済みの項目：{prms["item"]}\n\n' \
        f'{URLBASE}?token={prms["token"]}\n' \

    subject = '連絡帳等更新のご案内'
    body = f'{prms["pname"]} 様\n' \
        'お世話になってます。こちらは' \
        'アルバトロス for ファミリーからのシステムメールです。\n\n' \
        '事業所より情報の更新がありました。\n\n' \
        'ご確認をお願いいたします。\n\n'

    if content:
        body += f'{bodyCommonPart}\n\n【内容】\n{content}' \
            '\n--内容は以上です--'
    else:
        body += bodyCommonPart
    sig = \
        '\n\nこちらのメールは送信専用となっています。\n' \
        'ご返信いただいても対応できかねますのでご了承下さい。\n\n' \
        'こちらのメールに心当たりのない場合は事業所にお問い合わせ下さい。\n\n' \
        '---\n' \
        'アルバトロスサポートチーム\n' \
        'support@rbatosmail.com\n'
    body += sig

    msg, bcc_addrs = create_message(FROM_ADDRESS, to_addr, bcc_addrs, subject, body)
    send_result = send(FROM_ADDRESS, to_addr, bcc_addrs, msg)
    
    prms_decoded = {}
    for key, value in prms.items():
        prms_decoded[key] = urllib.parse.unquote(value)

    # rtn = {'result': send_result, 'prms': prms_decoded}
    rtn = {'result': send_result, }
    print("Content-Type: application/json; charset=UTF-8\n\n")
    print(json.dumps(rtn))
