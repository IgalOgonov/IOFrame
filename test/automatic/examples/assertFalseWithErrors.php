<?php
$_test = function($inputs,&$errors,$context,$params){
    $errors['error-bool'] = true;
    $errors['error-string-arr'] = ['string','one-more-string'];
    $errors['error-obj'] = [
        'sub-error-1'=>'some-error',
        'sub-error-2'=>'some-other-error',
    ];
    return false;
};