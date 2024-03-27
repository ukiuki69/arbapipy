<?php
  ini_set( 'display_errors', 1 );
  ini_set( 'error_reporting', E_ALL );
  const br =  '<br>';	//cronで走らせるときは\n?
  require './cipher.php';
  
  $s1 = '{"D20210601":{"end":"17:00","start":"13:30","service":"放課後等デイサービス","transfer":["自宅","自宅"],"offSchool":0,"actualCost":{"おやつ":100}},"D20210602":{"end":"17:00","start":"13:30","service":"放課後等デイサービス","transfer":["学校","自宅"],"offSchool":0,"actualCost":{"おやつ":100}}}';

  // $s2 = '{"D20210601":{"end":"18:30","start":"13:40","service":"放課後等デイサービス","transfer":["自宅","自宅"],"offSchool":0,"actualCost":{"おやつ":100}},"D20210603":{"end":"17:00","start":"13:30","service":"放課後等デイサービス","transfer":["学校","自宅"],"offSchool":0,"actualCost":{"おやつ":100}}}';

  $s2 = "{\"a\":0}";
  print('hoge' . '<br>');
  
  $cipher = encodeCph('Lepus-0601');
  echo $cipher . '<br>';
  echo decodeCph($cipher) . '<br>';

  $o1 = json_decode($s1, true);
  $o2 = json_decode($s2, true);

  $o3 = array_merge($o1, $o2);
  echo br;
  var_dump($o1);
  echo br;
  var_dump($o2);
  echo br;
  var_dump($o3);
  
  $a = ['a'=> 0, 'b'=> 0];
  echo br;
  $b = isset($a['c']);
  var_dump($b);
  $str = "aaaa" . "ccc" . "bbbb";
  echo($str);
  $a = '';
  if (!$a){
    echo('gerogero<br>');
  }
  $a = [
    [0, 1, 2, 3],
    'a' => [
      'b' => 'bbb',
      'c' => 'ccc',
      'd' => [
        'e' => 'eee',
        'f' => 'fff',
      ]
    ]
  ];
  echo('dump a<br>');
  var_dump($a);
  echo('<br>dump a.a<br>');
  var_dump($a['a']);
  $d = 'd';
  echo('<br>dump a.a.d<br>');
  var_dump($a['a'][$d]);
  $a['a']['b'] = '123';
  echo('<br>dump a.a mod 123<br>');
  var_dump($a['a']);
  echo('<br>dump a.x append 456<br>');
  $a['a']['x'] = '456';
  var_dump($a['a']);
  echo('<br>dump a.y.z deep append 789<br>');
  $a['a']['y']['z'] = '789';
  var_dump($a['a']);
  echo('<br>dump undef a.zz.zz <br>');
  var_dump($a['a']['zz']['zz']); // error

  $a = [
    "今日の雛形から入力しました。<br>ワンピース単単からのメッセージです。<br>12月20日火曜日 12:13 書き込み<br>このメッセージはfap側でバイタル項目がタブしか表示されていなかった。"
  ];
  echo $a[0];
  $a = array(
    "a"=>"今日の雛形から入力しました。<br>ワンピース単単からのメッセージです。<br>12月20日火曜日 12:13 書き込み<br>このメッセージはfap側でバイタル項目がタブしか表示されていなかった。",
    "b"=>0,
  );
  echo $a["a"];
  
?>
