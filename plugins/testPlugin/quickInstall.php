<?php

$_SESSION['testRandomNumber'] = 0;
if(preg_match('/\W| /',$options['testOption'])==0 && !$test)
    $_SESSION['testSetting'] = $options['testOption'];
else
    if($test)
        echo $options['testOption'];


if(!$local){
    //Create a PDO connection
    $sqlSettings = new IOFrame\Handlers\SettingsHandler($this->settings->getSetting('absPathToRoot').SETTINGS_DIR_FROM_ROOT.'/sqlSettings/');
    $conn = IOFrame\Util\prepareCon($sqlSettings);
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // INITIALIZE CORE VALUES TABLE
    /* Literally just the pivot time for now. */
    $query = "CREATE TABLE IF NOT EXISTS ".$this->SQLHandler->getSQLPrefix()."TEST_TABLE(
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
            $query = 'INSERT INTO '.$this->SQLHandler->getSQLPrefix().'TEST_TABLE (testVarchar,testLargeText,
            testDateVarchar,testInt,testFloat,testDate,testDatetime,testBlob) VALUES ';

            $randomRows = [];
            for($i = 0; $i<100; $i++){
                $query .='(';
                $temp = [];
                $temp[0]='"'.IOFrame\Util\Gerahash(100).'"';
                $temp[1]='"'.IOFrame\Util\Gerahash(1000).'"';
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
            if(!$test)
                $makeTB->execute();
            else
                echo $query.EOL;
        }
    }

    /* If this is true, we will initialize a lot of test values that should NOT be initialized in production.
       Also, we'll copy some files into the front end as part of the sandbox. */
    $initialize = $options['initializeTestValues']['initialize'];
    if($initialize){

        //We need at least 2 users for this - an admin account and a secondary one
        $users = $this->SQLHandler->selectFromTable(
            $this->SQLHandler->getSQLPrefix().'USERS',
            [],
            [],
            ['test'=>$test,'limit'=>2]
        );
        if(!$users || count($users) < 2)
            throw new \Exception('Not enough users to initiate test values, need at least 2!');
        else{
            $adminId = 1;
            $secondId = $users[1]['ID'];
        }

        $settings = $this->settings;

        // ------------------ ORDER TEST -------------------

        //OrderHandler is always included inside plugins
        $testParams = $this->defaultSettingsParams;
        $testParams['name'] = 'test';
        $testParams['tableName'] = 'CORE_VALUES';
        $testParams['columnNames'] = ['tableKey','tableValue'];
        $testParams['localURL'] = $settings->getSetting('absPathToRoot').'localFiles/';
        $testParams['separator'] = '@#$%$#@';

        $testOrder = new IOFrame\Handlers\OrderHandler(
            $settings,
            $testParams
        );

        $testOrder->pushToOrder('SOME UNGODLY NAME! AGH',['test'=>$test,'createNew'=>true]);
        $testOrder->pushToOrder('SOME UNGODLY NAME! AGH',['test'=>$test,'local'=>false]);

        $testOrder->pushToOrder('test2',['test'=>$test,'createNew'=>true]);
        $testOrder->pushToOrder('test2',['test'=>$test,'createNew'=>true,'local'=>false]);

        $testOrder->pushToOrder('SOME UNGODLY NAME! AGH',['test'=>$test,'createNew'=>true, 'unique'=>false]);
        $testOrder->pushToOrder('SOME UNGODLY NAME! AGH',['test'=>$test,'createNew'=>true,'local'=>false]);

        // ------------------ AUTH TEST -------------------

        $auth = new IOFrame\Handlers\AuthHandler($settings,$this->defaultSettingsParams);

        $auth->setGroups(
            [
                'Test Group'=>'Test Description.',
                'Another Test Group'=>'Test Description II - The Description Strikes Back.'
            ],
            ['test'=>$test]
        );

        $auth->modifyGroupActions(
            'Test Group',
            [
                'TREE_C_AUTH'=>true,
                'TREE_R_AUTH'=>true,
                'TREE_U_AUTH'=>true,
                'TREE_D_AUTH'=>true
            ],
            ['test'=>$test]
        );
        $auth->modifyGroupActions(
            'Another Test Group',
            [
                'BAN_USERS_AUTH'=>true,
                'ADMIN_ACCESS_AUTH'=>true
            ],
            ['test'=>$test]
        );

        $auth->modifyUserActions(
            $secondId,
            ['PLUGIN_GET_INFO_AUTH'=>true,'PLUGIN_GET_AVAILABLE_AUTH'=>true,'ASSIGN_OBJECT_AUTH'=>true],
            ['test'=>$test]
        );
        $auth->modifyUserActions(
            $adminId,
            ['BAN_USERS_AUTH'=>true,'ASSIGN_OBJECT_AUTH'=>true],
            ['test'=>$test]
        );

        $auth->modifyUserGroups(
            $secondId,
            ['Test Group'=>true,'Another Test Group'=>true],
            ['test'=>$test]
        );

        $auth->modifyUserGroups(
            $adminId,
            ['Another Test Group'=>true],
            ['test'=>$test]
        );

        // ------------------ TREE TEST -------------------
        //To be added later - fuck this shit

        // ------------------ OBJECTS TEST -------------------
        require_once __DIR__.'/../../IOFrame/Handlers/ObjectHandler.php';
        $objHandler = new IOFrame\Handlers\ObjectHandler($settings,$this->defaultSettingsParams);
        $arr = [
            ['{\"type\":\"filterTable\", \"filter\":\"A\", \"filters\":\"all core comm\", \"title\":\"Communication Networks\", \"content\":\"Network Layers, Error Detection schemes, Network structures (like Aloha, Ethernet, etc.), Wireless Communications, and TCP/IP protocols and packet structure.\" }', 'courses'],
            ['{\"type\":\"filterTable\", \"filter\":\"A\", \"filters\":\"all core img\", \"title\":\"Final Project - Obstacle Recognition\", \"content\":\"Design and testing of Obstacle Detection models - implementation using OpenCV and Python.\" }', 'courses'],
            ['{\"type\":\"filterTable\", \"filter\":\"A\", \"filters\":\"all core img\", \"title\":\"Multispectral Images - Seminar\", \"content\":\"Presentation of topics related to Multispectral Images, and their applications.\" }', 'courses'],
            ['{\"type\":\"filterTable\", \"filter\":\"A\", \"filters\":\"all adv img\", \"title\":\"Image Processing\", \"content\":\"Basic Image Proccessing - Transofrms, detections, compression, and more. Familiarity with Matlab.\" }', 'courses'],
            ['{\"type\":\"filterTable\", \"filter\":\"A\", \"filters\":\"all adv img\", \"title\":\"Image, Sound & Video Compression\", \"content\":\"Various image, sound and video compression techniques - includes some classic, and some more compression formats for each of the topics, such as JPEG, H-264/MPEG4, and more.\" }', 'courses'],
            ['{\"type\":\"filterTable\", \"filter\":\"A\", \"filters\":\"all adv default\", \"title\":\"Database Design\", \"content\":\"Relational Algebra, SQL Database scheme design, Internal DB design and optimization, translating client requirements to design, ERD schemes.\" }', 'courses'],
            ['{\"type\":\"filterTable\", \"filter\":\"A\", \"filters\":\"all adv finTech\", \"title\":\"Algoritrading\", \"content\":\"Various basic trading strategies, indicators, etc. Implementation of the above in EasyLanguage, using TradeStation.\" }', 'courses'],
            ['{\"type\":\"filterTable\", \"filter\":\"A\", \"filters\":\"all msc sec\", \"title\":\"Computer & Network Security\", \"content\":\"Basic software security exploits (Stack Overflow, etc), Symmetric and Asymmetric Crpytography, Signitures, MACs, Firewalls, various classic and current security protocols and techniques (WEP, Diffie Helman, RSA, Entrence Control Techniques, PKI, SSL, IPsec, etc..)\" }', 'courses'],
            ['{\"type\":\"filterTable\", \"filter\":\"A\", \"filters\":\"all msc sec\", \"title\":\"Foundations of Cryptography\", \"content\":\"Foundations of cryptographic primitives, with focus an rigous mathematical proofs. One-Way Functions, Pseudo Random Generators (and Functions), MACs, and many more well defined cryptographic primitives.\" }', 'courses'],
            ['{\"type\":\"filterTable\", \"filter\":\"A\", \"filters\":\"all msc sec comm\", \"title\":\"Advanced Topics in Internet Communications\", \"content\":\"Topics from basic protocols such as InServ, DiffServ, QoS etc. MPLS. VPN. SSL and IPSec, IPV6, Moobile IP, and much more.\" }', 'courses'],
            ['{\"type\":\"filterTable\", \"filter\":\"A\", \"filters\":\"all msc algo\", \"title\":\"Algorithms for Planar Graphs\", \"content\":\"The latest and greatest advancements in algorithms in Planar Graphs. Includes things such as MSSP, FR-Dijkstra, Cycle Seperators in Planar Graphs, Vertex-Cover and TSM (PTAS using Bakers Technique), and more.\" }', 'courses'],
            ['{\"type\":\"filterTable\", \"filter\":\"A\", \"filters\":\"all msc algo\", \"title\":\"Approximation Algorithms\", \"content\":\" Various approximation algorithms - no specific topics, most algorithms can be found in the advanced chapters of \\\"Algorithm Design by Jon Kleinberg, Eva Tardos\\\", and \\\"Design of Approximation Algorithms by David P.Williamson and David B. Shmoys\\\"\" }', 'courses'],
            ['{\"type\":\"filterTable\", \"filter\":\"A\", \"filters\":\"all msc algo\", \"title\":\"Massive Data Streams\", \"content\":\"Algorithms and methods related to Massive Data Streams - approximations and distributed solutions.\" }', 'courses'],
            ['test1', 'g1'],
            ['test2', 'g1'],
            ['test3', 'g2'],
            ['test4', 'g3'],
        ];
        $objHandler->addObjects( $arr, ['test'=>$test]);

        $objHandler->objectMapModifyMultiple(
            [
                [1,'CV'],
                [2,'CV'],
                [3,'CV'],
                [4,'CV'],
                [5,'CV'],
                [6,'CV'],
                [7,'CV'],
                [8,'CV'],
                [9,'CV'],
                [10,'CV'],
                [11,'CV'],
                [12,'CV'],
                [13,'CV']
            ],
            ['test'=>$test]
        );
        // ------------------ MEDIA TEST -------------------
        $FrontEndResourceHandler = new IOFrame\Handlers\FrontEndResourceHandler($settings,$this->defaultSettingsParams);
        $FrontEndResourceHandler->setGallery('Test Gallery',json_encode(['name'=>'Awesome Gallery']),['test'=>$test]);
        $FrontEndResourceHandler->addImagesToGallery(
            ['docs/Euler.png','docs/installScreenshots'],
            'Test Gallery',
            ['test'=>$test]
        );
    }
}

























?>