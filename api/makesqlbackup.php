<?php
  // このファイルはこのままでは動かない。ちゃんと動作するソースはエックスサーバー上にあり。
  $dbHost = 'mysql10077.xserver.jp';
  $dbUser = 'albatross56_x84e';
  $dbPass = 'eGR2JJtj.i7EpSN';
  $dbName = 'albatross56_sv1';
  $fileName = date('ymd').'_'.date('His').'.txt';
  $command = "mysqldump --default-character-set=binary ".$dbName." --host=".$dbHost." --user=".$dbUser." --password=".$dbPass." > ".$fileName;
  system($command);

?>