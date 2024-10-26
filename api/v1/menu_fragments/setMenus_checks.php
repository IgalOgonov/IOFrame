<?php
require_once $settings->getSetting('absPathToRoot').'vendor/autoload.php';

$setParams = [
    'test'=>$test,
    'override'=>(!($inputs['override'] !== null) || (bool)$inputs['override']),
    'update'=>($inputs['update'] !== null && (bool)$inputs['update'])
];

$config = HTMLPurifier_Config::createDefault();
$config->set('HTML.AllowedElements', []);
$purifier = new HTMLPurifier($config);

if ($inputs['inputs'] !== null) {

    if(!\IOFrame\Util\PureUtilFunctions::is_json($inputs['inputs'])){
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
        $tempInput = [];

        if (isset($inputArray['menuId'])) {
            $tempInput[$setColumnMap['menuId']] = $inputArray['menuId'];
            if(gettype($tempInput[$setColumnMap['menuId']]) !== 'string' || !preg_match('/'.MENU_IDENTIFIER_REGEX.'/',$tempInput[$setColumnMap['menuId']])){
                if($test)
                    echo 'menuId must match '.MENU_IDENTIFIER_REGEX.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
        }
        else{
            if ($test)
                echo 'menuId must be set for each input!' . EOL;
            exit(INPUT_VALIDATION_FAILURE);
        }

        if (isset($inputArray['title'])) {
            $tempInput[$setColumnMap['title']] = $purifier->purify($inputArray['title']);
            if(gettype($tempInput[$setColumnMap['title']]) !== 'string' || strlen($tempInput[$setColumnMap['title']]) === 0 || strlen($tempInput[$setColumnMap['title']]) > MENU_TITLE_MAX_LENGTH){
                if($test)
                    echo 'title must not be empty, or longer than '.MENU_TITLE_MAX_LENGTH.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
        }

        if (isset($inputArray['meta'])) {

            $tempInput[$setColumnMap['meta']] = $purifier->purify($inputArray['meta']);
            if(!\IOFrame\Util\PureUtilFunctions::is_json($tempInput[$setColumnMap['meta']])){
                if($test)
                    echo 'meta must be a valid json string!'.EOL;
                exit(INPUT_VALIDATION_FAILURE);
            }
        }

        $inputs['inputs'][$index] = $tempInput;

    }

}
else{
    if ($test)
        echo 'Inputs must be set!' . EOL;
    exit(INPUT_VALIDATION_FAILURE);
}