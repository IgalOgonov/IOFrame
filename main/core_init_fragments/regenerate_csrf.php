<?php

if(empty($SessionManager))
    require 'initiate_session.php';

if(!isset($_SESSION['CSRF_token'])){
    $SessionManager->reset_CSRF_token();
}