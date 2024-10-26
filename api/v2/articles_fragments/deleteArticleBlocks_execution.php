<?php
$ArticleHandler = new \IOFrame\Handlers\ArticleHandler($settings,$defaultSettingsParams);

$result = [
    'block'=>-1,
    'order'=>-1
];

if(!empty($inputs['permanent'])){
    $deletionTargets = [];
    foreach($inputs['targets'] as $ID){
        $deletionTargets[] = [
            'Article_ID' => $inputs['id'],
            'Block_ID' => $ID,
        ];
    }
    $result['block'] = $ArticleHandler->deleteItems($deletionTargets, 'general-block', $deletionParams);
}

$continue = true;

if(
    ($inputs['permanent'] && $result['block'] !== 0)
)
    $continue = false;

if($continue){
    $result['order'] =
        $inputs['permanent'] ?
            $ArticleHandler->removeOrphanBlocksFromArticle(
                $inputs['id'],
                ['test'=>$test]
            )
                :
            $ArticleHandler->removeBlocksFromArticle(
                $inputs['id'],
                $inputs['targets'],
                ['test'=>$test]
            );
}

$result = ['response'=>$result];
