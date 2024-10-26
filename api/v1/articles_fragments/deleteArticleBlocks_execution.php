<?php
$ArticleHandler = new \IOFrame\Handlers\ArticleHandler($settings,$defaultSettingsParams);

$result = [
    'block'=>-1,
    'order'=>-1
];

if($inputs['permanentDeletion']){
    $deletionTargets = [];
    foreach($inputs['deletionTargets'] as $ID){
        $deletionTargets[] = [
            'Article_ID' => $inputs['articleId'],
            'Block_ID' => $ID,
        ];
    }
    $result['block'] = $ArticleHandler->deleteItems($deletionTargets, 'general-block', $deletionParams);
}

$continue = true;

if(
    ($inputs['permanentDeletion'] && $result['block'] !== 0)
)
    $continue = false;

if($continue){
    $result['order'] =
        $inputs['permanentDeletion'] ?
            $ArticleHandler->removeOrphanBlocksFromArticle(
                $inputs['articleId'],
                ['test'=>$test]
            )
                :
            $ArticleHandler->removeBlocksFromArticle(
                $inputs['articleId'],
                $inputs['deletionTargets'],
                ['test'=>$test]
            );
}

