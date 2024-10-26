<?php
namespace IOFrame\Util{

    define('IOFrameUtilHelperFunctions',true);

    class FrameworkUtilFunctions{

        /** Generates base project url.
         * DO NOT MOVE WITHOUT CHANGING THE SUBSTR PREFIX
         * @returns string
         * */
        public static function getBaseUrl():string{
            $currentUrl = str_replace('\\','/',__DIR__).'/';
            return substr($currentUrl,0,-strlen('IOFrame/Util/'));
        }

        /** Returns a new PDO database connection to use, from the relevant $settings
         * @param \IOFrame\Handlers\SettingsHandler $sqlSettings IOFrame setting handler
         * @param $params array of the form:
         *         'hostAlias': string, default 'sql_server_addr' - name of host setting
         *         'dbAlias': string, default 'sql_db_name' - name of db name setting
         *         'portAlias': string, default 'sql_server_port' - name of db port setting
         *         'usernameAlias': string, default 'sql_username' - name of username setting
         *         'passwordAlias': string, default 'sql_password' - name of password setting
         *         'persistentAlias': string, default 'sql_password' - name of persistent connection setting
         * @returns \PDO New database connection
         */
        public static function prepareCon(\IOFrame\Handlers\SettingsHandler $sqlSettings, array $params = []): \PDO {
            return new \PDO(
                "mysql:host=".$sqlSettings->getSetting($params['hostAlias']??'sql_server_addr').
                ";dbname=".$sqlSettings->getSetting($params['dbAlias']??'sql_db_name').
                ";port=".$sqlSettings->getSetting($params['portAlias']??'sql_server_port'),
                $sqlSettings->getSetting($params['usernameAlias']??'sql_username'),
                $sqlSettings->getSetting($params['passwordAlias']??'sql_password'),
                [
                    \PDO::ATTR_PERSISTENT => $sqlSettings->getSetting($params['persistentAlias']??'sql_persistent') ?? false
                ]
            );
        }

        /**
        The purpose of this is to allow pages to be moved freely and still be able to perform needed actions on php files on the
        server who's location is already defined.
         * @param string $currAdr needs to be the callers $_SERVER['REQUEST_URI']
         * @param string $pathToRoot needs to be the setting 'pathToRoot'
         * @returns string '../' times the number of folders needed to go from given folder to reach server root.
         */
        public static function htmlDirDist(string $currAdr, string $pathToRoot): string {
            $res='';
            $rootCount=0;
            $count=0;
            $currAdr = preg_replace('/(\/)+/', '/', $currAdr);
            for($i=0; $i<strlen($pathToRoot); $i++){
                if($pathToRoot[$i]=='/') $rootCount++;
            }
            for($i=0; $i<strlen($currAdr); $i++){
                if($currAdr[$i]=='/') $count++;
            }
            for($i=0; $i<($count-$rootCount); $i++){
                $res.= '../';
            }
            return $res;
        }

        /** Gets current framework version.
         * This relates to the local files, installed version might be different.
         * @returns string | bool Version on success, false if couldn't read version.
         * */
        public static function getLocalFrameworkVersion($params = []): string | bool{
            $verbose = $params['verbose'] ?? ($params['test'] ?? false);
            try{
                return \IOFrame\Util\FileSystemFunctions::readFile(self::getBaseUrl().'meta/', 'ver');
            }
            catch (\Exception $e){
                if($verbose)
                    echo 'Unable to read current version, error '.$e->getMessage();
                return false;
            }
        }

    }

}