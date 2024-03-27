import imaplib
import email
from email.header import decode_header
import sys
import io

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

# IMAPサーバーの情報
imap_host = "rbatos.sakura.ne.jp"
username = "support@rbatosmail.com"
password = "fWCpW$g1Vwqi-w9"

# IMAPサーバーに接続
mail = imaplib.IMAP4_SSL(imap_host)
mail.login(username, password)

# 受信トレイを選択
mail.select("inbox")

# すべてのメールを検索
status, messages = mail.search(None, "ALL")

# メッセージIDのリストを取得
messages = messages[0].split(b' ')

for msg_id in messages[::-1]:
    # メッセージを取得
    _, msg_data = mail.fetch(msg_id, "(RFC822)")
    msg = email.message_from_bytes(msg_data[0][1])

    # 各パートを処理
    subject = ""
    from_ = ""
    body = ""
    html_body = ""
    for part in msg.walk():
        if part.get_content_type() == "text/plain":
            body = part.get_payload(decode=True).decode()
        elif part.get_content_type() == "text/html":
            html_body = part.get_payload(decode=True).decode()
        if part["Subject"] is not None:
            subject_data = decode_header(part["Subject"])
            subject = subject_data[0][0].decode(subject_data[0][1])
        if part["From"] is not None:
            from_data = decode_header(part["From"])
            from_ = from_data[0][0].decode(from_data[0][1])

    print("Subject:", subject)
    print("From:", from_)
    print("Body:", body)

# セッションを終了
mail.logout()
