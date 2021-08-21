<?php
//AUTH
if (!$auth->isLoggedIn() ){
    if($test)
        echo "User must be logged in".EOL;
    exit(1);
}

if(empty($inputs))
    $inputs = [];
$inputs['id'] = (int)$auth->getDetail('ID');