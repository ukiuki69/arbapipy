#!/usr/bin/env python3
import cgi
import smtplib
import json
from email.mime.multipart import MIMEMultipart
from email.mime.text import MIMEText
from email.utils import formatdate

# DEFAULT_BCC = 'yukihiro.yoshimura@gmail.com,hk.purestep@gmail.com'
DEFAULT_BCC = 'hk.purestep@gmail.com,reis.jpn@gmail.com'
FROM_ADDRESS = 'support@rbatosmail.com'
PASSWD = 'fWCpW$g1Vwqi-w9'
SMTP_SERVER = 'rbatos.sakura.ne.jp'
SMTP_PORT = 587

form = cgi.FieldStorage()

def main():
    try:
        to_address = form.getvalue("pmail")
        replyto_address = form.getvalue("replyto")  # 新しく追加した部分
        bcc_address = form.getvalue("bcc", "").split(',') + DEFAULT_BCC.split(',')
        subject = form.getvalue("title", "アルバトロス for familyからのお知らせ")
        content = form.getvalue("content")

        msg = MIMEMultipart()
        msg["From"] = FROM_ADDRESS
        msg["To"] = to_address
        if replyto_address:  # replytoが指定されている場合、reply-toを設定します
            msg["Reply-To"] = replyto_address
        msg["Bcc"] = ','.join(bcc_address)
        msg["Subject"] = subject
        msg["Date"] = formatdate(localtime=True)
        # 2023/08/18変更
        msg.attach(MIMEText(content, "html", _charset="utf-8"))
        # msg.attach(MIMEText(content, "html", "utf-8"))

        send_email(msg)

        result = {"result": True}
    except Exception as e:
        result = {"result": False}
    
    print("Content-Type: application/json")
    print("")
    print(json.dumps(result))

def send_email(msg):
    with smtplib.SMTP(SMTP_SERVER, SMTP_PORT) as server:
        server.ehlo()
        server.starttls()
        server.login(FROM_ADDRESS, PASSWD)
        server.send_message(msg)

if __name__ == "__main__":
    main()
