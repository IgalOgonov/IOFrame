<?php
require_once $settings->getSetting('absPathToRoot').'vendor/autoload.php';

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

$config = HTMLPurifier_Config::createDefault();
$config->set('HTML.AllowedElements', []);
$purifier = new HTMLPurifier($config);

if ($inputs['inputs'] !== null) {

    if(!\IOFrame\Util\is_json($inputs['inputs'])){
        if($test)
            echo 'inputs must be a valid json!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }

    $inputs['inputs'] = json_decode($inputs['inputs'],true);

    if(count($inputs['inputs']) === 0){
        if($test)
            echo 'inputs cannot be empty!!'.EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }

    foreach($inputs['inputs'] as $index => $inputArray){

        if (isset($inputArray['identifier'])) {
            if(!preg_match('/'.MENU_ITEM_IDENTIFIER_REGEX.'/',$inputArray['identifier'])){
                if($test)
                    echo 'identifier must match '.MENU_ITEM_IDENTIFIER_REGEX.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
        }

        if (isset($inputArray['address']) && gettype($inputArray['address']) === 'array') {
            foreach($inputArray['address'] as $identifier){
                if(!preg_match('/'.MENU_ITEM_IDENTIFIER_REGEX.'/',$identifier)){
                    if($test)
                        echo 'address must be an array (can be empty) of identifiers matching '.MENU_ITEM_IDENTIFIER_REGEX.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
            }
        }
        else{
            if ($test)
                echo 'address must be  an array, and set for each input!' . EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if (isset($inputArray['title']) && $inputArray['title'] !== null) {
            $inputArray['title'] = $purifier->purify($inputArray['title']);
            if(gettype($inputArray['title']) !== 'string' || strlen($inputArray['title']) === 0 || strlen($inputArray['title']) > MENU_ITEM_TITLE_MAX_LENGTH){
                if($test)
                    echo 'title must not be empty, or longer than '.MENU_ITEM_TITLE_MAX_LENGTH.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
            $inputs['inputs'][$index]['title'] = $inputArray['title'];
        }
        
        if (isset($inputArray['order']) && $inputArray['order'] !== null) {
            $order = explode(',',$inputArray['order']);
            foreach($order as $identifier)
                if(!preg_match('/'.MENU_ITEM_IDENTIFIER_REGEX.'/',$identifier)){
                    if($test)
                        echo 'Order needs to be a comma separated list of identifiers, which match '.MENU_ITEM_IDENTIFIER_REGEX.EOL;
                    exit(INPUT_VALIDATION_FAILURE);
                }
            $inputs['inputs'][$index]['order'] = $inputArray['order'];
        }

        if (isset($inputArray['delete']))
            $inputs['inputs'][$index]['delete'] = (bool)$inputArray['delete'];

    }

}
else{
    if ($test)
        echo 'Inputs must be set!' . EOL;
    exit(INPUT_VALIDATION_FAILURE);
}