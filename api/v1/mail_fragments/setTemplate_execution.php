<?php

$params = ['test'=>$test,'createNew'=>($action === 'createTemplate')];

$result = $MailHandler->setTemplate($inputs['id'],$inputs['title'],$inputs['content'],$params);