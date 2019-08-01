<?php
namespace IOFrame\Handlers{
    use IOFrame;
    use Monolog\Logger;
    use Monolog\Handler\IOFrameHandler;
    define('SecurityHandler',true);
    if(!defined('abstractDBWithCache'))
        require 'abstractDBWithCache.php';
    if(!defined('IPHandler'))
        require 'IPHandler.php';

    /* Means to handle general security functions related to the framework.
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */
    class SecurityHandler extends IOFrame\abstractDBWithCache{

        /** @var IPHandler $IPHandler
        */
        public $IPHandler;

        //Default constructor
        function __construct(SettingsHandler $localSettings,  $params = []){
            parent::__construct($localSettings,$params);

            if(isset($params['siteSettings']))
                $this->siteSettings = $params['siteSettings'];
            else
                $this->siteSettings = new SettingsHandler(
                    $localSettings->getSetting('absPathToRoot').'/'.SETTINGS_DIR_FROM_ROOT.'/siteSettings/',
                    $this->defaultSettingsParams
                );

            if(!isset($this->defaultSettingsParams['siteSettings']))
                $this->defaultSettingsParams['siteSettings'] = $this->siteSettings;

            if(isset($params['IPHandler']))
                $this->IPHandler = $params['IPHandler'];
            else
                $this->IPHandler = new IPHandler(
                    $localSettings,
                    $this->defaultSettingsParams
                );
        }

        //TODO Implement this properly
        function checkBanned($type = "default"){
            switch($type) {
                default:
                    if (isset($_SESSION['details'])) {
                        $details = json_decode($_SESSION['details'], true);
                        if ($details['Banned_Until']!= null && $details['Banned_Until'] > time()) {
                            return 'User is banned until '.date("Y-m-d, H:i:s",$details['Banned_Until'])
                            .', while now is '.date("Y-m-d, H:i:s")."<br>";
                        }
                    }
            }
            return 'ok';
        }

        /** Commits an action by an IP to the IP_ACTIONS table.
         * @param int $eventCode The code of the action
         * @param array $params of the form:
         *                      'IP' => String representing user IP
         *                      'fullIP' => String representing full IP - defaults to IP if not given
         *                      'isTrueIP' => Boolean, whether provided IP should be considered reliable
         *              If IP isn't provided, defaults to getting it from IPHandler
         *              If an IP is provided and isTrueIP is not, isTrueIP defaults to 'true'.
         *              If only isTrueIP is provided, it's ignored.
         * @param array $params
         *
         * @returns bool true if action succeeds, false if it fails (e.g. because the IP is invalid)
         */
        function commitEventIP($eventCode, $params = []){

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;

            if(isset($params['IP'])){
                $IP = $params['IP'];
                $fullIP = isset($params['fullIP'])? $params['fullIP'] : $IP;
                $isTrueIP = isset($params['isTrueIP'])? $params['isTrueIP'] : true;
            }
            else{
                $IP = $this->IPHandler->directIP;
                $fullIP = $this->IPHandler->fullIP;
                $isTrueIP = $this->IPHandler->isTrueIP;
            }
            //In case the IP is invalid, might as well return false
            if(!filter_var($IP,FILTER_VALIDATE_IP))
                return false;

            $query = 'SELECT '.$this->SQLHandler->getSQLPrefix().'commitEventIP(:IP,:Event_Type,:Is_Reliable,:Full_IP)';
            $bindings = [[':IP',$IP],[':Event_Type',$eventCode],[':Is_Reliable',$isTrueIP],[':Full_IP',$fullIP]];

            if(!$test)
                return $this->SQLHandler->exeQueryBindParam(
                    $query,
                    $bindings
                );
            if($verbose){
                echo 'Query to send: '.$query.EOL;
                echo 'Params: '.json_encode($bindings).EOL;
                return true;
            }
        }

        /**  Commits an action by/on a user to the USER_ACTIONS table.
         * @param int $eventCode   The code of the event
         * @param int $id           The user ID
         * @param array $params
         * @returns bool
         */
        function commitEventUser($eventCode, $id, array $params = []){

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;

            $query = 'SELECT '.$this->SQLHandler->getSQLPrefix().'commitEventUser(:ID,:Event_Type)';
            $bindings = [[':ID',$id],[':Event_Type',$eventCode]];
            if(!$test)
                return $this->SQLHandler->exeQueryBindParam(
                    $query,
                    $bindings
                );
            if($verbose){
                echo 'Query to send: '.$query.EOL;
                echo 'Params: '.json_encode($bindings).EOL;
                return true;
            }
        }


        /** Gets the whole Actions rulebook.
         * @param array $params of the form:
         *              'Category' => Action Category filter - default ones are 0 for IP, 1 for User, but others may be defined
         *              'Type'     => Action Type filter
         *              'Offset' => Results offset
         *              'Limit' => Results limit
         */
        function getRulebook( array $params = []){

        }

        /** Sets Action rules into the Actions rulebook
         * @param int $category     Action category
         * @param int $type         Action type
         * @param int $sq           Number of actions in sequence after which the rule applies -
         *                          if null, will update all existing ones
         * @param array $params of the form:
         *              'blacklistFor' => For how long an IP/User will get blacklisted after the rule is reached (for default categories)
         *              'addTTL'     => For how long this action will prolong the "memory" of the current action sequence
         *              'override' => Will override an existing action (defined by $category,$type,$sq)
         */
        function setRulebook(int $category, int $type, int $sq, array $params = []){

        }

        /** Deletes Action rules from the Actions rulebook
         * @param int $category     Action category
         * @param int $type         Action type
         * @param int $sq           Number of actions in sequence after which the rule applies.
         *                          If null, deletes all relevant actions.
         * @param array $params
         */
        function deleteRulebook(int $category, int $type, int $sq, array $params = []){

        }


    }

}

?>