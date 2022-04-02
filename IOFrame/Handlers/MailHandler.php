<?php

namespace IOFrame\Handlers{
    use IOFrame;
    use PHPMailer\PHPMailer\PHPMailer;
    define('MailHandler',true);
    if(!defined('abstractDBWithCache'))
        require 'abstractDBWithCache.php';
    /*Handles mail sending authentication
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */
    class MailHandler extends IOFrame\abstractDBWithCache{
        //PHP Mailer
        protected $mail;
        //Loads a template to serve as the body, using sendMailTemplate
        protected $template=null;
        //Default alias to send system mails with
        protected $defaultAlias=null;
        protected $mailTokenCachePrefix = 'mail_auth_token_';
        protected $mailTemplateCachePrefix = 'mail_template_';

        function __construct(
            SettingsHandler $settings,
            $params = []
        ){

            //Set defaults
            if(!isset($params['secure']))
                $secure = true;
            else
                $secure = $params['secure'];

            if(!isset($params['verbose']))
                $verbose = false;
            else
                $verbose = $params['verbose'];

            parent::__construct($settings,$params);

            $this->mailSettings = new SettingsHandler(
                $this->settings->getSetting('absPathToRoot').SETTINGS_DIR_FROM_ROOT.'/mailSettings',
                $this->defaultSettingsParams
            );

            $this->mail= new PHPMailer;
            $this->mail->isSMTP(); // Set mailer to use SMTP
            $this->mail->SMTPAuth = true;                               // Enable SMTP authentication
            $this->mail->isHTML(true);
            $this->updateMailSettings($secure, $verbose);
        }

        //An extantion of the construct function - but this has to be called before sending each mail, in case the settings
        //Changed
        function updateMailSettings($secure, $verbose){

            if (!$this->mailSettings->getSetting('mailHost') or !$this->mailSettings->getSetting('mailEncryption') or
                !$this->mailSettings->getSetting('mailUsername') or !$this->mailSettings->getSetting('mailPassword') or
                !$this->mailSettings->getSetting('mailPort')
            )
                throw(new \Exception('Cannot update mail settings - missing settings or cannot read settings file.'));
            else {
                $this->mail->Host = $this->mailSettings->getSetting('mailHost');                // Specify main and backup SMTP servers
                $this->mail->SMTPSecure = $this->mailSettings->getSetting('mailEncryption');    // Enable TLS/SSL encryption
                $this->mail->Username = $this->mailSettings->getSetting('mailUsername');        // SMTP username
                $this->mail->Password = $this->mailSettings->getSetting('mailPassword');        // SMTP password
                $this->mail->Port = $this->mailSettings->getSetting('mailPort');                // TCP port to connect to

                $this->defaultAlias = $this->mailSettings->getSetting('defaultAlias') ? $this->mailSettings->getSetting('defaultAlias'): $this->mail->Username;

                if($verbose)
                    $this->mail->SMTPDebug = 3;
                if(!$secure){
                    $this->mail->SMTPOptions = array(
                        'ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        )
                    );
                }
            }
        }

