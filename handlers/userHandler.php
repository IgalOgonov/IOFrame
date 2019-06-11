<?php
namespace IOFrame{
    define('userHandler',true);
    /**
     * Handles user registration, changes, login, etc.
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */
    if(!defined('abstractDBWithCache'))
        require 'abstractDBWithCache.php';
    use GeoIp2\Database\Reader;
    use Monolog\Logger;
    use Monolog\Handler\IOFrameHandler;

    class userHandler extends abstractDBWithCache
    {

        /* @var settingsHandler $userSettings User settings handler
         * */
        public $userSettings;

        /* @var settingsHandler $siteSettings Site settings handler
         * */
        public $siteSettings;

        /**
         * Basic construction function - as in abstractDBWithCache
         * @param settingsHandler $localSettings Settings handler containing LOCAL settings.
         * @param array $params Potentially containing siteSettings
         */
        public function __construct(settingsHandler $localSettings, $params = []){
            parent::__construct($localSettings,$params);

            //Initialize settings
            $this->userSettings = new settingsHandler(
                $localSettings->getSetting('absPathToRoot').'/'.SETTINGS_DIR_FROM_ROOT.'/userSettings/',
                $this->defaultSettingsParams
            );
            if(isset($params['siteSettings']))
                $this->siteSettings = $params['siteSettings'];
            else
                $this->siteSettings = new settingsHandler(
                    $localSettings->getSetting('absPathToRoot').'/'.SETTINGS_DIR_FROM_ROOT.'/siteSettings/',
                    $this->defaultSettingsParams
                );
        }


        /** Main registration function
         * @param string[] $inputs array of inputs needed to register of the form:
         *                  'u' - Username (required but may be empty for random username)
         *                  'm' - Mail
         *                  'p' - Password
         * @param bool $test
         *
         * @returns int
         *      0 - success
         *      -2 - failed - registration not allowed!
         *      -1 - failed - incorrect input - wrong or missing
         *      1 - failed - username already in use
         *      2 - failed - email already in use
         *      3 - failed - server error
         */
        function regUser(array $inputs, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;

            //Hash the password
            $pass = $inputs["p"];
            $hash = password_hash($pass, PASSWORD_DEFAULT);

            //First, check to see if email or username are taken, and for extra conditions
            if($inputs["u"] != ''){
                $res = $this->reg_checkExistingUserOrMail($inputs,['test'=>$test,'verbose'=>$verbose]);
                if($res !== 0)
                    return $res;
            }
            //Notice that the username might be random
            else{
                $hex = bin2hex(openssl_random_pseudo_bytes(8,$hex_secure));
                $inputs["u"] =$hex;
                $res = $this->reg_checkExistingUserOrMail($inputs,['test'=>$test,'verbose'=>$verbose]);
                //Duplicate mail is final
                if($res === 2)
                    return $res;
                //If by some change we hit an already existing username, try again
                elseif($res === 1){
                    while($res === 1){
                        $hex = bin2hex(openssl_random_pseudo_bytes(8,$hex_secure));
                        $inputs["u"] =$hex;
                        $res = $this->reg_checkExistingUserOrMail($inputs,['test'=>$test,'verbose'=>$verbose]);
                    }
                }
            }

            //Make user if all good
            $res = $this->reg_makeUserCore($inputs, $hash, ['test'=>$test,'verbose'=>$verbose]);
            if($res !== 0)
                return $res;

            //Add user extra data
            $res = $this->reg_makeUserExtra($inputs,['test'=>$test,'verbose'=>$verbose]);
            if($res !== 0)
                return $res;

            //Make an empty Auth table entry for the user
            $res = $this->reg_makeUserAuth($inputs,['test'=>$test,'verbose'=>$verbose]);
            if($res !== 0)
                return $res;

            if($verbose)
                echo "Test User Added!".' Values are :'.$inputs["u"].', '.$hash.', '.$inputs["m"].'.';

            return 0;

        }


        /** Checks if mail or username are taken
         * @param string[] $inputs array of inputs needed to log in.
         * @param array $params
         *
         * @returns int description in main function
         */
        private function reg_checkExistingUserOrMail(array $inputs, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            try{
                $checkRes=$this->sqlHandler->selectFromTable($this->sqlHandler->getSQLPrefix().'USERS',[['Email', $inputs["m"],'='],['Username', $inputs["u"],'='],'OR'],
                    [],['noValidate'=>true,'test'=>$test,'verbose'=>$verbose]);
            }
            catch(\Exception $e){
                //TODO LOG
                return 3;
            }
            if(is_array($checkRes) && count($checkRes)>0){
                if($checkRes[0]['Email'] == $inputs["m"]){
                    //Email
                    if($verbose)
                        echo 'Duplicate email!';
                    return 2;
                }
                else{
                    //Username
                    if($verbose)
                        echo  'Duplicate username!: ';
                    return 1;
                }
            }
            return 0;
        }

