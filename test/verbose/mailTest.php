<?php

try{
    $mail = new \IOFrame\Managers\MailManager($settings,array_merge($defaultSettingsParams,['verbose'=>true]));

    echo 'Getting template "default_activation"'.EOL;
    var_dump(
        $mail->getTemplate('default_activation',['test'=>true])
    );
    echo EOL;

    echo 'Filling template "default_activation"'.EOL;
    $mail->setWorkingTemplate('default_activation',['verbose'=>true]);
    var_dump(
        $mail->fillTemplate(null,['siteName'=>'TestCo','uId'=>'420','Code'=>'BL-4ZE-1T'])
    );
    echo EOL;

    echo 'Creating new template'.EOL;
    var_dump(
        $mail->setTemplate('test','Test Title','This is a test mail!',['test'=>true,'createNew'=>true])
    );
    echo EOL;

    echo 'Updating template'.EOL;
    var_dump(
        $mail->setTemplate('test','Test Title','This is a test mail!',['test'=>true])
    );
    echo EOL;

    echo 'Deleting template'.EOL;
    var_dump(
        $mail->deleteTemplate('test',['test'=>true])
    );
    echo EOL;

    // You can change this to your personal address to ensure the mails are actually received
    // Start the default mailing queue manually, in verbose mode:
    // cd /path/to/project/cli && php cron-management.php -v -a dynamic --fp cli/config/cron-management/start-queue-manager-mailing-default.json
    /*

    echo 'Sending async mail'.EOL;
    $templates = ["default_activation","default_password_reset","default_mail_reset"];
    var_dump(
        $mail->sendMailAsync(
            [
                'to'=>['example@example.com'=>'Example'],
                'from'=>['',$siteSettings->getSetting('siteName')],
                'subject'=>'Test title '.\IOFrame\Util\PureUtilFunctions::GeraHash(15),
                'template'=>$templates[rand(0,2)],
                'varArray'=>['uId'=>1,'Code'=>'test','siteName'=>$siteSettings->getSetting('siteName')]
            ],
            ['successQueue'=>true,'failureQueue'=>true,'test'=>false,'verbose'=>true]
        )
    );
    echo EOL;

    */

    /*

    echo 'Sending async mail with embedded images'.EOL;
    var_dump(
        $mail->sendMailAsync(
            [
                'to'=>['example@example.com'=>'Example'],
                'from'=>['',$siteSettings->getSetting('siteName')],
                'subject'=>'Test title '.\IOFrame\Util\PureUtilFunctions::GeraHash(15),
                'body'=>'Test images send to A <br>
                        <img src="cid:1.gif"> <img src="cid:2.jpg">',
                'embedded'=>[
                    __DIR__.'/exampleFiles/something.gif' => '1.gif',
                    __DIR__.'/exampleFiles/snail.jpg' => '2.jpg',
                ]
            ],
            ['successQueue'=>true,'failureQueue'=>true,'test'=>false,'verbose'=>true]
        )
    );
    echo EOL;

    */

    /*

    echo 'Sending async mail with embedded string attachment'.EOL;
    var_dump(
        $mail->sendMailAsync(
            [
                'to'=>['example@example.com'=>'Example'],
                'from'=>['',$siteSettings->getSetting('siteName')],
                'subject'=>'Test title '.\IOFrame\Util\PureUtilFunctions::GeraHash(15),
                'body'=>'Test images send to A',
                'stringAttachments'=>[
                     'string-1'=>[file_get_contents(__DIR__.'/exampleFiles/example.txt'), 'an_example.txt'],
                ]
            ],
            ['successQueue'=>true,'failureQueue'=>true,'test'=>false,'verbose'=>true]
        )
    );
    echo EOL;

    */
}
catch (\Exception $e){
    echo 'Mail settings not provided - '.$e->getMessage();
}