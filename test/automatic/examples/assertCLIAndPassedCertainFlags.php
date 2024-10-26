<?php
$_test = function($inputs,&$errors,$context,$params){
    $v = $context->variables;
    $res = [
        'testsIsJson'=>\IOFrame\Util\PureUtilFunctions::is_json($v['tests']??null),
        'inCli'=>php_sapi_name() == "cli"
    ];
    foreach ($res as $assertion => $result){
        if(!$result)
            $errors[$assertion] = false;
    }
    return $res;
};