        /** Makes core user
         * @param string[] $inputs array of inputs needed to log in.
         * @param string $hash password hash
         * @param array $params
         *
         * @returns int description in main function
         */
        private function reg_makeUserCore(array $inputs, string $hash, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            $query = "INSERT INTO ".$this->sqlHandler->getSQLPrefix().
                "USERS(Username, Password, Email, Active, Auth_Rank, SessionID)
             VALUES (:Username, :Password, :Email,:Active, :Auth_Rank,:SessionID)";
            $queryBind = [];
            array_push($queryBind,[':Username', $inputs["u"]],[':Password', $hash],[':Email', $inputs["m"]]);
            //Decides whether to activate user on creation or not
            if($this->userSettings->getSetting('regConfirmMail') && !isset($_SESSION['INSTALLING']) ){
                array_push($queryBind,[':Active', 0]);
            }
            else
                array_push($queryBind,[':Active', 1]);
            //Deciding whether to give a user a specific rank or not
            if (isset($inputs["r"])){
                if ( ($inputs["r"] < json_decode($_SESSION['details'],true)['Auth_Rank']))
                    array_push($queryBind,[':Auth_Rank', $inputs["r"]]);
                else
                    array_push($queryBind,[':Auth_Rank', 9999]);
            }
            else {
                if(isset($_SESSION['INSTALLING'])){
                    if($_SESSION['INSTALLING'] = true)
                        array_push($queryBind,[':Auth_Rank', 0]);
                    else
                        array_push($queryBind,[':Auth_Rank', 9999]);
                }
                else
                    array_push($queryBind,[':Auth_Rank', 9999]);
            }
            //Push the session ID
            array_push($queryBind,[':SessionID', session_id()]);
            if(!$test)
                try{
                    $this->sqlHandler->exeQueryBindParam($query,$queryBind);
                }
                catch(\Exception $e){
                    //TODO LOG
                    return 3;
                }
            if($verbose)
                echo 'Executing query '.$query.EOL;
            return 0;
        }


        /** Adds the auth info for the user - by default, it starts with just the ID and empty columns.
         * @param string[] $inputs array of inputs needed to log in.
         * @param array $params
         *
         * @returns int description in main function
         */
        private function reg_makeUserAuth(array $inputs, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            $query = "INSERT INTO ".$this->sqlHandler->getSQLPrefix()."USERS_AUTH(ID) SELECT ID FROM ".$this->sqlHandler->getSQLPrefix()."USERS WHERE Username=:Username";
            //Add extra data
            if(!$test)
                try{
                    $this->sqlHandler->exeQueryBindParam($query,[[':Username',$inputs["u"]]]);
                }
                catch(\Exception $e){
                    //TODO LOG
                    return 3;
                }
            if($verbose)
                echo 'Executing query '.$query.EOL;
            return 0;
        }

        /** Adds extra value to table - changed from app to app
         * @param string[] $inputs array of inputs needed to log in.
         * @param array $params
         *
         * @returns int description in main function
         */
        private function reg_makeUserExtra(array $inputs, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            //Need to fetch the ID of the user we just created in order to add meta-data

            //Get the ID of the user we just created
            try{
                $res = $this->sqlHandler->selectFromTable(
                    $this->sqlHandler->getSQLPrefix().'USERS',['Username',$inputs["u"],'='],[],['noValidate'=>true,'test'=>$test,'verbose'=>$verbose]
                )[0];
            }
            catch(\Exception $e){
                //TODO LOG
                return 3;
            }
            //In case we are in test mode, mock uId and uMail
            $uId = (!$test)? $res['ID'] : 1;
            $uMail = (!$test)? $res['Email'] : 'test@test.com';

            //Add extra data
            $query = "INSERT INTO ".$this->sqlHandler->getSQLPrefix()."USERS_EXTRA(ID, Created_On)
                                  VALUES (:ID,:Created_On)";
            if(!$test)
                try{
                    $this->sqlHandler->exeQueryBindParam($query,[[':ID', $uId],[':Created_On', date("YmdHis")]]);
                }
                catch(\Exception $e){
                    //TODO LOG
                    return 3;
                }
            if($verbose)
                echo 'Executing query '.$query.EOL;
            //If the user needs confirm his mail, we generate the confirmation code here and send the relevant mail to the user
            if(isset($_SESSION['INSTALLING']))
                if($_SESSION['INSTALLING'] = true)
                    return 0;

            if($this->userSettings->getSetting('regConfirmMail')){
                $this->accountActivation($uMail,$uId,['test'=>$test,'verbose'=>$verbose]);
            }

            return 0;
        }


        /** Changes the password
         * @param int $userID User ID
         * @param string $plaintextPassword User mail
         * @param array $params
         *
         * @returns int 0 on success
         *              1 if userID does not exist
         */
        function changePassword(int $userID,string $plaintextPassword, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            $hash = password_hash($plaintextPassword, PASSWORD_DEFAULT);
            $userInfo = $this->sqlHandler->selectFromTable(
                $this->sqlHandler->getSQLPrefix().'USERS',['ID',$userID,'='],['ID'],['test'=>$test,'verbose'=>$verbose]
            );
            if(is_array($userInfo) && count($userInfo)>0){
                $this->sqlHandler->updateTable(
                    $this->sqlHandler->getSQLPrefix().'USERS',
                    ['Password = "'.$hash.'"'],
                    ['ID',$userID,'='],
                    ['test'=>$test,'verbose'=>$verbose]
                );
                return 0;
            }
            else
                return 1;
        }

        /** Changes the email
         * @param int $userID User ID
         * @param string $newEmail User mail
         * @param array $params
         *
         * @returns int 0 on success
         *              1 if userID does not exist
         *              2 Email already in use
         */
        function changeMail(int $userID,string $newEmail, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            $tname = $this->sqlHandler->getSQLPrefix().'USERS';
            $userInfo = $this->sqlHandler->selectFromTable($tname,
                [
                    ['ID',$userID,'='],
                    ['Email',$newEmail,'='],
                    'OR'
                ],
                ['ID','Email'],
                ['test'=>$test,'verbose'=>$verbose]
            );
            if((is_array($userInfo) && count($userInfo)>0)){

                //Check for the case where we got a different user with an existing new email
                if(count($userInfo)>1)
                    return 2;
                //We might only get a user *because* he has a similar email, but the ID is different
                if($userInfo[0]['ID']!=$userID)
                    return 1;

                $this->sqlHandler->updateTable(
                    $this->sqlHandler->getSQLPrefix().'USERS',
                    ['Email = "'.$newEmail.'"'],
                    ['ID',$userID,'='],
                    ['test'=>$test,'verbose'=>$verbose]
                );
                return 0;
            }
            else
                return 1;
        }


