<?php
$ArticleHandler = new \IOFrame\Handlers\ArticleHandler($settings,$defaultSettingsParams);

$deleteItems = [];
foreach($inputs['articles'] as $index => $id){
    $deleteItems[] = ['Article_ID' => $id];
}

$result = !$inputs['permanentDeletion'] ?
    $ArticleHandler->hideArticles($inputs['articles'], ['test'=>$test])  : $ArticleHandler->deleteItems($deleteItems, 'articles', ['test'=>$test]) ;

$individualResults = [];

foreach($inputs['articles'] as $id){
    $individualResults[$id] = $result;
}

if(isset($articlesFailedAuth))
    foreach($articlesFailedAuth as $key=>$code)
        $individualResults[$key] = $code;

$result = $individualResults;