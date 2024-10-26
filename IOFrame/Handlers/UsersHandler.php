<?php
namespace IOFrame\Handlers{
    use RobThree\Auth\TwoFactorAuth;
    use RobThree\Auth\TwoFactorAuthException;

    define('IOFrameHandlersUsersHandler',true);

    /**
     * TODO Clean up everything, especially all those functions using sendConfirmationMail() / createCode()
     * TODO #2 Add supports to N-time login tokens
     * Handles user registration, changes, login, etc.
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */
    class UsersHandler extends \IOFrame\Abstract\DBWithCache
    {

        /* @var ?\IOFrame\Handlers\SettingsHandler $userSettings User settings handler
         * */
        public ?\IOFrame\Handlers\SettingsHandler $userSettings;

        /* @var ?\IOFrame\Handlers\SettingsHandler $siteSettings Site settings handler
         * */
        public ?\IOFrame\Handlers\SettingsHandler $siteSettings;

        /**
         * Basic construction function - as in Abstract/DBWithCache
         * @param SettingsHandler $localSettings Settings handler containing LOCAL settings.
         * @param array $params Potentially containing siteSettings
         * @throws \Exception
         * @throws \Exception
         */
        public function __construct(SettingsHandler $localSettings, $params = []){
            parent::__construct($localSettings,array_merge($params,['logChannel'=>\IOFrame\Definitions::LOG_USERS_CHANNEL]));

            //Initialize settings
            $this->userSettings = new \IOFrame\Handlers\SettingsHandler(
                $localSettings->getSetting('absPathToRoot').'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/userSettings/',
                $this->defaultSettingsParams
            );
            if(isset($params['siteSettings']))
                $this->siteSettings = $params['siteSettings'];
            else
                $this->siteSettings = new \IOFrame\Handlers\SettingsHandler(
                    $localSettings->getSetting('absPathToRoot').'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/siteSettings/',
                    $this->defaultSettingsParams
                );
        }


        /** Main registration function
         * @param string[] $inputs array of inputs needed to register of the form:
         *                  'u' - Username (required but may be empty for random username)
         *                  'm' - Mail
         *                  'p' - Password
         *                  'r' - rank
         * @param array $params
         *              'activateToken' => string, default null - checks a token, and activates account if it allows either any activation, or activation of provided email.
         *              'tokenConsumeUses' => int, default 1 - if activateToken is provided, indicates how many uses to consume on success
         *              'considerActive' => bool, default false - if true, will consider user active no matter what
         *              'language' =>  string, default null - if set, will search for relevant locale settings to send relevant mails, and set this as the user's preferred language
         * @returns int
         *      0 - success
         *      -2 - failed - registration not allowed!
         *      -1 - failed - incorrect input - wrong or missing
         *      1 - failed - username already in use
         *      2 - failed - email already in use
         *      3 - failed - server error
         *      4 - registration would succeed, but token does not allow activation
         */
        function regUser(#[\SensitiveParameter] array $inputs, array $params = []): int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $activateToken = $params['activateToken'] ?? null;
            $tokenConsumeUses = $params['tokenConsumeUses'] ?? 1;
            $considerActive = $params['considerActive'] ?? false;
            $language = $params['language'] ?? null;

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

            //Token activation
            if($activateToken){
                if($this->confirmInviteToken($activateToken,array_merge($params,['consume'=>$tokenConsumeUses,'mail'=>$inputs["m"]])) !== 0)
                    return  4;
                $considerActive = true;
            }

            //Make user if all good
            $res = $this->reg_makeUserCore($inputs, $hash, ['test'=>$test,'verbose'=>$verbose,'rank'=> $inputs['r'] ?? null,'active'=>(($considerActive || $activateToken)? 1 : 0)]);
            if($res !== 0){
                if($activateToken && $tokenConsumeUses)
                    $this->logger->error('Consumed token uses without creating user',['mail'=>$inputs["m"],'token'=>$activateToken]);
                return $res;
            }

            //Add user extra data
            $res = $this->reg_makeUserExtra($inputs,['test'=>$test,'verbose'=>$verbose, 'language'=>$language]);
            if($res !== 0){
                if($activateToken && $tokenConsumeUses)
                    $this->logger->error('Consumed token uses without creating user',['mail'=>$inputs["m"],'token'=>$activateToken]);
                $this->logger->error('Created user core but could not continue',['mail'=>$inputs["m"]]);
                return $res;
            }

            //Make an empty Auth table entry for the user
            $res = $this->reg_makeUserAuth($inputs,['test'=>$test,'verbose'=>$verbose]);
            if($res !== 0){
                if($activateToken && $tokenConsumeUses)
                    $this->logger->error('Consumed token uses without creating user',['mail'=>$inputs["m"],'token'=>$activateToken]);
                $this->logger->error('Created user extra but could not continue',['mail'=>$inputs["m"]]);
                return $res;
            }

            $regConfirmSetting = $this->userSettings->getSetting('regConfirmMail_'.$language) ?? $this->userSettings->getSetting('regConfirmMail');
            if($regConfirmSetting && !$considerActive){
                $this->accountActivation($inputs["m"],null,['test'=>$test,'verbose'=>$verbose]);
            }

            if($verbose)
                echo "User Added!".' Values are :'.$inputs["u"].', '.$inputs["m"].'.';

            return 0;

        }


        /** Checks if mail or username are taken
         * @param string[] $inputs array of inputs needed to log in.
         * @param array $params
         *
         * @return int
         */
        private function reg_checkExistingUserOrMail(array $inputs, array $params = []): int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $checkRes=$this->SQLManager->selectFromTable($this->SQLManager->getSQLPrefix().'USERS',[['Email', $inputs["m"],'='],['Username', $inputs["u"],'='],'OR'],
                [],['noValidate'=>true,'test'=>$test,'verbose'=>$verbose]);

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
            elseif(!empty($checkRes) && !is_integer($checkRes))
                return 3;
            return 0;
        }

        /** Makes core user
         * @param string[] $inputs array of inputs needed to log in.
         * @param string $hash password hash
         * @param array $params
         *              active - bool, default 0 - whether the account should be active or not on creation. Overridden by user setting regConfirmMail, or install session.
         *              rank - bool, default 9999 - User rank to set. Defaults to lowest rank (9999), highest rank is 0.
         *              language -  string, default null - if set, will search for relevant locale settings to send relevant mails
         * @returns int description in main function
         */
        private function reg_makeUserCore(array $inputs, #[\SensitiveParameter] string $hash, array $params = []): int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $active = $params['active'] ?? 0;
            $rank = $params['rank'] ?? 9999;
            $language = $params['language'] ?? null;
            $query = "INSERT INTO ".$this->SQLManager->getSQLPrefix().
                "USERS(Username, Password, Email, Active, Auth_Rank, SessionID)
             VALUES (:Username, :Password, :Email,:Active, :Auth_Rank,:SessionID)";
            $queryBind = [];
            array_push($queryBind,[':Username', $inputs["u"]],[':Password', $hash],[':Email', $inputs["m"]]);
            //Decides whether to activate user on creation or not
            if($this->userSettings->getSetting('regConfirmMail_'.$language) ?? $this->userSettings->getSetting('regConfirmMail') ){
                $queryBind[] = [':Active', $active];
            }
            else
                $queryBind[] = [':Active', 1];
            //Deciding whether to give a user a specific rank or not
            $queryBind[] = [':Auth_Rank', $rank];
            //Push the session ID
            $queryBind[] = [':SessionID', session_id()];
            if(!$test)
                try{
                    $this->SQLManager->exeQueryBindParam($query,$queryBind);
                }
                catch(\Exception $e){
                    $this->logger->error('Could not create user core',['username'=>$inputs["u"],'mail'=>$inputs["m"],'exception'=>$e->getMessage(),'trace'=>$e->getTrace()]);
                    return 3;
                }
            if($verbose)
                echo 'Executing query '.$query.' with bindings '.json_encode($queryBind).EOL;
            return 0;
        }


