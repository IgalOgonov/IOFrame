<?php
if(!$inputs['identifiers'] || !\IOFrame\Util\is_json($inputs['identifiers'])){
    if($test)
        echo 'identifiers must be set, and be a valid JSON!';
    exit(INPUT_VALIDATION_FAILURE);
}
else{
    $inputs['identifiers'] = json_decode($inputs['identifiers'],true);
    foreach($inputs['identifiers'] as $identifier){
        if(!preg_match('/'.IDENTIFIER_REGEX.'/',$identifier)){
            if($test)
                echo 'Invalid identifier '.htmlspecialchars($identifier).', must match '.IDENTIFIER_REGEX.EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }
    }
}