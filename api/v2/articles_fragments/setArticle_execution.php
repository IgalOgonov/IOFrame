<?php
$ArticleHandler = new \IOFrame\Handlers\ArticleHandler($settings,$defaultSettingsParams);

$result = $inputs['create'] ?
    $ArticleHandler->setItems([$cleanInputs], 'articles', $setParams) :
    $ArticleHandler->setItems([$cleanInputs], 'articles', $setParams)[$inputs['id']] ;

$result = [
    'response'=>$result
];