        /** Adds the auth info for the user - by default, it starts with just the ID and empty columns.
         * @param string[] $inputs array of inputs needed to log in.
         * @param array $params
         *
         * @return int
         */
        private function reg_makeUserAuth(array $inputs, array $params = []): int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $query = "INSERT INTO ".$this->SQLManager->getSQLPrefix()."USERS_AUTH(ID) SELECT ID FROM ".$this->SQLManager->getSQLPrefix()."USERS WHERE Username=:Username";
            //Add extra data
            if(!$test)
                try{
                    $this->SQLManager->exeQueryBindParam($query,[[':Username',$inputs["u"]]]);
                }
                catch(\Exception $e){
                    $this->logger->error('Could not create user auth',['username'=>$inputs["u"],'exception'=>$e->getMessage(),'trace'=>$e->getTrace()]);
                    return 3;
                }
            if($verbose)
                echo 'Executing query '.$query.EOL;
            return 0;
        }

        /** Adds extra value to table - changed from app to app
         * @param string[] $inputs array of inputs needed to log in.
         * @param array $params
         * @returns int description in main function
         */
        private function reg_makeUserExtra(array $inputs, array $params = []): int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $language = $params['language'] ?? null;
            //Need to fetch the ID of the user we just created in order to add meta-data

            //Get the ID of the user we just created
            $res = $this->SQLManager->selectFromTable(
                $this->SQLManager->getSQLPrefix().'USERS',['Username',$inputs["u"],'='],[],['noValidate'=>true,'test'=>$test,'verbose'=>$verbose]
            );
            if(!is_array($res)){
                return 3;
            }
            //In case we are in test mode, mock uId and uMail
            $uId = !$test? $res[0]['ID'] : 1;
            $uMail = !$test? $res[0]['Email'] : 'test@test.com';

            //Add extra data
            $query = "INSERT INTO ".$this->SQLManager->getSQLPrefix()."USERS_EXTRA(ID, Created, Preferred_Language)
                                  VALUES (:ID,:Created, :Preferred_Language)";
            if(!$test)
                try{
                    $this->SQLManager->exeQueryBindParam($query,[[':ID', $uId],[':Created', date("YmdHis")],[':Preferred_Language', $language]]);
                }
                catch(\Exception $e){
                    $this->logger->error('Could not create user extra',['username'=>$inputs["u"],'mail'=>$uMail,'exception'=>$e->getMessage(),'trace'=>$e->getTrace()]);
                    return 3;
                }
            if($verbose)
                echo 'Executing query '.$query.EOL;

            return 0;
        }


        /** Changes the password
         * @param int $userID User ID
         * @param string $plaintextPassword User mail
         * @param array $params
         * @return int
         */
        function changePassword(int $userID,#[\SensitiveParameter] string $plaintextPassword, array $params = []): int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $hash = password_hash($plaintextPassword, PASSWORD_DEFAULT);
            $userInfo = $this->SQLManager->selectFromTable(
                $this->SQLManager->getSQLPrefix().'USERS',['ID',$userID,'='],['ID'],['test'=>$test,'verbose'=>$verbose]
            );
            if(is_array($userInfo) && count($userInfo)>0){
                $this->SQLManager->updateTable(
                    $this->SQLManager->getSQLPrefix().'USERS',
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
         *                  'keepActive'=> bool, default to user setting regConfirmMail - if not true, will deactivate an account on Email change
         *                  'language' =>  string, default null - if set, will search for relevant locale settings to send relevant mails
         * @returns int 0 on success
         *              1 if userID does not exist
         *              2 Email already in use
         */
        function changeMail(int $userID,string $newEmail, array $params = []): int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $language = $params['language'] ?? null;
            $keepActive = $params['keepActive'] ?? $this->userSettings->getSetting('regConfirmMail') || ($language && $this->userSettings->getSetting('regConfirmMail_' . $language));

            $tname = $this->SQLManager->getSQLPrefix().'USERS';
            $userInfo = $this->SQLManager->selectFromTable($tname,
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

                $this->SQLManager->updateTable(
                    $this->SQLManager->getSQLPrefix().'USERS',
                    ['Email = "'.$newEmail.'"'.($keepActive?'':',Active = 0')],
                    ['ID',$userID,'='],
                    ['test'=>$test,'verbose'=>$verbose]
                );
                if(!$keepActive)
                    $this->accountActivation($newEmail,$userID,['test'=>$test,'verbose'=>$verbose]);
                return 0;
            }
            else
                return 1;
        }


        /** Creates (and potentially resets) the account activation parameters, as well as sends a (new) mail
         * @param string $uMail User mail
         * @param int|null $uId User ID
         * @param array $params of the form
         *                          'async' - bool, default true - If true, will try to send the mail asynchronously
         *                          'language' =>  string, default null - if set, will search for relevant locale settings to send relevant mails
         * @return int|string
         */
        function accountActivation(string $uMail, int $uId = null, array $params = []): int|string {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $async = $params['async'] ?? true;
            $language = $params['language'] ?? null;

            //Find user ID if it was not provided
            if($uId === null){
                $uId = $this->SQLManager->selectFromTable(
                    $this->SQLManager->getSQLPrefix().'USERS',[['Email',$uMail,'='],['Active',0,'='],'AND'],['ID'],['test'=>$test,'verbose'=>$verbose]
                );
                if($test)
                    $uId = 1;
                elseif(!is_array($uId) || count($uId)==0)
                    return -2;
                else
                    $uId = $uId[0]['ID'];
            }

            $hex_secure = false;
            $confirmCode = '';
            while(!$hex_secure)
                $confirmCode=bin2hex(openssl_random_pseudo_bytes(64,$hex_secure));

            //Set the new code
            if(!$this->createCode(
                $uId,
                $confirmCode,
                $this->userSettings->getSetting('mailConfirmExpires')*60*60,
                'ACCOUNT_ACTIVATION',
                ['test'=>$test,'verbose'=>$verbose]
            ))
                return -3;

            $templateNum = $this->userSettings->getSetting('regConfirmTemplate_'.$language) ?? $this->userSettings->getSetting('regConfirmTemplate');
            $title = $this->userSettings->getSetting('regConfirmTitle_'.$language) ?? $this->userSettings->getSetting('regConfirmTitle');
            $res = $this->sendConfirmationMail($uMail,$uId,$confirmCode,$templateNum,$title,$async,['test'=>$test,'verbose'=>$verbose]);
            if(!$async)
                return $res === 0? 0 : -1;
            else
                return is_string($res) ? $res : -1;
        }

        /**Confirms user registration
         * @param int $id User ID
         * @param string $code Activation code
         * @param array $params
         * @return int|mixed
         */

        function confirmRegistration(int $id,#[\SensitiveParameter] string $code, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $res = $this->confirmCode($id,$code,'ACCOUNT_ACTIVATION',['test'=>$test,'verbose'=>$verbose]);

            if($res === 0){
                try{
                    $query = "UPDATE ".$this->SQLManager->getSQLPrefix()."USERS
                                  SET Active = 1
                                  WHERE ID=:ID;";
                    $params = [[':ID',$id]];

                    if(!$test)
                        $this->SQLManager->exeQueryBindParam($query,$params);
                    if($verbose)
                        echo 'Query to send '.$query.', with parameters: '.json_encode($params).EOL;
                }
                catch(\Exception $e){
                    $this->logger->error('Could not confirm valid registration',['id'=>$id,'code'=>$code,'exception'=>$e->getMessage(),'trace'=>$e->getTrace()]);
                    return 4;
                }
            }
            return $res;
        }


        /** Sends out a password reset mail to the user
         * @param string $uMail User mail
         * @param array $params
         *                  'language' =>  string, default null - if set, will search for relevant locale settings to send relevant mails
         * @returns int|string
         *      -2 - internal server error
         *      [not async] 0 - All good.
         *      1 - User Mail isn't registered.
         *      3 - Mail failed to send!
         *      [async] see Mailhandler->sendMailAsync() with successQueue==true
         */
        function pwdResetSend(string $uMail, array $params = []): int|string {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $language = $params['language'] ?? null;
            isset($params['async'])?
                $async = $params['async'] : $async = true;

            $hex_secure = false;
            $confirmCode = '';
            while(!$hex_secure)
                $confirmCode=bin2hex(openssl_random_pseudo_bytes(64,$hex_secure));

            //See if user with said mail exists, if yes save his ID
            try{
                $query = 'SELECT * FROM
                      '.$this->SQLManager->getSQLPrefix().'USERS INNER JOIN
                      '.$this->SQLManager->getSQLPrefix().'USERS_EXTRA
                     ON '.$this->SQLManager->getSQLPrefix().'USERS.ID = '.$this->SQLManager->getSQLPrefix().'USERS_EXTRA.ID
                     WHERE '.$this->SQLManager->getSQLPrefix().'USERS.Email = :Email';

                if($verbose)
                    echo 'Query to send: '.$query.EOL;

                $currentUserSettings = $this->SQLManager->exeQueryBindParam(
                    $query,
                    [[':Email',$uMail]],
                    ['fetchAll'=>true]
                );
            }
            catch(\Exception $e){
                $this->logger->warning('Could not select from DB for pwdReset',['mail'=>$uMail,'exception'=>$e->getMessage(),'trace'=>$e->getTrace()]);
                return -2;
            }
            if(count($currentUserSettings)==0){
                if($verbose)
                    echo 'No users found!'.EOL;
                return 1;
            }
            //Reuse existing code if found
            $uId = $currentUserSettings[0]['ID'];
            if(isset($currentUserSettings[0]['PWDReset']))
                $confirmCode = $currentUserSettings[0]['PWDReset'];

            //Set the new code
            if(!$this->createCode(
                $uId,
                $confirmCode,
                $this->userSettings->getSetting('mailConfirmExpires')*60*60,
                'PASSWORD_RESET',
                ['test'=>$test,'verbose'=>$verbose]
            ))
                return -2;

            //Now, send the mail to the user.
            $templateNum = $this->userSettings->getSetting('pwdResetTemplate_'.$language) ?? $this->userSettings->getSetting('pwdResetTemplate');
            $title = $this->userSettings->getSetting('pwdResetTitle_'.$language) ?? $this->userSettings->getSetting('pwdResetTitle');
            return $this->sendConfirmationMail(
                $uMail,
                $uId,
                $confirmCode,
                $templateNum,
                $title,
                $async,
                ['test'=>$test,'verbose'=>$verbose]
            );
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
        function pwdResetConfirm(int $id, #[\SensitiveParameter] string $code, array $params = []): int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            return $this->confirmCode($id,$code,'PASSWORD_RESET',['test'=>$test,'verbose'=>$verbose]);
        }


        /** Sends out a mail change mail to the user
         * @param string $uMail User mail
         * @param array $params
         *                  'language' =>  string, default null - if set, will search for relevant locale settings to send relevant mails
         * @returns int|string
         *      -2 - internal server error
         *      [not async] 0 - All good.
         *      1 - User Mail isn't registered.
         *      3 - Mail failed to send!
         *      [async] see Mailhandler->sendMailAsync() with successQueue==true
         */
        function mailChangeSend(string $uMail, array $params = []): int|string {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $async = $params['async'] ?? true;
            $language = $params['language'] ?? null;

            $hex_secure = false;
            $confirmCode = '';
            while(!$hex_secure)
                $confirmCode=bin2hex(openssl_random_pseudo_bytes(64,$hex_secure));

            //See if user with said mail exists, if yes save his ID
            try{
                $query = 'SELECT * FROM
                      '.$this->SQLManager->getSQLPrefix().'USERS INNER JOIN
                      '.$this->SQLManager->getSQLPrefix().'USERS_EXTRA
                     ON '.$this->SQLManager->getSQLPrefix().'USERS.ID = '.$this->SQLManager->getSQLPrefix().'USERS_EXTRA.ID
                     WHERE '.$this->SQLManager->getSQLPrefix().'USERS.Email = :Email';

                if($verbose)
                    echo 'Query to send: '.$query.EOL;

                $currentUserSettings = $this->SQLManager->exeQueryBindParam(
                    $query,
                    [[':Email',$uMail]],
                    ['fetchAll'=>true]
                );
            }
            catch(\Exception $e){
                $this->logger->warning('Could not select from DB for mailChange',['mail'=>$uMail,'exception'=>$e->getMessage(),'trace'=>$e->getTrace()]);
                return -2;
            }
            if(count($currentUserSettings)==0){
                if($verbose)
                    echo 'No users found!'.EOL;
                return 1;
            }
            //Reuse existing code if found
            $uId = $currentUserSettings[0]['ID'];
            if(isset($currentUserSettings[0]['PWDReset']))
                $confirmCode = $currentUserSettings[0]['PWDReset'];

            //Set the new code
            if(!$this->createCode(
                $uId,
                $confirmCode,
                $this->userSettings->getSetting('mailConfirmExpires')*60*60,
                'MAIL_CHANGE',
                ['test'=>$test,'verbose'=>$verbose]
            ))
                return -2;

            //Now, send the mail to the user.
            $templateNum = $this->userSettings->getSetting('emailChangeTemplate_'.$language) ?? $this->userSettings->getSetting('emailChangeTemplate');
            $title = $this->userSettings->getSetting('emailChangeTitle_'.$language) ?? $this->userSettings->getSetting('emailChangeTitle');
            return $this->sendConfirmationMail(
                $uMail,
                $uId,
                $confirmCode,
                $templateNum,
                $title,
                $async,
                ['test'=>$test,'verbose'=>$verbose]
            );
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
        function mailChangeConfirm(int $id, #[\SensitiveParameter] string $code, array $params = []): int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            return $this->confirmCode($id,$code,'MAIL_CHANGE',['test'=>$test,'verbose'=>$verbose]);
        }
        /** Sends out a 2FA mail mail to the user
         * @param string $uMail User mail
         * @param array $params
         *                  'language' =>  string, default null - if set, will search for relevant locale settings to send relevant mails
         * @returns int|string
         *      -2 - internal server error
         *      [not async] 0 - All good.
         *      1 - User Mail isn't registered.
         *      3 - Mail failed to send!
         *      [async] see Mailhandler->sendMailAsync() with successQueue==true
         */
        function mail2FASend(string $uMail, array $params = []): int|string {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $async = $params['async'] ?? true;
            $language = $params['language'] ?? null;

            //See if user with said mail exists, if yes save his ID
            try{
                $query = 'SELECT * FROM
                      '.$this->SQLManager->getSQLPrefix().'USERS INNER JOIN
                      '.$this->SQLManager->getSQLPrefix().'USERS_EXTRA
                     ON '.$this->SQLManager->getSQLPrefix().'USERS.ID = '.$this->SQLManager->getSQLPrefix().'USERS_EXTRA.ID
                     WHERE '.$this->SQLManager->getSQLPrefix().'USERS.Email = :Email';

                if($verbose)
                    echo 'Query to send: '.$query.EOL;

                $currentUserSettings = $this->SQLManager->exeQueryBindParam(
                    $query,
                    [[':Email',$uMail]],
                    ['fetchAll'=>true]
                );
            }
            catch(\Exception $e){
                $this->logger->warning('Could not select from DB for mail2FASend',['mail'=>$uMail,'exception'=>$e->getMessage(),'trace'=>$e->getTrace()]);
                return -2;
            }
            if(count($currentUserSettings)==0){
                if($verbose)
                    echo 'No users found!'.EOL;
                return 1;
            }

            //Reuse existing code if found
            $uId = $currentUserSettings[0]['ID'];
            $TwoFactorAuth = isset($currentUserSettings[0]['Two_Factor_Auth']) && \IOFrame\Util\PureUtilFunctions::is_json($currentUserSettings[0]['Two_Factor_Auth'])?
                json_decode($currentUserSettings[0]['Two_Factor_Auth'], true) : [];
            $TwoFactorMailDetails = $TwoFactorAuth['2FADetails']['mail'] ?? null;

            $confirmCode = empty($TwoFactorMailDetails['code']) ? \IOFrame\Util\PureUtilFunctions::GeraHash(8) : $TwoFactorMailDetails['code'];

            if(empty($TwoFactorMailDetails['code']) || ( !empty($TwoFactorMailDetails['expires']) && ( $TwoFactorMailDetails['expires']<time() ) ) ){
                $res = $this->updateUser($uId,['2FAMailCode'=>$confirmCode],'ID',$params);
                if($res !== 0)
                    return -2;
            }

            //Now, send the mail to the user.
            $templateNum = $this->userSettings->getSetting('email2FATemplate_'.$language) ?? $this->userSettings->getSetting('email2FATemplate');
            $title = $this->userSettings->getSetting('email2FATitle_'.$language) ?? $this->userSettings->getSetting('email2FATitle');
            return $this->sendConfirmationMail(
                $uMail,
                $uId,
                $confirmCode,
                $templateNum,
                $title,
                $async,
                ['test'=>$test,'verbose'=>$verbose]
            );
        }

        /** Adds a confirmation code
         * @param int $id user ID
         * @param string $code confirmation code
         * @param int $ttl Time to live (in seconds)
         * @param string $action Action that the user ID is appended to
         * @param array $params
         * @return true on success, false on failure
         */
        function createCode(int $id, #[\SensitiveParameter] string $code, int $ttl, string $action, array $params = []): bool {

            $TokenHandler = $params['TokenHandler'] ?? new \IOFrame\Handlers\TokenHandler($this->settings, $this->defaultSettingsParams);

            $success = $TokenHandler->setTokens(
                [
                    $code => ['action'=>$action.'_'.$id,'ttl'=>$ttl],
                ],
                $params
            );

            if($success[$code] === 0)
                return true;
            else{
                $this->logger->error('Could not create desired user token',['id'=>$id,'action'=>$action,'code'=>$code]);
                return false;
            }
        }

        /** Confirms a code
         * @param int $id user ID
         * @param string $code confirmation code
         * @param string $action Expected action that the user ID is appended to
         * @param array $params
         * @return int
         *     -1 - Unexpected error
         *      0 - All good.
         *      1 - User ID doesn't exist.
         *      2 - Confirmation code wrong.
         *      3 - Confirmation code expired.
         */
        function confirmCode(int $id, #[\SensitiveParameter] string $code, string $action, array $params = []): int {

            $TokenHandler = $params['TokenHandler'] ?? new \IOFrame\Handlers\TokenHandler($this->settings, $this->defaultSettingsParams);

            $userInfo = $this->SQLManager->selectFromTable(
                $this->SQLManager->getSQLPrefix().'USERS',['ID',$id,'='],[],$params
            );

            if(is_array($userInfo) && count($userInfo)>0){
                $success = $TokenHandler->consumeToken($code,1,$action.'_'.$id,$params);
                if($success === 3)
                    return 3;
                elseif($success === 1)
                    return 2;
                elseif($success === 0)
                    return 0;
                else{
                    $this->logger->error('DB error when trying to confirm/consume code',['id'=>$id,'action'=>$action,'code'=>$code]);
                    return -1;
                }
            }
            else
                return 1;
        }

        /** Sends activation mail to the user whoes mail is $uMail, id is $uId, and the code is $confirmCode
         * @param string $uMail User mail
         * @param int $uId User ID
         * @param string $confirmCode Confirmation code needed to send async mail
         * @param string $templateID
         * @param string $title Mail title
         * @param boolean $async Send mail asynchronously or not
         * @param array $params
         *
         * @return int|string
         */
        function sendConfirmationMail(
            string $uMail, int $uId, #[\SensitiveParameter] string $confirmCode, string $templateID, string $title, bool $async, array $params = []): int|string {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            try {
                $mail = new \IOFrame\Managers\MailManager($this->settings,array_merge($this->defaultSettingsParams,['verbose'=>$verbose]));
            }
            catch (\Exception $e){
                $this->logger->error('Could not initiate mail handler '.$e->getMessage(),['id'=>$uId,'mail'=>$uMail,'templateID'=>$templateID]);
                return 3;
            }
            if(!$test){
                try{
                    if($async){
                        $res = $mail->sendMailAsync(
                            [
                                'from'=>['',$this->siteSettings->getSetting('siteName')],
                                'to'=>$uMail,
                                'subject'=>$title,
                                'template'=>$templateID,
                                'varArray'=>['uId'=>$uId, 'Code'=>$confirmCode, 'siteName'=>$this->siteSettings->getSetting('siteName')]
                            ],
                            ['successQueue'=>true]
                        );
                        if(is_string($res))
                            return $res;
                        else{
                            $this->logger->error('Could not push mail to async queue',['id'=>$uId,'mail'=>$uMail,'templateID'=>$templateID]);
                            return 3;
                        }
                    }
                    else{
                        if($mail->setWorkingTemplate($templateID)!==0){
                            $this->logger->error('Could not send working template',['templateID'=>$templateID]);
                            return 1;
                        }
                        if(
                            $mail->sendMailTemplate(
                                [$uMail=>null],
                                $title,
                                null,
                                [
                                    'varArray'=>['uId'=>$uId,'Code'=>$confirmCode, 'siteName'=>$this->siteSettings->getSetting('siteName')],
                                    'from'=>['',$this->siteSettings->getSetting('siteName')],
                                ]
                            )
                        )
                            return 0;
                        else{
                            $this->logger->error('Could not send mail',['id'=>$uId,'mail'=>$uMail,'templateID'=>$templateID]);
                            return 3;
                        }
                    }
                }
                catch(\Exception $e){
                    $this->logger->error('Could not send mail '.$e->getMessage(),['id'=>$uId,'mail'=>$uMail,'templateID'=>$templateID]);
                    return 3;
                }
            }
            if($verbose)
                echo 'Sending email about account activation to '.$uMail.EOL;
            return 0;
        }

        /** Creates an invite token
         * @param array $inputs
         *              'token' =>string, Default null. Specific token to use. Created automatically otherwise
         *              'action' =>string, Specific Action, Defaults to "REGISTER_ANY" - could be "REGISTER_MAIL" (to register a specific email),
         *                       "REGISTER_ANY" (register anyone with token), or custom.
         *              'mail' =>string, Default null - required if registering a specific mail.
         *              'uses' =>int, default 1 - How many uses a newly created token would have. Cannot be infinite, but can be 64 bit (so basically infinite)
         *              'ttl' =>int, defaults to email activation setting (72 hours by install default) - Token TTL in seconds
         * @param array $params
         * @return mixed
         */
        function createInviteToken(#[\SensitiveParameter] array $inputs = [],array $params = []){
            $inputs['token'] = !empty($inputs['token'])? $inputs['token'] :  bin2hex(openssl_random_pseudo_bytes(128,$hex_secure));
            return $this->createInviteTokens([$inputs],$params)[$inputs['token']];
        }

        /** Creates invite tokens
         * @param array $params
         *              'token' =>string, Default null. Specific token to use. Created automatically otherwise
         *              'action' =>string, Specific Action, Defaults to "REGISTER_ANY" - could be "REGISTER_MAIL" (to register a specific email),
         *                       "REGISTER_ANY" (register anyone with token), or custom.
         *              'mail' =>string, Default null - required if registering a specific mail.
         *              'uses' =>int, default 1 - How many uses a newly created token would have. Cannot be infinite, but can be 64 bit (so basically infinite)
         *              'ttl' =>int, defaults to email activation setting (774 hours, or 31 days, by install default) - Token TTL in seconds
         *              --
         *              Params for setTokens, with overwrite defaulting to "false" instead of "true"
         * @returns int Same as TokenHandler->setTokens()
         *              with possible code -3 - cannot create mail without $mail set
         */
        function createInviteTokens(#[\SensitiveParameter] array $inputs = [],array $params = []): array {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $overwrite = $params['overwrite'] ?? false;
            $results = [];
            $stuffToSet = [];
            foreach ($inputs as $inputArray){
                $token = !empty($inputArray['token'])? $inputArray['token'] : bin2hex(openssl_random_pseudo_bytes(128,$hex_secure));
                $results[$token] = -1;
                $inputArray['action'] = !empty($inputArray['action'])? $inputArray['action'] : "REGISTER_ANY";
                if($inputArray['action'] === 'REGISTER_MAIL'){
                    $results[$token] = -3;
                    if(empty($inputArray['mail']))
                        continue;
                    else{
                        $inputArray['action'] = 'REGISTER_MAIL_'.$inputArray['mail'];
                        unset($inputArray['mail']);
                    }
                }
                $inputArray['uses'] = !empty($inputArray['uses'])? $inputArray['uses'] : 1;
                $inputArray['ttl'] = !empty($inputArray['ttl'])? $inputArray['ttl'] : (int)($this->userSettings->getSetting('inviteExpires')? $this->userSettings->getSetting('inviteExpires') : $this->userSettings->getSetting('mailConfirmExpires'))*3600;
                $inputArray['tags'] = ['registration', ( $inputArray['action'] === 'REGISTER_ANY' ? 'any_registration' : 'mail_registration' ) ];
                $stuffToSet[$token] = $inputArray;
            }
            if(!count($stuffToSet))
                return $results;

            $TokenHandler = new \IOFrame\Handlers\TokenHandler($this->settings,array_merge($this->defaultSettingsParams,['verbose'=>$verbose]));

            $res = $TokenHandler->setTokens($stuffToSet,array_merge($params,['overwrite'=>$overwrite]));

            $catastrofic = [];
            foreach ($res as $token=>$result){
                if($result < 0)
                    $catastrofic[$token]=$result;
                $results[$token] = $result;
            }
            if(!empty($catastrofic))
                $this->logger->error('Could not create invite tokens',['tokens'=>$catastrofic]);

            return $results;
        }

        /** Confirms an invite token
         * @param string $token Token to check
         * @param array $params
         * @return int
         */
        function confirmInviteToken(#[\SensitiveParameter] string $token,array $params = []): int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $mail = $params['mail'] ?? null;
            $consume = $params['consume'] ?? 1;
            $TokenHandler = new \IOFrame\Handlers\TokenHandler($this->settings,array_merge($this->defaultSettingsParams,['verbose'=>$verbose]));
            $actionRegex = $mail? '^REGISTER_ANY$|^REGISTER_MAIL_'.
                str_replace('-','\\-',str_replace('.','\\.',$mail))
                .'$' : '^REGISTER_ANY$';

            if(!$consume){
                $tokenRes = $TokenHandler->getToken($token,$params);
                if(!empty($tokenRes['Token_Action']) && preg_match('/'.$actionRegex.'/',$tokenRes['Token_Action']) && ($tokenRes['Uses_Left'] >= $consume))
                    return 0;
                else
                    return 1;
            }
            else{
                $res = $TokenHandler->consumeToken($token,(int)$consume,$actionRegex,$params);
                if($res < 0)
                    $this->logger->error('Could not consume valid token',['specificEmail'=>$mail,'tokens'=>$token]);
                return $res;
            }
        }

        /** Sends invite mail that allows to both register and activate the account - on restricted systems, as well (by default).
         * @param string $uMail mail
         * @param int $templateNum Template to use
         * @param string $title Mail title
         * @param boolean $async Send mail asynchronously or not
         * @param array $params
         *              extraTemplateArguments:<Associative Array, extra arguments for the mail function>
         *              token:<string, Specific token to use. Created automatically otherwise>
         *              tokenUses:<int, default 1 - How many uses a newly created token would have. Cannot be infinite, but can be 64 bit (so basically infinite)>
         *              tokenTTL:<int, defaults to email activation setting (72 hours by install default) - Token TTL in seconds>
         *              mailSpecificToken:<string, default $uMail - If set to fault, will mail an invite that's valid for any email>
         * @returns int | string | array
         *          -3 - Token could not be created
         *          -2 - Mail failed to send
         *          -1 - Server error
         *           [not async] <string, valid invite token> - success
         *           [async] [ 'token'=><string, token from above>, 'queue'=><string, success queue name> ]
         *           1 - Template does not exist
         */
        function sendInviteMail(string $uMail, int $templateNum, string $title, bool $async,#[\SensitiveParameter] array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $templateArguments = $params['extraTemplateArguments'] ?? [];
            $token = $params['token'] ?? null;
            $tokenUses = $params['tokenUses'] ?? null;
            $tokenTTL = $params['tokenTTL'] ?? null;
            $mailSpecificToken = $params['mailSpecificToken'] ?? $uMail;
            if(!$token){
                $token = $this->createInviteToken([],array_merge($params,['uses'=>$tokenUses,'ttl'=>$tokenTTL,'mail'=>$mailSpecificToken,'action'=>($mailSpecificToken?'REGISTER_MAIL':'REGISTER_ANY')]));
                if($token !== 0)
                    return -3;
            }
            $templateArguments['token'] = $token;
            $templateArguments['mail'] = $uMail;
            $templateArguments['siteName'] = $this->siteSettings->getSetting('siteName');
            try {
                $mail = new \IOFrame\Managers\MailManager($this->settings,array_merge($this->defaultSettingsParams,['verbose'=>$verbose]));
            }
            catch (\Exception $e){
                $this->logger->error('Could not initiate mail handler '.$e->getMessage(),['mail'=>$uMail,'templateNumber'=>$templateNum]);
                return -2;
            }
            if(!$test){
                try{
                    if($async){
                        $res = $mail->sendMailAsync(
                            [
                                'from'=>['',$this->siteSettings->getSetting('siteName')],
                                'to'=>$uMail,
                                'subject'=>$title,
                                'template'=>$templateNum,
                                'varArray'=>$templateArguments
                            ],
                            ['successQueue'=>true]
                        );
                        if(is_string($res))
                            return $res;
                        else{
                            $this->logger->error('Could not push mail to async queue',['mail'=>$uMail,'templateNumber'=>$templateNum]);
                            return -2;
                        }
                    }
                    else{
                        if($mail->setWorkingTemplate($templateNum)!==0){
                            $this->logger->error('Could not send working template',['templateNumber'=>$templateNum]);
                            return 1;
                        }
                        if(
                            $mail->sendMailTemplate(
                                [$uMail=>null],
                                $title,
                                null,
                                [
                                    'varArray'=>$templateArguments,
                                    'from'=>['',$this->siteSettings->getSetting('siteName')],
                                ]
                            )
                        )
                            return $token;
                        else
                            return -2;
                    }
                }
                catch(\Exception $e){
                    $this->logger->error('Could not send mail '.$e->getMessage(),['mail'=>$uMail,'templateNumber'=>$templateNum]);
                    return -2;
                }
            }
            if($verbose)
                echo 'Sending '.($async?'async':'synced').' email about account activation to '.$uMail.EOL;
            return $token;
        }

        /** Bans user for $minutes minutes.
         *  See limitUser
         * @param int $minutes
         * @param mixed $identifier
         * @param array $params
         *
         * @return int
         */
        function banUser(int $minutes, mixed $identifier, array $params = []): int {
            return $this->limitUser('ban',$minutes,$identifier,$params);
        }


        /** Bans user for $minutes minutes.
         *  See limitUser
         * @param int $minutes
         * @param mixed $identifier
         * @param array $params
         *
         * @return int
         */
        function suspectUser(int $minutes, mixed $identifier, array $params = []): int {
            return $this->limitUser('sus',$minutes,$identifier,$params);
        }


        /** Bans user for $minutes minutes.
         *  See limitUser
         * @param int $minutes
         * @param mixed $identifier
         * @param array $params
         *
         * @return int
         */
        function lockUser(int $minutes, mixed $identifier, array $params = []): int {
            return $this->limitUser('lock',$minutes,$identifier,$params);
        }

        /** Bans/Locks/Suspects user for $minutes minutes.
         *  If $minutes is 0, will affect the user for 1,000,000,000 minutes.
         * @param string $type 'ban','sus' or 'lock'
         * @param int $minutes Minutes to ban - 0 up to 1,000,000,000
         * @param mixed $identifier Either an ID (Int) or a mail (String)
         * @param array $params
         *
         * @return int 0 success, 1 failure
         */
        function limitUser(string $type, int $minutes, mixed $identifier, array $params = []): int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            //Sanitation
            if($minutes = 0 || $minutes > 1000000000)
                $minutes = 1000000000;
            if($minutes<0)
                $minutes = 0;

            if(gettype($identifier) == 'integer'){
                $cond = ['ID',$identifier,'='];
            }
            else{
                $cond = ['ID',[[$this->SQLManager->getSQLPrefix().'USERS',['Email',[$identifier,'STRING'],'='],['ID'],[],'SELECT']],'IN'];
            }

            switch ($type){
                case 'ban':
                    $col = 'Banned_Until';
                    break;
                case 'sus':
                    $col = 'Suspicious_Until';
                    break;
                case 'lock':
                    $col = 'Locked_Until';
                    break;
                default:
                    return 1;
            }

            $res = $this->SQLManager->updateTable(
                $this->SQLManager->getSQLPrefix().'USERS_EXTRA',
                [$col.' ='.(time()+60*$minutes)],
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
         * @param array $params of the form:
         *                      'oldSesID' string|null, default '' - if not empty, will cause a remote logOut
         *                                 of a user with said sessionID ON THIS NODE / REDIS SERVER ONLY!
         *                      'forgetMe' bool, default true - Control whether we reset the authDetails in the users table,
         *                                  which'd make the server forget all user reconnect tokens - if
         *                                  @$sesOnly is false, this does not matter.
         *                      'sesOnly' bool, default false - Controls whether we only reset local session data, or update DB.
         *
         * TODO Check if it works properly with Redis Cluster
         * @throws \Exception
         * @throws \Exception
         */
        function logOut(array $params = []): void {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
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
                    $query = 'UPDATE '.$this->SQLManager->getSQLPrefix().'USERS SET
                SessionID=NULL, authDetails=:authDetails WHERE SessionID=:SessionID';
                    array_push($params,[':authDetails', NULL],[':SessionID', $oldSesID]);
                }
                else{
                    $query = 'UPDATE '.$this->SQLManager->getSQLPrefix().'USERS SET
                SessionID=NULL WHERE SessionID=:SessionID';
                    $params[] = [':SessionID', $oldSesID];
                }


                //Remove old session info from the database;
                if (!$test){
                    if(!$sesOnly)
                        $this->SQLManager->exeQueryBindParam($query,$params);
                }
                if ($verbose && isset($query))
                    echo 'Executing query '.$query.EOL;

                //Will log out user with a specific session ID remotely - but also the current user!
                if (!$test){
                    session_destroy();
                    session_id($oldSesID);
                    session_start();
                    session_unset();
                    session_destroy();
                }
                if ($verbose)
                    echo 'User with session '.$oldSesID.' logged out!'.EOL;
                //If you were logging somebody out - log yourself back in
                if(isset($currSesID)){
                    if (!$test){
                        session_id($currSesID);
                        session_start();
                    }
                    if ($verbose)
                        echo 'User assinged ID '.$currSesID.EOL;
                }
                else{
                    //If you are logging yourself out, erase all your relog cookies just so you dont wast time trying to relog with bad info later
                    if($forgetMe){
                        $cookiesToUnset = ['sesID','sesIV','userMail'];
                        foreach($cookiesToUnset as $cookieName){
                            if (!empty($_COOKIE[$cookieName])){
                                if(!$test){
                                    unset($_COOKIE[$cookieName]);
                                    setcookie($cookieName, '', -1,'/');
                                }
                                if ($verbose)
                                    echo 'Unsetting cookie '.$cookieName.EOL;
                            }
                        }
                    }

                    if (!$test){
                        session_start();
                        session_regenerate_id();
                    }
                    if ($verbose)
                        echo 'User new ID '.EOL;
                }
                if (!$test)
                    $_SESSION['discard_after'] = time() + $this->siteSettings->getSetting('maxInacTime');
                if ($verbose)
                    echo 'Discard time set to '.(time() + $this->siteSettings->getSetting('maxInacTime')).EOL;

        }


        /** TODO add OpenAuth option using https://github.com/thephpleague/oauth2-client , then add relevant settings
         * TODO #2 Add SMS 2FA
         * Main login function
         * @param string[] $inputs array of inputs needed to log in.
         *                      log: type of login, can be "in" (regular login with password) or "temp" (login using )
         *                      userID: identifier of current device, used for temp login, or for full login using "rememberMe"
         *                      m: user mail, always required
         *                      p: user password on full login
         *                      sesKey: user token  on temp login
         *                      2FAType: 2FA Method, if the user chooses to log in via 2FA. Valid methods are:
         *                          'mail' - send code via email, always supported on systems with mailSettings set
         *                          'app' - works when the user has a 2FA app enabled and configured
         *                          'sms' - send code via sms, supported on systems with smsSettings set, AND the user having a valid phone
         *                      2FACode: The 2FA code provided by the user. Required when 2FAType is set.
         * @param array $params
         *              'overrideAuth': bool, default false. If true, will not check password om regular login.
         *                              Meant to be used with external authentication.
         *               'ignoreSuspicious': bool, default false. If false, suspicious users can't logging in without 2FA.
         *               'ignoreBanned': bool, default false. If false, AND the user is suspicious, will not allow login even with 2FA.
         *               'ignoreLocked': bool, default false. If false, AND user locked, login is temporary disabled into account
         * @returns mixed
         *     -1 Error during some stage of the login, could not complete.
         *      0 all good - without rememberMe
         *      1 username/password combination wrong
         *      2 expired auto login token
         *      3 login type not allowed
         *      4 login would work, but 2FA is required (either user is suspicious or enabled 2FA himself)
         *      5 login would work, but 2FA code is incorrect
         *      6 login would work, but 2FA code expired
         *      7 login would work, but user does not have it set up (no confirmed phone, no registered 2FA app, etc)
         *      8 login would work, but 2FA method is not supported
         *      9 Account locked (also when user is both suspicious and banned, trying to 2FA login)
         *      32-byte hex encoded session ID string - The token for your next automatic relog, if you logged automatically.
         *      JSON encoded array of the form {'iv'=><32-byte hex encoded string>,'sesID'=><32-byte hex encoded string>}
         * @throws TwoFactorAuthException
         * @throws TwoFactorAuthException
         */
        function logIn(#[\SensitiveParameter] array $inputs, array $params = []): array|int|string {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $overrideAuth = $params['overrideAuth'] ?? false;
            $ignoreSuspicious = $params['ignoreSuspicious'] ?? false;
            $ignoreBanned = $params['ignoreBanned'] ?? false;
            $ignoreLocked = $params['ignoreLocked'] ?? false;
            $log = $inputs["log"];
            if($this->userSettings->getSetting('rememberMe') < 1)
                unset($inputs["userID"]);

            //If trying to log in temporary when the site doesn't allow it, tell the client so and return
            if( ($this->userSettings->getSetting('rememberMe') < 1) && $log=='temp'){
                return 3;
            }

            //Fetch table row that matches the username
            $prefix =  $this->SQLManager->getSQLPrefix();
            $checkRes = $this->SQLManager->selectFromTable(
                $prefix.'USERS JOIN '.$prefix.'USERS_EXTRA ON '.$prefix.'USERS.ID = '.$prefix.'USERS_EXTRA.ID',
                ['Email', $inputs["m"],'='],
                [],
                ['noValidate'=>true,'test'=>$test,'verbose'=>$verbose]
            );
            // Check if it found something
            if (is_array($checkRes) && count($checkRes)>0) {

                $locked = !$ignoreLocked && !empty($checkRes[0]['Locked_Until']) && ($checkRes[0]['Locked_Until'] > time());
                if($locked)
                    return 9;

                //Get auth details
                $authFullDetails = json_decode($checkRes[0]['authDetails']??'', true);
                $TwoFactorAuth = $checkRes[0]['Two_Factor_Auth']? json_decode($checkRes[0]['Two_Factor_Auth'], true) : [];

                //If user is trying to relog automatically to a device that doesn't exist - he wont be logged in.
                if($log=='temp' && !isset($authFullDetails[$inputs["userID"]]))
                    $log = 'wrong';
                //Else decode the relevant userID details
                elseif($log=='temp'){
                    $authDetails =  json_decode($authFullDetails[$inputs["userID"]], true);
                    $key = \IOFrame\Util\PureUtilFunctions::stringScrumble($authDetails['nextLoginID'],$authDetails['nextLoginIV']);
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
                if (
                    (
                        $log=='in'
                        &&
                        ($overrideAuth || password_verify($inputs["p"], $checkRes[0]['Password']))
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
                    //If the login worked, check whether it is allowed without a 2FA
                    if(
                        $log=='in' &&
                        ( ($checkRes[0]['Suspicious_Until'] > time() && !$ignoreSuspicious) || (!empty($TwoFactorAuth['require2FA']) && !$overrideAuth)) &&
                        ( empty($inputs['2FAType']) || empty($inputs['2FACode']) )
                    )
                    {
                        return 4;
                    }
                    //Else if 2FA type AND code were provided, validate them (if they weren't provided but were required, we would fail in the last step)
                    elseif($log=='in' && !empty($inputs['2FAType']) && !empty($inputs['2FACode'])){
                        //In the specific case where the user is both sus and banned, do not allow login when 2FA is enabled
                        if(
                            ( !$ignoreSuspicious && ($checkRes[0]['Suspicious_Until'] > time()) ) &&
                            ( !$ignoreBanned && ( $checkRes[0]['Banned_Until'] > time() ) )
                        )
                            return 9;
                        //Either way, for this to be used, the user needs some kind of 2FA details preset
                        if(empty($TwoFactorAuth['2FADetails']))
                            return 7;
                        //The following is based on the 2FA type
                        switch ($inputs['2FAType']){
                            case 'mail':
                                //Check Mail settings
                                $mailSettings = new \IOFrame\Handlers\SettingsHandler(
                                    $this->settings->getSetting('absPathToRoot').\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/mailSettings',
                                    $this->defaultSettingsParams
                                );
                                foreach(['mailHost','mailUsername','mailPassword','mailPort'] as $value){
                                    if(!$mailSettings->getSetting($value)){
                                        $this->logger->error('Could not use mail 2FA',['missingSetting'=>$value]);
                                        return 8;
                                    }
                                }
                                //Check that the code sent has not expired
                                if(empty($TwoFactorAuth['2FADetails']['mail']['expires']) || $TwoFactorAuth['2FADetails']['mail']['expires'] < time())
                                    return 6;
                                //Check that the user has a valid email code sent
                                if(empty($TwoFactorAuth['2FADetails']['mail']['code']))
                                    return 5;
                                //Check the code
                                if($TwoFactorAuth['2FADetails']['mail']['code'] !== $inputs['2FACode'])
                                    return 5;
                                else{
                                    $TwoFactorAuth['2FADetails']['mail']['code'] = null;
                                    $TwoFactorAuth['2FADetails']['mail']['expires'] = null;
                                }
                                break;
                            case 'app':
                                //Check that the user has a valid 2FA secret
                                if(empty($TwoFactorAuth['2FADetails']['secret']))
                                    return 7;
                                //Check the code
                                $tfa = new TwoFactorAuth($this->siteSettings->getSetting('siteName'));
                                if(!$tfa->verifyCode((string)$TwoFactorAuth['2FADetails']['secret'], $inputs['2FACode'],3))
                                    return 5;
                                break;
                            case 'sms':
                                //Check SMS settings
                                $smsSettings = new \IOFrame\Handlers\SettingsHandler(
                                    $this->settings->getSetting('absPathToRoot').\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/smsSettings/',
                                    $this->defaultSettingsParams
                                );
                                if(!$smsSettings->getSetting('provider'))
                                    return 8;
                                //Check that the user has a valid phone
                                if(empty($checkRes[0]['Phone']))
                                    return 7;
                                //Check that the code sent has not expired
                                if(empty($TwoFactorAuth['2FADetails']['sms']['expires']) || $TwoFactorAuth['2FADetails']['sms']['expires'] < time())
                                    return 6;
                                //Check that the user has a valid sms code sent
                                if(empty($TwoFactorAuth['2FADetails']['sms']['code']))
                                    return 5;
                                //Check that the SMS
                                if($TwoFactorAuth['2FADetails']['sms']['code'] !== $inputs['2FACode'])
                                    return 5;
                                else{
                                    $TwoFactorAuth['2FADetails']['sms']['code'] = null;
                                    $TwoFactorAuth['2FADetails']['sms']['expires'] = null;
                                }
                                break;
                            default:
                                return 8;
                        }
                        if(
                            !$this->SQLManager->updateTable(
                                $this->SQLManager->getSQLPrefix().'USERS',
                                ['Two_Factor_Auth = \''.json_encode($TwoFactorAuth).'\''],
                                ['Email', $inputs["m"],'='],
                                ['test'=>$test,'verbose'=>$verbose]
                            )
                        )
                            return -1;
                    }

                    //-----------------------Logout any user with the current old sessionID out-------------------------
                    //Only erases their Session data on the server, not data in DB
                    $this->logOut(['oldSesID' => $checkRes[0]['SessionID'],'forgetMe' => false,'sesOnly' => true,'test'=>$test,'verbose'=>$verbose]);

                    //------------------Regenerate session ID and update user table to current user ID------------------
                    if(!$test)
                        session_regenerate_id();
                    $query = 'UPDATE '.$this->SQLManager->getSQLPrefix().'USERS SET SessionID
                    =:SessionID WHERE Email=:Email';
                    if (!$test)
                        $this->SQLManager->exeQueryBindParam($query,[[':Email', $inputs["m"]],[':SessionID', session_id()]]);

                    //--------------------Encryption and stuff - temp login-------------------
                    if(isset($inputs["userID"])){

                        // Either way, we will be using this hex string as part of the login.
                        $hex_secure = false;
                        while(!$hex_secure)
                            $hex=bin2hex(openssl_random_pseudo_bytes(16,$hex_secure));

                        //Specify after how long this auto-login will expire (if not set, defaults to 1 year)
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
                            $resHex = \IOFrame\Util\PureUtilFunctions::stringScrumble($authDetails['nextLoginID'],$hex);
                            $encryptedKey = openssl_encrypt( $resHex, 'aes-256-ecb', hex2bin( $key ), OPENSSL_ZERO_PADDING);
                        }


                        $relogData=[
                            "nextLoginID" => $hex,
                            "nextLoginIV" => $iv,
                            "expires" => $expires
                        ];

                        //At this point, if we are using cookies to relog, we start by setting them here
                        if($this->userSettings->getSetting('relogWithCookies')){
                            $cookiesExpire = $expires ?: time()+60*60*24*365;
                            if ($verbose)
                                echo 'Cookies expire at '.$cookiesExpire.EOL;

                            if (!$test)
                                setcookie("userID", $inputs["userID"], $cookiesExpire, "/", "", 1, 1);
                            if ($verbose)
                                echo 'Setting cookie userID as '.$inputs["userID"].EOL;

                            if (!$test)
                                setcookie("userMail", $inputs["m"], $cookiesExpire, "/", "", 1, 1);
                            if ($verbose)
                                echo 'Setting cookie userMail as '.$inputs["m"].EOL;

                            if (!$test)
                                setcookie("sesIV", $iv, $cookiesExpire, "/", "", 1, 1);
                            if ($verbose)
                                echo 'Setting cookie sesIV as '.$iv.EOL;

                            if (!$test)
                                setcookie("sesID", $hex, $cookiesExpire, "/", "", 1, 1);
                            if ($verbose)
                                echo 'Setting cookie sesID as '.$hex.EOL;
                        }

                        $relogData = json_encode($relogData);

                        $authFullDetails[$inputs["userID"]] = $relogData;

                        $authFullDetails = json_encode($authFullDetails);


                        //Update database with it
                        $query = 'UPDATE '.$this->SQLManager->getSQLPrefix().'USERS SET
                        authDetails=:authFullDetails WHERE Email=:Email';

                        /*If it was test, test results well be echoed later, nothing left to do.
                          The reason this does not return -1 on failure is because, at worst, there wont be new relog info - still better than
                          dropping the whole process just over that.
                        */
                        if (!$test)
                            $this->SQLManager->exeQueryBindParam($query,[[':authFullDetails', $authFullDetails],[':Email', $inputs["m"]]]);
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
                        $res = bin2hex( base64_decode( $encryptedKey ));
                    else
                        $res = array('iv' => $iv, 'sesID' => $hex);

                    //---------Fetch all the extra user data and update current session-------------
                    $this->login_updateSessionCore($checkRes, ['test'=>$test,'verbose'=>$verbose]);
                    /* Update login history - This may fail, but we don't care enough to drop the whole login because of it */
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
        private function login_updateSessionCore(#[\SensitiveParameter] array $checkRes,array $params = []): void {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            isset($params['override'])?
                $override = $params['override'] : $override = false;
            //Update current session - this is how the rest of the app knows the user is logged in
            $data = [];
            //Fetch existing details if they exist
            if(isset( $_SESSION['details']) && !$override)
                $data = json_decode( $_SESSION['details'],true);
            //Get arguments from core user info
            $args = [
                "ID",
                "Username",
                "Email",
                "Phone",
                "Active",
                "Auth_Rank",
                "Preferred_Language",
                "Banned_Until"
            ];

            //Get extra argument names if they exist in userSettings
            $extraArgs = $this->userSettings->getSetting('extraUserColumns');
            if(\IOFrame\Util\PureUtilFunctions::is_json($extraArgs))
                $args = array_merge($args,json_decode($extraArgs,true));
            else
                $args = array_merge($args,explode(',',$extraArgs??''));

            foreach($args as $val){
                $data[$val]= $checkRes[0][$val] ?? null;
            }

            //Edge Case - Two Factor Auth
            if(!empty($checkRes[0]['Two_Factor_Auth']) && \IOFrame\Util\PureUtilFunctions::is_json($checkRes[0]['Two_Factor_Auth'])){
                $twoFactorAuth = json_decode($checkRes[0]['Two_Factor_Auth'],true);
                $data['require2FA']= !empty($twoFactorAuth['require2FA']);
                $data['TwoFactorAppReady'] = !empty($twoFactorAuth['2FADetails']['secret']);
            }

            if(!$test){
                $_SESSION['details']=json_encode($data);
                $_SESSION['logged_in']=true;
            }
            if($verbose){
                echo 'Session set to logged in!'.EOL;
                echo 'Setting new session details:'.json_encode($data).EOL;
            }
        }

        /** Updates login history
         * @param array $checkRes results gotten from the DB in an earlier stage
         * @param array $params
         * @return array|bool|int|string|string[]|null
         */
        private function login_updateHistory(#[\SensitiveParameter] array $checkRes,array $params = []): array|bool|int|string|null {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $query = 'INSERT INTO '.$this->SQLManager->getSQLPrefix().'LOGIN_HISTORY(ID, IP, Country, Login_Time)
                                           VALUES (:Username, :IP, :Country, :Login_Time)';

            //TODO Add support for a GeoIP plugin
            $countryRes='Unknown';
            $u = $checkRes[0]["ID"];
            $now = strtotime(date("YmdHis"));
            $IPHandler = new \IOFrame\Handlers\IPHandler($this->settings,$this->defaultSettingsParams);

            return $this->SQLManager->insertIntoTable(
                $this->SQLManager->getSQLPrefix().'LOGIN_HISTORY',
                ['ID','IP','Country','Login_Time'],
                [
                    [[$u,'STRING'],[$IPHandler->fullIP,'STRING'],[$countryRes,'STRING'],$now]
                ],
                $params
            );
        }


        /** TODO Decide whether to use this in the API or remove it
         * Checks whether the user is eligible to be logged into.
         * @param mixed $identifier - Either a number (id) or a string (mail)
         * @param array $params array of the form:
         *              'allowWhitelistedIP' = > string, checks for whitelisted IP that matches the string
         *              'allowCode' => string, code provided by the user. Will check the tokens (and check expiry)
         *              TODO Add more methods once sms / pre-shared secret 2FAs are supported
         * @return int|mixed
         * @throws \Exception
         */
        function checkUserLogin(mixed $identifier, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $prefix = $this->SQLManager->getSQLPrefix();

            //Works both with user mail and ID
            if(gettype($identifier) == 'integer'){
                $identityCond = [$prefix.'USERS.ID',$identifier,'='];
            }
            else{
                $identityCond = [$prefix.'USERS.Email',[$identifier,'STRING'],'='];
            }

            //Initial check
            $query = $this->SQLManager->selectFromTable(
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
                ['Suspicious_Until', '"User" AS Allowed', '-1 AS ID', '-1 AS Email'],
                ['justTheQuery'=>true,'test'=>false]
            );
            //This will just return the user mail/ID
            $query .=' UNION '.
                $this->SQLManager->selectFromTable(
                    $prefix.'USERS',
                    $identityCond,
                    ['-1 AS Suspicious_Until', '-1 AS Allowed', $prefix.'USERS.ID AS ID', $prefix.'USERS.Email AS Email'],
                    ['justTheQuery'=>true,'test'=>false]
                );
            //This will return -1 / -1 if the user does not exist
            $query .=' UNION '.
                $this->SQLManager->selectFromTable(
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
                    ['-1 AS Suspicious_Until', '-1 AS Allowed', '-1 AS ID', '-1 AS Email'],
                    ['justTheQuery'=>true,'test'=>false]
                );
            //Whitelisted IP Check
            if(isset($params['allowWhitelistedIP']))
                $query .=' UNION '.
                    $this->SQLManager->selectFromTable(
                        $prefix.'IP_LIST,'.$prefix.'USERS INNER JOIN '.$prefix.'USERS_EXTRA ON '.$prefix.'USERS.ID = '.$prefix.'USERS_EXTRA.ID',
                        [
                            $identityCond,
                            [$prefix.'IP_LIST.IP',$params['allowWhitelistedIP'],'='],
                            [$prefix.'IP_LIST.IP_Type',1,'='],
                            'AND'
                        ],
                        ['Suspicious_Until', '"IP" AS Allowed', '-1 AS ID', '-1 AS Email'],
                        ['justTheQuery'=>true,'test'=>false]
                    );
            //Code check

            if($verbose)
                echo 'Query to send: '.$query.EOL;
            $tempRes = $this->SQLManager->exeQueryBindParam($query,[],['fetchAll'=>true]);

            $res = 1;
            $userID = -1;
            $userMail = -1;

            //If the user is not suspicious, we got our user
            $tempCount = count($tempRes);
            for($i=0; $i<$tempCount; $i++){

                //Extract info if exists
                if($tempRes[$i]['ID'] !== -1){
                    $userID = $tempRes[$i]['ID'];
                }
                if($tempRes[$i]['Email'] !== -1){
                    $userMail = $tempRes[$i]['Email'];
                }

                //Check whether user may login
                if($tempRes[$i]['Allowed'] == 'User'){
                    $res = 0;
                }
                elseif($res === 1 && $tempRes[$i]['Allowed']){
                    $res = 2;
                }

            }
            //If the user was not allowed to log in, but allowCode is set, check the tokens.
            if(isset($params['allowCode']) && $res===1 && $userID!=-1 && $userMail!=-1){
                $codeUsage = $this->confirmCode($userID,$params['allowCode'],'TEMPORARY_LOGIN',$params);
                if($codeUsage === 0){
                    $res = 2;
                }
            }

            return $res;

        }

        /** Returns all users
         *
         * @param array $params
         *          'idIn' => int[], defaults to [] - Returns users with IDs in this array
         *          'idAtLeast' => int, defaults to 1 - Returns users with ID equal or greater than this
         *          'idAtMost' => int, defaults to null - if set, Returns users with ID equal or smaller than this
         *          'rankAtLeast' => int, defaults to 1 - Returns users with rank equal or greater than this
         *          'rankAtMost' => int, defaults to null - if set, Returns users with rank equal or smaller than this
         *          'usernameLike' => String, default null - returns results where username  matches a regex.
         *          'emailLike' => String, email, default null - returns results where email matches a regex.
         *          'isActive' => bool, defaults to null - if set, Returns users which are either active or inactive (true or false).
         *          'isBanned' => bool, defaults to null - if set, Returns users which are either banned or not banned (true or false).
         *          'isSuspicious' => bool, defaults to null - if set, Returns users which are either suspicious or unsuspicious (true or false).
         *          'languageIs' => string, defaults to null - if set, Returns users whith this preferred language
         *          'createdBefore' => String, Unix timestamp, default null - only returns results created before this date.
         *          'createdAfter' => String, Unix timestamp, default null - only returns results created after this date.
         *          'orderBy'            - string, defaults to null. Possible values include 'Created', 'Email', 'Username',
         *                                 and 'ID' (default)
         *          'orderType'          - bool, defaults to null.  0 for 'ASC', 1 for 'DESC'
         *          'limit' => typical SQL parameter
         *          'offset' => typical SQL parameter
         *
         * @returns array of the form:
         *          [
         *              <identifier*> => Array|Code
         *          ] where:
         *              The array is the DB columns array
         *              And a special meta member with the key '@' holds the child '#', which's value is the number of results without a limit.
         */
        function getUsers(array $params = []): array {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $idIn = $params['idIn'] ?? [];
            $idAtLeast = $params['idAtLeast'] ?? 0;
            $idAtMost = $params['idAtMost'] ?? null;
            $rankAtLeast = $params['rankAtLeast'] ?? 0;
            $rankAtMost = $params['rankAtMost'] ?? null;
            $usernameLike = $params['usernameLike'] ?? null;
            $emailLike = $params['emailLike'] ?? null;
            $isActive = $params['isActive'] ?? null;
            $isBanned = $params['isBanned'] ?? null;
            $isLocked = $params['isLocked'] ?? null;
            $isSuspicious = $params['isSuspicious'] ?? null;
            $languageIs = $params['languageIs'] ?? null;
            $createdAfter = $params['createdAfter'] ?? null;
            $createdBefore = $params['createdBefore'] ?? null;

            $prefix = $this->SQLManager->getSQLPrefix();
            $usersColPrefix = $prefix.'USERS.';
            $usersExtraColPrefix = $prefix.'USERS_EXTRA.';

            if(isset($params['orderBy'])){
                $params['orderBy'] = match ($params['orderBy']) {
                    'Created' => $usersExtraColPrefix . $params['orderBy'],
                    'Email', 'Username', 'ID' => $usersColPrefix . $params['orderBy'],
                    default => null,
                };
            }

            $retrieveParams = $params;
            $conditions = [];

            //Create all the conditions for the db
            if(!empty($idIn)){
                $conditions[] = [$usersColPrefix . 'ID', array_merge($idIn, ['CSV']), 'IN'];
            }
            if($idAtLeast){
                $conditions[] = [$usersColPrefix . 'ID', $idAtLeast, '>='];
            }
            if($idAtMost){
                $conditions[] = [$usersColPrefix . 'ID', $idAtMost, '<='];
            }
            if($rankAtLeast){
                $conditions[] = [$usersColPrefix . 'Auth_Rank', $rankAtLeast, '>='];
            }
            if($rankAtMost){
                $conditions[] = [$usersColPrefix . 'Auth_Rank', $rankAtMost, '<='];
            }

            if($usernameLike!== null){
                $conditions[] = [$usersColPrefix . 'Username', [$usernameLike, 'STRING'], 'RLIKE'];
            }

            if($emailLike!== null){
                $conditions[] = [$usersColPrefix . 'Email', [$emailLike, 'STRING'], 'RLIKE'];
            }

            if($isActive!== null){
                if($isActive)
                    $conditions[] = [$usersColPrefix . 'Active', 0, '>'];
                else
                    $conditions[] = [$usersColPrefix . 'Active', 0, '='];
            }

            if($isBanned!== null){
                $conditions[] = $isBanned ?
                    [
                        [[$usersExtraColPrefix . 'Banned_Until', 'ISNULL'], 'NOT'],
                        [$usersExtraColPrefix . 'Banned_Until', time(), '>='],
                        'AND'
                    ]
                    :
                    [
                        [$usersExtraColPrefix . 'Banned_Until', 'ISNULL'],
                        [$usersExtraColPrefix . 'Banned_Until', time(), '<'],
                        'OR'
                    ];
            }
            if($isLocked!== null){
                $conditions[] = $isLocked ?
                    [
                        [[$usersExtraColPrefix . 'Locked_Until', 'ISNULL'], 'NOT'],
                        [$usersExtraColPrefix . 'Locked_Until', time(), '>='],
                        'AND'
                    ]
                    :
                    [
                        [$usersExtraColPrefix . 'Locked_Until', 'ISNULL'],
                        [$usersExtraColPrefix . 'Locked_Until', time(), '<'],
                        'OR'
                    ];
            }
            if($isSuspicious!== null){
                $conditions[] = $isSuspicious ?
                    [
                        [[$usersExtraColPrefix . 'Suspicious_Until', 'ISNULL'], 'NOT'],
                        [$usersExtraColPrefix . 'Suspicious_Until', time(), '>='],
                        'AND'
                    ]
                    :
                    [
                        [$usersExtraColPrefix . 'Suspicious_Until', 'ISNULL'],
                        [$usersExtraColPrefix . 'Suspicious_Until', time(), '<'],
                        'OR'
                    ];
            }

            if($languageIs!== null){
                $conditions[] = [$usersColPrefix . 'Preferred_Language', [$languageIs, 'STRING'], '='];
            }

            if($createdAfter!== null){
                $conditions[] = [$usersExtraColPrefix . 'Created', date('YmdHis', $createdAfter), '>='];
            }

            if($createdBefore!== null){
                $conditions[] = [$usersExtraColPrefix . 'Created', date('YmdHis', $createdBefore), '<='];
            }

            if(count($conditions) > 0){
                $conditions[] = 'AND';
            }

            $results = [];

            $tableQuery = $prefix.'USERS INNER JOIN '.$prefix.'USERS_EXTRA ON '.$prefix.'USERS.ID = '.$prefix.'USERS_EXTRA.ID';

            $res = $this->SQLManager->selectFromTable(
                $tableQuery,
                $conditions,
                [],
                $retrieveParams
            );
            $count = $this->SQLManager->selectFromTable(
                $tableQuery,
                $conditions,
                ['COUNT(*)'],
                array_merge($retrieveParams,['limit'=>null])
            );
            if(is_array($res)){
                $resCount = $res ? count($res[0]) : 0;
                foreach($res as $resultArray){
                    for($i = 0; $i<$resCount/2; $i++)
                        unset($resultArray[$i]);
                    $results[$resultArray['ID']] = $resultArray;
                }
                $results['@'] = array('#' => $count[0][0]);
                return $results;
            }
            //Only returns this if the DB call failed
            return [];

        }

        /** Updates a user
         *
         * @param int|string $identifier - either user email or user ID, depending on $identifierType. Defaults to ID
         *
         * @param array $inputs
         *          'username' => String, default null - new username
         *          'email' => String, default null - new Email
         *          'phone' => String, default null - new Phone (needs to include the country prefix, including '+')
         *          'active' => Int, default null - user trust level (0 - untrusted, 1 - trusted email, >=2 - per system implementation)
         *          'created' => Int, default null - Unix timestamp, user creation date.
         *          'bannedDate' => Int, default null - Unix timestamp until which the user is banned (0 to unban the user).
         *          'lockedDate' => Int, default null - Unix timestamp until which the user is locked (0 to unban the user).
         *          'suspiciousDate' => Int, default null - Unix timestamp until which the user is suspicious (0 to make the user not suspicious).
         *          -- The following update the authDetails column --
         *          'require2FA' => Bool, default null - whether 2 factor auth is required to log into this user.
         *          '2FASecret' => string, default null - secret used by a 2FA app.
         *          '2FASMSCode' => string, default null - the code needed by the user to successfully log in using sms 2FA.
         *          'smsTTL' => int, defaults to user setting sms2FAExpires - How many SECONDS before an SMS code expires
         *          'mailTTL' => int, defaults to user setting mail2FAExpires - How many SECONDS before an Email code expires
         *
         * @param string $identifierType - 'ID' or 'Email', sets the identifier type
         *
         * @param array $params
         *
         * @return int
         */
        function updateUser(int|string $identifier, #[\SensitiveParameter] array $inputs, string $identifierType = 'ID', array $params = []): int {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            //Inputs
            $username = $inputs['username'] ?? null;
            $email = $inputs['email'] ?? null;
            $phone = $inputs['phone'] ?? null;
            $active = $inputs['active'] ?? null;
            $created = $inputs['created'] ?? null;
            $bannedDate = $inputs['bannedDate'] ?? null;
            $lockedDate = $inputs['lockedDate'] ?? null;
            $suspiciousDate = $inputs['suspiciousDate'] ?? null;

            $prefix = $this->SQLManager->getSQLPrefix();
            $usersColPrefix = $prefix.'USERS.';
            $usersExtraColPrefix = $prefix.'USERS_EXTRA.';
            $tableQuery = $prefix.'USERS INNER JOIN '.$prefix.'USERS_EXTRA ON '.$prefix.'USERS.ID = '.$prefix.'USERS_EXTRA.ID';
            $conditions = [];
            $assignments = [];

            //Create all the conditions for the db
            if($identifierType == 'ID'){
                if(!in_array(gettype($identifier),['string','integer']) || !$identifier)
                    return 2;
                $identifierCondition = [$usersColPrefix.'ID',$identifier,'='];
            }
            elseif($identifierType == 'Email'){
                if(gettype($identifier)!='string' || !$identifier)
                    return 2;
                $identifierCondition = [$usersColPrefix.'Email',[$identifier,'STRING'],'='];
            }
            else{
                return 1;
            }
            $conditions[] = $identifierCondition;

            //Assignments
            if($username !== null){
                $assignments[] = $usersColPrefix . 'Username = "' . $username . '"';
            }
            if($email !== null){
                $assignments[] = $usersColPrefix . 'Email = "' . $email . '"';
            }
            if($phone !== null){
                $assignments[] = $usersColPrefix . 'Phone = "' . $phone . '"';
            }
            if($active !== null){
                $assignments[] = $usersColPrefix . 'Active = "' . ($active ? $active : '0') . '"';
            }
            if($created !== null){
                $assignments[] = $usersExtraColPrefix . 'Created = "' . date('YmdHis', $created) . '"';
            }
            if($bannedDate !== null){
                $assignments[] = $usersExtraColPrefix . 'Banned_Until = "' . $bannedDate . '"';
            }
            if($lockedDate !== null){
                $assignments[] = $usersExtraColPrefix . 'Locked_Until = "' . $lockedDate . '"';
            }
            if($suspiciousDate !== null){
                $assignments[] = $usersExtraColPrefix . 'Suspicious_Until = "' . $suspiciousDate . '"';
            }

            //If we are assigning anything related to new auth, we need to get the old one
            if(isset($inputs['require2FA']) || isset($inputs['2FASecret']) || isset($inputs['2FASMSCode'])|| isset($inputs['2FAMailCode'])){

                $userInfo = $this->SQLManager->selectFromTable(
                    $prefix.'USERS',
                    $identifierCondition,
                    [],
                    ['test'=>$test,'verbose'=>$verbose]
                );

                //DB connection error
                if(gettype($userInfo) !== 'array'){
                    $this->logger->warning('Could not select from DB for updateUser',['cond'=>$identifierCondition]);
                    return  -1;
                }
                //If user does not exists, our query "succeeds" automatically
                if(empty($userInfo[0]))
                    return  0;

                $TwoFactorAuth = $userInfo[0]['Two_Factor_Auth']? json_decode($userInfo[0]['Two_Factor_Auth'], true) : [];
                if(!isset($TwoFactorAuth['2FADetails']))
                    $TwoFactorAuth['2FADetails'] = [];

                if(isset($inputs['require2FA']))
                    $TwoFactorAuth['require2FA'] =  (bool)$inputs['require2FA'];
                if(isset($inputs['2FASecret'])){
                    $TwoFactorAuth['2FADetails']['secret'] =  $inputs['2FASecret'];
                }
                if(isset($inputs['2FASMSCode'])){
                    if(empty($TwoFactorAuth['2FADetails']['sms']))
                        $TwoFactorAuth['2FADetails']['sms'] = [];
                    $smsTTL = isset($params['smsTTL'])? (int)$params['smsTTL'] : (int)$this->userSettings->getSetting('sms2FAExpires');
                    $TwoFactorAuth['2FADetails']['sms']['code'] =  $inputs['2FASMSCode'];
                    $TwoFactorAuth['2FADetails']['sms']['expires'] =  time() + $smsTTL;
                }
                if(isset($inputs['2FAMailCode'])){
                    if(empty($TwoFactorAuth['2FADetails']['mail']))
                        $TwoFactorAuth['2FADetails']['mail'] = [];
                    $mailTTL = isset($params['mailTTL'])? (int)$params['mailTTL'] : (int)$this->userSettings->getSetting('mail2FAExpires');
                    $TwoFactorAuth['2FADetails']['mail']['code'] =  $inputs['2FAMailCode'];
                    $TwoFactorAuth['2FADetails']['mail']['expires'] =  time() + $mailTTL;
                }

                $assignments[] = $usersColPrefix . 'Two_Factor_Auth = \'' . json_encode($TwoFactorAuth) . '\'';
            }

            if($assignments == []){
                return 3;
            }

            $res = $this->SQLManager->updateTable(
                $tableQuery,
                $assignments,
                $conditions,
                $params
            );

            if(!$res){
                $this->logger->warning('Could not update user',['assignments'=>$assignments,'cond'=>$conditions]);
                return -1;
            }
            return 0;

        }

    }
}