<?php
  // このファイルはこのままでは動かない。ちゃんと動作するソースはエックスサーバー上にあり。
  $dbHost = 'mysql10077.xserver.jp';
  $dbUser = 'albatross56_ysmr';
  $dbPass = 'kteMpg5D';
  $dbName = 'albatross56_sv1';
  $fileName = date('ymd').'_'.date('His').'.txt';
  $command = "mysqldump --default-character-set=binary ".$dbName." --host=".$dbHost." --user=".$dbUser." --password=".$dbPass." > ".$fileName;
  system($command);

?>