<?php
$result = $LanguageObjectHandler->moveItems(
    [
        [
            'Object_Name' => $inputs['name']
        ]
    ],
    [
        'Object_Name' => $inputs['newName']
    ],
    'language-objects',
    ['test'=>$test]
);