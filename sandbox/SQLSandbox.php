<?php


$backUpArr = ['OBJECT_CACHE_META','OBJECT_CACHE','OBJECT_MAP'];//'OBJECT_CACHE_META','OBJECT_CACHE','OBJECT_MAP'
$timeLimits = ['OBJECT_CACHE'=>3600*24,'OBJECT_MAP'=>3600*24 ,'OBJECT_CACHE_META'=>3600*24];//'OBJECT_CACHE'=>3600*24,'OBJECT_MAP'=>3600*24 ,'OBJECT_CACHE_META'=>3600*24
$SQLManager = new \IOFrame\Managers\SQLManager($settings);
$sqlSettings = new \IOFrame\Handlers\SettingsHandler($rootFolder.'/localFiles/sqlSettings/',$defaultSettingsParams);
$tableName = $sqlSettings->getSetting('sql_table_prefix').'OBJECT_MAP';
$testMetaTime = 0;
$prefix = $SQLManager->getSQLPrefix();

try {

    var_dump(
        $SQLManager->selectFromTable(
            'information_schema.TABLES',
            [
                ['TABLE_TYPE',['BASE TABLE','STRING'],'LIKE'],
                ['TABLE_NAME',['SETTINGS_APISETTINGS','STRING'],'='],
                'AND'
            ],
            ['TABLE_NAME','TABLE_TYPE'],
            ['test'=>true]
        )
    );

    $time_start = microtime(true);
    $testQuery = '';
    foreach(['siteSettings','localSettings'] as $name){
        $tname = strtolower($SQLManager->getSQLPrefix().\IOFrame\Handlers\SettingsHandler::SETTINGS_TABLE_PREFIX.$name);
        $testQuery.= $SQLManager->selectFromTable($tname,
                [ [$tname, [['settingKey', '_Last_Updated', '='],['settingValue',$testMetaTime,'>='],'AND'], ['settingKey','settingValue'], [], 'SELECT'], 'EXISTS'],
                ['settingKey','settingValue', '\''.$name.'\' as Source'],
                ['justTheQuery'=>true,'test'=>false]).' UNION ';
    }
    $testQuery =  substr($testQuery,0,-7);
    echo $testQuery.EOL;
    $temp = $SQLManager->exeQueryBindParam($testQuery, [], ['fetchAll'=>true]);
    if(!is_array($temp))
        $temp = [];
    $res = [];
    $sources = [];
    foreach($temp as $resArray){
        foreach($resArray as $k=>$v)
            if(!(preg_match_all('/\d/',$k) == strlen($k))){
                if(!array_key_exists($resArray['settingKey'],$res))
                    $res[$resArray['settingKey']] = $resArray['settingValue'];
                $sources[$resArray['Source']][$resArray['settingKey']] = $resArray['settingValue'];
            }
    }
    var_dump($res);
    var_dump($sources);
    var_dump($temp);

    $temp = $SQLManager->exeQueryBindParam('SELECT DATABASE(), CURRENT_USER(), CONNECTION_ID(), VERSION()', [], ['fetchAll'=>true]);
    var_dump($temp);


    $temp = $SQLManager->selectFromTable(
        'TEST_TABLE',
        [['ID', 1, '>'], ['ID', 100, '<'], 'AND'],
        [],
        ['orderBy' => ['ID'], 'orderType' => 0, 'limit' => 2, 'SQL_CALC_FOUND_ROWS' => true,'test'=>true]);
    var_dump($temp);
    $temp = $SQLManager->exeQueryBindParam("SELECT FOUND_ROWS();", [], ['fetchAll'=>true]);
    var_dump($temp);
    /*
 $SQLManager->insertIntoTable('test_table', ['ID', 'testVarchar', 'testLargeText', 'testDateVarchar', 'testInt'],
     [[5, ['yrdfasfas', 'STRING'], ['sdfasfaw3fafsdfadfadtagrd', 'STRING'], ['1544311887', 'STRING'], 888]],
     ['onDuplicateKey' => true], false);
 echo EOL.EOL;
        echo $SQLManager->deleteFromTable('test_table',['ID',100,'>'],
              ['returnRows'=>true,'test'=>false]).EOL;
          var_dump($SQLManager->insertIntoTable('test_table',['testVarchar','testLargeText','testDateVarchar','testInt'],
              [[['yrdfasfas', 'STRING'],['sdfasfaw3fafsdfadfadtagrd', 'STRING'],['1544311888', 'STRING'],666],
                  [['u67rjfyjf', 'STRING'], ['5t7ye5yetydthdgh564dtjhdtjh', 'STRING'], ['1544311999', 'STRING'],777]],
              ['onDuplicateKey'=>false, 'returnRows'=>true],false));
             $SQLManager->insertIntoTable('test_table',['ID','testVarchar','testLargeText','testDateVarchar','testInt'],
                 [[5,'yrdfasfas','sdfasfaw3fafsdfadfadtagrd','1544311888',888],
                     [6,'u67rjfyjf','5t7ye5yetydthdgh564dtjhdtjh','1544311999',999]],
                 ['onDuplicateKey'=>true],true);
             $SQLManager->updateTable('test_table',['testFloat = 0.011','testVarchar = "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"'],
                 [['ID',30,'<'],['testInt',-100,'>'],['testInt',100,'<'],'AND'],
                 ['orderBy'=>['ID'], 'orderType'=>1 , 'limit'=>2,'test'=>false]);

     $addedRows = '';
     $arrOfVal = [];
     for($i=0; $i<50; $i++)
         array_push($arrOfVal,['yrdfasfas','sdfasfaw3fafsdfadfadtagrd','1544311666',$i]);
     $addedRows .= implode(',',$SQLManager->insertIntoTable('test_table',['testVarchar','testLargeText','testDateVarchar','testInt'],
         $arrOfVal,
         ['onDuplicateKey'=>false, 'returnRows'=>true],false)).',';
     echo $addedRows.EOL;
     echo 'Deleted rows: '.$SQLManager->deleteFromTable('test_table',['ID',100,'>'],
             ['returnRows'=>true,'test'=>false]).EOL;

     $time_end = microtime(true);
     $time = $time_end - $time_start;
     echo "sql Operations took ".$time." seconds".EOL;


    $SQLManager->exeQueryBindParam(
        "INSERT INTO OBJECT_CACHE_META (Group_Name,Group_Size,Owner,Last_Updated)
        VALUES  ( 'g2', 2, 1, 1545517503 )  ON DUPLICATE KEY UPDATE
         Group_Size = (Group_Size - VALUES( Group_Size )), Last_Updated = VALUES( Last_Updated ) ;",[]);
     echo EOL.EOL;*/
}
catch (\Exception $e){
    echo $e.EOL;
}

/*
echo 'Backup Tables: '.$SQLManager->backupTables($backUpArr,[],$timeLimits).EOL;
echo 'Restore Tables:'.$SQLManager->restoreTables([["OBJECT_CACHE_META","OBJECT_CACHE_META_backup_1543888266.txt"]],['test'=>true]).EOL;
echo 'Restore Latest Tables:'.$SQLManager->restoreLatestTables(['OBJECT_CACHE_META','OBJECT_CACHE','OBJECT_MAP'],false).EOL;
echo 'Check Table Not empty ipv4'.$SQLManager->checkTablesNotEmpty(array_merge($backUpArr,['ip_list'])).EOL;
*/