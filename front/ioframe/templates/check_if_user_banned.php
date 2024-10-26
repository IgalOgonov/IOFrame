<?php

if(!isset($SecurityHandler))
    $SecurityHandler = new \IOFrame\Handlers\SecurityHandler(
        $settings,
        $defaultSettingsParams
    );

$banned = $SecurityHandler->checkBanned();
if($banned && empty($isLoginPage)){
    echo '
    <script>
    location = document.ioframe.rootURI + \'cp/login\';
    </script>';
        die();
}