<?php

namespace IOFrame\Managers{
    use PHPMailer\PHPMailer\Exception;
    use PHPMailer\PHPMailer\PHPMailer;

    define('IOFrameManagersMailManager',true);
    /*Handles mail sending authentication
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */
    class MailManager extends \IOFrame\Handlers\MailTemplateHandler{
        //Operation mode - currently supporting 'smtp', future integrations will also go here
        protected ?string $mode;
        //PHP Mailer
        protected \PHPMailer\PHPMailer\PHPMailer $mail;
        //Mail Settings
        public ?\IOFrame\Handlers\SettingsHandler $mailSettings = null;
        //Default alias to send system mails with
        protected ?string $defaultAlias=null;
        /** @throws \Exception if undefined settings
         * */
        function __construct(
            \IOFrame\Handlers\SettingsHandler $settings,
            $params = []
        ){

            //Set defaults
            $secure = $params['secure'] ?? true;
            $verbose = $params['verbose'] ?? false;
            $mailSettingsOverwrite = $params['mailSettingsOverwrite'] ?? [];
            $this->mode = $params['mode'] ?? 'smtp';

            parent::__construct($settings,$params);

            if(empty($params['mailSettings']) || !is_object($params['mailSettings']) || (get_class($params['mailSettings']) !== 'IOFrame\Handlers\SettingsHandler')){
                $this->mailSettings = new \IOFrame\Handlers\SettingsHandler(
                    $this->settings->getSetting('absPathToRoot').\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/mailSettings',
                    $this->defaultSettingsParams
                );
            }
            else
                $this->mailSettings = $params['mailSettings'];

            if($this->mode === 'smtp'){
                $this->mail= new PHPMailer;
                $this->mail->isSMTP(); // Set mailer to use SMTP
                $this->mail->SMTPAuth = true;                               // Enable SMTP authentication
                $this->mail->isHTML();
                $this->updateMailSettings($mailSettingsOverwrite, $secure, $verbose);
            }
        }

