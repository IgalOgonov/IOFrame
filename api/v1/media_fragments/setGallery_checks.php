<?php
require_once $settings->getSetting('absPathToRoot').'vendor/autoload.php';
$config = HTMLPurifier_Config::createDefault();
$config->set('HTML.AllowedElements', []);
$purifier = new HTMLPurifier($config);

//Gallery
if($inputs['gallery'] !== null){
    if(!preg_match('/'.GALLERY_REGEX.'/',$inputs['gallery'])){
        if($test)
            echo 'Invalid gallery identifier!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}
else{
    if($test)
        echo 'Gallery name must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

$meta = [];
//Meta information
$nameArr = ['name'];
foreach($languages as $lang){
    $nameArr[] = $lang . '_name';
}
foreach($nameArr as $nameParam){

    if($inputs[$nameParam] !== null)
        $inputs[$nameParam] = $purifier->purify($inputs[$nameParam]);

    if($inputs[$nameParam] !== null){
        if(strlen($inputs[$nameParam])>GALLERY_NAME_MAX_LENGTH){
            if($test)
                echo 'Invalid gallery name!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        $meta[$nameParam] = $inputs[$nameParam];
    }
}

if($meta == [])
    $meta = null;
else
    $meta = json_encode($meta);









