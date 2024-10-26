<?php
/*This example update will create a file, then always fail.*/
if(!file_exists($url.'test.txt')){
    if($verbose)
        echo 'Creating test.txt'.EOL;
    if(!$test)
        touch($url.'test.txt');
}
throw new \Exception("This exception is always thrown!");