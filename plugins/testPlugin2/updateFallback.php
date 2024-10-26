<?php
/*This example update will create a file, then always fail.*/
if(file_exists($url.'test.txt')){
    if($verbose)
        echo 'Deleting test.txt'.EOL;
    if(!$test)
        unlink($url.'test.txt');
}
throw new \Exception("This exception is always thrown, as well!");