<?php
$ObjectAuthHandler = new \IOFrame\Handlers\ObjectAuthHandler($settings,$defaultSettingsParams);

$result = $ObjectAuthHandler->deleteItems($inputs['items'], $type, $retrieveParams);