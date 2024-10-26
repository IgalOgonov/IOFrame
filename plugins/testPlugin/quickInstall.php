<?php

$_SESSION['testRandomNumber'] = 0;
if(preg_match('/\W| /',$options['testOption'])==0 && !$test)
    $_SESSION['testSetting'] = $options['testOption'];
else
    if($test)
        echo $options['testOption'];


if(!$local){
    //Create a PDO connection
    $sqlSettings = new \IOFrame\Handlers\SettingsHandler($this->settings->getSetting('absPathToRoot').\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/sqlSettings/');
    $siteSettings = new \IOFrame\Handlers\SettingsHandler($this->settings->getSetting('absPathToRoot').\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/siteSettings/');

    $conn = \IOFrame\Util\FrameworkUtilFunctions::prepareCon($sqlSettings);
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // INITIALIZE CORE VALUES TABLE
    /* Literally just the pivot time for now. */
    $query = "CREATE TABLE IF NOT EXISTS ".$this->SQLManager->getSQLPrefix()."TEST_TABLE(
                                                          ID int PRIMARY KEY NOT NULL AUTO_INCREMENT,
                                                          testVarchar varchar(255),
                                                          testLargeText TEXT,
                                                          testDateVarchar varchar(14),
                                                          testInt int,
                                                          testFloat FLOAT,
                                                          testDate DATE,
                                                          testDatetime DATETIME,
                                                          testTimestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                                          testBlob MEDIUMBLOB
                                                          ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
    $makeTB = $conn->prepare($query);

    if(!$test)
        $makeTB->execute();
    else
        echo $query.EOL;
    //Insert 100 random values.

    if(isset($options['insertRandomValues'])){
        if($options['insertRandomValues']==1){
            //Insert 100 random values.
            $query = 'INSERT INTO '.$this->SQLManager->getSQLPrefix().'TEST_TABLE (testVarchar,testLargeText,
            testDateVarchar,testInt,testFloat,testDate,testDatetime,testBlob) VALUES ';

            $randomRows = [];
            for($i = 0; $i<100; $i++){
                $query .='(';
                $temp = [];
                $temp[0]='"'.IOFrame\Util\PureUtilFunctions::GeraHash(100).'"';
                $temp[1]='"'.IOFrame\Util\PureUtilFunctions::GeraHash(1000).'"';
                $temp[2]='"'.(time()+rand(-1000,1000)).'"';
                $temp[3]=rand(-1000,1000);
                $temp[4]=rand(0,10000)/10000;
                $temp[5]="'".date("Y-m-d")."'";
                $temp[6]="'".date("Y-m-d h:m:s")."'";
                $temp[7]=base_convert(strval(rand(0,1000000)), 10, 2);/**/
                $query .=implode(',',$temp);
                $query .='), ';
            }
            $query = substr($query,0,-2);
            $makeTB = $conn->prepare($query);
            if(!$test)
                $makeTB->execute();
            else
                echo $query.EOL;
        }
    }
}
























