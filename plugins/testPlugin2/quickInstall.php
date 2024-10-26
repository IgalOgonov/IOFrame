<?php

if(!$test){
    $_SESSION['testRandomNumber2'] = 0;
    if(preg_match('/\W| /',$options['testOption'])==0)
        $_SESSION['testSetting2'] = $options['testOption'];
    else
        echo $options['testOption'];

}
else{
    echo 'quickInstall activates here!'.EOL;
    echo 'Option '.$options['testOption'].' validity is '.(preg_match('/\W| /',$options['testOption'])==0).EOL;
}























