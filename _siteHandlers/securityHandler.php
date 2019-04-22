<?php
namespace IOFrame{
    /* Means to handle general security functions related to the framework.
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */

    require_once 'abstractDBWithCache.php';
    require_once 'IPHandler.php';
    use Monolog\Logger;
    use Monolog\Handler\IOFrameHandler;

    class securityHandler extends abstractDBWithCache{

        /** @var IPHandler $IPHandler
        */
        public $IPHandler;

        //Default constructor
        function __construct(settingsHandler $localSettings,  $params = []){
            parent::__construct($localSettings,$params);

            if(isset($params['siteSettings']))
                $this->siteSettings = $params['siteSettings'];
            else
                $this->siteSettings = new settingsHandler(
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
         * @param bool $test
         *
         * @returns bool true if action succeeds, false if it fails (e.g. because the IP is invalid)
         */
        function commitEventIP($eventCode, $params = [], $test = false){
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

            $query = 'SELECT commitEventIP(:IP,:Event_Type,:Is_Reliable,:Full_IP)';
            $bindings = [[':IP',$IP],[':Event_Type',$eventCode],[':Is_Reliable',$isTrueIP],[':Full_IP',$fullIP]];

            if(!$test)
                return $this->sqlHandler->exeQueryBindParam(
                    $query,
                    $bindings,
                    false
                );
            else{
                echo 'Query to send: '.$query.EOL;
                echo 'Params: '.json_encode($bindings).EOL;
                return true;
            }
        }

        /**  Commits an action by/on a user to the USER_ACTIONS table.
         * @param int $eventCode   The code of the event
         * @param int $id           The user ID
         * @param bool $test
         * @returns bool
         */
        function commitEventUser($eventCode, $id, $test = false){

            $query = 'SELECT commitEventUser(:ID,:Event_Type)';
            $bindings = [[':ID',$id],[':Event_Type',$eventCode]];

            if(!$test)
                return $this->sqlHandler->exeQueryBindParam(
                    $query,
                    $bindings,
                    false
                );
            else{
                echo 'Query to send: '.$query.EOL;
                echo 'Params: '.json_encode($bindings).EOL;
                return true;
            }
        }


        /** Gets the whole Actions rulebook.
         * @param array $filters of the form:
         *              'Category' => Action Category filter - default ones are 0 for IP, 1 for User, but others may be defined
         *              'Type'     => Action Type filter
         *              'Offset' => Results offset
         *              'Limit' => Results limit
         * @param mixed $test
         */
        function getRulebook(array $params, $test = false){

        }

        /** Sets Action rules into the Actions rulebook
         * @param int $category     Action category
         * @param int $type         Action type
         * @param int $sq           Number of actions in sequence after which the rule applies -
         *                          if null, will update all existing ones
         * @param array $filters of the form:
         *              'blacklistFor' => For how long an IP/User will get blacklisted after the rule is reached (for default categories)
         *              'addTTL'     => For how long this action will prolong the "memory" of the current action sequence
         *              'override' => Will override an existing action (defined by $category,$type,$sq)
         * @param mixed $test
         */
        function setRulebook(int $category, int $type, int $sq, array $params, $test = false){

        }

        /** Deletes Action rules from the Actions rulebook
         * @param int $category     Action category
         * @param int $type         Action type
         * @param int $sq           Number of actions in sequence after which the rule applies.
         *                          If null, deletes all relevant actions.
         * @param mixed $test
         */
        function deleteRulebook(int $category, int $type, int $sq, $test = false){

        }


    }

}

?>