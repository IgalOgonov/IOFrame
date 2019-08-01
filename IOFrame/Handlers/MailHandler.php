<?php

namespace IOFrame\Handlers{
    use IOFrame;
    use PHPMailer\PHPMailer\PHPMailer;
    use Monolog\Logger;
    use Monolog\Handler\IOFrameHandler;
    define('MailHandler',true);
    if(!defined('abstractDBWithCache'))
        require 'abstractDBWithCache.php';
    require_once 'ext/phpmailer/vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once 'ext/phpmailer/vendor/phpmailer/phpmailer/src/SMTP.php';
    require_once 'ext/phpmailer/vendor/phpmailer/phpmailer/src/Exception.php';
    require_once 'ext/phpmailer/vendor/phpmailer/phpmailer/src/OAuth.php';
    /*Handles mail sending authentication
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */
    class MailHandler extends IOFrame\abstractDBWithCache{
        private $mail;          //PHP Mailer
        private $template=null; //Loads a templete to serve as the body, using sendMailTemplate
        private $conn = null;   //Database connection

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
                if($verbose)
                    $this->mail->SMTPDebug = 2;
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

        /* Creates a security token of the length $length to be used for $mail, valid for $duration minutes after creation.
         * By default, duration is 1, length is 30, override is false.
         * Will check for existing token for the email address. If $override is true, will always create and new token and set a new expiery,
         * else will if current token hasn't expired will return it instead.
         * Returns:
         * $token - Security token successfully created, token is the token. Will never be '0'.
         * */
        function createSecToken($mail, $duration=1, $length=30, $override=false){
            $dbres = $this->SQLHandler->selectFromTable($this->SQLHandler->getSQLPrefix().'MAIL_AUTH',['Name',$mail,'='],[],[]);
            //If no token for $mail existed, insert new values
            if(!is_array($dbres)|| count($dbres) == 0){
                $token = IOFrame\Util\GeraHash($length);
                $this->SQLHandler->exeQueryBindParam("INSERT INTO ".$this->SQLHandler->getSQLPrefix()."MAIL_AUTH(Name, Value, expires)
             VALUES (:Name, :Value, :expires)",[[':Name',$mail],[':Value',$token],[':expires',time()+60*$duration]]);
                return $token;
            }
            //Else, create a new token if the old one expired, or refresh the time if the token didn't expire
            else{
                $dbres = $dbres[0];
                if(($dbres['expires'] < time()) || $override){
                    $token = IOFrame\Util\GeraHash($length);
                    $this->SQLHandler->exeQueryBindParam("UPDATE ".$this->SQLHandler->getSQLPrefix()."MAIL_AUTH
                SET Value=:Value, expires=:expires WHERE Name=:Name",
                        [[':Name',$dbres['Name']],[':Value',$token],[':expires',time()+60*$duration]]);
                    return $token;
                }
                else{
                    $this->SQLHandler->exeQueryBindParam("UPDATE ".$this->SQLHandler->getSQLPrefix()."MAIL_AUTH
                SET expires=:expires WHERE Name=:Name", [[':Name',$dbres['Name']],[':expires',time()+60*$duration]]);
                    return $dbres['Value'];
                }
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
        function verfySecToken($token, $mails, array $params = []){

            $test = (isset($params['test']))? $params['test'] : false;
            $verbose = (isset($params['verbose']))? $params['verbose'] : $test;

            var_dump($token,$mails);

            IOFrame\Util\is_json($mails)?
                $cond = 'MailAPIGlobal'
                :
                $cond = $mails;
            $dbres = $this->SQLHandler->selectFromTable($this->SQLHandler->getSQLPrefix().'MAIL_AUTH',['Name',$cond,'='],[],[]);

            var_dump($dbres);

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
         * Return values:
         * 0 - all is good
         * 1 - templateID isn't a number!
         * 2 - ID doesn't match any template.
         * */
        function setTemplate($templateID, $convertFromSafeSTR = true){
            if (!preg_match('/^[0-9]*$/', $templateID)) {
                return 1;
            } else {
                if(!defined('safeSTR'))
                    require __DIR__.'/../Util/safeSTR.php';
                try{
                    $mailTemplate = $this->SQLHandler->selectFromTable($this->SQLHandler->getSQLPrefix().'MAIL_TEMPLATES',['ID',$templateID,'='],[],['noValidate'=>true]);
                    if(is_array($mailTemplate) && count($mailTemplate)>0){
                        $mailTemplate = $mailTemplate[0];
                        if($convertFromSafeSTR)
                            $mailTemplate['Content'] = IOFrame\Util\safeStr2Str($mailTemplate['Content']);
                        $this->template = $mailTemplate['Content'];
                        return 0;
                    }
                    else{
                        return 2;
                    }
                }
                catch(\Exception $e){
                    return 'Database error: '.$e;
                }
            }
        }

        //Resets Template to null
        function resetTemplate(){
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
        function sendMail( $addresses, $subject, $body, $mailerName =['',''], $altBody='', $async=false,
                           $attachments =[[]], $ccs=[[]], $bccs =[[]], $replies=[[]] ){

            //Handle who we set this as, first
            if($mailerName[0]!='')
                if($mailerName[1]!='')
                    $this->mail->setFrom($mailerName[0], $mailerName[1]);
                else
                    $this->mail->setFrom($mailerName[0]);
            else
                if($mailerName[1]!='')
                    $this->mail->setFrom($this->mail->Username, $mailerName[1]);
                else
                    $this->mail->setFrom($this->mail->Username);


            $this->mail->Subject = $subject;
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
            curl_setopt($ch,  CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 10);

            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_exec ($ch);

            curl_close ($ch);

        }

    }
}