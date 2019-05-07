<?php

require_once $rootFolder.'_siteHandlers/ext/parsedown/Parsedown.php';
$parser = new Parsedown();

if(!isset($executionParameters))
    $executionParameters = [];

if(!isset($executionParameters['extraColumns']))
    $executionParameters['extraColumns'] = [];

$executionParameters['extraColumns'] = array_merge(
    $executionParameters['extraColumns'],
    ['Trusted_Comment','Date_Comment_Created','Date_Comment_Updated']
);