        /** Creates (and potentially resets) the account activation parameters, as well as sends a (new) mail
         * @param string $uMail User mail
         * @param int $uId User ID
         * @param array $params of the form
         *                          async' - bool, default true - If true, will try to send the mail asynchronously
         *
         * @returns int description in main function
         *              -2 if user does not exist.
         */
        function accountActivation(string $uMail, int $uId = null, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            isset($params['async'])?
                $async = $params['async'] : $async = true;
            //Find user ID if it was not provided
            if($uId === null)
                $uId = $this->sqlHandler->selectFromTable(
                    $this->sqlHandler->getSQLPrefix().'USERS',['Email',$uMail,'='],['ID'],['test'=>$test,'verbose'=>$verbose]
                );

            if(!is_array($uId) || count($uId)==0)
                return -2;
            else
                $uId = $uId[0]['ID'];

            $confirmCode = GeraHash(50);
            //Set the new code
            if(!$this->createCode(
                $uId,
                $confirmCode,
                time()+$this->userSettings->getSetting('mailConfirmExpires')*60*60,
                'MailConfirm',
                'MailConfirm_Expires',
                ['test'=>$test,'verbose'=>$verbose]
            ))
                return -3;
            $templateNum = $this->userSettings->getSetting('regConfirmTemplate');
            $siteName = $this->siteSettings->getSetting('siteName');
            $title = 'Account activation - '.$siteName;
            $this->sendConfirmationMail($uMail,$uId,$confirmCode,$templateNum,$title,$async,['test'=>$test,'verbose'=>$verbose]);
            return 0;
        }

        /**Confirms user registration
         * @param int $id User ID
         * @param string $code Activation code
         * @param array $params
         * @returns int
         *      0 - All good.
         *      1 - User ID doesn't exist.
         *      2 - Confirmation code wrong.
         *      3 - Confirmation code expired.
         */

