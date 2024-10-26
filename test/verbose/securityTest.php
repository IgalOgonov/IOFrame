<?php
$SecurityHandler = new \IOFrame\Handlers\SecurityHandler($settings,$defaultSettingsParams);

echo EOL.'Commiting event 0 by this IP:'.EOL;
var_dump($SecurityHandler->commitEventIP(0,['test'=>true]));

echo EOL.'Getting all rulebook categories'.EOL;
var_dump($SecurityHandler->getRulebookCategories(['test'=>true]));

echo EOL.'Getting all rulebooks'.EOL;
var_dump($SecurityHandler->getRulebookRules(['test'=>true]));

echo EOL.'Getting all rulebooks of category 1'.EOL;
var_dump($SecurityHandler->getRulebookRules(['category'=>1,'test'=>true]));

echo EOL.'Getting all rulebooks of category 0, type 0'.EOL;
var_dump($SecurityHandler->getRulebookRules(['category'=>0,'type'=>0,'test'=>true]));

echo EOL.'Setting some sequences'.EOL;
var_dump($SecurityHandler->setRulebookRules(
    [
        [
            'category'=>0,
            'type'=>0,
            'sequence'=>0,
            'addTTL'=>0,
            'blacklistFor'=>0,
        ],
        [
            'category'=>0,
            'type'=>0,
            'sequence'=>1,
            'addTTL'=>0,
            'blacklistFor'=>0,
        ],
        [
            'category'=>1,
            'type'=>0,
            'sequence'=>5,
            'addTTL'=>0,
            'blacklistFor'=>0,
        ],
        [
            'category'=>1,
            'type'=>1000,
            'sequence'=>5,
            'addTTL'=>0,
            'blacklistFor'=>0,
        ],
        [
            'category'=>1000,
            'type'=>1000,
            'sequence'=>5,
            'addTTL'=>0,
            'blacklistFor'=>0,
        ],
    ],
    ['test'=>true])
);

echo EOL.'Setting some sequences, override false'.EOL;
var_dump($SecurityHandler->setRulebookRules(
    [
        [
            'category'=>0,
            'type'=>0,
            'sequence'=>0,
            'addTTL'=>0,
            'blacklistFor'=>0,
        ],
        [
            'category'=>0,
            'type'=>0,
            'sequence'=>1,
            'addTTL'=>0,
            'blacklistFor'=>0,
        ],
        [
            'category'=>1,
            'type'=>0,
            'sequence'=>5,
            'addTTL'=>0,
            'blacklistFor'=>0,
        ],
        [
            'category'=>1,
            'type'=>1000,
            'sequence'=>5,
            'addTTL'=>0,
            'blacklistFor'=>0,
        ],
        [
            'category'=>1000,
            'type'=>1000,
            'sequence'=>5,
            'addTTL'=>0,
            'blacklistFor'=>0,
        ],
    ],
    ['test'=>true,'override'=>false])
);

echo EOL.'Setting some sequences, update true'.EOL;
var_dump($SecurityHandler->setRulebookRules(
    [
        [
            'category'=>0,
            'type'=>1,
            'sequence'=>0,
            'addTTL'=>0,
            'blacklistFor'=>0,
        ],
        [
            'category'=>0,
            'type'=>1,
            'sequence'=>1,
            'addTTL'=>0,
            'blacklistFor'=>0,
        ],
        [
            'category'=>1,
            'type'=>1,
            'sequence'=>5,
            'addTTL'=>0,
            'blacklistFor'=>0,
        ],
        [
            'category'=>1,
            'type'=>1000,
            'sequence'=>5,
            'addTTL'=>0,
            'blacklistFor'=>0,
        ],
        [
            'category'=>1000,
            'type'=>1000,
            'sequence'=>5,
            'addTTL'=>0,
            'blacklistFor'=>0,
        ],
    ],
    ['test'=>true,'update'=>true])
);

echo EOL.'Deleting some event types and sequences'.EOL;
var_dump($SecurityHandler->deleteRulebookRules(
    [
        [
            'category'=>0,
            'type'=>0
        ],
        [
            'category'=>0,
            'type'=>0,
            'sequence'=>1
        ],
        [
            'category'=>1,
            'type'=>0,
            'sequence'=>5
        ],
        [
            'category'=>1000,
            'type'=>1000,
        ],
    ],
    ['test'=>true])
);

echo EOL.'Getting all events meta'.EOL;
var_dump($SecurityHandler->getEventsMeta(
    [],
    ['test'=>true])
);

echo EOL.'Getting some events meta'.EOL;
var_dump($SecurityHandler->getEventsMeta(
    [
        [
            'category'=>0,
            'type'=>0
        ],
        [
            'category'=>1,
            'type'=>0
        ],
        [
            'category'=>0
        ],
        [
            'category'=>1
        ],
        [
            'category'=>9999
        ],
    ],
    ['test'=>true])
);

echo EOL.'Setting some events meta'.EOL;
var_dump($SecurityHandler->setEventsMeta(
    [
        [
            'category'=>0,
            'type'=>0,
            'meta'=>json_encode([
                'name'=>'IP Incorrect Login Limit'
            ])
        ],
        [
            'category'=>1,
            'type'=>0,
            'meta'=>json_encode([
                'name'=>'User Incorrect Login Limit'
            ])
        ],
        [
            'category'=>0,
            'meta'=>json_encode([
                'name'=>'IP Related Events'
            ])
        ],
        [
            'category'=>1,
            'meta'=>json_encode([
                'name'=>'User Related Events'
            ])
        ],
    ],
    ['test'=>true])
);

echo EOL.'Deleting some events meta'.EOL;
var_dump($SecurityHandler->deleteEventsMeta(
    [
        [
            'category'=>0,
            'type'=>0
        ],
        [
            'category'=>1,
            'type'=>463
        ],
        [
            'category'=>0
        ]
    ],
    ['test'=>true])
);