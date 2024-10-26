<?php
$ArticleHandler = new \IOFrame\Handlers\ArticleHandler($settings,$defaultSettingsParams);

$deleteItems = [];
foreach($inputs['ids'] as $index => $id){
    $deleteItems[] = ['Article_ID' => $id];
}

$result = !($inputs['permanent']??false) ?
    $ArticleHandler->hideArticles($inputs['ids'], ['test'=>$test])  : $ArticleHandler->deleteItems($deleteItems, 'articles', ['test'=>$test]) ;

$individualResults = [];

foreach($inputs['ids'] as $id){
    $individualResults[$id] = $result;
}

if(isset($articlesFailedAuth))
    foreach($articlesFailedAuth as $key=>$code)
        $individualResults[$key] = $code;

$result = ['response'=>$individualResults];