<?php


if(!$inputs['remote'])
    $inputs['remote'] = false;
else
    $inputs['remote'] = true;

//Addresses
if($inputs['addresses'] === null || !\IOFrame\Util\PureUtilFunctions::is_json($inputs['addresses'])){
    if($test)
        echo 'Addresses need to be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

$inputs['addresses'] = json_decode($inputs['addresses'],true);

if(count($inputs['addresses'])<1){
    if($test)
        echo 'Addresses need to contain something!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

foreach($inputs['addresses'] as $index => $address){
    if(!$inputs['remote']){
        if(!\IOFrame\Util\ValidatorFunctions::validateRelativeFilePath($address)){
            if($test)
                echo 'Invalid address at index '.$index.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
    else{
        $valid = \IOFrame\Util\ValidatorFunctions::validateRelativeFilePath($address) || filter_var($address,FILTER_VALIDATE_URL);
        if(!$valid){
            if($test)
                echo 'Invalid address at index '.$index.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }

}

if($inputs['gallery'] === null){
    if($test)
        echo 'Gallery name must be set!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);

}

if(!preg_match('/'.GALLERY_REGEX.'/',$inputs['gallery'])){
    if($test)
        echo 'Gallery name invalid!'.EOL;
    exit(INPUT_VALIDATION_FAILURE);
}