<?php
$retrieveParams = [
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
