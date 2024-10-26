<?php

require_once __DIR__.'/../../IOFrame/Handlers/RouteHandler.php';
$RouteHandler = new \IOFrame\Handlers\RouteHandler($settings,$defaultSettingsParams);

echo 'Adding routes:'.EOL;
var_dump(
    $RouteHandler->setRoutes(
        [
            'test1'=>['GET|POST','api/[*:trailing]','api',null,false],
            'test2'=>['GET|POST','[*:trailing]','front',null,false],
            'test3'=>['GET|POST','*','404']
        ],
        [
            'test'=>true,
            'verbose'=>true
        ]
    )
);
echo EOL;

echo 'Adding routes 0 to 3:'.EOL;
var_dump(
    $RouteHandler->setRoutes(
        [
            'test1'=>['GET|POST','*','test0.1',null,false],
            'test2'=>['GET|POST','*','test0.1',null,false],
            'test3'=>['GET|POST','/','test0.2',null,false]
        ],
        [
            'test'=>true,
            'verbose'=>true
        ]
    )
);
echo EOL;

echo 'Swapping routes 0 and 1 in order:'.EOL;
var_dump(
    $RouteHandler->swapOrder(0,1,['verbose'=>true,'test'=>true])
);
echo EOL;

echo 'Deleting routes 0 to 3'.EOL;
var_dump(
    $RouteHandler->deleteRoutes(['test-1','test-2'],['test'=>true,'verbose'=>true ])
);
echo EOL;

echo 'Getting active routes:'.EOL;
var_dump(
    $RouteHandler->getActiveRoutes(['test'=>true,'verbose'=>true ])
);
echo EOL;

echo 'Setting matches for front, api, 404:'.EOL;
var_dump(
    $RouteHandler->setMatches(
        [
            'front'=>['front/ioframe/pages/[trailing]', 'php,html,htm'],
            'api'=>['api/[trailing]API','php',true],
            '404'=>['404','html',false]
        ],
        [
            'test'=>true,
            'verbose'=>true
        ]
    )
);
echo EOL;

echo 'Setting matches for front:'.EOL;
var_dump(
    $RouteHandler->setMatches(
        [
            'front'=>[
                [
                    'front/[trailing]',
                    [
                        'include'=> 'front/docs/[trailing]',
                        'exclude'=> ['test\..+$']
                    ]
                ],
                'php,html,htm'
            ]
        ],
        [
            'test'=>true,
            'verbose'=>true
        ]
    )
);
echo EOL;

echo 'Getting all matches:'.EOL;
var_dump($RouteHandler->getMatches([], ['test'=>true,'verbose'=>true]));
echo EOL;

echo 'Getting matches front, api, 404, test:'.EOL;
var_dump($RouteHandler->getMatches(['front', 'api', '404','test'], ['test'=>true,'verbose'=>true]));
echo EOL;

echo 'Deleting matches test, 404, api:'.EOL;
$RouteHandler->deleteMatches(['test', '404', 'api'], ['test'=>true,'verbose'=>true]);