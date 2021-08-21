<?php
$ObjectAuthHandler = new \IOFrame\Handlers\ObjectAuthHandler($settings,$defaultSettingsParams);

$result = $ObjectAuthHandler->setItems($inputs['inputs'], $type, $retrieveParams);