<?php

//IDs
if($inputs['ids'] === null)
    $inputs['ids'] = [];
else{
    $inputs['ids'] = json_decode($inputs['ids'],true);
    foreach($inputs['ids'] as $id){
        if(!filter_var($id,FILTER_VALIDATE_INT)){
            if($test)
                echo 'each ID must be an integer!'.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
}

