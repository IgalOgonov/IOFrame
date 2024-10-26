<?php

$result = $SecurityHandler->getRulebookRules(['type'=>$inputs['type'],'category'=>$inputs['category'],'test'=>$test]);

foreach($result as $mainIndex=> $res){
    foreach($res as $index=> $rules){
        $tempRes = [];
        if($index === '@')
            continue;
        $result[$mainIndex][$index] = [
            'blacklistFor' => $rules['Blacklist_For'],
            'addTTL' => $rules['Add_TTL']
        ];
    }
}