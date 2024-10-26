<?php
$result = (
    $action === 'addIP' ?
        $IPHandler->addIP($inputs['ip'],(bool)$inputs['type'],['reliable'=>(bool)$inputs['reliable'],'ttl'=>(int)$inputs['ttl'],'test'=>$test])
        :
        $IPHandler->updateIP($inputs['ip'],(bool)$inputs['type'],['reliable'=>(bool)$inputs['reliable'],'ttl'=>$inputs['ttl'],'test'=>$test])
);