        /** Creates a security token of the length $length to be used for $mail, valid for $duration minutes after creation.
         * @param string $mail Mail the token is valid for
         * @param array $params of the form:
         *          'duration'  - int, duration (in minutes) for how long the token is valid
         *          'length'    - int, Token length in characters
         *          'override'  - bool, By default, we check for existing token for the email address.
         *                         If $override is true, will always create and new token and set a new expiry,
         *                         else will if current token hasn't expired will return it instead.
         *
         * @return string|int
         *      -1 - failed to connect to DB
         *      <string>- Security token, if successfully created or already exists.
         * */
        function createSecToken(string $mail, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            $duration = isset($params['duration'])? $params['duration'] : 1;
            $length = isset($params['length'])? $params['length'] : 30;
            $override = isset($params['override'])? $params['override'] : 1;

            $existingSecToken = $this->getFromCacheOrDB(
                [$mail],
                'Name',
                'MAIL_AUTH',
                $this->mailTokenCachePrefix,
                [],
                array_merge($params,['useCache'=>false])
            );
            //Failed to connect to the db
            if($existingSecToken[$mail] === -1)
                return -1;
            //No existing token for the mail address
            elseif($existingSecToken[$mail] === 1){
                $token = IOFrame\Util\GeraHash($length);
                $res = $this->SQLHandler->insertIntoTable(
                    $this->SQLHandler->getSQLPrefix()."MAIL_AUTH",
                    ['Name', 'Value', 'expires'],
                    [
                        [
                            [$mail,'STRING'],
                            [$token,'STRING'],
                            [(string)(time()+60*$duration),'STRING'],
                        ]
                    ],
                    $params
                );
                return $res===true? $token : -1;
            }
            //A token for the mail address already exists - create a new token if the old one expired, or refresh the time if the token didn't expire
            else{
                $dbres = $existingSecToken[$mail];
                if(($dbres['expires'] < time()) || $override)
                    $token = IOFrame\Util\GeraHash($length);
                else
                    $token = $dbres['Name'];

                $res = $this->SQLHandler->insertIntoTable(
                    $this->SQLHandler->getSQLPrefix()."MAIL_AUTH",
                    ['Name', 'Value', 'expires'],
                    [
                        [
                            [$mail,'STRING'],
                            [$token,'STRING'],
                            [(string)(time()+60*$duration),'STRING'],
                        ]
                    ],
                    $params
                );
                return $res===true? $token : -1;
            }
        }

        /* Removes the security token from $mail. Basically deletes the entry.
         * returns true on success, false on failure or if the token hasn't expired and ifExpired was set to true.
         * */
        function removeSecToken($mail, $ifExpired = false){
            $res = true;
            if($ifExpired){
                $dbres = $this->SQLHandler->selectFromTable($this->SQLHandler->getSQLPrefix().'MAIL_AUTH',['Name',$mail,'='],[],[]);
                if(is_array($dbres) && count($dbres) > 0){
                    $dbres = $dbres[0];
                    $expires = $dbres['expires'];
                    if(time()<$expires)
                        $res = false;
                }

            }
            if($res){
                $this->SQLHandler->exeQueryBindParam("DELETE FROM ".$this->SQLHandler->getSQLPrefix()."MAIL_AUTH WHERE Name=:Name",
                    [[':Name',$mail]]);
                return true;
            }
            else
                return false;
        }

        /* Verfies security token and returns 0 if the API is allowed to send the mail judging by that token.
         * In order to send more than one mail, the token needs to verfy MailAPIGlobal, else it needs to verfy the mail adress.
         * Returns:
         * 0 if the given security token allows to send the mail or mails.
         * 1 if the mail (or MailAPIGlobal) does not exist,
         * 2 if the token is wrong,
         * 3 if it expired.
         *
         * */
        function verifySecToken($token, $mails, array $params = []){

            $test = (isset($params['test']))? $params['test'] : false;
            $verbose = (isset($params['verbose']))? $params['verbose'] : $test;

            IOFrame\Util\is_json($mails)?
                $cond = 'MailAPIGlobal'
                :
                $cond = $mails;
            $dbres = $this->SQLHandler->selectFromTable($this->SQLHandler->getSQLPrefix().'MAIL_AUTH',['Name',$cond,'='],[],[]);

            if($verbose)
                echo 'db response: '.json_encode($dbres).EOL;

            if(!is_array($dbres)|| count($dbres) == 0)
                return 1;
            else
                $dbres = $dbres[0];
            $neededToken = $dbres['Value'];
            $expires = $dbres['expires'];
            if($token==$neededToken){
                if(time()<$expires){
                    return 0;
                }
                else{
                    return 3;
                }
            }
            else
                return 2;
        }

        /* Makes selects a template from the Mail_Templates table to serve as the current template for this specific handler.
         * DOES NOT RECOGNIZE TITLES
         * @param int $templateID ID of the template
         * @param array $params of the form:
         *                  'safeStr' - bool, default true - convert from safeString to string
         *
         * Return values:
         * -1 - could not connect to db
         *  0 - all is good
         *  1 - ID doesn't match any template.
         * */
        function setWorkingTemplate(int $templateID, array $params = []){

            $test = (isset($params['test']))? $params['test'] : false;
            $verbose = (isset($params['verbose']))? $params['verbose'] : $test;

            if(!defined('safeSTR'))
                require __DIR__.'/../Util/safeSTR.php';

            $mailTemplate = $this->getTemplate(
                $templateID,
                $params
            );

            if($mailTemplate === -1)
                return -1;
            if($mailTemplate === 1)
                return 1;

            $this->template = $mailTemplate['Content'];
            if($verbose)
                echo 'Set working template to '.$this->template.EOL;

            return 0;
        }

