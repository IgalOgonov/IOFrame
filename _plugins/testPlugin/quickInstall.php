<?php

if(!$test){
    $_SESSION['testRandomNumber'] = 0;
    if(preg_match('/\W| /',$options['testOption'])==0)
        $_SESSION['testSetting'] = $options['testOption'];
    else
        echo $options['testOption'];


    //Create a PDO connection
    $sqlSettings = new IOFrame\settingsHandler($this->settings->getSetting('absPathToRoot').SETTINGS_DIR_FROM_ROOT.'/sqlSettings/');
    $conn = IOFrame\prepareCon($sqlSettings);
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // INITIALIZE CORE VALUES TABLE
    /* Literally just the pivot time for now. */
    $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$this->sqlHandler->getSQLPrefix()."TEST_TABLE(
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
                                                          ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");

    $makeTB->execute();
    //Insert 100 random values.
    $sqlHandler = new IOFrame\sqlHandler($this->settings);
    if(isset($options['insertRandomValues'])){
        if($options['insertRandomValues']==1){
            //Insert 100 random values.
            $query = 'INSERT INTO '.$this->sqlHandler->getSQLPrefix().'TEST_TABLE (testVarchar,testLargeText,
            testDateVarchar,testInt,testFloat,testDate,testDatetime,testBlob) VALUES ';

            $randomRows = [];
            for($i = 0; $i<100; $i++){
                $query .='(';
                $temp = [];
                $temp[0]='"'.IOFrame\Gerahash(100).'"';
                $temp[1]='"'.IOFrame\Gerahash(1000).'"';
                $temp[2]='"'.(time()+rand(-1000,1000)).'"';
                $temp[3]=rand(-1000,1000);
                $temp[4]=rand(0,10000)/10000;
                $temp[5]="'".date("Y-m-d")."'";
                $temp[6]="'".date("Y-m-d h:m:s")."'";
                $temp[7]=base_convert(strval(rand(0,1000000)), 10, 2);/**/
                $query .=implode(',',$temp);
                $query .='), ';
            };
            $query = substr($query,0,-2);
            $makeTB = $conn->prepare($query);
            $makeTB->execute();
        }
    }
}
else{
    echo 'quickInstall activates here!'.EOL;
    echo 'Option '.$options['testOption'].' validity is '.(preg_match('/\W| /',$options['testOption'])==0).EOL;
}
























?>