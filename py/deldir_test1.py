import os
import shutil
import time

DOCPATH = '/var/www/html/docs'
DELETE_CRITERIA = 60 * 60 * 5 # 削除基準 秒単位指定

# ドキュメント用ディレクトリから定期的にファイルを削除するための
# テスト

if __name__ == "__main__":
  now = time.time()
  files = os.listdir(DOCPATH)
  dirLst = [f for f in files if os.path.isdir(os.path.join(DOCPATH, f))]
  
  i = 1
  for f in dirLst:
    estTime = now - os.stat(DOCPATH + '/' + f).st_atime
    if (estTime > DELETE_CRITERIA):
      print(i, f, estTime)
      i += 1
      shutil.rmtree(DOCPATH + '/' + f)

