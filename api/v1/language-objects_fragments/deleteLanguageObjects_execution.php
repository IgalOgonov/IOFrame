<?php

//The columns to get only differ between category and base tags, not amongst themselves
$result = $LanguageObjectHandler->deleteLanguageObjects(
    $inputs['identifiers'],
    ['test'=>$test]
);