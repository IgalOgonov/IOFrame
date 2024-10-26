<?php

$result = $UsersHandler->mail2FASend($_SESSION['Extra_2FA_Mail'],[
    'test'=>$test,
    'async'=>false,
    'language'=>$inputs['language']
]);