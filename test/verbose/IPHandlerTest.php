<?php


require_once __DIR__.'/../../IOFrame/Handlers/IPHandler.php';

if(!isset($IPHandler))
    $IPHandler = new \IOFrame\Handlers\IPHandler(
        $settings,
        array_merge($defaultSettingsParams, ['siteSettings'=>$siteSettings])
    );

echo '10.213.234.10 blacklisted? '.($IPHandler->checkIP(['ip'=>'10.213.234.10','checkRange'=>true,'blacklisted'=>true,'test'=>true])?'Yes':'No').EOL;
echo '10.213.234.10 whitelisted? '.($IPHandler->checkIP(['ip'=>'10.213.234.10','checkRange'=>true,'blacklisted'=>false,'test'=>true])?'Yes':'No').EOL;
echo '10.10.21.50 blacklisted? '.($IPHandler->checkIP(['ip'=>'10.10.21.50','checkRange'=>true,'blacklisted'=>true,'test'=>true])?'Yes':'No').EOL;
echo '10.10.21.50 whitelisted? '.($IPHandler->checkIP(['ip'=>'10.10.21.50','checkRange'=>true,'blacklisted'=>false,'test'=>true])?'Yes':'No').EOL;
echo '10.213.234.0 whitelisted? '.($IPHandler->checkIP(['ip'=>'10.213.234.0','checkRange'=>true,'blacklisted'=>false,'test'=>true])?'Yes':'No').EOL;
echo '10.0.0.0 whitelisted? '.($IPHandler->checkIP(['ip'=>'10.0.0.0','checkRange'=>true,'blacklisted'=>false,'test'=>true])?'Yes':'No').EOL;

echo EOL.'Getting all IPs without conditions'.EOL;
var_dump(
    $IPHandler->getIPs([],['test'=>true])
);

echo EOL.'Getting all IPs with some conditions'.EOL;
var_dump(
    $IPHandler->getIPs(
        [],
        [
            'reliable'=>true,
            'type'=>1,
            'ignoreExpired'=>false,
            'limit'=>5,
            'offset'=>1,
            'test'=>true
        ]
    )
);


echo EOL.'Getting some IPs without conditions'.EOL;
var_dump(
    $IPHandler->getIPs(
        [
            '10.213.234.0', '10.10.21.50'
        ],
        [
            'test'=>true
        ]
    )
);

echo EOL.'Getting some IPs with some conditions'.EOL;
var_dump(
    $IPHandler->getIPs(
        [
           '10.213.234.0', '10.10.21.50'
        ],
        [
            'reliable'=>true,
            'type'=>1,
            'ignoreExpired'=>false,
            'limit'=>5,
            'offset'=>1,
            'test'=>true
        ]
    )
);

echo EOL.'Getting all IP Ranges without conditions'.EOL;
var_dump(
    $IPHandler->getIPRanges(
        [],
        [
            'test'=>true
        ]
    )
);

echo EOL.'Getting all IP Ranges with some conditions'.EOL;
var_dump(
    $IPHandler->getIPRanges(
        [],
        [
            'test'=>true,
            'type'=>0,
            'ignoreExpired'=>false
        ]
    )
);

echo EOL.'Getting some IP Ranges without conditions'.EOL;
var_dump(
    $IPHandler->getIPRanges(
        [
            [
                'prefix'=>'10.10',
                'from'=>0,
                'to'=>21
            ],
            [
                'prefix'=>'',
                'from'=>20,
                'to'=>35
            ],
        ],
        [
            'test'=>true
        ]
    )
);

echo EOL.'Getting some IP Ranges with some conditions'.EOL;
var_dump(
    $IPHandler->getIPRanges(
        [
            [
                'prefix'=>'10.10',
                'from'=>0,
                'to'=>21
            ],
            [
                'prefix'=>'',
                'from'=>20,
                'to'=>35
            ],
        ],
        [
            'test'=>true,
            'type'=>0,
            'ignoreExpired'=>false
        ]
    )
);

echo EOL.'Adding IP 10.213.234.0 to whitelist:'.EOL;
echo $IPHandler->addIP('10.213.234.0',true,['ttl'=>600000,'reliable'=>false,'override'=>true,'test'=>true]).EOL;

echo EOL.'Reducing IP 10.213.234.0 TTL to 3000:'.EOL;
echo $IPHandler->updateIP('10.213.234.0',true,['ttl'=>3000,'reliable'=>true,'test'=>true]).EOL;

echo EOL.'Deleting IP 10.213.234.0:'.EOL;
echo $IPHandler->deleteIP('10.213.234.0',['test'=>true]).EOL;

echo EOL.'Adding IP range to whitelist - 10.10.0-21'.EOL;
echo $IPHandler->addIPRange('10.10',0,21,true,3000,['override'=>true,'test'=>true]).EOL;

echo EOL.'Updating range 10.10.0-21 to be blacklisted, from range 10 to 25'.EOL;
echo $IPHandler->updateIPRange('10.10',0,21,['type'=>false,'from'=>10,'to'=>25,'test'=>true]).EOL;

echo EOL.'Deleting range 10.10.0-21'.EOL;
echo $IPHandler->deleteIPRange('10.10',0,21,['test'=>true]).EOL;

echo EOL.'Deleting expired rules'.EOL;
echo $IPHandler->deleteExpired(['test'=>true,'range'=>true]).EOL;

