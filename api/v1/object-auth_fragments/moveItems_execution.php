<?php
$ObjectAuthHandler = new \IOFrame\Handlers\ObjectAuthHandler($settings,$defaultSettingsParams);

$result = $ObjectAuthHandler->moveItems($inputs['items'],$inputs['inputs'], $type, $retrieveParams);