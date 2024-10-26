<?php
namespace IOFrame\Util\Installation{


    define('IOFrameUtilInstallationDBInitiationFunctions',true);


    class DBInitiationFunctions{

        /** Initiates the database (structure and/or default values).
         *  There are multiple ways to initiate the DB, based on the extent to which you wish to scale the system.
         *  As the "Table Relations" diagram shows (should be found on the docs DB / General overview), some relations, which use foreign keys by default,
         *  may be done programmatically.
         *  This would allow running separate DB nodes for those specific tables / table collections.
         *  However, you may wish to start on a single node, then detach them once (if) this type of scaling is required.
         *  Read more about this in the docs.
         *  TODO Database creation
         *  TODO Allow initiating different table groups in different DBs once pooling is implemented - at the moment, only supports logs
         *  TODO In highScalability, remove foreign key constraints to USERS from Articles, Purchase Orders and Logs
         *  TODO In highScalability, create pool based connections
         *  TODO Uncouple IP and User event rulebooks
         * @param \IOFrame\Handlers\SettingsHandler $localSettings
         * @param array $params
         *        'defaultSettingsParams' => array, default [] - see parameter by the same name for most Handlers
         *        'highScalability' => bool, default null - Overrides local setting of the same name. If true, will assume maximal scale by default (as few cross-table relations as possible).
         *        'hostUrl' => string, default $_SERVER['HTTP_HOST'] - Used to initiate default mail tables. Optional in most cases, but required in case of CLI
         *        'init' => bool, default true - default _init value, explained below in 'tables'.
         *        'populate' => bool, default true - default _populate value, explained below in 'tables'.
         *        'tables' => array, default explained below.
         *                    Each key correlates to a specific table, or collection of tables related to a module.
         *                    The value is an object of the form:
         *                    [
         *                      '_init' => <bool, default true - whether to initiate this table>
         *                      '_populate' => <bool, default true - whether to populate this table with initial install values>
         *                      <string, potential connection> => <bool, default !$params['highScalability'] - whether to use SQL relations (foreign keys)>
         *                    ]
         *                    The following keys are supported:
         *                     * Relevant table name(s) listed first after the key.
         *                     ** If it can (should) be populated on install, the table name has a star near it
         *                     *** Potential (removable) connections are listed below inside [square brackets]. Note that this does not include connections inside a single group of tables, only between different logical modules.
         *                     **** Connections which cannot be removed are listed inside [curly brackets].
         *                     ***** Functions have a (f) before their name.
         *                     'core' - CORE_VALUES*
         *                     'mail' - MAIL_TEMPLATES*
         *                     'users' - USERS, LOGIN_HISTORY, USERS_EXTRA
         *                     'contacts' - CONTACTS
         *                     'userAuth' - USERS_AUTH, GROUPS_AUTH, ACTIONS_AUTH, USERS_ACTIONS_AUTH, GROUPS_ACTIONS_AUTH, USERS_GROUPS_AUTH {users}
         *                     'ipSecurity' - IP_LIST, IPV4_RANGE
         *                     'securityEvents' - IP_EVENTS, USER_EVENTS, EVENTS_RULEBOOK, EVENTS_META, (f)commitEventUser, (f)commitEventIP {'userSecurity',ipSecurity'}
         *                     'dbBackupMeta' - DB_BACKUP_META
         *                     'routing' - ROUTING_MAP*, ROUTING_MATCH*
         *                     'tokens' - IOFRAME_TOKENS
         *                     'menus' - MENUS
         *                     'resources' - RESOURCES, RESOURCE_COLLECTIONS, RESOURCE_COLLECTIONS_MEMBERS
         *                     'tags' - TAGS, CATEGORY_TAGS ['resources']
         *                     'language' - LANGUAGE_OBJECTS
         *                     'orders' - DEFAULT_ORDERS, DEFAULT_USERS_ORDERS ['users']
         *                     'objectAuth' - OBJECT_AUTH_CATEGORIES, OBJECT_AUTH_OBJECTS, OBJECT_AUTH_ACTIONS, OBJECT_AUTH_GROUPS, OBJECT_AUTH_OBJECT_USERS ['users']
         *                     'articles' - ARTICLES, ARTICLE_TAGS, ARTICLE_BLOCKS ['users','resources','tags'] (the handler uses joins on contacts, but by the user - creator - ID, not via a connection here)
         *                     'logging' - DEFAULT_LOGS, REPORTING_GROUPS, REPORTING_GROUP_USERS, REPORTING_RULES, REPORTING_RULE_GROUPS ['users'] (DEFAULT_LOGS can already be hosted separately, but REPORTING_GROUP_USERS connects everything else to the user table)
         * @return bool | string
         *         true on success, name of the failed section on failure
         * @throws Exception
         */
        public static function initDB(\IOFrame\Handlers\SettingsHandler $localSettings, array $params = []): bool {
            $test = $params['test'] ?? false;
            $verbose = $params['verbose'] ?? $test;
            $hostUrl= $params['hostUrl'] ?? $_SERVER['HTTP_HOST'];
            $highScalability = (bool)($params['highScalability'] ?? ($localSettings->getSetting('highScalability') ?? false));
            $init = $params['init'] ?? true;
            $populate = $params['populate'] ?? true;
            $tables = $params['tables'] ?? [];
            $defaultSettingsParams = $params['defaultSettingsParams'] ?? [];

            $defaultTableParams = [
                '_init'=>$init,
                '_populate'=>$populate,
                'highScalability'=>$highScalability,
            ];
            $defaultTables = [
                'core' => $defaultTableParams,
                'mail' => $defaultTableParams,
                'users' => $defaultTableParams,
                'contacts' => $defaultTableParams,
                'userAuth' => $defaultTableParams,
                'ipSecurity' => $defaultTableParams,
                'securityEvents' => $defaultTableParams,
                'dbBackupMeta' => $defaultTableParams,
                'routing' => $defaultTableParams,
                'tokens' => $defaultTableParams,
                'menus' => $defaultTableParams,
                'resources' => $defaultTableParams,
                'tags' => array_merge(
                    $defaultTableParams,
                    [
                        'resources'=>!$highScalability
                    ]
                ),
                'language' => $defaultTableParams,
                'orders' => array_merge(
                    $defaultTableParams,
                    [
                        'users'=>!$highScalability
                    ]
                ),
                'objectAuth' => array_merge(
                    $defaultTableParams,
                    [
                        'users'=>!$highScalability
                    ]
                ),
                'articles' => array_merge(
                    $defaultTableParams,
                    [
                        'users'=>!$highScalability,
                        'resources'=>!$highScalability,
                        'tags'=>!$highScalability
                    ]
                ),
                'logging' => array_merge(
                    $defaultTableParams,
                    [
                        'users'=>!$highScalability
                    ]
                ),
            ];

            $tables = \IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct(
                $defaultTables,
                $tables
            );

            $userSettings = new \IOFrame\Handlers\SettingsHandler(\IOFrame\Util\FrameworkUtilFunctions::getBaseUrl().'/localFiles/userSettings/');
            $siteSettings = new \IOFrame\Handlers\SettingsHandler(\IOFrame\Util\FrameworkUtilFunctions::getBaseUrl().'/localFiles/siteSettings/');
            $sqlSettings = new \IOFrame\Handlers\SettingsHandler($localSettings->getSetting('absPathToRoot').\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/sqlSettings/');

            $SQLManager = new \IOFrame\Managers\SQLManager($localSettings);
            $prefix = $SQLManager->getSQLPrefix();
            $defaultSettingsParams['SQLManager'] = $SQLManager;

            try {
                //Create a PDO connection
                $conn = \IOFrame\Util\FrameworkUtilFunctions::prepareCon($sqlSettings);
                // set the PDO error mode to exception
                $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                if($verbose){
                    echo "Connected successfully" . EOL;
                    echo "Initializing...." . EOL;
                }
            }
            catch(\PDOException $e)
            {
                if($verbose)
                    echo "Error: " . $e->getMessage().EOL;
                return 'initial-connection';
            }

            if(!empty($tables['core']['_init'])){
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."CORE_VALUES(
                                                              tableKey varchar(255) PRIMARY KEY,
                                                              tableValue text
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "CORE VALUES table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "CORE VALUES table couldn't be initialized, error is: ".$e->getMessage().EOL;
                    return 'core-values-structure';
                }
            }
            if(!empty($tables['core']['_populate'])){
                $updateTB1 = $conn->prepare("INSERT INTO ".$prefix."CORE_VALUES (tableKey, tableValue)
                                      VALUES( 'privateKey','".$siteSettings->getSetting('privateKey')."') 
                                      ON DUPLICATE KEY UPDATE tableKey=VALUES(tableKey), tableValue=VALUES(tableValue)");
                $updateTB2 = $conn->prepare("INSERT INTO ".$prefix."CORE_VALUES (tableKey, tableValue)
                                      VALUES( 'secure_file_priv',@@secure_file_priv) 
                                      ON DUPLICATE KEY UPDATE tableKey=VALUES(tableKey), tableValue=VALUES(tableValue)");
                $getWrongSFP = $conn->prepare("SELECT * FROM ".$prefix.
                    "CORE_VALUES WHERE tableKey = :tableKey;");
                $sfp = $conn->prepare("UPDATE ".$prefix.
                    "CORE_VALUES SET tableValue = :tableValue WHERE tableKey = :tableKey;");
                try{
                    if(!$test){
                        $updateTB1->execute();
                        $updateTB2->execute();
                    }
                    $getWrongSFP->bindValue(':tableKey','secure_file_priv');
                    $getWrongSFP->execute();
                    $oldsfp = $getWrongSFP->fetchAll()[0]['tableValue'];
                    $newString = str_replace('\\', '/' , $oldsfp);
                    $sfp->bindValue(':tableKey','temp');
                    $sfp->bindValue(':tableValue',$newString);
                    if(!$test)
                        $sfp->execute();
                    if($verbose)
                        echo "CORE VALUES table initialized.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "CORE VALUES table couldn't be initialized, error is: ".$e->getMessage().EOL;
                    return 'core-values-population';
                }
            }

            if(!empty($tables['mail']['_init'])){
                /*ID - automatic increment
                 *Title - Name of the template
                 *Template - the template.
                 * */
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."MAIL_TEMPLATES(
                                                              ID VARCHAR(255) PRIMARY KEY NOT NULL,
                                                              Title varchar(255) NOT NULL,
                                                              Content TEXT,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "MAIL TEMPLATES table and indexes created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "MAIL TEMPLATES table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'mail-templates-structure';
                }
            }

            if(!empty($tables['mail']['_populate'])){
                $timeNow = (string)time();
                $mailTemplateArr = [
                    [
                        "INSERT INTO ".$prefix."MAIL_TEMPLATES (ID, Title, Content, Created, Last_Updated)
                                      VALUES( 'default_activation', 'Account Activation Default Template', :Content, :Created, :Last_Updated) 
                                      ON DUPLICATE KEY UPDATE ID=VALUES(ID), Title=VALUES(Title), Content=VALUES(Content), Created=VALUES(Created), Last_Updated=VALUES(Last_Updated)",
                        "Hello!<br>".
                        " To activate your account on %%siteName%%, click <a href=\\" .$hostUrl.$localSettings->getSetting('pathToRoot')."api/users?action=regConfirm&id=%%uId%%&code=%%Code%%\">this link</a><br>".
                        " The link will expire in ".$userSettings->getSetting('mailConfirmExpires')." hours"
                    ],
                    [
                        "INSERT INTO ".$prefix."MAIL_TEMPLATES (ID, Title, Content, Created, Last_Updated)
                                      VALUES( 'default_password_reset', 'Password Reset Default Template', :Content, :Created, :Last_Updated) 
                                      ON DUPLICATE KEY UPDATE ID=VALUES(ID), Title=VALUES(Title), Content=VALUES(Content), Created=VALUES(Created), Last_Updated=VALUES(Last_Updated)",
                        "Hello!<br>".
                        " You have requested to reset the password associated with this account. To do so, click <a href=\\" .$hostUrl.$localSettings->getSetting('pathToRoot')."api/users?action=pwdReset&id=%%uId%%&code=%%Code%%\"> this link</a><br>".
                        " The link will expire in ".$userSettings->getSetting('pwdResetExpires')." hours"
                    ],
                    [
                        "INSERT INTO ".$prefix."MAIL_TEMPLATES (ID, Title, Content, Created, Last_Updated)
                                      VALUES( 'default_mail_reset', 'Mail Reset Default Template', :Content, :Created, :Last_Updated) 
                                      ON DUPLICATE KEY UPDATE ID=VALUES(ID), Title=VALUES(Title), Content=VALUES(Content), Created=VALUES(Created), Last_Updated=VALUES(Last_Updated)",
                        "Hello!<br>".
                        " To change your mail on %%siteName%%, click <a href=\\" .$hostUrl.$localSettings->getSetting('pathToRoot'). "api/users?action=mailReset&id=%%uId%%&code=%%Code%%\">this link</a><br>".
                        " The link will expire in ".$userSettings->getSetting('mailConfirmExpires')." hours"
                    ],
                    [
                        "INSERT INTO ".$prefix."MAIL_TEMPLATES (ID, Title, Content, Created, Last_Updated)
                                      VALUES( 'default_invite', 'Invite Mail Default Template', :Content, :Created, :Last_Updated) 
                                      ON DUPLICATE KEY UPDATE ID=VALUES(ID), Title=VALUES(Title), Content=VALUES(Content), Created=VALUES(Created), Last_Updated=VALUES(Last_Updated)",
                        "Hello!<br>".
                        " You've been invited to join %%siteName%%. Click <a href=\\" .$hostUrl.$localSettings->getSetting('pathToRoot'). "api/users?action=checkInvite&mail=%%mail%%&token=%%token%%\">this link</a> to accept the invite.<br>".
                        " The invite will expire in ".(int)($userSettings->getSetting('inviteExpires')/24)." days"
                    ],
                    [
                        "INSERT INTO ".$prefix."MAIL_TEMPLATES (ID, Title, Content, Created, Last_Updated)
                                      VALUES( 'default_mail_2FA', 'Mail 2FA Template', :Content, :Created, :Last_Updated) 
                                      ON DUPLICATE KEY UPDATE ID=VALUES(ID), Title=VALUES(Title), Content=VALUES(Content), Created=VALUES(Created), Last_Updated=VALUES(Last_Updated)",
                        "To log into %%siteName%%, please enter the following code: <br> ".
                        "%%Code%% <br>".
                        " The code will expire in ".(int)($userSettings->getSetting('mail2FAExpires')/60)." minutes."
                    ],
                    [
                        "INSERT INTO ".$prefix."MAIL_TEMPLATES (ID, Title, Content, Created, Last_Updated)
                                      VALUES( 'default_mail_sus', 'Suspicious Account Activity', :Content, :Created, :Last_Updated) 
                                      ON DUPLICATE KEY UPDATE ID=VALUES(ID), Title=VALUES(Title), Content=VALUES(Content), Created=VALUES(Created), Last_Updated=VALUES(Last_Updated)",
                        "Suspicious activity has been detected in your account on %%siteName%%.<br> ".
                        "If you did not perform any actions such as failing to log in with an incorrect 2FA code, you should change your password immediately."
                    ],
                    [
                        "INSERT INTO ".$prefix."MAIL_TEMPLATES (ID, Title, Content, Created, Last_Updated)
                                      VALUES( 'default_log_report', 'Default Logs Report', :Content, :Created, :Last_Updated) 
                                      ON DUPLICATE KEY UPDATE ID=VALUES(ID), Title=VALUES(Title), Content=VALUES(Content), Created=VALUES(Created), Last_Updated=VALUES(Last_Updated)",
                        "Logs report from %%siteName%%<br>".
                        "%%Summary_Header%% ".
                        "%%Summary_Body%% ".
                        "%%Summary_Footer%% "
                    ],
                ];
                $updateMailTemplates = [];
                for($i=0; $i<count($mailTemplateArr); $i++){
                    $updateMailTemplates[$i] = $conn->prepare($mailTemplateArr[$i][0]);
                    $updateMailTemplates[$i]->bindValue(':Content', \IOFrame\Util\SafeSTRFunctions::str2SafeStr($mailTemplateArr[$i][1]));
                    $updateMailTemplates[$i]->bindValue(':Created', $timeNow);
                    $updateMailTemplates[$i]->bindValue(':Last_Updated', $timeNow);
                }

                try{
                    for($i=0; $i<count($updateMailTemplates); $i++){
                        if(!$test)
                            $updateMailTemplates[$i]->execute();
                    }
                    if($verbose)
                        echo "MAIL TEMPLATES table initialized.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "MAIL TEMPLATES table couldn't be initialized, error is: ".$e->getMessage().EOL;
                    return 'mail-templates-population';
                }
            }

            if(!empty($tables['users']['_init'])){
                /* ID - automatic increment
                 * Username - user's name of choice. If you decide to make it similar to users mail, remember to change all the
                 *           validation functions to reflect the added @, namely in addUser and logUser.
                 * Password - encrypted password
                 * Email - Users mail.
                 * Rank - highest is 0 (site admin), lowest is 9999 (logged out user)
                 * SessionID - used to identify the user
                 * authDetails - used for user automatic relog
                 */
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."USERS (
                                                              ID int PRIMARY KEY NOT NULL AUTO_INCREMENT,
                                                              Username varchar(64) UNIQUE NOT NULL,
                                                              Password varchar(255) NOT NULL,
                                                              Email varchar(255) UNIQUE NOT NULL,
                                                              Phone varchar(32) UNIQUE DEFAULT NULL,
                                                              Active int(11) NOT NULL,
                                                              Auth_Rank int,
                                                              SessionID varchar(255),
                                                              authDetails TEXT,
                                                              Two_Factor_Auth TEXT DEFAULT NULL,
                                                              INDEX (Username),
                                                              INDEX (Email),
                                                              INDEX (Active)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "USERS table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "USERS table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'users-structure';
                }

                /*THIS IS AN EXTRA TABLE - the site core modules should work fine without it.
                 * ID - foreign key - tied to USERS table
                 * Created - Date the user was created on, yyyymmddhhmmss format
                 * Banned_Until, Locked_Until, Suspicious_Until - Dates until which user is banned/locked/suspicious.
                 */
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."USERS_EXTRA (
                                                              ID int PRIMARY KEY,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Preferred_Language varchar(14) DEFAULT NULL,
                                                              Banned_Until varchar(14),
                                                              Locked_Until varchar(14),
                                                              Suspicious_Until varchar(14),
                                                                FOREIGN KEY (ID)
                                                                REFERENCES ".$prefix."USERS(ID)
                                                                ON DELETE CASCADE,
                                                              INDEX (Banned_Until),
                                                              INDEX (Locked_Until),
                                                              INDEX (Suspicious_Until)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
                try{
                    if(!$test)
                        $makeTB->execute();
                    echo "USERS_EXTRA table created.".EOL;
                }
                catch(\Exception $e){
                    echo "USERS_EXTRA table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'users-extra-structure';
                }

                /* Username     - connected to USERS table
                 * IP           - IP of the user when logging in.
                 * Country      - Country said IP matches.
                 * Login_History- an hArray that represents each time the user logged in.
                 * */
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."LOGIN_HISTORY (
                                                              ID INT NOT NULL,
                                                              IP varchar(45) NOT NULL,
                                                              Country varchar(20) NOT NULL,
                                                              Login_Time int NOT NULL,
                                                              PRIMARY KEY (ID, IP,Login_Time),
                                                              FOREIGN KEY (ID)
                                                                REFERENCES ".$prefix."USERS(ID)
                                                                ON DELETE CASCADE ON UPDATE CASCADE,
                                                              INDEX(Country),
                                                              INDEX(Login_Time)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "USER LOGIN HISTORY history table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "USER LOGIN HISTORY table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'user-login-history-structure';
                }
            }

