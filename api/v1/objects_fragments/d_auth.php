<?php

if($sesInfo=== null || !$auth->isAuthorized(0)){
    if($test)
        echo 'You must be an admin to delete objects!!';
    exit(INPUT_VALIDATION_FAILURE);
}
