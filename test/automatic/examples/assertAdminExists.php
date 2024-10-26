<?php

$_test = function($inputs,&$errors,$context,$params){

    $dp = $params['defaultParams'];

    $UsersHandler = new \IOFrame\Handlers\UsersHandler($dp['localSettings'],$dp);

    $adminUsers = $UsersHandler->getUsers(['test'=>$params['test'],'verbose'=>$params['verbose'],'rankAtMost'=>0]);
    if(is_array($adminUsers) && count($adminUsers)){

        if($params['verbose']){
            $adminUsers = $adminUsers[array_keys($adminUsers)[0]];
            echo 'Admin user : '.json_encode(['id'=>$adminUsers['ID'],'username'=>$adminUsers['Username'],'email'=>$adminUsers['Email']],JSON_PRETTY_PRINT).EOL;
        }
        return true;
    }
    else{
        $errors['admin-user-exists'] = false;
        return false;
    }
};