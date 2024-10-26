<?php

//IDs
if($inputs['ids'] === null)
    $inputs['ids'] = [];
else{
    $inputs['ids'] = json_decode($inputs['ids'],true);
    foreach($inputs['ids'] as $id){
        if(!preg_match('/'.TEMPLATE_ID_REGEX.'/',$id)){
            if($test)
                echo 'each ID must be an valid!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
}

