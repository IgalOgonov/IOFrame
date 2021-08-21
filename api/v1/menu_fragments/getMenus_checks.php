<?php
$retrieveParams = [
    'test'=>$test
];

if ($inputs['limit'] !== null) {
    if (!filter_var($inputs['limit'], FILTER_VALIDATE_INT)) {
        if ($test)
            echo 'limit must be a valid integer!' . EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
    $retrieveParams['limit'] = $inputs['limit'];
} else
    $retrieveParams['limit'] = 50;

if ($inputs['offset'] !== null) {
    if (!filter_var($inputs['offset'], FILTER_VALIDATE_INT)) {
        if ($test)
            echo 'offset must be a valid integer!' . EOL;
        exit(INPUT_VALIDATION_FAILURE);
    }
    $retrieveParams['offset'] = $inputs['offset'];
}