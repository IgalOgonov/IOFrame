<?php

namespace IOFrame{

    if(!defined('SettingsHandler'))
        require __DIR__ . '/../IOFrame/Handlers/SettingsHandler.php';
    if(!defined('helperFunctions'))
        require __DIR__ . '/../IOFrame/Util/helperFunctions.php';
    if(!defined('safeSTR'))
        require __DIR__ . '/../IOFrame/Util/safeSTR.php';


    /*Database initiation function. Does require the user to already have a MySQL database up, as well as a user with enough
     * privileges.
     * @param SettingsHandler $localSettings
     * @returns bool true only if everything succeeded.
     * */

    function initDB(Handlers\SettingsHandler $localSettings){
        $userSettings = new Handlers\SettingsHandler(Util\getAbsPath().'/localFiles/userSettings/');
        $siteSettings = new Handlers\SettingsHandler(Util\getAbsPath().'/localFiles/siteSettings/');
        $sqlSettings = new Handlers\SettingsHandler($localSettings->getSetting('absPathToRoot').SETTINGS_DIR_FROM_ROOT.'/sqlSettings/');

        $prefix = $sqlSettings->getSetting('sql_table_prefix');

        $res = true;
        try {
            //Create a PDO connection
            $conn = Util\prepareCon($sqlSettings);
            // set the PDO error mode to exception
            $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            echo "Connected successfully".EOL;
            echo "Initializing....".EOL;

            // INITIALIZE CORE VALUES TABLE
            /* Literally just the pivot time for now. */
            $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."CORE_VALUES(
                                                              tableKey varchar(255) UNIQUE NOT NULL,
                                                              tableValue text
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");

            $updateTB1 = $conn->prepare("INSERT INTO ".$prefix."CORE_VALUES (tableKey, tableValue)
                                      VALUES( 'privateKey','".$siteSettings->getSetting('privateKey')."')");
            $updateTB2 = $conn->prepare("INSERT INTO ".$prefix."CORE_VALUES (tableKey, tableValue)
                                      VALUES( 'secure_file_priv',@@secure_file_priv)");
            $getWrongSFP = $conn->prepare("SELECT * FROM ".$prefix.
                "CORE_VALUES WHERE tableKey = :tableKey;");
            $sfp = $conn->prepare("UPDATE ".$prefix.
                "CORE_VALUES SET tableValue = :tableValue WHERE tableKey = :tableKey;");
            try{
                $makeTB->execute();
                echo "CORE VALUES table created.".EOL;
                $updateTB1->execute();
                $updateTB2->execute();
                $getWrongSFP->bindValue(':tableKey','secure_file_priv');
                $getWrongSFP->execute();
                $oldsfp = $getWrongSFP->fetchAll()[0]['tableValue'];
                $newString = str_replace('\\', '/' , $oldsfp);
                $sfp->bindValue(':tableKey','temp');
                $sfp->bindValue(':tableValue',$newString);
                $sfp->execute();
                echo "CORE VALUES table initialized.".EOL;
            }
            catch(\Exception $e){
                echo "CORE VALUES table couldn't be initialized, error is: ".$e->getMessage().EOL;
                $res = false;
            }

            //CREATE AMD INITIALIZE MAIL TEMPLATES TABLE
            /*ID - automatic increment
             *Title - Name of the template
             *Template - the template.
             * */
            $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."MAIL_TEMPLATES(
                                                              ID int PRIMARY KEY NOT NULL AUTO_INCREMENT,
                                                              Title varchar(255) NOT NULL,
                                                              Content TEXT
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");

            $updateTB1 = $conn->prepare("INSERT INTO ".$prefix."MAIL_TEMPLATES (Title, Content)
                                      VALUES( 'Account Activation Default Template', :Content)");
            $content = "Hello!<br> To activate your account on ".$siteSettings->getSetting('siteName').", click <a href=\"http://".$_SERVER['HTTP_HOST'].$localSettings->getSetting('pathToRoot')."api/users?action=regConfirm&id=%%uId%%&code=%%Code%%\">this link</a><br> The link will expire in ".$userSettings->getSetting('mailConfirmExpires')." hours";
            $updateTB1->bindValue(':Content', Util\str2SafeStr($content));

            $updateTB2 = $conn->prepare("INSERT INTO ".$prefix."MAIL_TEMPLATES (Title, Content)
                                      VALUES( 'Password Reset Default Template', :Content)");
            $content = "Hello!<br> You have requested to reset the password associated with this account. To do so, click <a href=\"http://".$_SERVER['HTTP_HOST'].$localSettings->getSetting('pathToRoot')."api/users?action=pwdReset&id=%%uId%%&code=%%Code%%\"> this link</a><br> The link will expire in ".$userSettings->getSetting('pwdResetExpires')." hours";
            $updateTB2->bindValue(':Content', Util\str2SafeStr($content));

            $updateTB3 = $conn->prepare("INSERT INTO ".$prefix."MAIL_TEMPLATES (Title, Content)
                                      VALUES( 'Mail Reset Default Template', :Content)");
            $content = "Hello!<br> To change your mail on ".$siteSettings->getSetting('siteName').", click <a href=\"http://".$_SERVER['HTTP_HOST'].$localSettings->getSetting('pathToRoot'). "api/users?action=mailReset&id=%%uId%%&code=%%Code%%\">this link</a><br> The link will expire in ".$userSettings->getSetting('mailConfirmExpires')." hours";
            $updateTB3->bindValue(':Content', Util\str2SafeStr($content));

            try{
                $makeTB->execute();
                echo "MAIL TEMPLATES table created.".EOL;
                $updateTB1->execute();
                $updateTB2->execute();
                $updateTB3->execute();
                echo "MAIL TEMPLATES table initialized.".EOL;
            }
            catch(\Exception $e){
                echo "MAIL TEMPLATES table couldn't be initialized, error is: ".$e->getMessage().EOL;
                $res = false;
            }

            // INITIALIZE USERS TABLE
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
                                                              Username varchar(16) UNIQUE NOT NULL,
                                                              Password varchar(255) NOT NULL,
                                                              Email varchar(255) UNIQUE NOT NULL,
                                                              Active BOOLEAN NOT NULL,
                                                              Auth_Rank int,
                                                              SessionID varchar(255),
                                                              authDetails TEXT
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
            //Index Creation
            //When logging in, you usually search by mail.
            $makeLoginIndex = $conn->prepare("CREATE INDEX IF NOT EXISTS loginIndex ON ".$prefix.
                "USERS (Email);");
            try{
                $makeTB->execute();
                echo "USERS table created.".EOL;
            }
            catch(\Exception $e){
                echo "USERS table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }

            try{
                $makeLoginIndex->execute();
            }
            catch(\Exception $e){
                echo "USERS table indexing failed, error is: ".$e->getMessage().EOL;
            }

            // INITIALIZE USER LOGIN HISTORY
            /* Username     - connected to USERS table
             * IP           - IP of the user when logging in.
             * Country      - Country said IP matches.
             * Login_History- an hArray that represents each time the user logged in.
             * */
            $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."LOGIN_HISTORY (
                                                              Username varchar(16) NOT NULL,
                                                              IP varchar(45) NOT NULL,
                                                              Country varchar(20) NOT NULL,
                                                              Login_History longtext NOT NULL,
                                                              PRIMARY KEY (Username, IP),
                                                              FOREIGN KEY (Username)
                                                                REFERENCES ".$prefix."USERS(Username)
                                                                ON DELETE CASCADE
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
            try{
                $makeTB->execute();
                echo "USER LOGIN HISTORY history table created.".EOL;
            }
            catch(\Exception $e){
                echo "USER LOGIN HISTORY table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }

            // INITIALIZE USERS EXTRA TABLE
            /*THIS IS AN EXTRA TABLE - the site core modules should work fine without it.
             * ID - foreign key - tied to USERS table
             * Created_on - Date the user was created on, yyyymmddhhmmss format
             * Banned_Until - Date until which user is banned.
             */
            $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."USERS_EXTRA (
                                                              ID int PRIMARY KEY,
                                                              Created_On varchar(14) NOT NULL,
                                                              Banned_Until varchar(14),
                                                              Suspicious_Until varchar(14),
                                                                FOREIGN KEY (ID)
                                                                REFERENCES ".$prefix."USERS(ID)
                                                                ON DELETE CASCADE
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
            //We will often try to find a list of banned users
            $makeIndex1 = $conn->prepare("CREATE INDEX IF NOT EXISTS Banned ON ".$prefix.
                "USERS_EXTRA (Banned_Until);");
            //We will often filter by Suspicious Activity
            $makeIndex2 = $conn->prepare("CREATE INDEX IF NOT EXISTS Suspicious ON ".$prefix.
                "USERS_EXTRA (Suspicious_Until);");
            try{
                $makeTB->execute();
                $makeIndex1->execute();
                $makeIndex2->execute();
                echo "USERS_EXTRA table created.".EOL;
            }
            catch(\Exception $e){
                echo "USERS_EXTRA table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }


            // INITIALIZE USERS AUTH TABLE
            /*THIS IS AN AUTH TABLE- it is responsible for authorization.
             * ID - foreign key - tied to USERS table
             * Last_Changed - The latest time this users actions/groups were changed.
             */
            $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."USERS_AUTH (
                                                              ID int PRIMARY KEY,
                                                              Last_Changed varchar(11),
                                                                FOREIGN KEY (ID)
                                                                REFERENCES ".$prefix."USERS(ID)
                                                                ON DELETE CASCADE
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
            //We will often filter by Last_Changed
            $makeIndex = $conn->prepare("CREATE INDEX IF NOT EXISTS LastChangedIndex ON ".$prefix.
                "USERS_AUTH (Last_Changed);");
            try{
                $makeTB->execute();
                $makeIndex->execute();
                echo "USER AUTH table created.".EOL;
            }
            catch(\Exception $e){
                echo "USER AUTH table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }

            // INITIALIZE GROUPS AUTH TABLE
            /*THIS IS A GROUPS AUTH TABLE- it is responsible for authorization of groups.
             * Auth_Group - name of the group
             * Last_Changed - The latest time this groups actions were changed.
             * Description - Optional group description
             */
            $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."GROUPS_AUTH (
                                                              Auth_Group varchar(256) PRIMARY KEY,
                                                              Last_Changed varchar(11) NOT NULL DEFAULT '0',
                                                              Description TEXT
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
            //We will often filter by Last_Changed
            $makeIndex = $conn->prepare("CREATE INDEX IF NOT EXISTS LastChangedIndex ON ".$prefix.
                "GROUPS_AUTH (Last_Changed);");
            try{
                $makeTB->execute();
                $makeIndex->execute();
                echo "GROUPS AUTH table created.".EOL;
            }
            catch(\Exception $e){
                echo "GROUPS AUTH table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }

            // INITIALIZE ACTIONS AUTH TABLE
            /*THIS IS AN ACTIONS AUTH TABLE- it is responsible for saving available actions, as well as providing descriptions.
             * Auth_Action - name of the action
             * Description - Optional action description
             */
            $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."ACTIONS_AUTH (
                                                              Auth_Action varchar(256) PRIMARY KEY,
                                                              Description TEXT
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
            try{
                $makeTB->execute();
                echo "ACTIONS_AUTH table created.".EOL;
            }
            catch(\Exception $e){
                echo "ACTIONS_AUTH table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }

            // INITIALIZE USERS ACTIONS TABLE
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
                                                                ON DELETE CASCADE,
                                                                PRIMARY KEY (ID, Auth_Action)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
            try{
                $makeTB->execute();
                echo "USER ACTIONS_AUTH table created.".EOL;
            }
            catch(\Exception $e){
                echo "USER ACTIONS_AUTH table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }

            // INITIALIZE GROUPS ACTIONS TABLE
            /*THIS IS A GROUPS ACTIONS TABLE - a one-to-many table saving the actions allocated to each group.
             * Auth_Group - foreign key - name of the group
             * Auth_Action - an action the group is authorized to do.
             */
            $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."GROUPS_ACTIONS_AUTH (
                                                                Auth_Group varchar(256),
                                                                Auth_Action varchar(256),
                                                                FOREIGN KEY (Auth_Group)
                                                                REFERENCES ".$prefix."GROUPS_AUTH(Auth_Group)
                                                                ON DELETE CASCADE,
                                                                FOREIGN KEY (Auth_Action)
                                                                REFERENCES ".$prefix."ACTIONS_AUTH(Auth_Action)
                                                                ON DELETE CASCADE,
                                                                PRIMARY KEY (Auth_Group, Auth_Action)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
            try{
                $makeTB->execute();
                echo "GROUPS_ACTIONS_AUTH table created.".EOL;
            }
            catch(\Exception $e){
                echo "GROUPS_ACTIONS_AUTH table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }

            // INITIALIZE USERS GROUPS TABLE
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
                                                                ON DELETE CASCADE,
                                                                PRIMARY KEY (ID, Auth_Group)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
            try{
                $makeTB->execute();
                echo "USERS_GROUPS_AUTH table created.".EOL;
            }
            catch(\Exception $e){
                echo "USERS_GROUPS_AUTH table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }


            // INITIALIZE MAIL AUTH TABLE
            /*THIS IS A MAIL AUTH TABLE- it is responsible - at the moment of writing this - for mail API authorization.
             *This is used to asynchronously send mail to users.
             * Name - Target Mail.
             * Value- Secure token to allow server to send a mail to the target mail.
             * Expires - Date after which token is no longer valid (UNIX Timestamp).
             */
            $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."MAIL_AUTH (
                                                              Name varchar(255) PRIMARY KEY,
                                                              Value longtext NOT NULL,
                                                              expires varchar(14) NOT NULL
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
            try{
                $makeTB->execute();
                echo "MAIL AUTH table created.".EOL;
            }
            catch(\Exception $e){
                echo "MAIL AUTH table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }


            // INITIALIZE IP_LIST
            /* This table is for storing the list of white/black listed IPs
             * IP - Either an IPV4 or IPV6 address - as a string
             * IP_Type - Boolean, where FALSE = Blacklisted, TRUE = Whitelisted
             * Is_Reliable - Whether we were 100$ sure the IP was controlled by the user, or it was possibly spoofed
             * Expires - the date until the effect should last. Stored in Unix timestamp, usually.
             * Meta -  Currently unused, reserved for later
             */
            $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."IP_LIST (
                                                              IP varchar(45) NOT NULL,
                                                              Is_Reliable BOOLEAN NOT NULL,
                                                              IP_Type BOOLEAN NOT NULL,
                                                              Expires varchar(14) NOT NULL,
                                                              Meta varchar(10000) DEFAULT NULL,
                                                              PRIMARY KEY (IP)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
            //We will often filter by Expires
            $makeIndex = $conn->prepare("CREATE INDEX IF NOT EXISTS ExpiresIndex ON ".$prefix.
                "IP_LIST (IP_Type,Expires);");
            try{
                $makeTB->execute();
                $makeIndex->execute();
                echo "IP_LIST table created.".EOL;
            }
            catch(\Exception $e){
                echo "IP_LIST table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }


            // INITIALIZE IPV4_RANGE
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
                                                              PRIMARY KEY (Prefix, IP_From, IP_To)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
            //We will often filter by Expires
            $makeIndex = $conn->prepare("CREATE INDEX IF NOT EXISTS ExpiresIndex ON ".$prefix.
                "IPV4_RANGE (Expires);");
            try{
                $makeTB->execute();
                $makeIndex->execute();
                echo "IPV4_RANGE table created.".EOL;
            }
            catch(\Exception $e){
                echo "IPV4_RANGE table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }


            // INITIALIZE IP_LIST_META
            /* This table is for storing meta information (currently just Last_Changed) of the IP_LIST table
             * settingKey - Similar to a settings table
             * settingValue - similar to a settings table
             */
            $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."IP_LIST_META (
                                                              settingKey varchar(255) PRIMARY KEY,
                                                              settingValue varchar(255) NOT NULL
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;
                                                              ");
            try{
                $makeTB->execute();
                echo "IP_LIST_META table created.".EOL;
            }
            catch(\Exception $e){
                echo "IP_LIST_META table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }

            // INITIALIZE IPV4_RANGE_META
            /* Similar to IP_LIST_META
             */
            $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."IPV4_RANGE_META (
                                                              settingKey varchar(255) PRIMARY KEY,
                                                              settingValue varchar(255) NOT NULL
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;
                                                              ");
            try{
                $makeTB->execute();
                echo "IPV4_RANGE_META table created.".EOL;
            }
            catch(\Exception $e){
                echo "IPV4_RANGE_META table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }


            // INITIALIZE IP_EVENTS
            /* This table is for storing the list of (probably suspicious) events per IP
             * IP                 - The IP.
             * Event_Type        - A code for the event. Codes are set by the framework user, and vary per app.
             *                      Unsigned BIGINT, as I can think of use cases requiring it to be this big
             * Sequence_Start_Time- The date at which this specific event sequence started.
             * Sequence_Count     - Number of events in this sequence. Any additional event before "Sequence_Expires"
             *                      Increases this count (and probably prolongs Sequence_Expires)
             * Sequence_Expires   - The time when current event aggregation sequence expires, and a new one may begin.
             * Meta               - At the moment, used to store the full IP array as a CSV.
             */
            $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."IP_EVENTS (
                                                              IP varchar(45) NOT NULL,
                                                              Event_Type BIGINT UNSIGNED NOT NULL,
                                                              Sequence_Expires varchar(14) NOT NULL,
                                                              Sequence_Start_Time varchar(14) NOT NULL,
                                                              Sequence_Count BIGINT UNSIGNED NOT NULL,
                                                              Meta varchar(10000) DEFAULT NULL,
                                                              PRIMARY KEY (IP,Event_Type,Sequence_Start_Time),
                                                              UNIQUE INDEX (IP,Event_Type,Sequence_Expires)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
            try{
                $makeTB->execute();
                echo "IP_EVENTS table created.".EOL;
            }
            catch(\Exception $e){
                echo "IP_EVENTS table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }



            // INITIALIZE USER_EVENTS
            /* This table is for storing the list of (probably suspicious) events per User
             * ID                 - User ID
             * Event_Type        - A code for the event. Codes are set by the framework user, and vary per app.
             *                      Unsigned BIGINT, as I can think of use cases requiring it to be this big
             * Sequence_Start_Time- The date at which this specific event sequence started.
             * Sequence_Count     - Number of events in this sequence. Any additional event before "Sequence_Expires"
             *                      Increases this count (and probably prolongs Sequence_Expires)
             * Sequence_Expires   - The time when current event aggregation sequence expires, and a new one may begin.
             */
            $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."USER_EVENTS (
                                                              ID int NOT NULL,
                                                              Event_Type BIGINT UNSIGNED NOT NULL,
                                                              Sequence_Expires varchar(14) NOT NULL,
                                                              Sequence_Start_Time varchar(14) NOT NULL,
                                                              Sequence_Count BIGINT UNSIGNED NOT NULL,
                                                              PRIMARY KEY (ID,Event_Type,Sequence_Start_Time),
                                                              UNIQUE INDEX (ID,Event_Type,Sequence_Expires),
                                                              FOREIGN KEY (ID)
                                                              REFERENCES ".$prefix."USERS(ID)
                                                              ON DELETE CASCADE
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
            try{
                $makeTB->execute();
                echo "USER_EVENTS table created.".EOL;
            }
            catch(\Exception $e){
                echo "USER_EVENTS table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }


            // INITIALIZE EVENTS_RULEBOOK
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
             *
             * Note that there has to be a rule of the form:
             *      Event_Category | Event_Type | Sequence_Number | Blacklist_For | Add_TTL
             *    0/IP, 1/User, etc |   <Code>    |         0       |       X       |   X>0
             * for any specific event sequence to even begin.
             */
            $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."EVENTS_RULEBOOK (
                                                              Event_Category INT(32),
                                                              Event_Type BIGINT UNSIGNED,
                                                              Sequence_Number INT UNSIGNED,
                                                              Blacklist_For INT UNSIGNED,
                                                              Add_TTL INT UNSIGNED,
                                                              CONSTRAINT not_empty CHECK (NOT (ISNULL(Add_TTL) AND ISNULL(Blacklist_For)) ),
                                                              PRIMARY KEY (Event_Category,Event_Type,Sequence_Number)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
            try{
                $makeTB->execute();
                echo "EVENTS_RULEBOOK table created.".EOL;
            }
            catch(\Exception $e){
                echo "EVENTS_RULEBOOK table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }

            // INITIALIZE DB_BACKUP_META
            /* This table stores meta information about table backups, the ones performed by SQLHandler.
             * ID - Integer, increases automatically.
             * Backup_Date - unix timestamp (in seconds) of when the backup occurred
             * Table_Name - name of the table that was backed up
             * Full_Name - full filename of the table backup
             */
            $makeTB = $conn->prepare("CREATE TABLE IF NOT EXISTS ".$prefix."DB_BACKUP_META(
                                                              ID int PRIMARY KEY NOT NULL AUTO_INCREMENT,
                                                              Backup_Date varchar(14) NOT NULL,
                                                              Table_Name varchar(64) NOT NULL,
                                                              Full_Name varchar(256) NOT NULL
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;");
            try{
                $makeTB->execute();
                echo "DB_Backup_Meta table created.".EOL;
            }
            catch(\Exception $e){
                echo "DB_Backup_Meta table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }

// INITIALIZE OBJECT_CACHE
            /* This table is for storing Objects. The main purpose of storing them here is cache functionality.
             * All objects have a unique ID, but they also have a Group (optional), and last-updated time. Can be anything.
             * ID - Integer, increases with each object added.
             * Ob_Group - Varchar(255), Optional group the object belongs to. Max 255 characters, meant to be just a name.
             * Last_Updated - Varchar(14), last time the object was updated. Initial time is object creation. Is the unix timestamp.
             * Owner - ID of the "main" owner of the object.
             * Owner_Group - JSON of IDs of all owners of the group. Separate from "Owner" for performance reasons.
             * Min_Modify_Rank - the minimum rank at which every user can modify the object. For "private" objects, should be 0.
             * Min_View_Rank -  the minimum rank at which every user can view the object. Default is null (free for view).
             * Object - MEDIUMTEXT, The actual object. As long as 16,777,215 bytes. Can be anything, must be something.
             * Meta - Any additional meta information about the object, varies by system. Can even be a foreign key.
             */
            $query = "CREATE TABLE IF NOT EXISTS ".$prefix."OBJECT_CACHE (
                                                              ID int PRIMARY KEY NOT NULL AUTO_INCREMENT,
                                                              Ob_Group Varchar(255) DEFAULT NULL,
                                                              Last_Updated varchar(14) NOT NULL,
                                                              Owner int,
                                                              Owner_Group varchar(10000),
                                                              Min_Modify_Rank int DEFAULT 0 NOT NULL CHECK(Min_Modify_Rank>=0),
                                                              Min_View_Rank int DEFAULT -1,
                                                              CHECK( IF( Min_View_Rank IS NOT NULL, Min_View_Rank>=-1, FALSE ) ),
                                                              Object MEDIUMTEXT NOT NULL,
                                                              Meta varchar(255) DEFAULT NULL
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
            $makeTB = $conn->prepare($query);
            $indexGroups = "ALTER TABLE ".$prefix."OBJECT_CACHE ADD INDEX (Ob_Group);";
            $indexGroups = $conn->prepare($indexGroups);
            try{
                $makeTB->execute();
                $indexGroups->execute();
                echo "Object Cache table created.".EOL;
            }
            catch(\Exception $e){
                echo "Object Cache table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }

            // INITIALIZE OBJECT_CACHE_META
            /* This table is meant to optimize queries made on groups where nothing has changed (which might be the case
             * quite commonly).
             * Group_Name - Varchar(255) unique group name.
             * Owner     - Owner of the group.
             * Last_Updated - varchar(14), the last time ANY element of the group was updated.
             * Allow_Addition - Whether addition is allowed to the group by default.
             */
            $query ="CREATE TABLE IF NOT EXISTS ".$prefix."OBJECT_CACHE_META (
                                                              Group_Name Varchar(255) PRIMARY KEY,
                                                              Owner int CHECK(ISNULL(Owner) OR Owner>0 ),
                                                              Last_Updated varchar(14) NOT NULL,
                                                              Allow_Addition bool NOT NULL DEFAULT false
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
            $makeTB = $conn->prepare($query);
            try{
                $makeTB->execute();
                echo "Object Cache Meta table created.".EOL;
            }
            catch(\Exception $e){
                echo "Object Cache table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }


            // INITIALIZE OBJECT_MAP
            /* This table is meant to serve as a map, matching each page in the site to the objects it contains.
             * Note that this table doesn't have to be used, objects can be called manually in the code on each page - however,
             * this table does open the way for many mechanisms, and also adds cleanness to the code.
             * Map_Name - Varchar(500), the route from the root of the site to the page. If the page is at, for example,
             *            www.site.com/path/to/page.php, the value is "path/to/page.php". For www.site.com/Page Name.php, the
             *            value is rawurlencode("Page Name.php"), .
             *            Only for www.site.com itself, the value is "@", and for global objects, the value is "#".
             * Objects - Varchar(20000) A JSON assoc array of the format:
             *           {"#":"ObjID1,ObjID2,...", "group":"ObjID1,ObjID2,...", "anotherGroup":"ObjID1,..."}
             *           where # is the collection of the group-less objects of the current page.
             * Last_Changed - Last time objects/groups/anything were added/removed to/from the page, unix timestamp.
             */
            $query = "CREATE TABLE IF NOT EXISTS ".$prefix."OBJECT_MAP (
                                                              Map_Name Varchar(255) PRIMARY KEY,
                                                              Objects TEXT,
                                                              Last_Changed varchar(14)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
            $makeTB = $conn->prepare($query);
            try{
                $makeTB->execute();
                echo "Object Map table created.".EOL;
            }
            catch(\Exception $e){
                echo "Object Map table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }



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
                                                              ID int PRIMARY KEY NOT NULL AUTO_INCREMENT,
                                                              Method varchar(256) NOT NULL,
                                                              Route varchar(1024) NOT NULL,
                                                              Match_Name varchar(64) NOT NULL,
                                                              Map_Name varchar(256)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
            $makeTB = $conn->prepare($query);
            $indexRoutes = "ALTER TABLE ".$prefix."ROUTING_MAP ADD INDEX (Match_Name);";
            $indexRoutes = $conn->prepare($indexRoutes);

            try{
                $makeTB->execute();
                echo "Routing Map table created.".EOL;
            }
            catch(\Exception $e){
                echo "Routing Map table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }

            try{
                $indexRoutes->execute();
                echo "Routing Map table index created.".EOL;
            }
            catch(\Exception $e){
                echo "Routing Map table index couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
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
                                                              Extensions varchar(256)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
            $makeTB = $conn->prepare($query);
            try{
                $makeTB->execute();
                echo "Routing Match table created.".EOL;
            }
            catch(\Exception $e){
                echo "Routing Match table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }



            // INITIALIZE IOFRAME_TOKENS Table
            /* This table is meant to be the default table for tokens in IOFrame.
             * Tokens are used for a variaty of things, such as account activation, password resets, and more.
             *
             * Token        -   Varchar(256), primary identifier. Is the token.
             * Token_Action -   Varchar(1024), The action of the token. Should describe the purpose of the token, for example
             *                  ACCOUNT_ACTIVATION_5 (account activation of user with ID 5), but could be anything - like a link
             *                  to a resource. The function that requests the token can decide what to do with it on match.
             * Uses_Left    -   int, Number of uses left for the token.
             *                  For a single use token may be 1, but not all tokens are single use...
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
                                                              Uses_Left int NOT NULL,
                                                              Expires varchar(14) NOT NULL,
                                                              Session_Lock varchar(256) DEFAULT NULL,
                                                              Locked_At varchar(14) DEFAULT NULL
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
            $makeTB = $conn->prepare($query);
            $query = "ALTER TABLE ".$prefix."IOFRAME_TOKENS ADD INDEX (Expires);";
            $indexTB1 = $conn->prepare($query);
            $query = "ALTER TABLE ".$prefix."IOFRAME_TOKENS ADD INDEX (Token_Action);";
            $indexTB2 = $conn->prepare($query);

            try{
                $makeTB->execute();
                echo "Default Token table created.".EOL;
            }
            catch(\Exception $e){
                echo "Default Token table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }

            try{
                $indexTB1->execute();
                $indexTB2->execute();
                echo "Default Token table indexes created.".EOL;
            }
            catch(\Exception $e){
                echo "Default Token table indexes couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }


            // INITIALIZE RESOURCES Table
            /* This table stores resource information.
             *
             * Address      -   Varchar(512), Address of the resource - from relevant folder root if local, or full URI if
             *                  not local. By default it should include file extension for local files.
             * Resource_Type-   Varchar(64), should be 'image', 'js', 'css', 'text' or 'blob' currently.
             * Resource_Local -   Boolean, default true - whether the resource should be treated as a local one.
             * Minified_Version - Boolean, default false - Whether you can get a minified version of the resource.
             *                    Rules of how to handle it are defined by the handler.
             * Version      -   int, default 1. meant for versioning purposes. Is incremented by the user.
             * Created      -   Varchar(14), UNIX timestamp of when the resource was added.
             * Last_Changed -   Varchar(14), UNIX timestamp of when the resource was last changed (just in the DB).
             * Text_Content -   Space for general text content.
             * Blob_Content -   Space for general blob content.
             */
            $query = "CREATE TABLE IF NOT EXISTS ".$prefix."RESOURCES (
                                                              Resource_Type varchar(64),
                                                              Address varchar(512),
                                                              Resource_Local BOOLEAN NOT NULL,
                                                              Minified_Version BOOLEAN NOT NULL,
                                                              Version int DEFAULT 1 NOT NULL,
                                                              Created varchar(14) NOT NULL,
                                                              Last_Changed varchar(14) NOT NULL,
                                                              Text_Content TEXT,
                                                              Blob_Content BLOB,
                                                               PRIMARY KEY(Resource_Type, Address)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
            $makeTB = $conn->prepare($query);
            $query = "ALTER TABLE ".$prefix."RESOURCES ADD INDEX (Resource_Local);";
            $indexTB1 = $conn->prepare($query);
            $query = "ALTER TABLE ".$prefix."RESOURCES ADD INDEX (Created);";
            $indexTB2 = $conn->prepare($query);
            $query = "ALTER TABLE ".$prefix."RESOURCES ADD INDEX (Last_Changed);";
            $indexTB3 = $conn->prepare($query);

            try{
                $makeTB->execute();
                echo "Resource table created.".EOL;
            }
            catch(\Exception $e){
                echo "Resource table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }

            try{
                $indexTB1->execute();
                $indexTB2->execute();
                $indexTB3->execute();
                echo "Resource table indexes created.".EOL;
            }
            catch(\Exception $e){
                echo "Resource table indexes couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }



            // INITIALIZE RESOURCE_COLLECTIONS Table
            /* This table stores resource collections.
             *
             * Collection_Name -   Varchar(128), Name of the collection
             * Collection_Order-   TEXT, Reserved for order a collection might have.
             * Resource_Type-   Varchar(64), should be 'image', 'js', 'css', 'text' or 'blob' currently.
             * Created      -   Varchar(14), UNIX timestamp of when the collection was added.
             * Last_Changed -   Varchar(14), UNIX timestamp of when the collection (or any of its memebers was last changed.
             */
            $query = "CREATE TABLE IF NOT EXISTS ".$prefix."RESOURCE_COLLECTIONS (
                                                              Resource_Type varchar(64),
                                                              Collection_Name varchar(128),
                                                              Collection_Order TEXT DEFAULT NULL,
                                                              Created varchar(14) NOT NULL,
                                                              Last_Changed varchar(14) NOT NULL,
                                                              Meta TEXT,
                                                               PRIMARY KEY(Resource_Type, Collection_Name)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
            $makeTB = $conn->prepare($query);
            $query = "ALTER TABLE ".$prefix."RESOURCE_COLLECTIONS ADD INDEX (Created);";
            $indexTB1 = $conn->prepare($query);
            $query = "ALTER TABLE ".$prefix."RESOURCE_COLLECTIONS ADD INDEX (Last_Changed);";
            $indexTB2 = $conn->prepare($query);

            try{
                $makeTB->execute();
                echo "Resource collection table created.".EOL;
            }
            catch(\Exception $e){
                echo "Resource collection table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }

            try{
                $indexTB1->execute();
                $indexTB2->execute();
                echo "Resource collection table indexes created.".EOL;
            }
            catch(\Exception $e){
                echo "Resource collection table indexes couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }


            // INITIALIZE RESOURCE_COLLECTIONS_MEMBERS Table
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
                                                              ON DELETE CASCADE,
                                                              FOREIGN KEY (Resource_Type, Address)
                                                              REFERENCES ".$prefix."RESOURCES(Resource_Type, Address)
                                                              ON DELETE CASCADE,
                                                              PRIMARY KEY (Resource_Type, Collection_Name, Address)
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;";
            $makeTB = $conn->prepare($query);
            try{
                $makeTB->execute();
                echo "Resource collection members table created.".EOL;
            }
            catch(\Exception $e){
                echo "Resource collection members table couldn't be created, error is: ".$e->getMessage().EOL;
                $res = false;
            }

            /** ------------------------------------------ FUNCTIONS ------------------------------------------------
             * The very few DB functions in the framework.
             * While it violates my intention to keep all logic on the Application layer, and away from the DB, any other
             * solution would either create security vulnerabilities or carry an extreme performance penalty.
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
            IP varchar(45),
            Event_Type BIGINT(20) UNSIGNED,
            Is_Reliable BOOLEAN,
            Full_IP VARCHAR(10000)
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
                                Sequence_Count = eventCount + 1,
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
                        1,
                        Full_IP
                    )
                     ON DUPLICATE KEY UPDATE Sequence_Count = Sequence_Count+1;
                    END;
                END IF;

                #We might need to blacklist the IP
                IF Blacklist_For > 0 THEN
                    INSERT INTO ".$prefix."IP_LIST (IP_Type,Is_Reliable,IP,Expires) VALUES (0,Is_Reliable,IP,UNIX_TIMESTAMP()+Blacklist_For)
                    ON DUPLICATE KEY UPDATE Expires = GREATEST(Expires,UNIX_TIMESTAMP()+Blacklist_For);
                END IF;

                RETURN eventCount+1;
            END");
            try{
                $dropFunc->execute();
                $makeFunc->execute();
                echo "commitEventIP function created.".EOL;
            }
            catch(\Exception $e){
                echo "commitEventIP function  couldn't be created, error is: ".$e->getMessage().EOL;
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
            $makeFunc = $conn->prepare("CREATE FUNCTION ".$prefix."commitEventUser (ID int(11), Event_Type BIGINT(20) UNSIGNED)
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
                SELECT ".$prefix."EVENTS_RULEBOOK.Add_TTL,".$prefix."EVENTS_RULEBOOK.Blacklist_For INTO Add_TTL,Blacklist_For FROM ".$prefix."ACTIONS_RULEBOOK WHERE
                                        Event_Category = 1 AND
                                        ".$prefix."EVENTS_RULEBOOK.Event_Type = Event_Type AND
                                        Sequence_Number<=eventCount ORDER BY Sequence_Number DESC LIMIT 1;

                IF eventCount>0 THEN
                    BEGIN
                        UPDATE ".$prefix."USER_EVENTS SET
                                Sequence_Expires = Sequence_Expires + Add_TTL,
                                Sequence_Count = eventCount + 1
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
                        1
                    );
                    END;
                END IF;

                #We might need to blacklist the IP
                IF Blacklist_For > 0 THEN
                    UPDATE ".$prefix."USERS_EXTRA SET
                            Suspicious_Until = GREATEST(Suspicious_Until,UNIX_TIMESTAMP()+Blacklist_For)
                            WHERE
                                ".$prefix."USERS_EXTRA.ID = ID;
                END IF;

                RETURN eventCount+1;
            END");

            try{
                $dropFunc->execute();
                $makeFunc->execute();
                echo "commitEventUser function created.".EOL;
            }
            catch(\Exception $e){
                echo "commitEventUser function  couldn't be created, error is: ".$e->getMessage().EOL;
            }


        }

        catch(\PDOException $e)
        {
            echo "Error: " . $e->getMessage().EOL;
            $res = false;
        }

        return $res;
    }

}

?>