        //Resets Template to null
        function resetWorkingTemplate(){
            $this->template = null;
        }

        function printActiveTemplate(){
            echo $this->template;
        }

        /* Inserts values from varArray into the fitting places in the $template.
         * $varArray should be in a JSON format, and look like: {"Var1":"Value1", "Var2":"Value2" ...}
         * Variables in the template are formatted like %%VARIABLE_NAME%% and ARE case-sensitive!
         * */
        function fillTemplate($template, $varArray){
            $body=$template;
            $vars = json_decode($varArray,true);

            foreach ($vars as $key=>$value){
                $body=str_replace("%%".$key."%%", $value, $body);
            }

            return $body;
        }

        /* addresses  = array of all the recipients' mails. Two dimentional array, where [x][0] is the address, [x][1] is an optional name
         * subject    = the subject of your mail
         * body       = the body of your mail
         * mailerName = The mail and name the recipient will see of the one who sent them the mail. Array of length 2
         * altBody    = alternative body for your mail, containing no HTML
         * attachments= array of file locations you want to send as attachments
         * ccs        = array of CC recipients
         * bccs       = array of BCC recipients
         * replies    = array of people you want to send this mail to as a reply
         * */
        function sendMail( $addresses, $subject, $body, $mailerName =['',''], $altBody='', $attachments =[[]], $ccs=[[]], $bccs =[[]], $replies=[[]] ){

            //Handle who we set this as, first
            if($mailerName[0]!='')
                if($mailerName[1]!='')
                    $this->mail->setFrom($mailerName[0], $mailerName[1]);
                else
                    $this->mail->setFrom($mailerName[0]);
            else
                if($mailerName[1]!='')
                    $this->mail->setFrom($this->defaultAlias, $mailerName[1]);
                else
                    $this->mail->setFrom($this->defaultAlias);

            $this->mail->CharSet = 'utf-8';
            $this->mail->Subject = iconv(mb_detect_encoding($subject, 'auto'), 'UTF-8', $subject);
            $this->mail->Body    = $body;
            $this->mail->AltBody = $altBody;

            if(count($addresses)>0)
                foreach($addresses as $key=>$value){
                    if(isset($value[0]))
                        if(isset($value[1]))
                            $this->mail->addAddress($value[0], $value[1]);
                        else
                            $this->mail->addAddress($value[0]);
                }
            else{
                throw(new \Exception('Cannot send a mail with no recipients!'));
            }

            foreach($replies as $key=>$value){
                if(isset($value[0]))
                    if(isset($value[1]))
                        $this->mail->addReplyTo($value[0], $value[1]);
                    else
                        $this->mail->addReplyTo($value[0]);
            }

            foreach($ccs as $key=>$value){
                if(isset($value[0]))
                    if(isset($value[1]))
                        $this->mail->addCC($value[0], $value[1]);
                    else
                        $this->mail->addCC($value[0]);
            }

            foreach($bccs as $key=>$value){
                if(isset($value[0]))
                    if(isset($value[1]))
                        $this->mail->addBCC($value[0], $value[1]);
                    else
                        $this->mail->addBCC($value[0]);
            }

            foreach($attachments as $key=>$value){
                if(isset($value[0]))
                    if(isset($value[1]))
                        $this->mail->addAttachment($value[0], $value[1]);
                    else
                        $this->mail->addAttachment($value[0]);
            }

            if(!$this->mail->send()) {
                throw(new \Exception('Message could not be sent, Mailer Error: '. $this->mail->ErrorInfo));
            } else {
                return true;
            }

        }


