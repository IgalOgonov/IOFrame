<?php
if(isset($inputs['mail']) && $result === 0){
    $shouldCommitActions = true;
    require 'limit_success_check.php';
}