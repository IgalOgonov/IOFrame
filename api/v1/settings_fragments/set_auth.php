<?php
//Auth check TODO Add relevant actions, not just rank 0
//TODO REMEMBER DIFFERENT ACTIONS - DEPENDING ON REQUEST

if(!$auth->isAuthorized()){
    if($test)
        echo 'Authorization rank must be 0!';
    exit(AUTHENTICATION_FAILURE);
}

