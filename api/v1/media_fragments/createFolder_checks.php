<?php


//Category
if($inputs['category'] !== null){
    if(!in_array($inputs['category'],['img','vid'])){
        if($test)
            echo 'Upload category must be either "img" or "vid"!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}
else
    $inputs['category'] = 'img';

//Gallery
if($inputs['relativeAddress'] !== null){
    if(!\IOFrame\Util\ValidatorFunctions::validateRelativeDirectoryPath($inputs['relativeAddress'])){
        if($test)
            echo 'Invalid address!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}
else{
    $inputs['relativeAddress'] = '';
}

//Name - folder name is similar to upload filename in terms of restrictions
if($inputs['name'] !== null){
    if(!preg_match('/'.UPLOAD_FILENAME_REGEX.'/',$inputs['name'])){
        if($test)
            echo 'Invalid folder name!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}
else
    $inputs['name'] = 'New Folder';