        /** An extantion of the construct function - but this has to be called before sending each mail, in case the settings changed
         * @throws \Exception if undefined settings
         * */
        function updateMailSettings($mailSettingsOverwrite, $secure, $verbose): void {
            $requiredSettings = ['mailHost','mailEncryption','mailUsername','mailPassword','mailPort'];
            foreach ($requiredSettings as $setting){
                if(empty($mailSettingsOverwrite[$setting]) && !$this->mailSettings->getSetting($setting))
                    throw(new \Exception('Cannot update mail settings - missing settings or cannot read settings file.'));
            }

            $this->mail->Host = $mailSettingsOverwrite['mailHost'] ?? $this->mailSettings->getSetting('mailHost');                // Specify main and backup SMTP servers
            $this->mail->SMTPSecure = $mailSettingsOverwrite['mailEncryption'] ?? $this->mailSettings->getSetting('mailEncryption');    // Enable TLS/SSL encryption
            $this->mail->Username = $mailSettingsOverwrite['mailUsername'] ?? $this->mailSettings->getSetting('mailUsername');        // SMTP username
            $this->mail->Password = $mailSettingsOverwrite['mailPassword'] ?? $this->mailSettings->getSetting('mailPassword');        // SMTP password
            $this->mail->Port = $mailSettingsOverwrite['mailPort'] ?? $this->mailSettings->getSetting('mailPort');                // TCP port to connect to

            $this->defaultAlias = $mailSettingsOverwrite['defaultAlias'] ?? ($this->mailSettings->getSetting('defaultAlias') ?? $this->mail->Username);

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

        /** Sends mail via SMTP, using PHPMailer
         * See https://github.com/PHPMailer/PHPMailer/wiki/Tutorial for most optional inputs
         * @param array|string $to Object of the form <string, address to send mail to> => <null|string, Recipient name or null>, or just a single email
         * @param string $subject Mail subject
         * @param string $body Mail body, in HTML
         * @param array $params Optional parameters of the form:
         *               'from': <string|string[2], default null - either just the email we send from, [$prettyName,$email]>
         *               'altBody': <string, default null - alternative body without HTML>
         *               ------ All the following parameters operate via the following logic:
         *                      Single argument - uses object key
         *                      2 arguments - uses key and value as 1st and 2nd arguments, respectively
         *                      >2 arguments, or 1st argument not a good key - value should be array, used to fill arguments
         *               'attachments': <object, default null - of the form $absolutePathToFile => $optionalNameToAppearInEmail | [$optionalNameToAppearInEmail,$encoding,$type] >
         *               'stringAttachments': <object, default null - of the form [file_get_contents($absolutePathToFile),$optionalNameToAppearInEmail]>
         *               'embedded': <object, default null - of the form $absolutePathToFile => $cidToBeUsedInMailHTML >
         *               'cc': <object, default null - of the form $ccRecipientMail => $optionalNameToAppearInEmail >
         *               'bcc': <object, default null - of the form $ccRecipientMail => $optionalNameToAppearInEmail >
         *               'replies': <object, default null - of the form $ccRecipientMail => $optionalNameToAppearInEmail >
         * @return bool
         * @throws Exception
         */
        function sendMail(array|string $to, string $subject, string $body, array $params = [] ): bool {
            $test = $params['test']??false;
            $verbose = $params['verbose'] ?? $test;

            if($this->mode === 'smtp'){
                //Handle who we set this as, first
                if(isset($params['from'])){
                    if(is_array($params['from'])){
                        if(empty($params['from'][0]))
                            $params['from'][0] = $this->defaultAlias;
                        $this->mail->setFrom($params['from'][0], $params['from'][1]);
                    }
                    else{
                        $this->mail->setFrom($this->defaultAlias,$params['from']);
                    }
                }
                else{
                    $this->mail->setFrom($this->defaultAlias);
                }

                $this->mail->CharSet = 'utf-8';
                $this->mail->Subject = iconv(mb_detect_encoding($subject, 'auto'), 'UTF-8', $subject);
                $this->mail->Body    = $body;
                $this->mail->AltBody = $params['altBody']??'Your email client does not support HTML';

                if(empty($to))
                    throw(new \Exception('Cannot send a mail with no recipients!'));

                if(!is_array($to))
                    $to = [$to => null];
                foreach ($to as $email=>$prettyName){
                    if(!$prettyName)
                        $this->mail->addAddress($email);
                    else
                        $this->mail->addAddress($email,$prettyName);
                }
                $opt = ['cc'=>'addCC','bcc'=>'addBCC','replies'=>'addReplyTo','attachments'=>'addAttachment',
                    'embedded'=>'addEmbeddedImage','stringAttachments'=>'addStringAttachment'];
                foreach ($opt as $optParam => $funcName){
                    if(!empty($params[$optParam])){
                        foreach ($params[$optParam] as $target => $paramContents){
                            if(!$paramContents)
                                call_user_func_array(array($this->mail, $funcName), [$target]);
                            else{
                                if(is_array($paramContents))
                                    call_user_func_array(array($this->mail, $funcName), [...$paramContents]);
                                else
                                    call_user_func_array(array($this->mail, $funcName), [$target,$paramContents]);
                            }
                        }
                    }
                }

                if($verbose)
                    echo 'Sending mail '.$subject.' to '.(is_string($to)?$to:json_encode($to)).' with params '.json_encode($params);

                if($test || $this->mail->send()) {
                    return true;
                } else {
                    $this->logger->error('Failed to send mail',['to'=>$to,'subject'=>$subject,'parameters'=>$params]);
                    throw(new \Exception('Message could not be sent, Mailer Error: '. $this->mail->ErrorInfo));
                }
            }

            return false;

        }

        /** Sends mail via SMTP, using PHPMailer
         * @param array $to Object of the form <string, address to send mail to> => <null|string, Recipient name or null>
         * @param string $subject Mail subject
         * @param ?string $template Template number
         * @param array $params Optional parameters of the form:
         *               'varArray': <object, of the form <string, variable name> => <mixed, new value> >
         *               'from': see sendMail()
         *               'altBody': see sendMail()
         *               'attachments': see sendMail()
         *               'embedded': see sendMail()
         *               'stringAttachments': see sendMail()
         *               'cc': see sendMail()
         *               'bcc': see sendMail()
         *               'replies':see sendMail()
         * @return bool
         * @throws Exception
         */
        function sendMailTemplate(array $to, string $subject, string $template=null, array $params = []  ): bool {
            $test = $params['test']??false;
            $verbose = $params['verbose'] ?? $test;
            $varArray = $params['varArray'] ?? [];

            if(!$template && !$this->template){
                if($verbose)
                    echo 'Template not provided'.EOL;
                return false;
            }
            if($template){
                $setNewTemplate = $this->setWorkingTemplate($template,['test'=>$test,'verbose'=>$verbose]);
                if($setNewTemplate !== 0){
                    if($verbose)
                        echo 'Could not load template'.EOL;
                    return false;
                }
            }

            return $this->sendMail(
                $to,
                $subject,
                $this->fillTemplate(null,$varArray),
                $params
            );
        }

        /** Sends a mail, into the mailing queue
         * @param array $inputs
         *          {
         *              'from': see sendMail()
         *              'to': see sendMail()
         *              'subject': see sendMail()
         *              'body': see sendMail()
         *              'altBody': see sendMail()
         *              'attachments': see sendMail()
         *              'embedded': see sendMail()
         *              'stringAttachments': see sendMail()
         *              'replies': see sendMail()
         *              'cc': see sendMail()
         *              'bcc': see sendMail()
         *              'template': int, see sendMailTemplate()
         *              'varArray': object, see sendMailTemplate()
         *          }
         *          If both template and body are set, body takes precedence.
         * @param array $params test/verbose, as well as:
         *               'queue': <string, default 'default_mailing' - queue to send mail to>
         *               'differentPrefix': <string, default null - different queue prefix>
         *               'successQueue / failureQueue': <string|bool, default null - if passed, will create a  success / failure queue, and return its name as a result on success
         *                                              if true, uses default prefix with random suffix, if string is passed, uses the string>
         *               'successQueueExp/ failureQueueExp': <int, default 300 - if successQueue / failureQueue is true, will expire after this many seconds>
         * @returns bool|int|string|array Will return false if missing any of the required inputs
         *                     If successQueue AND failureQueue are false, returns RedisConcurrency->pushToQueues() codes.
         *                     If successQueue OR failureQueue are true, can instead return the name of the queue on success.
         *                     If successQueue AND failureQueue are true, can instead return a an array of the form ['success'=><string,queue name>, 'failure'=><string, queue name>]
         *
         */
        function sendMailAsync( array $inputs, array $params = []){
            $test = $params['test']??false;
            $verbose = $params['verbose'] ?? $test;
            $queue = $params['queue'] ?? 'default_mailing';
            $differentPrefix = $params['differentPrefix'] ?? null;
            $successQueue = $params['successQueue']??null;
            $successQueueExp = $params['successQueueExp']??300;
            $failureQueue = $params['failureQueue']??null;
            $failureQueueExp = $params['failureQueueExp']??300;

            if($successQueue)
                $successQueue = is_string($successQueue)?$successQueue:('mail_sent_'.\IOFrame\Util\PureUtilFunctions::GeraHash(20));
            if($failureQueue)
                $failureQueue = is_string($failureQueue)?$failureQueue:('mail_failed_to_send_'.\IOFrame\Util\PureUtilFunctions::GeraHash(20));
            $required = [
                'to'=>true,
                'subject'=>true,
                'body'=>false,
                'from'=>false,
                'altBody'=>false,
                'attachments'=>false,
                'embedded'=>false,
                'stringAttachments'=>false,
                'cc'=>false,
                'bcc'=>false,
                'replies'=>false,
                'template'=>false,
                'varArray'=>false,
            ];

            $failedRequired = false;
            foreach ($required as $param => $req){
                if($req && !isset($inputs[$param])){
                    $failedRequired = $param;
                    break;
                }
                $inputs[$param] = $inputs[$param] ?? null;
            }
            if(empty($inputs['body']) && empty($inputs['template'])){
                $failedRequired = 'body-or-template';
            }
            if($failedRequired){
                if($verbose)
                    echo 'Missing required input '.$failedRequired;
                return false;
            }

            $opt = ['test'=>$test,'id'=>time().'_'.(is_array($inputs['to'])? json_encode($inputs['to']): $inputs['to']),'queuePrefix'=>$differentPrefix];

            if($successQueue){
                $opt['successQueue'] = $successQueue;
                $opt['successQueueExp'] = $successQueueExp;
            }

            if($failureQueue){
                $opt['failureQueue'] = $failureQueue;
                $opt['failureQueueExp'] = $failureQueueExp;
            }

            $ConcurrencyHandler = new \IOFrame\Managers\Extenders\RedisConcurrency($this->RedisManager);
            $pushResult = $ConcurrencyHandler->pushToQueues($queue,$inputs,$opt,['verbose'=>$verbose])[$queue];
            if(($pushResult <= 0) || (!$successQueue && !$failureQueue))
                return $pushResult;
            elseif (($successQueue && !$failureQueue) || (!$successQueue && $failureQueue))
                return $successQueue?: $failureQueue;
            else
                return ['success'=>$successQueue, 'failure'=>$failureQueue];
        }

    }
}