<?php
namespace IOFrame\Util{

    use IOFrame\Handlers\SettingsHandler;

    define('IOFrameUtilDefaultErrorTemplatingFunctions',true);

    class DefaultErrorTemplatingFunctions{

        /** Used to return specific error status codes, and generate default templates, or require user files defined in relevant settings.
         * All of the passed parameters are accessible within the file which is potentially required by this function.
         *
         * @param SettingsHandler $settings local settings handler
         * @param array $params Array of parameters of the form:
         *              'error'=><int, default 500 - error code>
         *              'errorHTTPMsg'=><int, default 'Internal server error' - http message>
         *              'errorInMsg'=><bool, default true - whether to include error code in msg>
         *              'mainMsg'=><int, default 'Internal Server Error' - header to display in template>
         *              'subMsg'=><int, default null - description of error, displayed under the header>
         *              'start'=><int, default null - potential start time for things like maintenance, Unix timestamp>
         *              'startTimeFormat'=><int, default 'H:i, d M Y eP' - how to format start time (see date() on php.net)>
         *              'eta'=><int, default null - potential ETA for things like maintenance, in minutes>
         *              'cssColor'=><int, default '200,20,20' - rgb comma separated value, used to color default template>
         *              'mainFilePath'=><string, default null - main path from project root to PHP/template file, typically taken from relevant settings>
         *              'backupFilePath'=><string, default '/templates/errors/ioframe-generic-error-notice.html' - backup file path>
         *              'context'=><context, default null - context of the class ($this), when this function is executed within one>
         *
         */
        public static function handleGenericHTTPError(\IOFrame\Handlers\SettingsHandler $settings ,array $params = []): void {

            $rootFolder = $settings->getSetting('absPathToRoot');
            $error = $params['error']??500;
            $errorInMsg = $params['errorInMsg']??true;
            $errorHTTPMsg = $params['errorHTTPMsg']??'Internal server error';
            $mainMsg = $params['mainMsg']??'Internal Server Error';
            $cssColor = $params['cssColor']??'200,20,20';
            $startTimeFormat = $params['startTimeFormat']??'H:i, d M Y eP';
            $backupFilePath = $params['backupFilePath']??'/templates/errors/ioframe-generic-error-notice.html';

            $subMsg = $params['subMsg']??null;
            $startTime = $params['startTime']??null;
            $eta = $params['eta']??null;
            $mainFilePath = $params['mainFilePath']??null;
            $context = $params['context']??null;

            if($startTime)
                $startTime = date($startTimeFormat,(int)$startTime);
            if($eta){
                $temp = [];
                if($eta>=60)
                    $temp[] = floor($eta / 60) . ' Hours';
                if($eta%60)
                    $temp[] = ($eta % 60) . ' Minutes';
                $eta = implode(', ',$temp);
            }

            header('HTTP/1.0 '.$error.' '.$errorHTTPMsg);

            if($mainFilePath)
                $filePath = $rootFolder.$mainFilePath;

            if(!$mainFilePath || !is_file($filePath))
                $filePath = $rootFolder.$backupFilePath;

            if(!is_file($filePath))
                die();

            $fileType = explode('.',$filePath);
            $fileType = array_pop($fileType);

            if($fileType === 'php'){
                require $filePath;
            }
            else{
                header('Content-type: text/html');
                $file = file_get_contents($filePath);
                try {
                    $file = \IOFrame\Util\TemplateFunctions::itemFromTemplate(
                        $file,
                        [
                            'ERROR_CODE'=>(string)$error,
                            'ERROR_IN_MSG'=>$errorInMsg && $error,
                            'CSS_BASE_RGB'=>$cssColor,
                            'ERROR_MSG_MAIN'=>$mainMsg,
                            'ERROR_MSG_SUB_EXISTS'=>(bool)$subMsg,
                            'ERROR_MSG_SUB'=>$subMsg,
                            'START_EXP_EXISTS'=>(bool)$startTime,
                            'START_EXP'=>$startTime,
                            'ETA_EXP_EXISTS'=>(bool)$eta,
                            'ETA_EXP'=>$eta
                        ]
                    );
                }
                catch (\Exception){
                    $file = $error.' '.$errorHTTPMsg;
                }
                echo $file;
            }

            die();
        }
    }

}