        /* Sends a mail from a template.
         * Will replace the %%VARIABLES%% in the template with matching values from $varArray
         * $varArray should be in a JSON format, and look like: {"VAR1":"Value1", "Var2":"Value2" ...}
         * */
        function sendMailTemplate( $addresses, $subject, $template='', $varArray='', $mailerName =['',''],
                                   $altBody='', $attachments =[[]], $ccs=[[]], $bccs =[[]], $replies=[[]] ){
            if($template=='' and $this->template == null)
                throw(new \Exception('You cannot send an email from a template without a template!'));
            if($template!='')
                return $this->sendMail( $addresses, $subject, $this->fillTemplate($template,$varArray), $mailerName, $altBody,
                    $attachments, $ccs, $bccs, $replies);
            else if( $this->template != null)
                return $this->sendMail( $addresses, $subject, $this->fillTemplate($this->template,$varArray), $mailerName, $altBody,
                    $attachments, $ccs, $bccs, $replies);
            else
                return false;
        }

        /* Sends a mail "asynchronously" - aka with 1 second delay.
         * Most fields are seen in the functions above, only that this function allows only limited use of them, and has
         * type - The type of email sent, template or normal.
         * secToken - Security token needed to validate the email being sent using the API.
         * */
        function sendMailAsync( $address, $subject, $secToken, $mailerName =['',''],
                                $mBody='', $templateNum='', $varArray='',$type = 'normal' ){
            $post = 'action=mailTo&mail='.$address.'&subj='.$subject.'&secToken='.$secToken;
            if($type=='template'){
                $post.='&type=template&templateNum='.$templateNum.'&varArray='.$varArray;
            }
            else{
                $post.='&type=normal&mBody='.$mBody;
            }

            if($mailerName[0]!='')
                $post.='&mName1='.$mailerName[0];
            if($mailerName[1]!='')
                $post.='&mName2='.$mailerName[1];

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL,"http://".$_SERVER['SERVER_NAME'].$this->settings->getSetting('pathToRoot')."/api/mail");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_USERAGENT, 'api');
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);

            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

            $res = curl_exec ($ch);

            //TODO use log handler to log anything that isn't 0

            curl_close ($ch);
        }

        /** TEMPLATE RELATED STUFF **/
        /** Gets a single template.
         * @param int $templateID ID
         * @param array $params
         *
         * @returns array|int
         *          DB array,
         *          OR code:
         *         -1 - could not connect to db
         *          1 - template does not exist
         *
        */
        function getTemplate(int $templateID, array $params = []){
            return $this->getTemplates([$templateID],$params)[$templateID];
        }

        /** Gets all templates available
         *
         * @param array $templates defaults to [], if not empty will only get specific templates
         * @param array $params getFromCacheOrDB() params, as well as:
         *          'createdAfter'      - int, default null - Only return items created after this date.
         *          'createdBefore'     - int, default null - Only return items created before this date.
         *          'changedAfter'      - int, default null - Only return items last changed after this date.
         *          'changedBefore'     - int, default null - Only return items last changed  before this date.
         *          'includeRegex'      - string, default null - A  regex string that titles need to match in order
         *                                to be included in the result.
         *          'excludeRegex'      - string, default null - A  regex string that titles need to match in order
         *                                to be excluded from the result.
         *          'safeStr'           - bool, default true. Whether to convert Meta to a safe string
         *          ------ Using the parameters bellow disables caching ------
         *          'limit'             - string, SQL LIMIT, defaults to system default
         *          'offset'            - string, SQL OFFSET
         *
         * @returns array Array of the form:
         *      [
         *       <Template ID> =>   <Array of DB info> | <code from getTemplate()>,
         *      ...
         *      ],
         *
         *      on full search, the array will include the item '@' of the form:
         *      {
         *          '#':<number of total results>
         *      }
         */
        function getTemplates(array $templates = [], array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            $createdAfter = isset($params['createdAfter'])? $params['createdAfter'] : null;
            $createdBefore = isset($params['createdBefore'])? $params['createdBefore'] : null;
            $changedAfter = isset($params['changedAfter'])? $params['changedAfter'] : null;
            $changedBefore = isset($params['changedBefore'])? $params['changedBefore'] : null;
            $includeRegex = isset($params['includeRegex'])? $params['includeRegex'] : null;
            $excludeRegex = isset($params['excludeRegex'])? $params['excludeRegex'] : null;
            $limit = isset($params['limit'])? $params['limit'] : null;
            $offset = isset($params['offset'])? $params['offset'] : null;
            $safeStr = isset($params['safeStr'])? $params['safeStr'] : true;

            $retrieveParams = $params;
            $extraDBConditions = [];
            $extraCacheConditions = [];

            //If we are using any of this functionality, we cannot use the cache
            if( $offset || $limit){
                $retrieveParams['useCache'] = false;
                $retrieveParams['limit'] =  $limit? $limit : null;
                $retrieveParams['offset'] =  $offset? $offset : null;
            }

            //Create all the conditions for the db/cache
            if($createdAfter!== null){
                $cond = ['Created',$createdAfter,'>'];
                array_push($extraCacheConditions,$cond);
                array_push($extraDBConditions,$cond);
            }

            if($createdBefore!== null){
                $cond = ['Created',$createdBefore,'<'];
                array_push($extraCacheConditions,$cond);
                array_push($extraDBConditions,$cond);
            }

            if($changedAfter!== null){
                $cond = ['Last_Updated',$changedAfter,'>'];
                array_push($extraCacheConditions,$cond);
                array_push($extraDBConditions,$cond);
            }

            if($changedBefore!== null){
                $cond = ['Last_Updated',$changedBefore,'<'];
                array_push($extraCacheConditions,$cond);
                array_push($extraDBConditions,$cond);
            }

            if($includeRegex!== null){
                array_push($extraCacheConditions,['Title',$includeRegex,'RLIKE']);
                array_push($extraDBConditions,['Title',[$includeRegex,'STRING'],'RLIKE']);
            }

            if($excludeRegex!== null){
                array_push($extraCacheConditions,['Title',$excludeRegex,'NOT RLIKE']);
                array_push($extraDBConditions,['Title',[$excludeRegex,'STRING'],'NOT RLIKE']);
            }

            if($extraCacheConditions!=[]){
                array_push($extraCacheConditions,'AND');
                $retrieveParams['columnConditions'] = $extraCacheConditions;
            }
            if($extraDBConditions!=[]){
                array_push($extraDBConditions,'AND');
                $retrieveParams['extraConditions'] = $extraDBConditions;
            }

            if($templates == []){
                $results = [];
                $res = $this->SQLHandler->selectFromTable(
                    $this->SQLHandler->getSQLPrefix().'MAIL_TEMPLATES',
                    $extraDBConditions,
                    [],
                    $retrieveParams
                );
                $count = $this->SQLHandler->selectFromTable(
                    $this->SQLHandler->getSQLPrefix().'MAIL_TEMPLATES',
                    $extraDBConditions,
                    ['COUNT(*)'],
                    array_merge($retrieveParams,['limit'=>0])
                );
                if(is_array($res)){
                    $resCount = isset($res[0]) ? count($res[0]) : 0;
                    foreach($res as $resultArray){
                        for($i = 0; $i<$resCount/2; $i++)
                            unset($resultArray[$i]);
                        if($safeStr)
                            if($resultArray['Content'] !== null)
                                $resultArray['Content'] = IOFrame\Util\safeStr2Str($resultArray['Content']);
                        $results[$resultArray['ID']] = $resultArray;
                    }
                    $results['@'] = array('#' => $count[0][0]);
                }
                return ($res)? $results : [];
            }
            else{
                $results = $this->getFromCacheOrDB(
                    $templates,
                    'ID',
                    'MAIL_TEMPLATES',
                    $this->mailTemplateCachePrefix,
                    [],
                    $retrieveParams
                );

                if($safeStr)
                    foreach($results as $template =>$result){
                        if($results[$template]['Content'] !== null)
                            $results[$template]['Content'] = IOFrame\Util\safeStr2Str($results[$template]['Content']);
                    }

                return $results;
            }

        }


        /** Sets a template. ( value NULL ignores something by default, '' sets something to null if allowed )
         * @param int $templateID ID of the template. If -1, will create a new template instead.
         * @param string|null $title Title you wish to set
         * @param string|null $content Content of the template
         * @param array $params of the form:
         *          'createNew' - bool, default false - if true, will disregard ID and create a new template.
         *          'override' - bool, default true - will overwrite existing templates.
         *          'update' - bool, default false - will only update existing templates.
         *          'existing' - Array, potential existing templates if we already got them earlier.
         *          'safeStr' - bool, default true. Whether to convert Content to a safe string
         * @returns int Code of the form:
         *         -3 - Template exists and override is false
         *         -2 - Template does not exist and required fields are not provided, or 'update' is true
         *         -1 - Could not connect to db
         *          0 - All good
         *         [createNew] ID of the newly created template
         */
        function setTemplate( int $templateID = -1, string $title = null, string $content = null, array $params = []){
            $createNew = isset($params['createNew'])? $params['createNew'] : false;
            return
                (
                $createNew ?
                    $this->setTemplates([[$templateID,$title,$content]],$params) :
                    $this->setTemplates([[$templateID,$title,$content]],$params)[$templateID]
                );
        }

        /** Sets a set of template.
         * @param array $inputs Array of input arrays in the same order as the inputs in setTemplate.
         *                      If even ONE of the ids is -1, will enter creation mode.
         * @param array $params from setTemplate
         * @returns int[]|int Array of the form
         *          <templateID> => <code>
         *          where the codes come from setTemplate().
         *          On createNew, the ID of the FIRST CREATED TEMPLATE will be returned.
         */
        function setTemplates(array $inputs, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            $update = isset($params['update'])? $params['update'] : false;
            $override = isset($params['override'])? $params['override'] : true;
            $safeStr = isset($params['safeStr'])? $params['safeStr'] : true;
            $createNew = isset($params['createNew'])? $params['createNew'] : false;

            $templates = [];
            $existingTemplates = [];
            $indexMap = [];
            $templateMap = [];
            $results = [];
            $templatesToSet = [];
            $currentTime = (string)time();

            if(!$createNew)
                foreach($inputs as $index=>$input){
                    array_push($templates,$input[0]);
                    $results[$input[0]] = -1;
                    $indexMap[$input[0]] = $index;
                    $templateMap[$index] = $input[0];
                }

            if(!$createNew){
                if(isset($params['existing']))
                    $existing = $params['existing'];
                else
                    $existing = $this->getTemplates($templates, array_merge($params,['updateCache'=>false]));
            }
            else{
                $update = false;
                $override = true;
            }

            foreach($inputs as $index=>$input){
                //In this case the template does not exist or couldn't connect to db
                if($createNew || !is_array($existing[$templateMap[$index]])){
                    //If we could not connect to the DB, just return because it means we wont be able to connect next
                    if(!$createNew && $existing[$templateMap[$index]] == -1)
                        return $results;
                    else{
                        //If we are only updating, continue
                        if($update){
                            $results[$input[0]] = -2;
                            continue;
                        }
                        //If the template does not exist, make sure all needed fields are provided
                        //Set title to null if not provided
                        if(!isset($inputs[$index][1]) || $inputs[$index][1] === null)
                            $inputs[$index][1] = null;
                        //content
                        if(!isset($inputs[$index][2]))
                            $inputs[$index][2] = null;
                        elseif($safeStr)
                            $inputs[$index][2] = IOFrame\Util\str2SafeStr($inputs[$index][2]);

                        //Add the template to the array to set
                        $itemToSet = [];
                        if(!$createNew)
                            array_push($itemToSet,$inputs[$index][0]);
                        array_push($itemToSet,[$inputs[$index][1],'STRING']);
                        array_push($itemToSet,[$inputs[$index][2],'STRING']);
                        array_push($itemToSet,[$currentTime,'STRING']);
                        array_push($itemToSet,[$currentTime,'STRING']);
                        array_push($templatesToSet,$itemToSet);
                    }
                }
                //This is the case where the item existed
                else{
                    //If we are not allowed to override existing templates, go on
                    if(!$override && !$update){
                        $results[$input[0]] = -3;
                        continue;
                    }
                    //Push an existing template in to be removed from the cache
                    array_push($existingTemplates,$this->mailTemplateCachePrefix.$input[0]);
                    //Complete every field that is NULL with the existing template
                    //title
                    if(!isset($inputs[$index][1]) || $inputs[$index][1] === null)
                        $inputs[$index][1] = $existing[$templateMap[$index]]['Title'];
                    //content
                    if(!isset($inputs[$index][2]) || $inputs[$index][2] === null)
                        $inputs[$index][2] = $existing[$templateMap[$index]]['Content'];
                    if($safeStr)
                        $inputs[$index][2] = IOFrame\Util\str2SafeStr($inputs[$index][2]);
                    //Add the template to the array to set
                    array_push($templatesToSet,[
                        $inputs[$index][0],
                        [$inputs[$index][1],'STRING'],
                        [$inputs[$index][2],'STRING'],
                        [$existing[$templateMap[$index]]['Created_On'],'STRING'],
                        [$currentTime,'STRING']
                    ]);
                }
            }

            //If we got nothing to set, return
            if($templatesToSet==[])
                return $results;

            $columns = $createNew ?
                ['Title','Content','Created_On','Last_Updated'] : ['ID','Title','Content','Created_On','Last_Updated'];

            $res = $this->SQLHandler->insertIntoTable(
                $this->SQLHandler->getSQLPrefix().'MAIL_TEMPLATES',
                $columns,
                $templatesToSet,
                array_merge($params,['onDuplicateKey'=>!$createNew,'returnRows' => $createNew])
            );

            //If we succeeded, set results to success and remove them from cache
            if($res){
                //If we are in creation mode, get latest ID
                if($createNew){
                    $results = $test? $res : 1;
                    if($results == 0)
                        $results = -1;
                }
                //Else, set results and delete from cache
                else{
                    foreach($templates as $template){
                        if($results[$template] == -1)
                            $results[$template] = 0;
                    }
                    if($existingTemplates != []){
                        if(count($existingTemplates) == 1)
                            $existingTemplates = $existingTemplates[0];

                        if($verbose)
                            echo 'Deleting templates '.json_encode($existingTemplates).' from cache!'.EOL;

                        if(!$test)
                            $this->RedisHandler->call('del',[$existingTemplates]);
                    }
                }
            }

            return $results;
        }

        /** Deletes a template
         * @param int $templateID
         * @param array $params
         * @returns int Code of the form:
         *         -1 - Failed to connect to db
         *          0 - All good
         *          1 - Template does not exist
         *
         */
        function deleteTemplate(int $templateID, array $params){
            return $this->deleteTemplates([$templateID],$params);
        }

        /** Deletes templates.
         *
         * @param array $templates
         * @param array $params
         *          'checkExisting' - bool, default true - whether to check for existing templates
         * @returns Array of the form:
         * [
         *       <templateID> =>  <code>,
         *       ...
         * ]
         * Where the codes are from deleteTemplate
         */
        function  deleteTemplates(array $templates, array $params = []){
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])? $params['verbose'] : ($test ? true : false);
            $checkExisting = isset($params['checkExisting'])? $params['checkExisting'] : true;

            $results = [];
            $templatesToDelete = [];
            $templatesToDeleteFromCache = [];
            $failedGetConnection = false;
            $existing = $checkExisting ? $this->getTemplates($templates,array_merge($params,['updateCache'=>false])) : [];

            foreach($templates as $template){
                if($existing!=[] && !is_array($existing[$template])){
                    if($verbose)
                        echo 'Template '.$template.' does not exist!'.EOL;
                    if($existing[$template] == -1)
                        $failedGetConnection = true;
                    $results[$template] = $existing[$template];
                }
                else{
                    $results[$template] = -1;
                    array_push($templatesToDelete,[$template,'STRING']);
                    array_push($templatesToDeleteFromCache,$this->mailTemplateCachePrefix.$template);
                }
            }

            //Assuming if one result was -1, all of them were
            if($failedGetConnection){
                return $results;
            }

            if($templatesToDelete == []){
                if($verbose)
                    echo 'Nothing to delete, exiting!'.EOL;
                return $results;
            }

            $res = $this->SQLHandler->deleteFromTable(
                $this->SQLHandler->getSQLPrefix().'MAIL_TEMPLATES',
                [
                    'ID',
                    $templatesToDelete,
                    'IN'
                ],
                $params
            );

            if($res){
                foreach($templates as $template){
                    if($results[$template] == -1)
                        $results[$template] = 0;
                }

                if($templatesToDeleteFromCache != []){
                    if(count($templatesToDeleteFromCache) == 1)
                        $templatesToDeleteFromCache = $templatesToDeleteFromCache[0];

                    if($verbose)
                        echo 'Deleting templates '.json_encode($templatesToDeleteFromCache).' from cache!'.EOL;

                    if(!$test)
                        $this->RedisHandler->call('del',[$templatesToDeleteFromCache]);
                }
            }

            return $results;
        }

    }
}