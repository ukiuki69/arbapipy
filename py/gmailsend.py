import smtplib
from email.mime.text import MIMEText
from email.utils import formatdate
import ssl
import cgi

FROM_ADDRESS = 'support@rbatos.com'
MY_PASSWORD = 'UNka7r43zyPa'
TO_ADDRESS = 'y.yoshimura@purestep.co.jp'
SMTP_SERVER = 'sv10767.xserver.jp'
BCC = ''
SUBJECT = 'xserver smtpメール送信'
BODY = 'pythonでメール送信'
CGI_URL = 'http://153.127.61.191/py/'

def create_message(from_addr, to_addr, bcc_addrs, subject, body):
  msg = MIMEText(body)
  msg['Subject'] = subject
  msg['From'] = from_addr
  msg['To'] = to_addr
  msg['Bcc'] = bcc_addrs
  msg['Date'] = formatdate()
  return msg

def send(from_addr, to_addrs, msg):
  #context = ssl.create_default_context()
  smtpobj = smtplib.SMTP_SSL(SMTP_SERVER, 465, timeout=10)
  smtpobj.login(FROM_ADDRESS, MY_PASSWORD)
  smtpobj.sendmail(from_addr, to_addrs, msg.as_string())
  smtpobj.close()

if __name__ == '__main__':
  to_addr = TO_ADDRESS
  subject = SUBJECT
  body = BODY

  msg = create_message(FROM_ADDRESS, to_addr, BCC, subject, body)
  send(FROM_ADDRESS, to_addr, msg)