<?php
$RateLimiting = new \IOFrame\Handlers\Extenders\RateLimiting($settings,$defaultSettingsParams);

echo EOL.'Locking action 1 of user 1 for 2 seconds (NOT IN TEST MODE - but this will only create a mutex)'.EOL;
var_dump(
    $RateLimiting->checkAction(
        1,
        1,
        1,
        2,
        ['maxWait'=>0,'randomDelay'=>0,'verbose'=>true]
    )
);

echo EOL.'Checking action 1 of user 1 (should be locked)'.EOL;
var_dump(
    $RateLimiting->checkAction(
        1,
        1,
        1,
        2,
        ['maxWait'=>0,'randomDelay'=>0,'verbose'=>true,'test'=>true]
    )
);

echo EOL.'Check whether IP Forbidden Action exists, if not, commit it (NOT IN TEST MODE - but for 127.0.0.1)'.EOL;
$result = $RateLimiting->checkActionEventLimit(
    0,
    '127.0.0.1',
    2,
    ['checkExpiry'=>true,'verbose'=>true,'test'=>true]
);
var_dump($result);
if(!$result)
    $RateLimiting->commitEventIP(2,[
        'verbose'=>true,
        'test'=>false,
        'IP'=>'127.0.0.1',
        'fullIP'=>'127.0.0.1',
        'isTrueIP'=>true
    ]);

echo EOL.'Clear action 2 AND BLACKLIST for IP 127.0.0.1'.EOL;
var_dump(
    $RateLimiting->clearActionEventLimit(
        0,
        '127.0.0.1',
        2,
        ['removeBlacklisted'=>true,'verbose'=>true,'test'=>true]
    )
);

echo EOL.'Clear action 1 and sus/ban for user 1'.EOL;
var_dump(
    $RateLimiting->clearActionEventLimit(
        1,
        1,
        1,
        ['removeBanned'=>true,'removeSuspicious'=>true,'verbose'=>true,'test'=>true]
    )
);

