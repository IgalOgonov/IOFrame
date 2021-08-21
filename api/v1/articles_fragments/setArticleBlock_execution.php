<?php
$ArticleHandler = new \IOFrame\Handlers\ArticleHandler($settings,$defaultSettingsParams);

$result = [
    'block'=>-1,
    'order'=>-1
];

$result['block'] = $ArticleHandler->setItems([$cleanInputs], $inputs['type'].'-block', $setParams);

if(!$inputs['create'])
    $result['block'] = $result['block'][$inputs['articleId'].'/'.$inputs['blockId']];

$continue = true;

if(
    (!$inputs['create'] || $result['block'] < 1)
)
    $continue = false;

if($continue){
    $result['order'] = $ArticleHandler->addBlocksToArticle(
        $inputs['articleId'],
        [ $inputs['orderIndex'] => ($inputs['create']? $result['block'] : $inputs['blockId']) ],
        ['test'=>$test]
    );
}

