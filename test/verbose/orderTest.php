<?php


require_once __DIR__.'/../../IOFrame/Managers/OrderManager.php';
$testParams = $defaultSettingsParams;
$testParams['name'] = 'test';
$testParams['tableName'] = 'CORE_VALUES';
$testParams['columnNames'] = ['tableKey','tableValue'];
$testParams['localURL'] = $settings->getSetting('absPathToRoot').'localFiles/';
$testParams['separator'] = '@#$%$#@';

$testOrder = new \IOFrame\Managers\OrderManager(
    $settings,
    $testParams
);

/*
$testOrder->pushToOrder('SOME UNGODLY NAME! AGH',['test'=>false,'verbose'=>true,'createNew'=>true]);
$testOrder->pushToOrder('SOME UNGODLY NAME! AGH',['test'=>false,'verbose'=>true,'local'=>false]);

*/
echo 'Pushing test2 to order!'.EOL;
var_dump(
    $testOrder->pushToOrder('test2',['test'=>true,'verbose'=>true,'createNew'=>true])
);
echo EOL;
//$testOrder->pushToOrder('test2',['test'=>false,'verbose'=>true,'createNew'=>true,'local'=>false]);

echo 'Pushing SOME UNGODLY NAME! AGH to order!'.EOL;
var_dump(
    $testOrder->pushToOrder('SOME UNGODLY NAME! AGH',['test'=>true,'verbose'=>true,'createNew'=>true, 'unique'=>false])
);
echo EOL;
//$testOrder->pushToOrder('SOME UNGODLY NAME! AGH',['test'=>true,'verbose'=>true,'createNew'=>true,'local'=>false]);

echo 'Getting local order'.EOL;
var_dump($testOrder->getOrder(['local'=>false,'test'=>true]));
echo EOL;

echo 'Getting DB order'.EOL;
var_dump($testOrder->getOrder(['local'=>true,'test'=>true]));
echo EOL;

echo 'Removing SOME UNGODLY NAME! AGH from order, by name'.EOL;
var_dump(
    $testOrder->removeFromOrder(
        'SOME UNGODLY NAME! AGH',
        'name',
        ['test'=>true]
    )
);

echo 'Removing item on index 1 in order'.EOL;
var_dump(
    $testOrder->removeFromOrder(
        1,
        'index',
        ['test'=>true]
    )
);
echo EOL;

echo 'Moving order item 0 to 1'.EOL;
var_dump(
    $testOrder->moveOrder(
        0,
        1,
        ['test'=>true]
    )
);
echo EOL;

echo 'Moving order item 1 to 0, locally'.EOL;
var_dump(
    $testOrder->moveOrder(
        1,
        0,
        ['backUp'=>true,'local'=>true,'test'=>true]
    )
);
echo EOL;

echo 'Moving order item 1 to 0, in the DB'.EOL;
var_dump(
    $testOrder->moveOrder(
        1,
        0,
        ['backUp'=>true,'local'=>false,'test'=>true]
    )
);
echo EOL;

echo 'Swapping items 2 and 0, locally'.EOL;
var_dump(
    $testOrder->swapOrder(
        2,
        0,
        ['backUp'=>true,'local'=>true,'test'=>true]
    )
);
echo EOL;

echo 'Swapping items 0 and 1, locally'.EOL;
var_dump(
    $testOrder->swapOrder(
        0,
        1,
        ['backUp'=>true,'local'=>true,'test'=>true]
    )
);
echo EOL;

echo 'Swapping items 1 and 2, locally'.EOL;
var_dump(
    $testOrder->swapOrder(
        2,
        1,
        ['backUp'=>true,'local'=>true,'test'=>true]
    )
);

