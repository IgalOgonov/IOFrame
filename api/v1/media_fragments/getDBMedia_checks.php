<?php


if($inputs['address'] !== null){
    if(!\IOFrame\Util\ValidatorFunctions::validateRelativeDirectoryPath($inputs['address'])){
        header("HTTP/1.1 400 Invalid address");
        die();
    }
}
else{
    header("HTTP/1.1 400 Invalid address");
    die();
}

if($inputs['resourceType'] !== null){
    if(!preg_match('/'.RESOURCE_TYPE_REGEX.'/',$inputs['resourceType'])){
        header("HTTP/1.1 400 Invalid resource type");
        die();
    }
}
else
    $inputs['resourceType'] = 'img';

if($inputs['lastChanged'] !== null){
    if(!filter_var($inputs['lastChanged'],FILTER_VALIDATE_INT)){
        $inputs['lastChanged'] = null;
    }
}