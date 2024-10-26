<?php

if($CLIManager->failedInitiation){
    $result['error'] = $initiationError;
    $result['initiation-status'] = $initiation;
    die(json_encode($result));
}