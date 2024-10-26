<?php

$temp = \IOFrame\Util\FileSystemFunctions::fetchAllFolderAddresses(__DIR__.'/../cli',['subFolders'=>true,'returnFolders'=>true,'include'=>['\.php$']]);

foreach ($temp as $url)
    echo $url.' is '.(is_dir($url)?'dir' : (is_file($url)? 'file' : 'invalid')).EOL;


echo 'now is '.time().'<br>';

$array1 = array('blue' => 1, 'red' => 2, 'green' => 3, 'purple' => 4);
$array2 = array('blue' => 1, 'red' => 2, 'green' => 3);

var_dump(array_diff_key($array1, $array2));

var_dump($RedisManager->keys('_settings_*'));

if(isset($_REQUEST['testJSON'])){
    $temp = '';
    $tempjson = json_decode($_REQUEST['testJSON'],true);
    var_dump($tempjson);
}
for($i = 0; $i<10; $i++){
    if(isset($_REQUEST["test".($i+1)])){
        echo 'Test '.($i+1).' value is '.$_REQUEST["test".($i+1)].EOL;
    }
}
echo '  <form method="post" action="">
        <input type="text" name="test1" placeholder="Test text">
        <input type="number" name="test2" placeholder="Test num">
        <input type="radio" name="test3" value="r1">R1
        <input type="radio" name="test3" value="r2" checked>R2
        <input type="checkbox" name="test5">C1
        <input type="checkbox" name="test6">C2
        <input type="email" name="test7" placeholder="Your mail">
        <input type="password" name="test8" placeholder="Password">
        <textarea name="test9" placeholder="Test text Area"></textarea>
         <select name = test10>
          <option value="s1">s1</option>
          <option value="s2">s2</option>
          <option value="s3">s3</option>
        </select>
        <input type="submit" value="Submit Test Query">
        </form>';

echo EOL.'Test 15 character GeraHash:'.EOL.IOFrame\Util\PureUtilFunctions::GeraHash(15).EOL;