        function confirmRegistration(int $id, string $code, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            $res = $this->confirmCode($id,$code,'MailConfirm','MailConfirm_Expires',['test'=>$test,'verbose'=>$verbose]);
            if($res === 0){
                try{
                    $query = "UPDATE ".$this->sqlHandler->getSQLPrefix()."USERS
                                  SET Active = 1
                                  WHERE ID=:ID;";
                    $params = [[':ID',$id]];

                    if(!$test)
                        $this->sqlHandler->exeQueryBindParam($query,$params);
                    if($verbose)
                        echo 'Query to send '.$query.', with parameters: '.json_encode($params).EOL;

                    if(!$test)
                        $this->sqlHandler->exeQueryBindParam("UPDATE ".$this->sqlHandler->getSQLPrefix()."USERS_EXTRA
                                      SET MailConfirm = NULL, MailConfirm = NULL
                                      WHERE ID=:ID",[[':ID', $id]]);
                    if($verbose)
                        echo 'Query to send: UPDATE '.$this->sqlHandler->getSQLPrefix().'USERS_EXTRA
                                      SET MailConfirm = NULL, MailConfirm_Expires = NULL
                                      WHERE ID='.$id.EOL;
                }
                catch(\Exception $e){
                    //TODO LOG
                    return 4;
                }
            }
            return $res;
        }


        /** Sends out a password reset mail to the user
         * @param string $uMail User mail
         * @param array $params
         * @returns int
         *      -2 - internal server error
         *      0 - All good.
         *      1 - User Mail isn't registered.
         *      3 - Mail failed to send!
         */
        function pwdResetSend(string $uMail, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            $confirmCode = GeraHash(50);
            //See if user with said mail exists, if yes save his ID
            try{
                $query = 'SELECT * FROM
                      '.$this->sqlHandler->getSQLPrefix().'USERS INNER JOIN
                      '.$this->sqlHandler->getSQLPrefix().'USERS_EXTRA
                     ON '.$this->sqlHandler->getSQLPrefix().'USERS.ID = '.$this->sqlHandler->getSQLPrefix().'USERS_EXTRA.ID
                     WHERE '.$this->sqlHandler->getSQLPrefix().'USERS.Email = :Email';

                if($verbose)
                    echo 'Query to send: '.$query.EOL;

                $currentUserSettings = $this->sqlHandler->exeQueryBindParam(
                    $query,
                    [[':Email',$uMail]],
                    ['fetchAll'=>true]
                );
            }
            catch(\Exception $e){
                //TODO LOG
                return -2;
            }
            if(count($currentUserSettings)==0){
                if($verbose)
                    echo 'No users found!'.EOL;
                return 1;
            }
            //Reuse existing code if found
            $uId = $currentUserSettings[0][0];
            if(isset($currentUserSettings[0]['PWDReset']))
                $confirmCode = $currentUserSettings[0]['PWDReset'];

            //Set the new code
            if(!$this->createCode(
                $uId,
                $confirmCode,
                time()+$this->userSettings->getSetting('mailConfirmExpires')*60*60,
                'PWDReset',
                'PWDReset_expires',
                ['test'=>$test,'verbose'=>$verbose]
            ))
                return -2;

            //Now, send the mail to the user.
            if(!$test){
                return $this->sendConfirmationMail(
                    $uMail,
                    $uId,
                    $confirmCode,
                    $this->userSettings->getSetting('pwdResetTemplate'),
                    'Password Reset - '.$this->siteSettings->getSetting('siteName'),
                    false,
                    ['test'=>$test,'verbose'=>$verbose]
                );
            }
            if($verbose)
                echo 'Sending mail from template pwdResetTemplate to '.$uMail.EOL;
            return true;
        }

        /** Confirms a password reset code
         * @param int $id user ID
         * @param string $code confirmation code
         * @param array $params
         * @return int
         *      0 - All good.
         *      1 - User ID doesn't exist.
         *      2 - Confirmation code wrong.
         *      3 - Confirmation code expired.
         */
        function pwdResetConfirm(int $id, string $code, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            $res = $this->confirmCode($id,$code,'PWDReset','PWDReset_Expires',['test'=>$test,'verbose'=>$verbose]);
            if($res === 0){
                try{
                    if(!$test)
                        $this->sqlHandler->exeQueryBindParam("UPDATE ".$this->sqlHandler->getSQLPrefix()."USERS_EXTRA
                                      SET PWDReset = NULL, PWDReset_Expires = NULL
                                      WHERE ID=:ID",[[':ID', $id]]);
                    if($verbose)
                        echo 'Query to send: UPDATE '.$this->sqlHandler->getSQLPrefix().'USERS_EXTRA
                                      SET PWDReset = NULL, PWDReset_Expires = NULL
                                      WHERE ID='.$id.EOL;
                }
                catch(\Exception $e){
                    //TODO LOG
                    return 4;
                }
            }
            return $res;
        }


        /** Sends out a mail change mail to the user
         * @param string $uMail User mail
         * @param array $params
         * @returns int
         *      -2 - internal server error
         *      0 - All good.
         *      1 - User Mail isn't registered.
         *      3 - Mail failed to send!
         */
        function mailChangeSend(string $uMail, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            $confirmCode = GeraHash(50);
            //See if user with said mail exists, if yes save his ID
            try{
                $query = 'SELECT * FROM
                      '.$this->sqlHandler->getSQLPrefix().'USERS INNER JOIN
                      '.$this->sqlHandler->getSQLPrefix().'USERS_EXTRA
                     ON '.$this->sqlHandler->getSQLPrefix().'USERS.ID = '.$this->sqlHandler->getSQLPrefix().'USERS_EXTRA.ID
                     WHERE '.$this->sqlHandler->getSQLPrefix().'USERS.Email = :Email';

                if($verbose)
                    echo 'Query to send: '.$query.EOL;

                $currentUserSettings = $this->sqlHandler->exeQueryBindParam(
                    $query,
                    [[':Email',$uMail]],
                    ['fetchAll'=>true]
                );
            }
            catch(\Exception $e){
                //TODO LOG
                return -2;
            }
            if(count($currentUserSettings)==0){
                if($verbose)
                    echo 'No users found!'.EOL;
                return 1;
            }
            //Reuse existing code if found
            $uId = $currentUserSettings[0][0];
            if(isset($currentUserSettings[0]['PWDReset']))
                $confirmCode = $currentUserSettings[0]['PWDReset'];

            //Set the new code
            if(!$this->createCode(
                $uId,
                $confirmCode,
                time()+$this->userSettings->getSetting('mailConfirmExpires')*60*60,
                'MailConfirm',
                'MailConfirm_expires',
                ['test'=>$test,'verbose'=>$verbose]
            ))
                return -2;

            //Now, send the mail to the user.
            if(!$test)
                return $this->sendConfirmationMail(
                    $uMail,
                    $uId,
                    $confirmCode,
                    $this->userSettings->getSetting('emailChangeTemplate'),
                    'Email Change - '.$this->siteSettings->getSetting('siteName'),
                    false,
                    ['test'=>$test,'verbose'=>$verbose]
                );
            if($verbose)
                echo 'Sending mail from template emailChangeTemplate to '.$uMail.EOL;
            return true;
        }

        /** Confirms a mail change code
         * @param int $id user ID
         * @param string $code confirmation code
         * @param array $params
         * @return int
         *      0 - All good.
         *      1 - User ID doesn't exist.
         *      2 - Confirmation code wrong.
         *      3 - Confirmation code expired.
         */
        function mailChangeConfirm(int $id, string $code, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            $res = $this->confirmCode($id,$code,'MailConfirm','MailConfirm_Expires',['test'=>$test,'verbose'=>$verbose]);
            if($res === 0){
                try{
                    if(!$test)
                        $this->sqlHandler->exeQueryBindParam("UPDATE ".$this->sqlHandler->getSQLPrefix()."USERS_EXTRA
                                      SET MailConfirm = NULL, MailConfirm = NULL
                                      WHERE ID=:ID",[[':ID', $id]]);
                    if($verbose)
                        echo 'Query to send: UPDATE '.$this->sqlHandler->getSQLPrefix().'USERS_EXTRA
                                      SET MailConfirm = NULL, MailConfirm_Expires = NULL
                                      WHERE ID='.$id.EOL;
                }
                catch(\Exception $e){
                    //TODO LOG
                    return 4;
                }
            }
            return $res;
        }

        /** Confirms a code
         * @param int $id user ID
         * @param string $code confirmation code
         * @param array $params
         * @return true on success, false on failure
         */
        function createCode(int $id, string $code, int $expiryTime, string $correctField, string $expireField, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            $query = "UPDATE ".$this->sqlHandler->getSQLPrefix()."USERS_EXTRA
                                  SET ".$correctField."=:".$correctField.", ".$expireField."=:".$expireField."
                                  WHERE ID=:ID";
            $params = [[':ID', $id],[':'.$correctField, $code],[':'.$expireField, $expiryTime]];
            try{
                if(!$test)
                    $this->sqlHandler->exeQueryBindParam($query,$params);
                if($verbose)
                    echo 'Query to send: '.$query.' with parameters: '.json_encode($params).EOL;
            }
            catch(\Exception $e){
                //TODO LOG
                return false;
            }
            return true;
        }

        /** Confirms a code
         * @param int $id user ID
         * @param string $code confirmation code
         * @param string $correntField confirmation code feild
         * @param string $expireField confirmation code expiery field
         * @param array $params
         * @return int
         *      0 - All good.
         *      1 - User ID doesn't exist.
         *      2 - Confirmation code wrong.
         *      3 - Confirmation code expired.
         */
        function confirmCode(int $id, string $code, string $correntField, string $expireField, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            $userInfo = $this->sqlHandler->selectFromTable(
                $this->sqlHandler->getSQLPrefix().'USERS_EXTRA',['ID',$id,'='],[],['test'=>$test,'verbose'=>$verbose]
            );
            if(is_array($userInfo) && count($userInfo)>0){
                $userInfo = $userInfo[0];
                $correct = $userInfo[$correntField];
                $expires = $userInfo[$expireField];
                if($code==$correct){
                    if(time()<$expires){
                        return 0;
                    }
                    else
                        return 3;
                }
                else
                    return 2;
            }
            else
                return 1;
        }

        /** Sends activation mail to the user whoes mail is $uMail, id is $uId, and the code is $confirmCode
         * @param string $uMail User mail
         * @param int $uId User ID
         * @param string $confirmCode Confirmation code needed to send async mail
         * @param int $templateNum Template to use
         * @param string $title Mail title
         * @param array $params
         *
         * @returns int description in main function
         */
        function sendConfirmationMail(
            string $uMail, int $uId, string $confirmCode, int $templateNum, string $title, bool $async, array $params = [])
        {
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;

            if(!defined('mailHandler'))
                require 'mailHandler.php';
            $mail = new mailHandler($this->settings,['sqlHandler'=>$this->sqlHandler,'logger'=>$this->logger]);
            if(!$test){
                try{
                    if($async){
                        $token = $mail->createSecToken($uMail);
                        $mail->sendMailAsync( $uMail, $title, $token, ['',$this->siteSettings->getSetting('siteName').' automated email'],
                            '', $templateNum, '{"uId":'.$uId.',"Code":"'.$confirmCode.'"}',$type = 'template' );
                    }
                    else{
                        $mail->setTemplate($templateNum);
                        if($mail->sendMailTemplate(
                            [[$uMail]],
                            $title,
                            '',
                            '{"uId":'.$uId.',"Code":"'.$confirmCode.'"}',
                            ['',$this->siteSettings->getSetting('siteName').' automated email'])
                        )
                            return 0;
                        else
                            return 3;
                    }
                }
                catch(\Exception $e){
                    //TODO LOG
                    return 3;
                }
            }
            if($verbose)
                echo 'Sending async email about account activation'.EOL;
            return 0;
        }

        /** Bans user for $minutes minutes.
         *  If $minutes is 0, will ban for 1,000,000,000 minutes.
         * @param int $minutes Minutes to ban - 0 up to 1,000,000,000
         * @param mixed $identifier Either an ID (Int) or a mail (String)
         * @param array $params
         *
         * @returns int
         *          0 - All good (whether the user was found or not)
         *          1 - No user found.
         */
        function banUser(int $minutes, $identifier, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            //Sanitation
            if($minutes = 0 || $minutes > 1000000000)
                $minutes = 1000000000;
            if($minutes<0)
                $minutes = 0;

            if(gettype($identifier) == 'integer'){
                $cond = ['ID',$identifier,'='];
            }
            else{
                $cond = ['ID',[[$this->sqlHandler->getSQLPrefix().'USERS',['Email','1@1.co','='],['ID'],[],'SELECT']],'IN'];
            }

            $res = $this->sqlHandler->updateTable(
                $this->sqlHandler->getSQLPrefix().'USERS_EXTRA',
                ['Banned_Until ='.(time()+60*$minutes)],
                $cond,
                ['returnRows'=>true,'test'=>$test,'verbose'=>$verbose]
            );
            //Rows affected are opposite to our return code
            if($res == 1)
                $res = 0;
            else
                $res = 1;

            return $res;

        }


        /**LogOut function - logs user out as long as his session is registered.
        TODO remember! Once distributed mode is implemented, a synchronizer will be needed
         * @param array $params of the form:
         *                      'oldSesID' string|null, default '' - if not empty, will cause a remote logOut
         *                                 of a user with said sessionID ON THIS NODE / REDIS SERVER ONLY!
         *                      'forgetMe' bool, default true - Control whether we reset the authDetails in the users table,
         *                                  which'd make the server forget all user reconnect tokens - if
         *                                  @$sesOnly is false, this does not matter.
         *                      'sesOnly' bool, default false - Controls whether we only reset local session data, or update DB.
         *
         */
        function logOut($params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            //Set defaults
            if(!isset($params['oldSesID']))
                $oldSesID = '';
            else
                $oldSesID = $params['oldSesID'];

            if(!isset($params['forgetMe']))
                $forgetMe = true;
            else
                $forgetMe = $params['forgetMe'];

            if(!isset($params['sesOnly']))
                $sesOnly = false;
            else
                $sesOnly = $params['sesOnly'];

            //If you are logging somebody out, save your own session to be logged back in, don't.
            if($oldSesID=='')
                $oldSesID = session_id();
            else
                $currSesID = session_id();

            //For security reasons, manually logging out will reset the "remember me" for ALL devices
            $params = [];
            if(!$sesOnly)
                if($forgetMe){
                    $query = 'UPDATE '.$this->sqlHandler->getSQLPrefix().'USERS SET
                SessionID=NULL, authDetails=:authDetails WHERE SessionID=:SessionID';
                    array_push($params,[':authDetails', NULL],[':SessionID', $oldSesID]);
                }
                else{
                    $query = 'UPDATE '.$this->sqlHandler->getSQLPrefix().'USERS SET
                SessionID=NULL WHERE SessionID=:SessionID';
                    array_push($params,[':SessionID', $oldSesID]);
                }


            if (!$test) {
                //Remove old session info from the database;
                if(!$sesOnly)
                    $this->sqlHandler->exeQueryBindParam($query,$params);

                //Will log out user with a specific session ID remotely - but also the current user!
                session_destroy();
                session_id($oldSesID);
                session_start();
                session_unset();
                session_destroy();
                //If you were logging somebody out - log yourself back in
                if(isset($currSesID)){
                    session_id($currSesID);
                    session_start();
                }
                else{
                    session_start();
                    session_regenerate_id();
                }
                $_SESSION['discard_after'] = time() + $this->siteSettings->getSetting('maxInacTime');
            }
            if ($verbose)
                echo 'Test user with session '.$oldSesID.' logged out!'.EOL;

        }


        /** Main login function
         * @param string[] $inputs array of inputs needed to log in.
         * @param array $params
         *
         * @returns mixed
         *
         *      0 all good - without rememberMe
         *      1 username/password combination wrong
         *      2 expired auto login token
         *      3 login type not allowed
         *      32-byte hex encoded session ID string - The token for your next automatic relog, if you logged automatically.
         *      JSON encoded array of the form {'iv'=><32-byte hex encoded string>,'sesID'=><32-byte hex encoded string>}
         */
        function logIn(array $inputs, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            $log = $inputs["log"];
            if($this->userSettings->getSetting('rememberMe') < 1)
                unset($inputs["userID"]);

            //If trying to log in temporary when the site doesn't allow it, tell the client so and return
            if( ($this->userSettings->getSetting('rememberMe') < 1) && $log=='temp'){
                return 3;
            }

            //Fetch table row that matches the username
            $prefix =  $this->sqlHandler->getSQLPrefix();
            $checkRes = $this->sqlHandler->selectFromTable(
                $prefix.'USERS JOIN '.$prefix.'USERS_EXTRA ON '.$prefix.'USERS.ID = '.$prefix.'USERS_EXTRA.ID',
                ['Email', $inputs["m"],'='],
                [],
                ['noValidate'=>true,'test'=>$test,'verbose'=>$verbose]
            );
            // Check if it found something
            if (is_array($checkRes) && count($checkRes)>0) {
                //Get auth details
                $authFullDetails = json_decode($checkRes[0]['authDetails'], true);
                //If user is trying to relog automatically to a device that doesn't exist - he wont be logged in.
                if($log=='temp' && !isset($authFullDetails[$inputs["userID"]]))
                    $log = 'wrong';
                //Else decode the relevant userID details
                else if($log=='temp'){
                    $authDetails =  json_decode($authFullDetails[$inputs["userID"]], true);
                    $key = stringScrumble($authDetails['nextLoginID'],$authDetails['nextLoginIV']);
                    //If auto-login for this device expired, return 2
                    if(isset($authDetails['expires'])){
                        if(($authDetails['expires'] != 0) &&
                            ($authDetails['expires'] < time())){
                            return 2;
                        }
                    }
                }
                // Check if the password matches OR if temp log and user identifier is consistent with stored one.
                // If all good, log the user in
                if (    (
                        $log=='in'
                        &&
                        password_verify($inputs["p"], $checkRes[0]['Password'])==true
                    )
                    ||(
                        $log=='temp'
                        &&
                        hash_equals(
                            openssl_decrypt(base64_encode( hex2bin( $inputs["sesKey"])),'aes-256-ecb' , hex2bin($key) ,OPENSSL_ZERO_PADDING)
                            ,$inputs["userID"]
                        )
                    )
                ){
                    //-----------------------Logout any user with the current old sessionID out-------------------------
                    //Only erases their Session data on the server, not data in DB
                    $this->logOut(['oldSesID' => $checkRes[0]['SessionID'],'forgetMe' => false,'sesOnly' => true,'test'=>$test,'verbose'=>$verbose]);

                    //------------------Regenerate session ID and update user table to current user ID------------------
                    if(!$test)
                        session_regenerate_id();
                    $query = 'UPDATE '.$this->sqlHandler->getSQLPrefix().'USERS SET SessionID
                    =:SessionID WHERE Email=:Email';
                    if (!$test)
                        $this->sqlHandler->exeQueryBindParam($query,[[':Email', $inputs["m"]],[':SessionID', session_id()]]);

                    //--------------------Encryption and stuff - temp login-------------------
                    if(isset($inputs["userID"])){
                        // Either way, we will be using this hex string as part of the login.
                        $hex_secure = false;
                        while(!$hex_secure)
                            $hex=bin2hex(openssl_random_pseudo_bytes(16,$hex_secure));
                        //Specify after how long this auto-login will expire (if it will)
                        $expires = ($this->userSettings->getSetting('userTokenExpiresIn') == 0)?
                            0 : time()+$this->userSettings->getSetting('userTokenExpiresIn');
                        // If user logged in with a password, send him and update in the DB a new IV for the next auto-connect.
                        // Else use the old.
                        if($log!='temp'){
                            $iv_secure = false;
                            while(!$iv_secure)
                                $iv=bin2hex(openssl_random_pseudo_bytes(16,$iv_secure));
                        }
                        else{
                            $iv = $authDetails['nextLoginIV'];
                            $resHex = stringScrumble($authDetails['nextLoginID'],$hex);
                            $e = openssl_encrypt( $resHex, 'aes-256-ecb', hex2bin( $key ), OPENSSL_ZERO_PADDING);
                        }


                        $relogData=[
                            "nextLoginID" => $hex,
                            "nextLoginIV" => $iv,
                            "expires" => $expires
                        ];

                        $relogData = json_encode($relogData);

                        $authFullDetails[$inputs["userID"]] = $relogData;

                        $authFullDetails = json_encode($authFullDetails);
                        //Update database with it

                        $query = 'UPDATE '.$this->sqlHandler->getSQLPrefix().'USERS SET
                        authDetails=:authFullDetails WHERE Email=:Email';
                        //If it was test, test results well be echoed later, nothing left to do.
                        if (!$test)
                            $this->sqlHandler->exeQueryBindParam($query,[[':authFullDetails', $authFullDetails],[':Email', $inputs["m"]]]);
                        if ($verbose)
                            echo 'Executing '.$query.EOL;
                    }

                    /* 1. If userID is unset, it means either it was a one-time login or server seeting RememberMe is off
                     * 2. If it was set but the login was 'temp', it means the user has an unchanged $iv and just needs a new $hex,
                     *    but due to the protocol you have to encrypt the old and new hexes, scrumbled.
                     * 3. Else, user is logging in with a passwords and wants to be remembered - send a new $iv and a new $hex.
                     * */
                    if(!isset($inputs["userID"]))
                        $res = 0;
                    else if($log=='temp')
                        $res = bin2hex( base64_decode( $e ));
                    else
                        $res = json_encode(array('iv' => $iv, 'sesID' => $hex));

                    //---------Fetch all the extra user data and update current session-------------
                    $this->login_updateSessionCore($checkRes, ['test'=>$test,'verbose'=>$verbose]);
                    //--------------------------Update login history------------------------------
                    $this->login_updateHistory($checkRes, ['test'=>$test,'verbose'=>$verbose]);
                    return $res;
                }
                else{
                    if($verbose)
                        echo "Test User with mail \"" . $inputs["m"] . "\" doesn't exist - or password/session key is wrong!";
                    return 1;
                }
            }
            //if no matching password is found, report it and exit
            else{

                if($verbose)
                    echo "Test User with mail \"" . $inputs["m"] . "\" doesn't exist - or password/session key is wrong!";
                return 1;
            }

        }


        /** Updates session with core values
         * @param array $checkRes results gotten from the DB in an earlier stage
         * @param array $params array of the form:
         *                     'override' => bool, default false - whether to reuse all earlier session details,
         *                      or completely override them
         */
        private function login_updateSessionCore(array $checkRes,array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            isset($params['override'])?
                $override = $params['override'] : $override = false;
            //Update current session - this is how the rest of the app knows the user is logged in
            $_SESSION['logged_in']=true;
            $data = [];
            //Fetch existing details if they exist
            if(isset( $_SESSION['details']) && !$override)
                $data = json_decode( $_SESSION['details'],true);
            //Get arguements from core user info
            $args = [
                "ID",
                "Username",
                "Email",
                "Active",
                "Auth_Rank",
                "Banned_Until"
            ];
            foreach($args as $val){
                $data[$val]=$checkRes[0][$val];
            }
            if(!$test)
                $_SESSION['details']=json_encode($data);
            if($verbose)
                echo 'Setting new session details:'.json_encode($data).EOL;
        }

        /** Updates login history TODO - once number of logins exceeds 200, delete the oldest 100 and send them to archive.
         * @param array $checkRes results gotten from the DB in an earlier stage
         * @param array $params
         */
        private function login_updateHistory(array $checkRes,array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            //First save country code
            require_once 'ext/GeoIP/vendor/autoload.php';

            // This creates the Reader object, which should be reused across
            // lookups.
            try{
                $reader = new Reader($this->settings->getSetting('absPathToRoot').SETTINGS_DIR_FROM_ROOT.'/geoip-db/GeoLite2-Country.mmdb');
                // Replace "city" with the appropriate method for your database, e.g.,
                // "country".
                try{
                    $record = $reader->country($_SERVER['REMOTE_ADDR']);
                    $countryRes=$record->country->isoCode;
                }
                catch(\Exception $e){
                    if($verbose)
                        echo 'IP not in country db!'.EOL;
                    $countryRes='Unkonwn';
                }
            }
            catch(\Exception $e){
                if($verbose)
                    echo 'Country DB Does not exist!'.EOL;
                $countryRes='Unkonwn';
            }

            //Check if the username-ip combo already exists
            $u = $checkRes[0]["Username"];
            $checkRes = $this->sqlHandler->selectFromTable($this->sqlHandler->getSQLPrefix().'LOGIN_HISTORY',
                [['Username', $u,'='],['IP', [$_SERVER['REMOTE_ADDR'],'STRING'],'='],'AND'],
                [],
                ['test'=>$test,'verbose'=>$verbose]
            );
            //if yes update them
            if (is_array($checkRes) && count($checkRes)>0) {
                if(!defined('hArray'))
                    require __DIR__ . '/../util/hArray.php';
                //Remember that we got the IP history until now as one of the results - update it!
                $lHist= new hArray($checkRes[0]['Login_History']);
                $lHist->hArrPush(strtotime(date("YmdHis")));
                $query = 'UPDATE '.$this->sqlHandler->getSQLPrefix().'LOGIN_HISTORY SET Login_History=:Login_History
                                           WHERE Username=:Username AND IP=:IP';
                $params = [];
                array_push($params,[':Login_History', $lHist->hArrGet()],[':Username', $u],[':IP', $_SERVER['REMOTE_ADDR']]);
                if(!$test)
                    $this->sqlHandler->exeQueryBindParam($query,$params);
                if($verbose)
                    echo 'Updating login history for User/IP '.$u.'/'.$_SERVER['REMOTE_ADDR'].EOL;
            }
            else{
                $hInit = strtotime(date("YmdHis"));
                $query = 'INSERT INTO '.$this->sqlHandler->getSQLPrefix().'LOGIN_HISTORY(Username, IP, Country, Login_History)
                                           VALUES (:Username, :IP, :Country, :Login_History)';
                $params = [];
                array_push($params,[':Login_History', $hInit.'#'],[':Username', $u],[':IP', $_SERVER['REMOTE_ADDR']],[':Country', $countryRes]);
                if(!$test)
                    $this->sqlHandler->exeQueryBindParam($query,$params);
                if($verbose)
                    echo 'Creating new login history for User/IP '.$u.'/'.$_SERVER['REMOTE_ADDR'].EOL;
            }
        }


        /** Checks whether the user is eligible to be logged into.
         * @param mixed $identifier - Either a number (id) or a string (mail)
         * @param array $params array of the form:
         *              'allowWhitelistedIP' = > string, checks for whitelisted IP that matches the string
         *              'allowCode' => string, code provided by the user. Will compare it to MailConfirm (and check expiry)
         *              TODO Add more methods once sms / pre-shared secret 2FAs are supported
         * @param mixed $test
         *  @returns int
         *          0 - User is eligible to be logged into
         *          1 - User can't be logged into due to being "Suspicious"
         *          2 - User is "Suspicious", but may be logged into due to some condition (2FA, Whitelisted IP, etc).
         *              Notice that "User Does Not Exist" is an acceptable condition here.
         */
        function checkUserLogin($identifier, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;

            $prefix = $this->sqlHandler->getSQLPrefix();

            //Works both with user mail and ID
            if(gettype($identifier) == 'integer'){
                $identityCond = [$prefix.'USERS.ID',$identifier,'='];
            }
            else{
                $identityCond = [$prefix.'USERS.Email',[$identifier,'STRING'],'='];
            }

            //Initial check
            $query = $this->sqlHandler->selectFromTable(
                $prefix.'USERS INNER JOIN '.$prefix.'USERS_EXTRA ON '.$prefix.'USERS.ID = '.$prefix.'USERS_EXTRA.ID',
                [
                    $identityCond,
                    [
                        [$prefix.'USERS_EXTRA.Suspicious_Until','ISNULL'],
                        [$prefix.'USERS_EXTRA.Suspicious_Until',time(),'<'],
                        'OR'
                    ],
                    'AND'
                ],
                ['Suspicious_Until', '"User" AS Allowed'],
                ['justTheQuery'=>true,'test'=>false]
            );
            //This will return -1 / -1 if the user does not exist
            $query .=' UNION '.
                $this->sqlHandler->selectFromTable(
                    $prefix.'USERS',
                    [
                        [
                            [
                                $prefix.'USERS',
                                $identityCond,
                                [],
                                [],
                                'SELECT'
                            ],
                            'EXISTS'
                        ],
                        'NOT'
                    ],
                    ['-1 AS Suspicious_Until', '-1 AS Allowed'],
                    ['justTheQuery'=>true,'test'=>false]
                );
            //Whitelisted IP Check
            if(isset($params['allowWhitelistedIP']))
                $query .=' UNION '.
                    $this->sqlHandler->selectFromTable(
                        $prefix.'IP_LIST,'.$prefix.'USERS INNER JOIN '.$prefix.'USERS_EXTRA ON '.$prefix.'USERS.ID = '.$prefix.'USERS_EXTRA.ID',
                        [
                            $identityCond,
                            [$prefix.'IP_LIST.IP',$params['allowWhitelistedIP'],'='],
                            [$prefix.'IP_LIST.IP_Type',1,'='],
                            'AND'
                        ],
                        ['Suspicious_Until', '"IP" AS Allowed'],
                        ['justTheQuery'=>true,'test'=>false]
                    );
            //Code check
            if(isset($params['allowCode']))
                $query .=' UNION '.
                    $this->sqlHandler->selectFromTable(
                        $prefix.'USERS INNER JOIN '.$prefix.'USERS_EXTRA ON '.$prefix.'USERS.ID = '.$prefix.'USERS_EXTRA.ID',
                        [
                            $identityCond,
                            [$prefix.'USERS.Active',1,'='],
                            [$prefix.'USERS_EXTRA.MailConfirm',[$params['allowCode'],'STRING'],'='],
                            [
                                [[$prefix.'USERS_EXTRA.MailConfirm_Expires','ISNULL'],'NOT'],
                                [$prefix.'USERS_EXTRA.MailConfirm_Expires',time(),'>'],
                                'AND'
                            ],
                            'AND'
                        ],
                        ['Suspicious_Until', '"Code" AS Allowed'],
                        ['justTheQuery'=>true,'test'=>false]
                    );

            if($verbose)
                echo 'Query to send: '.$query.EOL;
            $tempRes = $this->sqlHandler->exeQueryBindParam($query,[],['fetchAll'=>true]);

            if($verbose){
                var_dump($tempRes);
            }

            $res = 1;

            //If the user is not suspicious, we got our user
            $tempCount = count($tempRes);
            for($i=0; $i<$tempCount; $i++){
                if($tempRes[$i]['Allowed'] == 'User')
                    $res = 0;
                elseif($res === 1 && $tempRes[$i]['Allowed']){
                    $res = 2;
                }
            }

            return $res;

        }

    }
}
?>