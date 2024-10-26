<?php
$setParams = [
    'test'=>$test
];

if ($inputs['identifier'] !== null) {

    if(!preg_match('/'.MENU_IDENTIFIER_REGEX.'/',$inputs['identifier'])){
        if($test)
            echo 'Menu identifier must match '.MENU_IDENTIFIER_REGEX.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }

}
else{
    if ($test)
        echo 'Menu identifier must be set!' . EOL;
    exit(INPUT_VALIDATION_FAILURE);
}


if ($inputs['blockIdentifier'] !== null) {
    if(!preg_match('/'.MENU_ITEM_IDENTIFIER_REGEX.'/',$inputs['blockIdentifier'])){
        if($test)
            echo 'identifier must match '.MENU_ITEM_IDENTIFIER_REGEX.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}
else{
    if ($test)
        echo 'identifier must be set for each input!' . EOL;
    exit(INPUT_VALIDATION_FAILURE);
}

if ($inputs['orderIndex'] !== null) {
    if($inputs['orderIndex'] !== 0 && !filter_var($inputs['orderIndex'],FILTER_VALIDATE_INT)){
        if($test)
            echo 'orderIndex must be a valid integer!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
    $setParams['orderIndex'] = $inputs['orderIndex'];
}

$addresses = ['sourceAddress','targetAddress'];

foreach($addresses as $addressParam){

    if(!\IOFrame\Util\PureUtilFunctions::is_json($inputs[$addressParam])){
        if($test)
            echo $addressParam.' must be a valid json!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }

    $inputs[$addressParam] = json_decode($inputs[$addressParam],true);

    if ($inputs[$addressParam] !== null && gettype($inputs[$addressParam]) === 'array') {
        foreach($inputs[$addressParam] as $identifier){
            if(!preg_match('/'.MENU_ITEM_IDENTIFIER_REGEX.'/',$identifier)){
                if($test)
                    echo $addressParam.' must be an array (can be empty) of identifiers matching '.MENU_ITEM_IDENTIFIER_REGEX.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
        }
    }
    else{
        if ($test)
            echo 'address must be  an array, and set for each input!' . EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
}