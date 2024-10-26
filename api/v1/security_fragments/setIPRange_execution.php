<?php
$result = (
$action === 'addIPRange' ?
    $IPHandler->addIPRange(
        $inputs['prefix'],
        $inputs['from'],
        $inputs['to'],
        (bool)$inputs['type'],
        $inputs['ttl'],
        ['test'=>$test]
    )
    :
    $IPHandler->updateIPRange(
        $inputs['prefix'],
        $inputs['from'],
        $inputs['to'],
        [
            'from'=>$inputs['newFrom'],
            'to'=>$inputs['newTo'],
            'type'=>$inputs['type'],
            'ttl'=>$inputs['ttl'],
            'test'=>$test
        ]
    )
);