            if(!empty($tables['contacts']['_init'])){
                /* This table is meant to save contact cards. While those cards might be tied to users, they dont have to be.
                 * There will most likely be no default API for it, as each system usually handles contacts differently.
                 * Identifier - can be anything from user email, to username, to a random hash generated on creation.
                 * First_Name - Self explanatory.
                 * Last_Name - Self explanatory.
                 * Email -  Self explanatory.
                 * Phone -  Self explanatory - should contain country code, too.
                 * Fax -  Self explanatory - should contain country code, too.
                 * Contact_Info - Extra unindexed contact info.
                 * Country -  Self explanatory.
                 * State -  Self explanatory.
                 * City - Self explanatory.
                 * Street -  Self explanatory.
                 * Zip_Code -  Self explanatory.
                 * Address - Extra unindexed address info.
                 * Company_Name -  Self explanatory.
                 * Company_ID -  Self explanatory.
                 * Extra_Info - Any extra info - wont be indexed.
                 * Created - Date the card was created on, Timestamp
                 * Last_Updated - Date when was last updated on, Timestamp
                 */
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."CONTACTS (
                                                              Contact_Type varchar(64),
                                                              Identifier varchar(256),
                                                              First_Name varchar (64),
                                                              Last_Name varchar (64),
                                                              Email varchar (256),
                                                              Phone varchar (32),
                                                              Fax varchar (32),
                                                              Contact_Info TEXT,
                                                              Country varchar (64),
                                                              State varchar (64),
                                                              City varchar (64),
                                                              Street varchar (64),
                                                              Zip_Code varchar (14),
                                                              Address TEXT,
                                                              Company_Name varchar (256),
                                                              Company_ID varchar (64),
                                                              Extra_Info TEXT,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
    														  PRIMARY KEY(Contact_Type,Identifier),
                                                              INDEX (First_Name,Last_Name),
                                                              INDEX (Email),
                                                              INDEX (Country),
                                                              INDEX (City),
                                                              INDEX (Company_Name),
                                                              INDEX (Company_ID),
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "CONTACTS table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "CONTACTS table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'contacts-structure';
                }
            }


            if(!empty($tables['userAuth']['_init'])){

                /*THIS IS AN AUTH TABLE - it is responsible for authorization.
                 * ID - foreign key - tied to USERS table
                 * Last_Updated - The latest time this users actions/groups were changed.
                 */
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."USERS_AUTH (
                                                              ID int PRIMARY KEY,
                                                              Last_Updated varchar(11),
                                                                FOREIGN KEY (ID)
                                                                REFERENCES ".$prefix."USERS(ID)
                                                                ON DELETE CASCADE,
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "USER AUTH table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "USER AUTH table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'user-auth-structure';
                }

