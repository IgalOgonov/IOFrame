<?php



$result = $TagHandler->moveItems(
    [
        [
            'Tag_Type' => $inputs['type'],
            'Category_ID' => $inputs['category']??null,
            'Tag_Name' => $inputs['name']
        ]
    ],
    [
        'Tag_Name' => $inputs['newName']
    ],
    $inputs['type'],
    ['test'=>$test,'cacheFullResultsCustomSuffix'=>($categories ? $inputs['type'].'/'.$inputs['category'] : $inputs['type'])]
);