                /*THIS IS A GROUPS AUTH TABLE- it is responsible for authorization of groups.
                 * Auth_Group - name of the group
                 * Last_Updated - The latest time this groups actions were changed.
                 * Description - Optional group description
                 */
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."GROUPS_AUTH (
                                                              Auth_Group varchar(256) PRIMARY KEY,
                                                              Last_Updated varchar(11) NOT NULL DEFAULT '0',
                                                              Description TEXT,
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "GROUPS AUTH table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "GROUPS AUTH table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'groups-auth-structure';
                }

                /*THIS IS AN ACTIONS AUTH TABLE- it is responsible for saving available actions, as well as providing descriptions.
                 * Auth_Action - name of the action
                 * Description - Optional action description
                 */
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."ACTIONS_AUTH (
                                                              Auth_Action varchar(256) PRIMARY KEY,
                                                              Description TEXT
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "ACTIONS_AUTH table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "ACTIONS_AUTH table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'actions-auth-structure';
                }

                /*THIS IS A USERS ACTIONS TABLE - a one-to-many table saving the actions allocated to each user.
                 * ID - foreign key - tied to USERS table
                 * Auth_Action - an action the user is authorized to do.
                 */
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."USERS_ACTIONS_AUTH (
                                                                ID int,
                                                                Auth_Action varchar(256),
                                                                FOREIGN KEY (ID)
                                                                REFERENCES ".$prefix."USERS(ID)
                                                                ON DELETE CASCADE,
                                                                FOREIGN KEY (Auth_Action)
                                                                REFERENCES ".$prefix."ACTIONS_AUTH(Auth_Action)
                                                                ON DELETE CASCADE ON UPDATE CASCADE,
                                                                PRIMARY KEY (ID, Auth_Action)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "USER ACTIONS_AUTH table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "USER ACTIONS_AUTH table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'users-actions-structure';
                }

                /*THIS IS A GROUPS ACTIONS TABLE - a one-to-many table saving the actions allocated to each group.
                 * Auth_Group - foreign key - name of the group
                 * Auth_Action - an action the group is authorized to do.
                 */
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."GROUPS_ACTIONS_AUTH (
                                                                Auth_Group varchar(256),
                                                                Auth_Action varchar(256),
                                                                FOREIGN KEY (Auth_Group)
                                                                REFERENCES ".$prefix."GROUPS_AUTH(Auth_Group)
                                                                ON DELETE CASCADE ON UPDATE CASCADE,
                                                                FOREIGN KEY (Auth_Action)
                                                                REFERENCES ".$prefix."ACTIONS_AUTH(Auth_Action)
                                                                ON DELETE CASCADE ON UPDATE CASCADE,
                                                                PRIMARY KEY (Auth_Group, Auth_Action)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "GROUPS_ACTIONS_AUTH table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "GROUPS_ACTIONS_AUTH table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'groups-actions-auth-structure';
                }

                /*THIS IS A USERS GROUPS TABLE - a one-to-many table saving the groups allocated to each user.
                 * ID - foreign key - tied to USERS table
                 * Auth_Group - foreign key - name of the group
                 */
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."USERS_GROUPS_AUTH (
                                                                ID int,
                                                                Auth_Group varchar(256),
                                                                FOREIGN KEY (ID)
                                                                REFERENCES ".$prefix."USERS(ID)
                                                                ON DELETE CASCADE,
                                                                FOREIGN KEY (Auth_Group)
                                                                REFERENCES ".$prefix."GROUPS_AUTH(Auth_Group)
                                                                ON DELETE CASCADE ON UPDATE CASCADE,
                                                                PRIMARY KEY (ID, Auth_Group)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "USERS_GROUPS_AUTH table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "USERS_GROUPS_AUTH table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'users-groups-auth-structure';
                }
            }

            if(!empty($tables['userAuth']['_populate'])){

                //Insert auth actions TODO Use relevant handler
                $columns = ['Auth_Action','Description'];
                $assignments = [
                    ['REGISTER_USER_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to register a user when self-registration is not allowed')],
                    ['ADMIN_ACCESS_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to access administrator pages')],
                    ['AUTH_MODIFY_RANK',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to modify user ranks (down to your rank at most)')],
                    ['AUTH_VIEW',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to view auth actions/groups/users')],
                    ['AUTH_SET',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to freely modify auth actions/groups')],
                    ['AUTH_SET_ACTIONS',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to set auth actions')],
                    ['AUTH_SET_GROUPS',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to set auth groups')],
                    ['AUTH_DELETE',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to delete auth actions/groups')],
                    ['AUTH_DELETE_ACTIONS',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to delete auth actions')],
                    ['AUTH_DELETE_GROUPS',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to delete auth groups')],
                    ['AUTH_MODIFY',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to delete auth actions/groups/users')],
                    ['AUTH_MODIFY_ACTIONS',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to delete auth actions')],
                    ['AUTH_MODIFY_GROUPS',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to delete auth groups')],
                    ['AUTH_MODIFY_USERS',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to delete auth users')],
                    ['CONTACTS_GET',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to freely get contacts')],
                    ['CONTACTS_MODIFY',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to freely modify contacts')],
                    ['PLUGIN_GET_AVAILABLE_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to get available plugins')],
                    ['PLUGIN_GET_INFO_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to get plugin info')],
                    ['PLUGIN_GET_ORDER_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to get plugin order')],
                    ['PLUGIN_PUSH_TO_ORDER_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to push to plugin order')],
                    ['PLUGIN_REMOVE_FROM_ORDER_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to remove from plugin order')],
                    ['PLUGIN_MOVE_ORDER_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to move plugin order')],
                    ['PLUGIN_SWAP_ORDER_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to swap plugin order')],
                    ['PLUGIN_INSTALL_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to install a plugin')],
                    ['PLUGIN_UNINSTALL_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to uninstall a plugin')],
                    ['PLUGIN_IGNORE_VALIDATION',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to ignore plugin validation during installation')],
                    ['BAN_USERS_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to ban users')],
                    ['SET_USERS_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to update users')],
                    ['GET_USERS_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to get users information')],
                    ['MODIFY_USER_ACTIONS_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to modify user actions')],
                    ['MODIFY_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to modify all user auth')],
                    ['MODIFY_USER_RANK_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to modify user ranks')],
                    ['MODIFY_GROUP_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to modify auth groups')],
                    ['IMAGE_UPLOAD_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allow image upload')],
                    ['IMAGE_FILENAME_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allow choosing image filename on upload')],
                    ['IMAGE_OVERWRITE_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allow overwriting existing images')],
                    ['IMAGE_GET_ALL_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allows getting all images (and each individual one)')],
                    ['IMAGE_UPDATE_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allow unlimited image updating (both alt tag and name)')],
                    ['IMAGE_ALT_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allow unlimited image alt tag changing')],
                    ['IMAGE_NAME_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allow unlimited image name changing')],
                    ['IMAGE_MOVE_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allow unlimited image moving')],
                    ['IMAGE_DELETE_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allow unlimited image deletion')],
                    ['IMAGE_INCREMENT_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allow unlimited image version incrementation')],
                    ['GALLERY_GET_ALL_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allows getting all galleries (and each individual one)')],
                    ['GALLERY_CREATE_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allow unlimited gallery creation')],
                    ['GALLERY_UPDATE_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allow unlimited gallery updating - includes adding/removing media to/from gallery')],
                    ['GALLERY_DELETE_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allow unlimited gallery deletion')],
                    ['MEDIA_FOLDER_CREATE_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allows creating media folders')],
                    ['ORDERS_VIEW_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allows viewing all orders')],
                    ['ORDERS_MODIFY_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allow modyfing all orders')],
                    ['USERS_ORDERS_VIEW_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allow viewing all user-order relations')],
                    ['USERS_ORDERS_MODIFY_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allow modifying all user-order relations')],
                    ['OBJECT_AUTH_VIEW',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allows viewing all object action auth')],
                    ['OBJECT_AUTH_MODIFY',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allow modyfing all object action auth')],
                    ['ARTICLES_MODIFY_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allow modyfing all articles')],
                    ['ARTICLES_VIEW_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allows viewing all articles')],
                    ['ARTICLES_CREATE_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allows creating new articles')],
                    ['ARTICLES_UPDATE_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allows updating all articles')],
                    ['ARTICLES_DELETE_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allows deleting all articles')],
                    ['ARTICLES_BLOCKS_ASSUME_SAFE',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allow inserting potentially "unsafe" content into articles.')],
                    ['CAN_ACCESS_CP',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allows accessing the control panel even when not an admin')],
                    ['CAN_UPDATE_SYSTEM',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allows updating the system even when not an admin')],
                    ['INVITE_USERS_AUTH',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allows inviting users - either via mail, or by just creating invites')],
                    ['SET_INVITE_MAIL_ARGS',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Allows passing invite mail arguments')],
                    ['TAGS_SET',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to modify tags')],
                    ['TAGS_DELETE',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to delete tags')],
                    ['TAGS_GET_ADMIN_PARAMS',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Get admin params when getting tags')],
                    ['LANGUAGE_OBJECTS_SET',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to modify language objects')],
                    ['LANGUAGE_OBJECTS_DELETE',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to delete language objects')],
                    ['LANGUAGE_OBJECTS_GET_ADMIN_PARAMS',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Get unparsed language objects')],
                    ['LOGS_VIEW',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to view logs')],
                    ['REPORTING_VIEW_GROUPS',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to view reporting groups')],
                    ['REPORTING_SET_GROUPS',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to add / modify reporting groups')],
                    ['REPORTING_REMOVE_GROUPS',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to remove reporting groups')],
                    ['REPORTING_VIEW_GROUP_USERS',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to view reporting group users')],
                    ['REPORTING_MODIFY_GROUP_USERS',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to add / remove reporting group users')],
                    ['REPORTING_VIEW_RULES',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to view reporting rules')],
                    ['REPORTING_SET_RULES',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to add / modify reporting rules')],
                    ['REPORTING_REMOVE_RULES',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to remove reporting rules')],
                    ['REPORTING_VIEW_RULE_GROUPS',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to view rule groups and group rules')],
                    ['REPORTING_MODIFY_RULE_GROUPS',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to add / remove reporting rules to / from groups')],
                    ['MAILING_VIEW_MAILING_LISTS',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to view mailing lists')],
                    ['MAILING_SET_MAILING_LISTS',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to add / modify mailing lists, including setting the template')],
                    ['MAILING_REMOVE_MAILING_LISTS',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to remove mailing lists')],
                    ['MAILING_SEND_TO_MAILING_LIST',\IOFrame\Util\SafeSTRFunctions::str2SafeStr('Required to begin mass email sending to mailing list')],
                ];

                foreach($assignments as $k=>$v){
                    $assignments[$k][0] = [$v[0],'STRING'];
                    $assignments[$k][1] = [$v[1],'STRING'];
                }

                $authInit = $SQLManager->insertIntoTable($SQLManager->getSQLPrefix().'ACTIONS_AUTH',$columns,$assignments,['onDuplicateKey'=>true,'test'=>$test,'verbose'=>false]);

                if($authInit) {
                    if($verbose)
                        echo EOL.'Default Actions initiated!' . EOL;
                }
                else{
                    if($verbose)
                        echo EOL.'Default Actions NOT initiated properly! Please initiate the database properly.'.EOL;
                    return 'actions-auth-population';
                }
            }

            if(!empty($tables['ipSecurity']['_init'])){

                // INITIALIZE IP_LIST
                /* This table is for storing the list of white/black listed IPs
                 * IP - Either an IPV4 or IPV6 address - as a string
                 * IP_Type - Boolean, where FALSE = Blacklisted, TRUE = Whitelisted
                 * Is_Reliable - Whether we were 100% sure the IP was controlled by the user, or it was possibly spoofed
                 * Expires - the date until the effect should last. Stored in Unix timestamp, usually.
                 * Meta -  Currently unused, reserved for later
                 */
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."IP_LIST (
                                                              IP varchar(45) NOT NULL,
                                                              Is_Reliable BOOLEAN NOT NULL,
                                                              IP_Type BOOLEAN NOT NULL,
                                                              Expires varchar(14) NOT NULL,
                                                              Meta varchar(10000) DEFAULT NULL,
                                                              PRIMARY KEY (IP),
                                                              INDEX (IP_Type,Expires)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "IP_LIST table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "IP_LIST table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'ip-list-structure';
                }


                /* This table is for storing the list of RANGES of white/black listed IPs
                 * IP_Type - Boolean, where FALSE = Blacklisted, TRUE = Whitelisted
                 * Prefix - Varchar(11), the prefix for the banned/allowed range. It is at most "xxx.xxx.xxx", which is 11 chars.
                 * IP_From/IP_To - Range of affected IPs with the matching prefix. Tinyints, unsigned.
                 * Expires - the date until the effect should last. Stored in Unix timestamp, usually.
                 */
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."IPV4_RANGE (
                                                              IP_Type BOOLEAN NOT NULL,
                                                              Prefix Varchar(11) NOT NULL,
                                                              IP_From TINYINT UNSIGNED NOT NULL,
                                                              IP_To TINYINT UNSIGNED NOT NULL,
                                                              Expires varchar(14) NOT NULL,
                                                              PRIMARY KEY (Prefix, IP_From, IP_To),
                                                              INDEX (Expires)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "IPV4_RANGE table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "IPV4_RANGE table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'ipv4-range-structure';
                }

                /* This table is for storing the list of (probably suspicious) events per IP
                 * IP                 - The IP.
                 * Event_Type        - A code for the event. Codes are set by the framework user, and vary per app.
                 *                      Unsigned BIGINT, as I can think of use cases requiring it to be this big
                 * Sequence_Start_Time- The date at which this specific event sequence started.
                 * Sequence_Count     - Number of events in this sequence. Any additional event before "Sequence_Expires"
                 *                      Increases this count (and probably prolongs Sequence_Expires)
                 * Sequence_Expires   - The time when current event aggregation sequence expires, and a new one may begin.
                 * Sequence_Limited_Until - The time until which the current sequence should be considered limited (action shouldn't be allowed to be repeated)
                 * Meta               - At the moment, used to store the full IP array as a CSV.
                 */
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."IP_EVENTS (
                                                              IP varchar(45) NOT NULL,
                                                              Event_Type BIGINT UNSIGNED NOT NULL,
                                                              Sequence_Expires varchar(14) NOT NULL,
                                                              Sequence_Start_Time varchar(14) NOT NULL,
                                                              Sequence_Count BIGINT UNSIGNED NOT NULL,
                                                              Sequence_Limited_Until varchar(14) DEFAULT NULL,
                                                              Meta varchar(10000) DEFAULT NULL,
                                                              PRIMARY KEY (IP,Event_Type,Sequence_Start_Time),
                                                              UNIQUE INDEX (IP,Event_Type,Sequence_Expires)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "IP_EVENTS table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "IP_EVENTS table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'ip-events-structure';
                }
            }


            if(!empty($tables['securityEvents']['_init'])){

                /* This table is for storing the list of (probably suspicious) events per User
                 * ID                 - User ID
                 * Event_Type        - A code for the event. Codes are set by the framework user, and vary per app.
                 *                      Unsigned BIGINT, as I can think of use cases requiring it to be this big
                 * Sequence_Start_Time- The date at which this specific event sequence started.
                 * Sequence_Count     - Number of events in this sequence. Any additional event before "Sequence_Expires"
                 *                      Increases this count (and probably prolongs Sequence_Expires)
                 * Sequence_Expires   - The time when current event aggregation sequence expires, and a new one may begin.
                 * Sequence_Limited_Until - The time until which the current sequence should be considered limited (action shouldn't be allowed to be repeated)
                 */
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."USER_EVENTS (
                                                              ID int NOT NULL,
                                                              Event_Type BIGINT UNSIGNED NOT NULL,
                                                              Sequence_Expires varchar(14) NOT NULL,
                                                              Sequence_Start_Time varchar(14) NOT NULL,
                                                              Sequence_Count BIGINT UNSIGNED NOT NULL,
                                                              Sequence_Limited_Until varchar(14) DEFAULT NULL,
                                                              PRIMARY KEY (ID,Event_Type,Sequence_Start_Time),
                                                              UNIQUE INDEX (ID,Event_Type,Sequence_Expires),
                                                              FOREIGN KEY (ID)
                                                              REFERENCES ".$prefix."USERS(ID)
                                                              ON DELETE CASCADE
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "USER_EVENTS table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "USER_EVENTS table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'user-events-structure';
                }


                /* This table is for simple set of rules to automatically blacklist IPs/Users after they commit a specific
                 * number of specific events, a set number of times. This table also specifies for how much longer to "remember" a
                 * sequence of events after the latest event (well, more specifically - for how long to prolong those "memories"),
                 * and how long the blacklisting lasts depending on number of events in current sequence
                 *
                 * Event_Category    - 0/false for IP, 1/true for User, anything else is reserved for later.
                 * Event_Type        - A code for the event, same as the event tables. The same codes may have different
                 *                      meanings depending on Category (User code 42 is probably not IP code 42).
                 * Sequence_Number    - Number of events in a sequence before the following rules are applied.
                 *                      For example, if event Users/1 has sequence numbers 0 and 5, then there is one rule set
                 *                      for events 0-4 and a different one for events 5+ in the same sequence.
                 * Blacklist_For      - How long (in seconds) to blacklist an IP / mark a user as suspicious for.
                 * Add_TTL            - How many seconds to add to the Sequence_Expires of this sequence.
                 * Meta               - Meta/Extra information about this specific sequence
                 *
                 * Note that there has to be a rule of the form:
                 *      Event_Category | Event_Type | Sequence_Number | Blacklist_For | Add_TTL
                 *    0/IP, 1/User, etc |   <Code>    |         0       |       X       |   X>0
                 * for any specific event sequence to even begin.
                 */
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."EVENTS_RULEBOOK (
                                                              Event_Category INT(32),
                                                              Event_Type INT(32),
                                                              Sequence_Number INT UNSIGNED,
                                                              Blacklist_For INT UNSIGNED,
                                                              Add_TTL INT UNSIGNED,
                                                              Meta TEXT DEFAULT NULL,
                                                              CONSTRAINT not_empty CHECK (NOT (ISNULL(Add_TTL) AND ISNULL(Blacklist_For)) ),
                                                              PRIMARY KEY (Event_Category,Event_Type,Sequence_Number)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "EVENTS_RULEBOOK table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "EVENTS_RULEBOOK table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'events-rulebook-structure';
                }

                /* This table is for meta information regarding event categories and types.
                 *
                 * Event_Category    - event category
                 * Event_Type        - If null, this row is meta information regarding a category, if not - regarding a specific event.
                 * Meta              - Meta/Extra information about this specific item.
                 */
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."EVENTS_META (
                                                              Event_Category INT(32),
                                                              Event_Type INT(32) DEFAULT -1,
                                                              Meta TEXT DEFAULT NULL,
                                                              PRIMARY KEY (Event_Category,Event_Type)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "EVENTS_META table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "EVENTS_META table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'events-meta-structure';
                }


                /** ------------------------------------------ FUNCTIONS ------------------------------------------------
                 * The very few DB functions in the framework.
                 * While it violates my intention to keep all logic on the Application layer, and away from the DB, any other
                 * solution would either create security vulnerabilities or carry an extreme performance penalty.
                 * TODO properly handle Add_Weight that isn't 1, as not to skip various TTL/Blacklist stages
                 */

                /** Create function commitEventIP
                 *
                 * Inputs:
                 *      IP     - IPv4 or IPV6
                 *      Event_Type - An event code as defined in EVENTS_RULEBOOK for an IP
                 *
                 * Changes state:
                 *      Adds the relevant event to IP_EVENTS, or increases an existing session counter/expiry.
                 *      Blacklists an IP if the event count has passed a threshold defined in EVENTS_RULEBOOK.
                 *
                 * Outputs:
                 *      The number of events in the current active sequence (at least 1)
                 */
                $dropFunc = $conn->prepare("DROP FUNCTION IF EXISTS ".$prefix."commitEventIP");
                $makeFunc = $conn->prepare("CREATE FUNCTION ".$prefix."commitEventIP (
            IP VARCHAR(45),
            Event_Type BIGINT(20) UNSIGNED,
            Is_Reliable BOOLEAN,
            Full_IP VARCHAR(10000),
            Add_Weight INT(10) UNSIGNED,
            Blacklist_On_Limit BOOLEAN
            )
            RETURNS INT(20)
            BEGIN
                DECLARE eventCount INT;
                DECLARE Add_TTL INT;
                DECLARE Blacklist_For INT;
                #Either the event sequence already exists, or a new one needs to be created.
                SELECT Sequence_Count INTO eventCount FROM
                           ".$prefix."IP_EVENTS WHERE(
                               ".$prefix."IP_EVENTS.IP = IP AND
                               ".$prefix."IP_EVENTS.Event_Type = Event_Type AND
                               ".$prefix."IP_EVENTS.Sequence_Expires > UNIX_TIMESTAMP()
                           )
                            LIMIT 1;

                #eventCount may be null!
                IF ISNULL(eventCount) THEN
                    SELECT 0 INTO eventCount;
                END IF;

                #Either way we need to know how much TTL/Blacklist to add
                SELECT ".$prefix."EVENTS_RULEBOOK.Add_TTL,".$prefix."EVENTS_RULEBOOK.Blacklist_For INTO Add_TTL,Blacklist_For FROM ".$prefix."EVENTS_RULEBOOK WHERE
                                        Event_Category = 0 AND
                                        ".$prefix."EVENTS_RULEBOOK.Event_Type = Event_Type AND
                                        Sequence_Number<=eventCount ORDER BY Sequence_Number DESC LIMIT 1;

                IF eventCount>0 THEN
                    BEGIN
                        UPDATE ".$prefix."IP_EVENTS SET
                                Sequence_Expires = Sequence_Expires + Add_TTL,
                                Sequence_Count = eventCount + Add_Weight,
                                Meta = Full_IP
                                WHERE
                                   ".$prefix."IP_EVENTS.IP = IP AND
                                   ".$prefix."IP_EVENTS.Event_Type = Event_Type AND
                                   ".$prefix."IP_EVENTS.Sequence_Expires > UNIX_TIMESTAMP();
                    END;
                ELSE
                    BEGIN
                    INSERT INTO ".$prefix."IP_EVENTS (
                        IP,
                        Event_Type,
                        Sequence_Expires,
                        Sequence_Start_Time,
                        Sequence_Count,
                        Meta
                    )
                    VALUES (
                        IP,
                        Event_Type,
                        UNIX_TIMESTAMP()+Add_TTL,
                        UNIX_TIMESTAMP(),
                        Add_Weight,
                        Full_IP
                    )
                     ON DUPLICATE KEY UPDATE Sequence_Count = Sequence_Count+Add_Weight;
                    END;
                END IF;

                #We might need to blacklist the IP
                IF Blacklist_For > 0 THEN
                    IF Blacklist_On_Limit THEN 
                        INSERT INTO ".$prefix."IP_LIST (IP_Type,Is_Reliable,IP,Expires) VALUES (0,Is_Reliable,IP,UNIX_TIMESTAMP()+Blacklist_For)
                        ON DUPLICATE KEY UPDATE Expires = GREATEST(Expires,UNIX_TIMESTAMP()+Blacklist_For);
                    END IF;
                    UPDATE ".$prefix."IP_EVENTS SET
                            Sequence_Limited_Until = IF(ISNULL(Sequence_Limited_Until),UNIX_TIMESTAMP()+Blacklist_For,GREATEST(Sequence_Limited_Until,UNIX_TIMESTAMP()+Blacklist_For))
                            WHERE
                               ".$prefix."IP_EVENTS.IP = IP AND
                               ".$prefix."IP_EVENTS.Event_Type = Event_Type AND
                               ".$prefix."IP_EVENTS.Sequence_Expires > UNIX_TIMESTAMP();
                END IF;

                RETURN eventCount+Add_Weight;
            END");
                try{
                    if(!$test){
                        $dropFunc->execute();
                        $makeFunc->execute();
                    }
                    if($verbose)
                        echo "commitEventIP function created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "commitEventIP function  couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'commit-event-ip-structure';
                }

                /** Create function commitEventUser
                 *
                 * Inputs:
                 *      ID          - User ID as defined in USERS
                 *      Event_Type - An event code as defined in EVENTS_RULEBOOK for a User
                 *
                 * Changes state:
                 *      Adds the relevant event to USER_EVENTS, or increases an existing session counter/expiry.
                 *      Marks a User as Suspicious if the event count has passed a threshold defined in EVENTS_RULEBOOK.
                 *
                 * Outputs:
                 *      The number of events in the current active sequence (at least 1)
                 */
                $dropFunc = $conn->prepare("DROP FUNCTION IF EXISTS ".$prefix."commitEventUser");
                $makeFunc = $conn->prepare("CREATE FUNCTION ".$prefix."commitEventUser (
                ID int(11),
                Event_Type BIGINT(20) UNSIGNED,
                Add_Weight INT(10) UNSIGNED,
                Suspicious_On_Limit BOOLEAN,
                Ban_On_Limit BOOLEAN,
                Lock_On_Limit BOOLEAN
             )
            RETURNS INT(20)
            BEGIN
                DECLARE eventCount INT;
                DECLARE Add_TTL INT;
                DECLARE Blacklist_For INT;
                #Either the event sequence already exists, or a new one needs to be created.
                SELECT Sequence_Count INTO eventCount FROM
                           ".$prefix."USER_EVENTS WHERE(
                               ".$prefix."USER_EVENTS.ID = ID AND
                               ".$prefix."USER_EVENTS.Event_Type = Event_Type AND
                               ".$prefix."USER_EVENTS.Sequence_Expires > UNIX_TIMESTAMP()
                           )
                            LIMIT 1;

                #eventCount may be null!
                IF ISNULL(eventCount) THEN
                    SELECT 0 INTO eventCount;
                END IF;

                #Either way we need to know how much TTL/Blacklist to add
                SELECT ".$prefix."EVENTS_RULEBOOK.Add_TTL,".$prefix."EVENTS_RULEBOOK.Blacklist_For INTO Add_TTL,Blacklist_For FROM ".$prefix."EVENTS_RULEBOOK WHERE
                                        Event_Category = 1 AND
                                        ".$prefix."EVENTS_RULEBOOK.Event_Type = Event_Type AND
                                        Sequence_Number<=eventCount ORDER BY Sequence_Number DESC LIMIT 1;

                IF eventCount>0 THEN
                    BEGIN
                        UPDATE ".$prefix."USER_EVENTS SET
                                Sequence_Expires = Sequence_Expires + Add_TTL,
                                Sequence_Count = eventCount + Add_Weight
                                WHERE
                                    ".$prefix."USER_EVENTS.ID = ID AND
                                    ".$prefix."USER_EVENTS.Event_Type = Event_Type AND
                                    ".$prefix."USER_EVENTS.Sequence_Expires > UNIX_TIMESTAMP();

                    END;
                ELSE
                    BEGIN
                    INSERT INTO ".$prefix."USER_EVENTS (
                        ID,
                        Event_Type,
                        Sequence_Expires,
                        Sequence_Start_Time,
                        Sequence_Count
                    )
                    VALUES (
                        ID,
                        Event_Type,
                        UNIX_TIMESTAMP()+Add_TTL,
                        UNIX_TIMESTAMP(),
                        Add_Weight
                    );
                    END;
                END IF;

                #We might need to blacklist the USER
                IF Blacklist_For > 0 THEN
                    IF Suspicious_On_Limit THEN 
                        UPDATE ".$prefix."USERS_EXTRA SET
                                Suspicious_Until = IF(ISNULL(Suspicious_Until),UNIX_TIMESTAMP()+Blacklist_For,GREATEST(Suspicious_Until,UNIX_TIMESTAMP()+Blacklist_For))
                                WHERE
                                    ".$prefix."USERS_EXTRA.ID = ID;
                    END IF;
                    IF Ban_On_Limit THEN 
                        UPDATE ".$prefix."USERS_EXTRA SET
                                Banned_Until = IF(ISNULL(Banned_Until),UNIX_TIMESTAMP()+Blacklist_For,GREATEST(Banned_Until,UNIX_TIMESTAMP()+Blacklist_For))
                                WHERE
                                    ".$prefix."USERS_EXTRA.ID = ID;
                    END IF;
                    IF Lock_On_Limit THEN 
                        UPDATE ".$prefix."USERS_EXTRA SET
                                Locked_Until = IF(ISNULL(Locked_Until),UNIX_TIMESTAMP()+Blacklist_For,GREATEST(Locked_Until,UNIX_TIMESTAMP()+Blacklist_For))
                                WHERE
                                    ".$prefix."USERS_EXTRA.ID = ID;
                    END IF;
                    UPDATE ".$prefix."USER_EVENTS SET
                            Sequence_Limited_Until = IF(ISNULL(Sequence_Limited_Until),UNIX_TIMESTAMP()+Blacklist_For,GREATEST(Sequence_Limited_Until,UNIX_TIMESTAMP()+Blacklist_For))
                            WHERE
                                ".$prefix."USER_EVENTS.ID = ID AND 
                                ".$prefix."USER_EVENTS.Event_Type = Event_Type AND 
                                ".$prefix."USER_EVENTS.Sequence_Expires > UNIX_TIMESTAMP();
                END IF;

                RETURN eventCount+Add_Weight;
            END");

                try{
                    if(!$test){
                        $dropFunc->execute();
                        $makeFunc->execute();
                    }
                    if($verbose)
                        echo "commitEventUser function created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "commitEventUser function  couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'commit-event-user-structure';
                }
            }

            if(!empty($tables['securityEvents']['_populate'])){

                $SecurityHandler = new \IOFrame\Handlers\SecurityHandler($localSettings,$defaultSettingsParams);

                //Insert security events TODO use SecurityHandler
                $columns = ['Event_Category','Event_Type','Sequence_Number','Blacklist_For','Add_TTL'];
                $assignments = [
                    [0,0,0,0,8640],
                    [0,0,1,0,0],
                    [0,0,5,60,0],
                    [0,0,7,300,3600],
                    [0,0,8,1200,43200],
                    [0,0,9,3600,86400],
                    [0,0,10,86400,604800],
                    [0,0,11,31557600,31557600],

                    [0,1,0,0,86400],
                    [0,1,1,0,0],
                    [0,1,2,60,0],
                    [0,1,3,3600,0],
                    [0,1,4,86400,2678400],
                    [0,1,5,86400,0],
                    [0,1,10,31557600,31557600],

                    [0,2,0,0,86400],
                    [0,2,1,0,0],
                    [0,2,5,3600,2678400],
                    [0,2,6,3600,3600],
                    [0,2,7,86400,86400],
                    [0,2,8,2678400,2678400],
                    [0,2,9,31557600,31557600],

                    [1,0,0,0,17280],
                    [1,0,1,0,0],
                    [1,0,5,0,86400],
                    [1,0,6,0,0],
                    [1,0,10,0,2678400],
                    [1,0,11,0,0],
                    [1,0,100,2678400,31557600],

                    [1,1,0,60,3600],
                    [1,1,2,600,21600],
                    [1,1,4,3600,86400],
                    [1,1,5,86400,2678400],

                    [1,2,0,60,3600],
                    [1,2,2,600,21600],
                    [1,2,5,1900,86400],
                    [1,2,10,3600,2678400],

                    [1,3,0,60,3600],
                    [1,3,1,60,0],
                    [1,3,10,3600,86400],
                    [1,3,11,3600,0],

                    [1,4,0,10,86400],
                    [1,4,1,60,0],
                    [1,4,3,180,86400],
                    [1,4,4,3600,2678400],
                    [1,4,5,3600,0],
                ];

                $rulebookInit = $SQLManager->insertIntoTable($SQLManager->getSQLPrefix().'EVENTS_RULEBOOK',$columns,$assignments,['onDuplicateKey'=>true,'test'=>$test,'verbose'=>false]);

                if($rulebookInit) {
                    if($verbose)
                        echo EOL.'Events Rulebook initiated!' . EOL;
                }
                else{
                    if($verbose)
                        echo EOL.'Events Rulebook NOT initiated properly! Please initiate the database properly.'.EOL;
                    return 'events-rulebook-population';
                }

                $rulebookMetaInit = $SecurityHandler->setEventsMeta(
                    [
                        [
                            'category'=>0,
                            'type'=>0,
                            'meta'=>json_encode([
                                'name'=>'IP Incorrect Login Limit'
                            ])
                        ],
                        [
                            'category'=>0,
                            'type'=>1,
                            'meta'=>json_encode([
                                'name'=>'IP Request Reset Mail Limit'
                            ])
                        ],
                        [
                            'category'=>0,
                            'type'=>2,
                            'meta'=>json_encode([
                                'name'=>'IP Forbidden Action Repeat Limit'
                            ])
                        ],
                        [
                            'category'=>0,
                            'type'=>3,
                            'meta'=>json_encode([
                                'name'=>'IP Registration Limit'
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
                            'category'=>1,
                            'type'=>1,
                            'meta'=>json_encode([
                                'name'=>'User Registration Confirmation Mail Limit'
                            ])
                        ],
                        [
                            'category'=>1,
                            'type'=>2,
                            'meta'=>json_encode([
                                'name'=>'User Reset Mail Limit'
                            ])
                        ],
                        [
                            'category'=>1,
                            'type'=>3,
                            'meta'=>json_encode([
                                'name'=>'User 2FA Mail Limit'
                            ])
                        ],
                        [
                            'category'=>1,
                            'type'=>4,
                            'meta'=>json_encode([
                                'name'=>'Incorrect 2FA Code General Limit'
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
                    [
                        'test'=>$test,
                        'verbose'=>false
                    ]
                );
                if($rulebookMetaInit){
                    if($verbose)
                        echo EOL.'Default security events meta information initiated!' . EOL;
                }
                else{
                    if($verbose)
                        echo EOL.'Default security events meta information NOT initiated!'.EOL;
                    return 'events-rulebook-meta-population';
                }
            }

            if(!empty($tables['dbBackupMeta']['_init'])){
                /* This table stores meta information about table backups, the ones performed by SQLManager.
                 * ID - Integer, increases automatically.
                 * Backup_Date - unix timestamp (in seconds) of when the backup occurred
                 * Table_Name - name of the table that was backed up
                 * Full_Name - full filename of the table backup
                 */
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."DB_BACKUP_META(
                                                              ID int PRIMARY KEY NOT NULL AUTO_INCREMENT,
                                                              Backup_Date varchar(14) NOT NULL,
                                                              Table_Name varchar(64) NOT NULL,
                                                              Full_Name varchar(256) NOT NULL,
                                                              Meta TEXT DEFAULT NULL
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "DB_BACKUP_META table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "DB_BACKUP_META table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'db-backup-meta-structure';
                }
            }


            if(!empty($tables['routing']['_init'])){
                // INITIALIZE ROUTING_MAP
                /* This table is meant to serve as a map, based on the documentation of altorouter over at
                 * http://altorouter.com/usage/mapping-routes.html .
                 * Basically, an array of $router->map([...]) is ran at the routing page based on this table.
                 *
                 * ID - int, used for indexing, ordering and caching purposes
                 * Method -  Varchar(256), as in the altorouter documentation .
                 * Route -   Varchar(1024) as in the router documentation. It's large because the named parameters might have long names.
                 *           NOTE - ALL parameters must be named parameters for correct operation (aka instead of [i], write [i:someInt]).
                 * Match_Name - Varchar(64), as in the altorouter documentation .
                 * Map_Name - Varchar(256), as in the altorouter documentation .
                 */
                $query = "CREATE TABLE IF NOT EXISTS ".$prefix."ROUTING_MAP (
                                                              ID varchar(64) PRIMARY KEY NOT NULL,
                                                              Method varchar(256) NOT NULL,
                                                              Route varchar(1024) NOT NULL,
                                                              Match_Name varchar(64) NOT NULL,
                                                              Map_Name varchar(256),
                                                              INDEX (Match_Name)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
                $makeTB = $conn->prepare($query);

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "ROUTING_MAP table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "ROUTING_MAP table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'routing-map-structure';
                }

                // INITIALIZE ROUTING_MATCH
                /*
                /* This table is meant to tell the ROUTING_MAP matches what they should do on match, based on the documentation
                 * of altorouter over at http://altorouter.com/usage/matching-requests.html .
                 *
                 * If $match = $router->match(), then Target is $match['target'].
                 *
                 * -- SIMPLE URL ROUTING --
                 * On match, code akin to
                     1.   $ext = ['php','html','htm'];
                     2.   foreach($ext as $extension){
                     3.       $filename = __DIR__.'/front/'.$routeParams['trailing'].'.'.$extension;
                     4.       if((file_exists($filename))){
                     5.           require $filename;
                     6.           return;
                     7.       }
                     8.   }
                 * will be executed (can be found in index.php).
                 * $ext is determined by a comma separated array stored at Extensions, and defaults to the above.
                 * $filename will always start with __DIR__ (as routing is relative to the root), but URL is built as following:
                 *  The requested URL is stored at the URL column, and has to be a valid URL (no double "/"'s, either).
                 *  However, the URL may contain named parameters of the form '[paramName]'
                 *  Every named parameter is at construction replaced with $routeParams[<paramName>].
                 *
                 *  So in order to get the URL in the example, your URL should be:
                 *  "/front/[trailing]".
                 *  Finally, the $extension will be appended in order it appears in Extensions (or default, if Extensions is null).
                 *
                 * -- ADVANCED URL ROUTING (Exclusion)--
                 * The code shown above is actually a simplified version of the real thing.
                 * In reality, the URL may be an object (assoc array) of the form:
                 * [
                 *  'include' => '<Include Path Like In The Basic Example>'
                 *  'exclude' => <Array of regex patterns to forbid matching with>
                 * ]
                 * What happens here is that basically, if a match is found, the path is then checked against each of the
                 * exclusion regex patterns, and if it matches one of them, the match is considered invalid.
                 * This is useful for forbidding specific folders/files that would otherwise be included and avoid
                 * server restrictions like .htaccess.
                 *
                 * -- ADVANCED URL ROUTING (Multiple Possible Matches)--
                 * The URL may also be an array of strings, or of inclusion/exclusion objects.
                 * It may also contain a mix between the two.
                 * What then happens is that the router will match to the first valid match out of the possible ones.
                 * If multiple ones are possible, the chronologically first one will be matched.
                 * This is useful for setting up multiple backup matches (for example, if your front end modules / api may
                 * sit in two potential locations, you may try to match the first one, then the 2nd).
                 *
                 *  --- COMPLEX ROUTING ---
                 *  What to do if you want to do some complex logic based on the named parameters?
                 *  Well, you can always create a PHP script, and route to it, then have it do your complex logic, either
                 *  using the parameters for computation (e.g REST API) or using them to route farther (insert Yo Dawg meme).
                 *  In case you want to disable access to such files directly, I define a magic constant
                 *  REQUEST_PASSED_THROUGH_ROUTER = true on top of index.php . Check for it, or other indicators, on those PHP pages.
                 *  .. or just put them in a folder with an .htaccess blocking web requests.
                 *
                 * Match_Name - varchar 64, name of the matched event - corresponds to Match_Name in the other ROUTING_MAP.
                 * URL - varchar 1024, Explained above. Is this long because of possible long parameter names.
                 * Extensions - varchar 256, CSV string of extensions - WITHOUT THE DOT! (aka php,html and not .php,.html)
                 *
                 */
                $query = "CREATE TABLE IF NOT EXISTS ".$prefix."ROUTING_MATCH (
                                                              Match_Name varchar(64) PRIMARY KEY NOT NULL,
                                                              URL varchar(1024) NOT NULL,
                                                              Extensions varchar(256),
                                                              Match_Partial_URL boolean DEFAULT false NOT NULL
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
                $makeTB = $conn->prepare($query);
                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "ROUTING_MATCH table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "ROUTING_MATCH table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'routing-match-structure';
                }

            }

            if(!empty($tables['routing']['_populate'])){
                //Insert routing rules
                $RouteHandler = new \IOFrame\Handlers\RouteHandler($localSettings,$defaultSettingsParams);

                $routesAdded = $RouteHandler->setRoutes(
                    [
                        'default-api'=>['GET|POST|PUT|DELETE|PATCH|HEAD','api/[*:trailing]','api',null],
                        'default-front'=>['GET|POST','[*:trailing]','front',null]
                    ],
                    [
                        'test'=>$test,
                        'verbose'=>false
                    ]
                );

                if(($routesAdded['default-api'] === 0) && ($routesAdded['default-front'] === 0)){
                    if($verbose)
                        echo EOL.'Default Routes initiated!' . EOL;
                }
                else{
                    if($verbose)
                        echo EOL.'Default Routes NOT initiated properly!'.json_encode($routesAdded).EOL;
                    return 'routing-map-population';
                }


                $matches = $RouteHandler->setMatches(
                    [
                        'front'=>['front/ioframe/pages/[trailing]', 'php,html,htm',true],
                        'api'=>['api/[trailing]','php',true]
                    ],
                    [
                        'test'=>$test,
                        'verbose'=>false
                    ]
                );

                if($matches['front']===0 && $matches['api']===0 ){
                    if($verbose)
                        echo EOL.'Default Matches initiated!' . EOL;
                }
                else{
                    if($verbose)
                        echo EOL.'Default Matches NOT initiated properly!'.EOL;
                    return 'routing-match-structure';
                }
            }

            if(!empty($tables['tokens']['_init'])){

                /* This table is meant to be the default table for tokens in IOFrame.
                 * Tokens are used for a variaty of things, such as account activation, password resets, and more.
                 *
                 * Token        -   Varchar(256), primary identifier. Is the token.
                 * Token_Action -   Varchar(1024), The action of the token. Should describe the purpose of the token, for example
                 *                  ACCOUNT_ACTIVATION_5 (account activation of user with ID 5), but could be anything - like a link
                 *                  to a resource. The function that requests the token can decide what to do with it on match.
                 * Uses_Left    -   int, Number of uses left for the token.
                 *                  For a single use token may be 1, but not all tokens are single use...
                 * Tags         -   Used for token tags
                 * Expires      -   Varchar(14), UNIX timestamp of when the token expires. Every token has to expire, by nature.
                 * Session_Lock -   Varchar(256), Since operations with tokens are meant to be atomic, there has to be a
                 *                  way to prevent 2 sessions querying a token at the same time from "consuming" it twice
                 *                  (or returning info about it while it's being "consumed").
                 *                  With this field, a session first sets a lock for the token, then queries again to see
                 *                  if it "got" the token. Once a "winning" session is done with the token,
                 *                  it sets the lock to NULL. The timing is managed by the DB.
                 * Locked_At     -  Varchar(14), UNIX timestamp. Meant meant signify when a lock was created, so locks that
                 *                  are too old may be removed (as it's probably due to a crash).
                 */
                $query = "CREATE TABLE IF NOT EXISTS ".$prefix."IOFRAME_TOKENS (
                                                              Token varchar(256) PRIMARY KEY NOT NULL,
                                                              Token_Action varchar(1024) NOT NULL,
                                                              Uses_Left BIGINT NOT NULL,
                                                              Tags  varchar(1024) DEFAULT NULL,
                                                              Expires varchar(14) NOT NULL,
                                                              Session_Lock varchar(256) DEFAULT NULL,
                                                              Locked_At varchar(14) DEFAULT NULL,
                                                              INDEX (Tags),
                                                              INDEX (Expires),
                                                              INDEX (Token_Action)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
                $makeTB = $conn->prepare($query);

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "Default Token table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "Default Token table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'default-tokens-structure';
                }
            }


            if(!empty($tables['menus']['_init'])){

                /* This table is the default table for dynamic menus.
                 *
                 * Menu_ID        -   Varchar(256), primary identifier
                 * Title        - varchar(1024), title of the menu.
                 * Menu_Value    -  TEXT JSON encoded menu, of the required form
                 * Meta    -  TEXT JSON encoded meta information about the menu
                 * Created      -   Varchar(14), UNIX timestamp of when the menu was created.
                 * Last_Updated      -   Varchar(14), UNIX timestamp of when the menu was last changed.
                 */
                $query = "CREATE TABLE IF NOT EXISTS ".$prefix."MENUS (
                                                              Menu_ID varchar(256) PRIMARY KEY NOT NULL,
                                                              Title varchar(1024) DEFAULT NULL,
                                                              Menu_Value TEXT DEFAULT NULL,
                                                              Meta TEXT DEFAULT NULL,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
                $makeTB = $conn->prepare($query);

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "DEFAULT_MENUS table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "DEFAULT_MENUS table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'menus-structure';
                }
            }

            if(!empty($tables['resources']['_init'])){

                /* This table stores resource information.
                 * Resource_Type-   Varchar(64), should be 'image', 'js', 'css', 'text' or 'blob' currently.
                 * Address      -   Varchar(512), Address of the resource - from relevant folder root if local, or full URI if
                 *                  not local. By default it should include file extension for local files.
                 * Resource_Local -   Boolean, default true - whether the resource should be treated as a local one.
                 * Minified_Version - Boolean, default false - Whether you can get a minified version of the resource.
                 *                    Rules of how to handle it are defined by the handler.
                 * Version      -   int, default 1. meant for versioning purposes. Is incremented by the user.
                 * Created      -   Varchar(14), UNIX timestamp of when the resource was added.
                 * Last_Updated -   Varchar(14), UNIX timestamp of when the resource was last changed (just in the DB).
                 * Text_Content -   Space for general text content.
                 * Blob_Content -   Space for general blob content.
                 * Data_Type    -   In case of binary content, this is used to save the type (e.g "application/pdf")
                 */
                $query = "CREATE TABLE IF NOT EXISTS ".$prefix."RESOURCES (
                                                              Resource_Type varchar(64),
                                                              Address varchar(512),
                                                              Resource_Local BOOLEAN NOT NULL,
                                                              Minified_Version BOOLEAN NOT NULL,
                                                              Version int DEFAULT 1 NOT NULL,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
                                                              Text_Content TEXT,
                                                              Blob_Content LONGBLOB,
                                                              Data_Type varchar(512) DEFAULT NULL,
                                                               PRIMARY KEY(Resource_Type, Address),
                                                              INDEX (Resource_Local),
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
                $makeTB = $conn->prepare($query);

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "RESOURCE_TABLE created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "RESOURCE_TABLE couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'resources-structure';
                }


                /* This table stores resource collections.
                 *
                 * Collection_Name -   Varchar(128), Name of the collection
                 * Collection_Order-   TEXT, Reserved for order a collection might have.
                 * Resource_Type-   Varchar(64), should be 'image', 'js', 'css', 'text' or 'blob' currently.
                 * Created      -   Varchar(14), UNIX timestamp of when the collection was added.
                 * Last_Updated -   Varchar(14), UNIX timestamp of when the collection (or any of its memebers was last changed.
                 */
                $query = "CREATE TABLE IF NOT EXISTS ".$prefix."RESOURCE_COLLECTIONS (
                                                              Resource_Type varchar(64),
                                                              Collection_Name varchar(128),
                                                              Collection_Order TEXT DEFAULT NULL,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
                                                              Meta TEXT,
                                                               PRIMARY KEY(Resource_Type, Collection_Name),
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
                $makeTB = $conn->prepare($query);

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "RESOURCE_COLLECTIONS table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "RESOURCE_COLLECTIONS table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'resource-collections-structure';
                }


                /* Many to many table of resource collections to resources.
                 *
                 * Resource_Type-   Varchar(64), should be 'image', 'js', 'css', 'text' or 'blob' currently.
                 * Collection_Name -   Varchar(128), Name of the collection
                 * Address      -   Varchar(512), Address of the resource.
                 */
                $query = "CREATE TABLE IF NOT EXISTS ".$prefix."RESOURCE_COLLECTIONS_MEMBERS (
                                                              Resource_Type varchar(64) NOT NULL,
                                                              Collection_Name varchar(128) NOT NULL,
                                                              Address varchar(512) NOT NULL,
                                                              FOREIGN KEY (Resource_Type, Collection_Name)
                                                              REFERENCES ".$prefix."RESOURCE_COLLECTIONS(Resource_Type, Collection_Name)
                                                              ON DELETE CASCADE ON UPDATE CASCADE,
                                                              FOREIGN KEY (Resource_Type, Address)
                                                              REFERENCES ".$prefix."RESOURCES(Resource_Type, Address)
                                                              ON DELETE CASCADE ON UPDATE CASCADE,
                                                              PRIMARY KEY (Resource_Type, Collection_Name, Address)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
                $makeTB = $conn->prepare($query);
                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "RESOURCE_COLLECTION members table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "RESOURCE_COLLECTION members table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'resource-collections-members-structure';
                }
            }

            if(!empty($tables['resources']['_populate'])){
                $FrontEndResources = new \IOFrame\Handlers\Extenders\FrontEndResources($localSettings,$defaultSettingsParams);
                $resourceCreation = $FrontEndResources->setResources(
                    [
                        ['address'=>'sec/aes.js'],
                        ['address'=>'sec/mode-ecb.js'],
                        ['address'=>'sec/mode-ctr.js'],
                        ['address'=>'sec/pad-ansix923-min.js'],
                        ['address'=>'sec/pad-zeropadding.js'],
                        ['address'=>'utils.js'],
                        ['address'=>'initPage.js'],
                        ['address'=>'objects.js'],
                        ['address'=>'fp.js'],
                        ['address'=>'ezAlert.js']
                    ],
                    'js',
                    [
                        'test'=>$test,
                        'verbose'=>false
                    ]
                );
                foreach($resourceCreation as $res)
                    if($res === -1){
                        if($verbose)
                            echo EOL.'Resource creation failed, could not connect to db!'.EOL;
                        return 'resources-js-population';
                    }

                $collectionCreation = $FrontEndResources->setJSCollection(
                    'IOFrameCoreJS',
                    null,
                    [
                        'test'=>$test,
                        'verbose'=>false
                    ]
                );
                if($collectionCreation === -1){
                    if($verbose)
                        echo EOL.'Resource collection creation failed, could not connect to db!'.EOL;
                    return 'resources-js-collection-population';
                }

                $collectionInit = $FrontEndResources->addJSFilesToCollection(
                    ['sec/aes.js','sec/mode-ecb.js','sec/mode-ctr.js','sec/pad-ansix923-min.js','sec/pad-zeropadding.js',
                        'utils.js','initPage.js','fp.js','ezAlert.js'],
                    'IOFrameCoreJS',
                    [
                        'test'=>$test,
                        'verbose'=>false
                    ]
                );
                foreach($collectionInit as $res)
                    if($res === -1){
                        if($verbose)
                            echo EOL.'Resource collection population failed, could not connect to db!'.EOL;
                        return 'resources-js-collection-addition-population';
                    }


                if($verbose)
                    echo EOL.'Resources created!'.EOL;
            }


            if(!empty($tables['tags']['_init'])){

                /* Tag_Type - Type of tag. Differentiates different types of tags, which have different uses
                 * Tag_Name - the identifier of the tag. A short string.
                 * Resource_Type - same as RESOURCES, but meant to be 'img', as an optional image for the tag
                 * Resource_Address - same as RESOURCES
                 * Meta - Potential json for extra information
                 * Weight - used to potentially sort the tags - could be set manually, or via an algorithm
                 */
                $foreignKey = 'FOREIGN KEY (Resource_Type,Resource_Address)
                      REFERENCES '.$prefix.'RESOURCES(Resource_Type,Address)
                      ON UPDATE CASCADE ON DELETE SET NULL,';
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."TAGS(
                                                          Tag_Type varchar(64),
                                                          Tag_Name varchar(64),
                                                          Resource_Type varchar(64) DEFAULT 'img',
                                                          Resource_Address varchar(512) DEFAULT NULL,
                                                          Meta TEXT DEFAULT NULL,
                                                          Weight int NOT NULL DEFAULT 0,
                                                          Created varchar(14) NOT NULL DEFAULT 0,
                                                          Last_Updated varchar(14) NOT NULL DEFAULT 0,
                                                          PRIMARY KEY (Tag_Type,Tag_Name),
                                                          ".(!empty($tables['tags']['resources'])?$foreignKey:"")."
                                                          INDEX (Weight),
                                                          INDEX (Created),
                                                          INDEX (Last_Updated)
                                                          ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "TAGS table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "TAGS table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'tags-structure';
                }

                /* Same as TAGS, but for things that have a category
                 */
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."CATEGORY_TAGS(
                                                          Tag_Type varchar(64),
                                                          Category_ID int,
                                                          Tag_Name varchar(64),
                                                          Resource_Type varchar(64) DEFAULT 'img',
                                                          Resource_Address varchar(512) DEFAULT NULL,
                                                          Meta TEXT DEFAULT NULL,
                                                          Weight int NOT NULL DEFAULT 0,
                                                          Created varchar(14) NOT NULL DEFAULT 0,
                                                          Last_Updated varchar(14) NOT NULL DEFAULT 0,
                                                          PRIMARY KEY (Tag_Type,Category_ID,Tag_Name),
                                                          ".(!empty($tables['tags']['resources'])?$foreignKey:"")."
                                                          INDEX (Weight),
                                                          INDEX (Created),
                                                          INDEX (Last_Updated)
                                                          ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "CATEGORY_TAGS table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "CATEGORY_TAGS table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'category-tags-structure';
                }
            }

            if(!empty($tables['language']['_init'])){
                /* Object_Name - Name of the object
                 * Object - JSON encoded object
                 */
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."LANGUAGE_OBJECTS(
                                                          Object_Name varchar(128) PRIMARY KEY,
                                                          Object TEXT NOT NULL,
                                                          Created varchar(14) NOT NULL DEFAULT 0,
                                                          Last_Updated varchar(14) NOT NULL DEFAULT 0,
                                                          INDEX (Created),
                                                          INDEX (Last_Updated)
                                                          ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "LANGUAGE_OBJECTS table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "LANGUAGE_OBJECTS table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'language-objects-structure';
                }
            }


            if(!empty($tables['orders']['_init'])){
                /* This table is meant to be the default table for purchase orders in IOFrame.
                 * Like written in the handler, by themselves orders do not have a meaning, and have to be expanded by the
                 * system that uses them.
                 * The reason that be default information is not relational, is because orders must persist even when the items
                 * that were ordered are no longer in the system. That is, among other things, in case things like products are
                 * archived, and would otherwise trigger the relational events like Delete, or the foreign key constraints.
                 *
                 * ID           - int, Auto incrementing ID.
                 * Order_Info   - text, NON-SEARCHABLE information about the current state of the order itself. This can include
                 *                stuff like discount, payment method, shipping details, etc.
                 *                However, if - for example - a specific system needs to be able to sort payments by country, then the
                 *                information needs to be duplicated into an additional (indexed) "Country" column, not just placed here.
                 *                Likewise, if there exist relational data like Buyer <=> Order <=> Seller, than such data needs
                 *                to be placed into its own tables, and handled by the class extending "PurchaseOrderHandler".
                 * Order_History- text, NON-SEARCHABLE information about the current state of the order history.
                 *                This is meant to give the admin (and sometimes the customer) the ability to see the history
                 *                of changes made in the order.
                 *                Each order MUST save every change made to it, so every state of the order at any moment in
                 *                time may be restored (important to remember when extending!).
                 *                The format is a JSON encoded array of objects, aka (example):
                 *               [
                 *                  {time:1572192007,delivery:{oldValue:"collectFromStore",newValue:"deliverToAddress"}},
                 *                  {time:1572197764,city:{oldValue:"London",newValue:"Manchester"}},
                 *                  {time:1572197764,street:{oldValue:"Graham",newValue:"Warwick"}},
                 *                  {time:1572197764,houseNum:{oldValue:45,newValue:13}},
                 *                ]
                 * Created   -   Varchar(14), UNIX timestamp of when the order was created.
                 * Last_Updated -   Varchar(14), UNIX timestamp of when the order was last updated.
                 * Session_Lock -   Varchar(256), Same as the tokens table. Orders must function correctly, even at the cost of performance.
                 * Locked_At    -  Varchar(14), UNIX timestamp. Same as tokens table.
                 */
                $query = "CREATE TABLE IF NOT EXISTS ".$prefix."DEFAULT_ORDERS (
                                                              ID int PRIMARY KEY AUTO_INCREMENT,
                                                              Order_Info TEXT DEFAULT NULL,
                                                              Order_History TEXT DEFAULT NULL,
                                                              Order_Type varchar(64) DEFAULT NULL,
                                                              Order_Status varchar(64) DEFAULT NULL,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
                                                              Session_Lock varchar(256) DEFAULT NULL,
                                                              Locked_At varchar(14) DEFAULT NULL,
                                                              INDEX (Order_Type),
                                                              INDEX (Order_Status),
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
                $makeTB = $conn->prepare($query);

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "DEFAULT_ORDERS table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "DEFAULT_ORDERS table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'default-orders-structure';
                }


                // INITIALIZE DEFAULT_USERS_ORDERS Table
                /* This table is the complement table for DEFAULT_ORDERS - it's a many-to-many table binding users to orders.
                 * It is important to note that even if an order is unbound from a user (for example its creator), the fact that
                 * it was initially bound to that user would still be saved in the order Info and/or History.
                 *
                 * User_ID      - int, User ID
                 * Order_ID     - int, User ID
                 * Relation_Type- varchar(256), type of relationship between the user and order. By default, it is empty, but
                 *                different systems may have many different relationship types for this table (for example,
                 *                a user may be either a buyer or a seller for an order)
                 * Meta         - text, NOT SEARCHABLE meta information about the relationship. Generally a JSON encoded string
                 *                with system specific logic.
                 * Created   - Varchar(14), UNIX timestamp of when the relationship was created.
                 * Last_Updated - Varchar(14), UNIX timestamp of when the relationship was last updated.
                 */
                $foreignKey = 'FOREIGN KEY (User_ID)
                       REFERENCES '.$prefix.'USERS(ID)
                       ON DELETE CASCADE,';
                $query = "CREATE TABLE IF NOT EXISTS ".$prefix."DEFAULT_USERS_ORDERS (
                                                              User_ID int NOT NULL,
                                                              Order_ID int NOT NULL,
                                                              Relation_Type varchar(256) DEFAULT NULL,
                                                              Meta TEXT DEFAULT NULL,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
                                                              PRIMARY KEY (Order_ID, User_ID),
                                                              ".(!empty($tables['orders']['users'])?$foreignKey:"")."
                                                              FOREIGN KEY (Order_ID)
                                                              REFERENCES ".$prefix."DEFAULT_ORDERS(ID)
                                                              ON DELETE CASCADE,
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
                $makeTB = $conn->prepare($query);

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "DEFAULT_USERS_ORDERS table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "DEFAULT_USERS_ORDERS table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'default-users-orders-structure';
                }
            }

            if(!empty($tables['objectAuth']['_init'])){
                /* ------------------------------------------------------------
                 * ObjectAuth related tables - quite a few
                 * ------------------------------------------------------------
                */

                /* INITIALIZE OBJECT_AUTH_CATEGORIES Table
                 *
                 * Object_Auth_Category     - varchar(128), ID.
                 * Title        - varchar(1024), title of the category.
                 * Created   -   varchar(14), UNIX timestamp of when the order was created.
                 * Last_Updated -   varchar(14), UNIX timestamp of when the order was last updated.
                 */
                $query = "CREATE TABLE IF NOT EXISTS ".$prefix."OBJECT_AUTH_CATEGORIES (
                                                              Object_Auth_Category varchar(128) PRIMARY KEY,
                                                              Title varchar(1024) DEFAULT NULL,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
                                                              INDEX (Title),
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
                $makeTB = $conn->prepare($query);

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "OBJECT_AUTH_CATEGORIES table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "OBJECT_AUTH_CATEGORIES table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'object-auth-structure';
                }

                /* INITIALIZE OBJECT_AUTH_OBJECTS Table
                 * Object_Auth_Category     - varchar(128), ID.
                 * Object_Auth_Object       - varchar(1024), identifier of object.
                 * Title        - varchar(1024), title of the object.
                 * Is_Public       - bool, default null - whether the object is public. NULL is the default, and depends on business logic.
                 * Created   -   varchar(14), UNIX timestamp of when the order was created.
                 * Last_Updated -   varchar(14), UNIX timestamp of when the order was last updated.
                 */
                $query = "CREATE TABLE IF NOT EXISTS ".$prefix."OBJECT_AUTH_OBJECTS (
                                                              Object_Auth_Category varchar(128) NOT NULL,
                                                              Object_Auth_Object varchar(256) NOT NULL,
                                                              Title varchar(1024) DEFAULT NULL,
                                                              Is_Public BOOLEAN DEFAULT FALSE,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
                                                              PRIMARY KEY(Object_Auth_Category, Object_Auth_Object),
                                                              FOREIGN KEY (Object_Auth_Category)
                                                              REFERENCES ".$prefix."OBJECT_AUTH_CATEGORIES(Object_Auth_Category)
                                                              ON DELETE CASCADE,
                                                              INDEX (Title),
                                                              INDEX (Is_Public),
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
                $makeTB = $conn->prepare($query);

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "OBJECT_AUTH_OBJECTS table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "OBJECT_AUTH_OBJECTS table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'object-auth-objects-structure';
                }

                /* INITIALIZE OBJECT_AUTH_ACTIONS Table
                 * Object_Auth_Category     - varchar(128), ID.
                 * Object_Auth_Action       - varchar(1024), identifier of object.
                 * Title        - varchar(1024), title of the object.
                 * Created   -   varchar(14), UNIX timestamp of when the order was created.
                 * Last_Updated -   varchar(14), UNIX timestamp of when the order was last updated.
                 */
                $query = "CREATE TABLE IF NOT EXISTS ".$prefix."OBJECT_AUTH_ACTIONS (
                                                              Object_Auth_Category varchar(128) NOT NULL,
                                                              Object_Auth_Action varchar(256) NOT NULL,
                                                              Title varchar(1024) DEFAULT NULL,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
                                                              PRIMARY KEY(Object_Auth_Category, Object_Auth_Action),
                                                              FOREIGN KEY (Object_Auth_Category)
                                                              REFERENCES ".$prefix."OBJECT_AUTH_CATEGORIES(Object_Auth_Category)
                                                              ON DELETE CASCADE,
                                                              INDEX (Title),
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
                $makeTB = $conn->prepare($query);

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "OBJECT_AUTH_ACTIONS table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "OBJECT_AUTH_ACTIONS table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'object-auth-actions-structure';
                }

                /* INITIALIZE OBJECT_AUTH_GROUPS Table
                 *
                 * Object_Auth_Category     - varchar(128), ID.
                 * Object_Auth_Object       - varchar(1024), identifier of object.
                 * Object_Auth_Group        - int, Auto incrementing ID.
                 * Title        - varchar(1024), title of the category.
                 * Created   -   varchar(14), UNIX timestamp of when the order was created.
                 * Last_Updated -   varchar(14), UNIX timestamp of when the order was last updated.
                 */
                $query = "CREATE TABLE IF NOT EXISTS ".$prefix."OBJECT_AUTH_GROUPS (
                                                              Object_Auth_Category varchar(128) NOT NULL,
                                                              Object_Auth_Object varchar(256) NOT NULL,
                                                              Object_Auth_Group int AUTO_INCREMENT,
                                                              Title varchar(1024) DEFAULT NULL,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
                                                              PRIMARY KEY(Object_Auth_Category, Object_Auth_Object, Object_Auth_Group),
                                                              FOREIGN KEY (Object_Auth_Category, Object_Auth_Object)
                                                              REFERENCES ".$prefix."OBJECT_AUTH_OBJECTS(Object_Auth_Category, Object_Auth_Object)
                                                              ON DELETE CASCADE ON UPDATE CASCADE,
                                                              INDEX(Object_Auth_Group),
                                                              INDEX (Title),
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
                $makeTB = $conn->prepare($query);

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "OBJECT_AUTH_GROUPS table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "OBJECT_AUTH_GROUPS table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'object-auth-groups-structure';
                }

                /* INITIALIZE OBJECT_AUTH_OBJECT_USERS Table
                 *
                 * Object_Auth_Category     - varchar(128), ID.
                 * Object_Auth_Object       - varchar(1024), identifier of object.
                 * ID           - int, Auto incrementing ID.
                 * Created   -   varchar(14), UNIX timestamp of when the order was created.
                 * Last_Updated -   varchar(14), UNIX timestamp of when the order was last updated.
                 */
                $query = "CREATE TABLE IF NOT EXISTS ".$prefix."OBJECT_AUTH_OBJECT_USERS (
                                                              Object_Auth_Category varchar(128) NOT NULL,
                                                              Object_Auth_Object varchar(256) NOT NULL,
                                                              ID int NOT NULL,
                                                              Object_Auth_Action varchar(256) NOT NULL,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
                                                              PRIMARY KEY(Object_Auth_Category, Object_Auth_Object, ID, Object_Auth_Action),
                                                              FOREIGN KEY (Object_Auth_Category, Object_Auth_Object)
                                                              REFERENCES ".$prefix."OBJECT_AUTH_OBJECTS(Object_Auth_Category, Object_Auth_Object)
                                                              ON DELETE CASCADE ON UPDATE CASCADE,
                                                              FOREIGN KEY (Object_Auth_Category, Object_Auth_Action)
                                                              REFERENCES ".$prefix."OBJECT_AUTH_ACTIONS(Object_Auth_Category, Object_Auth_Action)
                                                              ON DELETE CASCADE ON UPDATE CASCADE,
                                                              FOREIGN KEY (ID)
                                                              REFERENCES ".$prefix."USERS(ID)
                                                              ON DELETE CASCADE,
                                                              INDEX (ID,Object_Auth_Object),
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
                $makeTB = $conn->prepare($query);

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "OBJECT_AUTH_OBJECT_USERS table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "OBJECT_AUTH_OBJECT_USERS table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'object-auth-object-users-structure';
                }

                /* INITIALIZE OBJECT_AUTH_OBJECT_GROUPS Table
                 *
                 * Object_Auth_Category     - varchar(128), ID.
                 * Object_Auth_Object       - varchar(1024), identifier of object.
                 * Object_Auth_Group           - int, Auto incrementing ID.
                 * Created   -   varchar(14), UNIX timestamp of when the order was created.
                 * Last_Updated -   varchar(14), UNIX timestamp of when the order was last updated.
                 */
                $query = "CREATE TABLE IF NOT EXISTS ".$prefix."OBJECT_AUTH_OBJECT_GROUPS (
                                                              Object_Auth_Category varchar(128) NOT NULL,
                                                              Object_Auth_Object varchar(256) NOT NULL,
                                                              Object_Auth_Group int NOT NULL,
                                                              Object_Auth_Action varchar(256) NOT NULL,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
                                                              PRIMARY KEY(Object_Auth_Category, Object_Auth_Object, Object_Auth_Group, Object_Auth_Action),
                                                              FOREIGN KEY (Object_Auth_Category, Object_Auth_Object)
                                                              REFERENCES ".$prefix."OBJECT_AUTH_OBJECTS(Object_Auth_Category, Object_Auth_Object)
                                                              ON DELETE CASCADE ON UPDATE CASCADE,
                                                              FOREIGN KEY (Object_Auth_Category, Object_Auth_Action)
                                                              REFERENCES ".$prefix."OBJECT_AUTH_ACTIONS(Object_Auth_Category, Object_Auth_Action)
                                                              ON DELETE CASCADE ON UPDATE CASCADE,
                                                              FOREIGN KEY (Object_Auth_Category, Object_Auth_Object, Object_Auth_Group)
                                                              REFERENCES ".$prefix."OBJECT_AUTH_GROUPS(Object_Auth_Category, Object_Auth_Object, Object_Auth_Group)
                                                              ON DELETE CASCADE,
                                                              INDEX (Object_Auth_Group,Object_Auth_Object),
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
                $makeTB = $conn->prepare($query);

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "OBJECT_AUTH_OBJECT_GROUPS table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "OBJECT_AUTH_OBJECT_GROUPS table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'object-auth-object-groups-structure';
                }

                /* INITIALIZE OBJECT_AUTH_USERS_GROUPS Table
                 *
                 * Object_Auth_Category     - varchar(128), ID.
                 * Object_Auth_Object       - varchar(1024), identifier of object.
                 * Object_Auth_Group           - int, Auto incrementing ID.
                 * ID           - int, Auto incrementing ID.
                 * Created   -   varchar(14), UNIX timestamp of when the order was created.
                 * Last_Updated -   varchar(14), UNIX timestamp of when the order was last updated.
                 */
                $query = "CREATE TABLE IF NOT EXISTS ".$prefix."OBJECT_AUTH_USERS_GROUPS (
                                                              Object_Auth_Category varchar(128) NOT NULL,
                                                              Object_Auth_Object varchar(256) NOT NULL,
                                                              ID int NOT NULL,
                                                              Object_Auth_Group int NOT NULL,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
                                                              PRIMARY KEY(Object_Auth_Category, Object_Auth_Object, ID, Object_Auth_Group),
                                                              FOREIGN KEY (Object_Auth_Category, Object_Auth_Object)
                                                              REFERENCES ".$prefix."OBJECT_AUTH_OBJECTS(Object_Auth_Category, Object_Auth_Object)
                                                              ON DELETE CASCADE ON UPDATE CASCADE,
                                                              FOREIGN KEY (ID)
                                                              REFERENCES ".$prefix."USERS(ID)
                                                              ON DELETE CASCADE,
                                                              FOREIGN KEY (Object_Auth_Category, Object_Auth_Object, Object_Auth_Group)
                                                              REFERENCES ".$prefix."OBJECT_AUTH_GROUPS(Object_Auth_Category, Object_Auth_Object, Object_Auth_Group)
                                                              ON DELETE CASCADE,
                                                              INDEX (Object_Auth_Group,ID),
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
                $makeTB = $conn->prepare($query);

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "OBJECT_AUTH_USERS_GROUPS table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "OBJECT_AUTH_USERS_GROUPS table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'object-auth-users-groups-structure';
                }
            }

            if(!empty($tables['articles']['_init'])){
                /* ------------------------------------------------------------
                 * Articles related tables - quite a few
                 * ------------------------------------------------------------
                */

                /* INITIALIZE ARTICLES Table
                 *
                 * Article_ID   - int, Auto incrementing ID.
                 * Creator_ID   - int, ID of the user who created the article
                 * Article_Title        - varchar(512), title of the article.
                 * Article_Address      - varchar(512), address of the article - used in many things, such as tying an article
                 *                to a page, or getting it via routing to help SEO.
                 * Article_View_Auth    - int, Authentication level required to view the article.
                 *                By default, the meanings are:
                 *                  0 - Public - anybody can view.
                 *                  1 - Restricted - Author and anybody with specific permissions can view
                 *                  2 - Private - Only author (and system admins) can view.
                 * Article_Text_Content - text, Space for general text content that isn't indexed and has no relational siginifance -
                 *                for example captions, sub-title, read duration, etc...
                 * Thumbnail_Resource_Type-   Varchar(64), 'img' default column needed for the foreign key.
                 * Thumbnail_Address - Varchar(512), Optional address of an image to serve as the thumbnail for an article.
                 *                     Properties such as caption/alt/name are also recovered, and can be used by the client.
                 * Article_Weight       - int, used to promote certain articles over others. Generally, at any given time only 1/2
                 *                different weights (like 1 and 0) should exist.
                 * Block_Order  - Order of the blocks to be displayed. Blocks not in the order should be piled in the end in random order.
                 * Created   -   varchar(14), UNIX timestamp of when the order was created.
                 * Last_Updated -   varchar(14), UNIX timestamp of when the order was last updated.
                 */
                $foreignKeyUsers = 'FOREIGN KEY (Creator_ID)
                       REFERENCES '.$prefix.'USERS(ID),';
                $foreignKeyResources = 'FOREIGN KEY (Thumbnail_Resource_Type,Thumbnail_Address)
                      REFERENCES '.$prefix.'RESOURCES(Resource_Type,Address)
                      ON UPDATE CASCADE ON DELETE SET NULL,';
                $query = "CREATE TABLE IF NOT EXISTS ".$prefix."ARTICLES (
                                                              Article_ID int PRIMARY KEY AUTO_INCREMENT,
                                                              Creator_ID int NOT NULL,
                                                              Article_Title varchar(512) NOT NULL,
                                                              Article_Address varchar(512) NOT NULL,
                                                              Article_Language varchar(32) DEFAULT NULL,
                                                              Article_View_Auth int NOT NULL DEFAULT 0,
                                                              Article_Text_Content TEXT,
                                                              Thumbnail_Resource_Type varchar(64) DEFAULT 'img',
                                                              Thumbnail_Address varchar(512) DEFAULT NULL,
                                                              Article_Weight int NOT NULL DEFAULT 0,
                                                              Block_Order varchar(2048) DEFAULT NULL,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
                                                              ".(!empty($tables['articles']['resources'])?$foreignKeyResources:"")."
                                                              ".(!empty($tables['articles']['users'])?$foreignKeyUsers:"")."
                                                              INDEX (Article_Title),
                                                              INDEX (Article_Address),
                                                              INDEX (Article_Language),
                                                              INDEX (Article_Weight),
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
                $makeTB = $conn->prepare($query);

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "ARTICLES table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "ARTICLES table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'articles-structure';
                }


                /* INITIALIZE ARTICLE_TAGS Table
                 *
                 * Article_ID   - int, Article ID.
                 * Tag_Type   - varchar(64), defaults to 'article'
                 * Tag_Name        - varchar(64), tag identifier
                 */
                $foreignKeyTags = 'FOREIGN KEY (Tag_Type,Tag_Name)
                      REFERENCES '.$prefix.'TAGS(Tag_Type,Tag_Name)
                      ON DELETE CASCADE ON UPDATE CASCADE,';
                $query = "CREATE TABLE IF NOT EXISTS ".$prefix."ARTICLE_TAGS (
                                                              Article_ID int,
                                                              Tag_Type varchar(64) DEFAULT 'default-article-tags',
                                                              Tag_Name varchar(64),
                                                              PRIMARY KEY (Article_ID,Tag_Type,Tag_Name),
                                                              ".(!empty($tables['articles']['tags'])?$foreignKeyTags:"")."
                                                              FOREIGN KEY (Article_ID)
                                                              REFERENCES ".$prefix."ARTICLES(Article_ID)
                                                              ON DELETE CASCADE ON UPDATE CASCADE
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
                $makeTB = $conn->prepare($query);

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "ARTICLE_TAGS table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "ARTICLE_TAGS table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'article-tags-structure';
                }


                /* INITIALIZE ARTICLE_BLOCKS Table
                 * Article_ID     - int, Article ID.
                 * Block_ID       - int, identifier of specific blocks.
                 * Block_Type     - varchar(64), block type (of the supported types)
                 * Text_Content   - text block. For a markdown block this could be MD text, for a youtube block this could be
                 *                  the video identifer (e.g. "dQw4w9WgXcQ"), etc.
                 * Other_Article_ID - int, Other article ID - used in article blocks.
                 * Image_Resource_Type -   Varchar(64), if this block links to a resource or a collection, this needs to be set.
                 * Resource_Address - Varchar(512), Optional address of a resource.
                 *                     Properties such as caption/alt/name are also recovered,
                 *                     and are used merged with Image_Meta (the latter has higher priority).
                 * Collection_Name - Varchar(128), Name of a resource collection name (such as a gallery)
                 * Meta           - text, optional meta information that overrides the one from resources if present.
                 * Created   -   varchar(14), UNIX timestamp of when the order was created.
                 * Last_Updated -   varchar(14), UNIX timestamp of when the order was last updated.
                 */
                $foreignKeyResources = 'FOREIGN KEY (Resource_Type,Resource_Address)
                      REFERENCES '.$prefix.'RESOURCES(Resource_Type,Address)
                      ON UPDATE CASCADE ON DELETE CASCADE,';
                $foreignKeyResourceCollections = 'FOREIGN KEY (Resource_Type, Collection_Name)
                      REFERENCES '.$prefix.'RESOURCE_COLLECTIONS(Resource_Type, Collection_Name)
                      ON UPDATE CASCADE ON DELETE CASCADE,';
                $query = "CREATE TABLE IF NOT EXISTS ".$prefix."ARTICLE_BLOCKS (
                                                              Article_ID int NOT NULL,
                                                              Block_ID BIGINT NOT NULL AUTO_INCREMENT,
                                                              Block_Type varchar(64) NOT NULL,
                                                              Text_Content TEXT DEFAULT NULL,
                                                              Other_Article_ID int DEFAULT NULL,
                                                              Resource_Type varchar(64) DEFAULT NULL,
                                                              Resource_Address varchar(512) DEFAULT NULL,
                                                              Collection_Name varchar(128) DEFAULT NULL,
                                                              Meta TEXT DEFAULT NULL,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
                                                              PRIMARY KEY(Article_ID, Block_ID),
                                                              FOREIGN KEY (Article_ID)
                                                              REFERENCES ".$prefix."ARTICLES(Article_ID)
                                                              ON DELETE CASCADE,
                                                              FOREIGN KEY (Other_Article_ID)
                                                              REFERENCES ".$prefix."ARTICLES(Article_ID)
                                                              ON DELETE CASCADE,
                                                              ".(!empty($tables['articles']['resources'])?$foreignKeyResources:"")."
                                                              ".(!empty($tables['articles']['resources'])?$foreignKeyResourceCollections:"")."
                                                              INDEX (Block_ID),
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
                $makeTB = $conn->prepare($query);
                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "ARTICLE_BLOCKS table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "ARTICLE_BLOCKS table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'article-blocks-structure';
                }
            }

            if(!empty($tables['logging']['_init'])){

                /* Keep in mind it's a good idea to actually initialize a similar table on a different SQL server for durability - this is just the default.
                 * If the other DB is not within the same network as your application servers, read about securing data in transit:
                 *  https://mariadb.com/kb/en/data-in-transit-encryption/
                 * Channel - Log channel
                 * Log_Level - Corresponds to RFC 5424 log levels.
                 * Created - Unix timestamp (relative to when the log was created, not when it was written to the DB)
                 * Node - The node that made the report
                 * Message - The report message (JSON, merged "message" and "context" from standard Monolog structure)
                 */
                $creationQuery = "CREATE TABLE IF NOT EXISTS ".$prefix."DEFAULT_LOGS (
                                                              Channel  varchar(128) NOT NULL,
                                                              Log_Level int(11) NOT NULL,
                                                              Created varchar(20) NOT NULL,
                                                              Node varchar(128) NOT NULL,
                                                              Message TEXT DEFAULT NULL,
                                                              Uploaded varchar(20) NOT NULL DEFAULT 0,
    														  PRIMARY KEY(Channel,Log_Level,Created,Node),
                                                              INDEX (Node),
                                                              INDEX (Uploaded)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";

                if($tables['logging']['highScalability']){
                    //TODO Do this with proper pooling and a more generic connection function
                    $logSettings = new \IOFrame\Handlers\SettingsHandler(
                        $localSettings->getSetting('absPathToRoot').'/localFiles/logSettings/',
                        array_merge($defaultSettingsParams,['opMode'=>\IOFrame\Handlers\SettingsHandler::SETTINGS_OP_MODE_LOCAL])
                    );
                    $logSQLSettings = clone $sqlSettings;
                    $logSQLSettings->combineWithSettings($logSettings,[
                        'settingAliases'=>[
                            'logs_sql_table_prefix'=>'sql_table_prefix',
                            'logs_sql_server_addr'=>'sql_server_addr',
                            'logs_sql_server_port'=>'sql_server_port',
                            'logs_sql_username'=>'sql_username',
                            'logs_sql_password'=>'sql_password',
                            'logs_sql_db_name'=>'sql_db_name',
                            'logs_sql_persistent'=>'sql_persistent',
                        ],
                        'includeRegex'=>'logs_sql',
                        'ignoreEmptyStrings'=>['logs_sql_server_addr','logs_sql_server_port','logs_sql_username','logs_sql_password','logs_sql_db_name','logs_sql_persistent'],
                        'verbose'=>$verbose
                    ]);
                    try {
                        //Create a PDO connection
                        $defaultLogsConn = \IOFrame\Util\FrameworkUtilFunctions::prepareCon($logSQLSettings);
                        // set the PDO error mode to exception
                        $defaultLogsConn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                        if($verbose){
                            echo "Connected to Default Logs DB successfully" . EOL;
                            echo "Initializing...." . EOL;
                        }
                    }
                    catch(\PDOException $e)
                    {
                        if($verbose)
                            echo "Error: " . $e->getMessage().EOL;
                        return 'logs-connection';
                    }
                    $makeTB = $defaultLogsConn->prepare($creationQuery);
                }
                else{
                    $makeTB = $conn->prepare("$creationQuery");
                }

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "DEFAULT_LOGS table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "DEFAULT_LOGS table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'default-logs-structure';
                }

                /* This table defines reporting groups. A group consists of users, and is tied to reporting rules.
                 * Group_Type - Type of the group
                 * Group_ID - Group identifier
                 * Created - When the group was created
                 * Last_Updated - When the group was created
                 * Meta - The report message (JSON, merged "message" and "context" from standard monolog structure)
                 */
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."REPORTING_GROUPS (
                                                              Group_Type varchar(64) NOT NULL,
                                                              Group_ID varchar(64) NOT NULL,
                                                              Meta TEXT DEFAULT NULL,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
    														  PRIMARY KEY(Group_Type,Group_ID),
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "REPORTING_GROUPS table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "REPORTING_GROUPS table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'reporting-groups-structure';
                }

                /* Group_Type -
                 * Group_ID -
                 * User_ID
                 */
                $foreignKey = 'FOREIGN KEY (User_ID)
                       REFERENCES '.$prefix.'USERS(ID)
                       ON DELETE CASCADE,';
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."REPORTING_GROUP_USERS (
                                                              Group_Type varchar(64) NOT NULL,
                                                              Group_ID varchar(64) NOT NULL,
                                                              User_ID  int NOT NULL,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
    														  PRIMARY KEY(Group_Type,Group_ID,User_ID),
                                                              FOREIGN KEY (Group_Type, Group_ID)
                                                              REFERENCES ".$prefix."REPORTING_GROUPS(Group_Type, Group_ID)
                                                              ON DELETE CASCADE ON UPDATE CASCADE,
                                                              ".(!empty($tables['logging']['users'])?$foreignKey:"")."
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "REPORTING_GROUP_USERS table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "REPORTING_GROUP_USERS table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'reporting-group-users-structure';
                }

                /* This table defines specific reporting rules that may override higher level-based defaults from logSettings
                 * Those are useless by themselves, and are instead used by groups
                 * Channel - Log channel
                 * Log_Level - Corresponds to RFC 5424 log levels.
                 * Report_Type - Type of report (e.g. "email", "sms")
                 * Created - When the rule was created
                 * Last_Updated - When the rule was updated
                 */
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."REPORTING_RULES (
                                                              Channel  varchar(128) NOT NULL,
                                                              Log_Level int(11) NOT NULL,
                                                              Report_Type varchar(64) NOT NULL,
                                                              Meta TEXT DEFAULT NULL,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
    														  PRIMARY KEY(Channel,Log_Level,Report_Type),
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "REPORTING_RULES table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "REPORTING_RULES table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'reporting-rules-structure';
                }

                /* Despite the name, only shares a structure - not relation - with REPORTING_RULES.
                 * Group_Type -
                 * Group_ID -
                 * Channel -
                 * Log_Level -
                 * Report_Type -
                 */
                $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."REPORTING_RULE_GROUPS (
                                                              Channel  varchar(128) NOT NULL,
                                                              Log_Level int(11) NOT NULL,
                                                              Report_Type varchar(64) NOT NULL,
                                                              Group_Type varchar(64) NOT NULL,
                                                              Group_ID varchar(64) NOT NULL,
                                                              Created varchar(14) NOT NULL DEFAULT 0,
                                                              Last_Updated varchar(14) NOT NULL DEFAULT 0,
    														  PRIMARY KEY(Channel,Log_Level,Report_Type,Group_Type,Group_ID),
                                                              FOREIGN KEY (Channel,Log_Level,Report_Type)
                                                              REFERENCES ".$prefix."REPORTING_RULES(Channel,Log_Level,Report_Type)
                                                              ON DELETE CASCADE ON UPDATE CASCADE,
                                                              FOREIGN KEY (Group_Type, Group_ID)
                                                              REFERENCES ".$prefix."REPORTING_GROUPS(Group_Type, Group_ID)
                                                              ON DELETE CASCADE ON UPDATE CASCADE,
                                                              INDEX (Created),
                                                              INDEX (Last_Updated)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");

                try{
                    if(!$test)
                        $makeTB->execute();
                    if($verbose)
                        echo "REPORTING_RULE_GROUPS table created.".EOL;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo "REPORTING_RULE_GROUPS table couldn't be created, error is: ".$e->getMessage().EOL;
                    return 'reporting-rule-groups-structure';
                }

            }

            if(!empty($tables['logging']['_populate'])){

                //Insert default logging rules
                $LoggingHandler = new \IOFrame\Handlers\LoggingHandler($localSettings,array_merge($defaultSettingsParams,['opMode'=>\IOFrame\Handlers\SettingsHandler::SETTINGS_OP_MODE_LOCAL]));
                $newLoggingRules = $LoggingHandler->setItems(
                    [
                        ['Channel'=>\IOFrame\Definitions::LOG_DEFAULT_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error Default'])],
                        ['Channel'=>\IOFrame\Definitions::LOG_GENERAL_SECURITY_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error Security'])],
                        ['Channel'=>\IOFrame\Definitions::LOG_USERS_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error Users'])],
                        ['Channel'=>\IOFrame\Definitions::LOG_TOKENS_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error Tokens'])],
                        ['Channel'=>\IOFrame\Definitions::LOG_TAGS_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error Tags'])],
                        ['Channel'=>\IOFrame\Definitions::LOG_SETTINGS_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error Settings'])],
                        ['Channel'=>\IOFrame\Definitions::LOG_ROUTING_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error Routing'])],
                        ['Channel'=>\IOFrame\Definitions::LOG_RESOURCES_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error Resources'])],
                        ['Channel'=>\IOFrame\Definitions::LOG_ORDERS_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error Default'])],
                        ['Channel'=>\IOFrame\Definitions::LOG_PLUGINS_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error Plugins'])],
                        ['Channel'=>\IOFrame\Definitions::LOG_MAILING_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error Mailing'])],
                        ['Channel'=>\IOFrame\Definitions::LOG_CLI_JOBS_CHANNEL,'Log_Level'=>400,'Report_Type'=>'email','Meta'=>json_encode(['title'=>'Mail On Error CLI Jobs'])]
                    ],
                    'reportingRules',
                    ['verbose'=>$verbose,'test'=>$test]
                );
                foreach($newLoggingRules as $res)
                    if($res === -1){
                        if($verbose)
                            echo EOL.'Could not add default logging rules'.EOL;
                        return 'logging-rules-population';
                    }
                if($verbose)
                    echo EOL.'Default logging rules created!'.EOL;
            }

            return true;
        }
    }

}
