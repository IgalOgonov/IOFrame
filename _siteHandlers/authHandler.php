<?php
namespace IOFrame{
    define('authHandler',true);

    if(!defined('abstractDBWithCache'))
        require 'abstractDBWithCache.php';
    use Monolog\Logger;
    use Monolog\Handler\IOFrameHandler;

    /**Handles user authorization (not authentication!) in IOFrame
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
    */
    class authHandler extends abstractDBWithCache{

        /** @var bool $loggedIn Whether the user is logged in or not
         * */
        protected $loggedIn=false;

        /** @var int $rank User rank
         * */
        protected $rank=10000;

        /** @var string[] $actions User actions
         * */
        protected $actions=[];

        /** @var array $groups Groups belonging to a user.
         *              An array of the form "groupName" => <Array of Actions> (if groups are initiated)
         * */
        protected $groups=[];

        /** @var string[] $details User Details array
         * */
        protected $details=[];

        /** @var bool $useCache Specifies whether we should be using cache
         */
        protected $useCache = false;

        /** @var int $lastUpdated When the user information we got was last updated.
         */
        protected $lastUpdatedUser = 0;

        /** @var array $lastUpdatedGroups When the group information we got was last updated. Of the form:
         *                                  "groupName" => last update time
         */
        protected $lastUpdatedGroups = [];

        /** @var bool $userInitiated Whether the user was initiated with all his groups/actions from the DB/Cache
         * */
        protected $userInitiated=false;

        /**
         * Basic construction function
         * @param settingsHandler $settings local settings handler.
         * @param array $params Typical default settings array, with one extra parameter:
         *                      'initiateUser' => Boolean, whether to initiate actions/groups from the start
         *                                          (default false, actions are initiated only on request)
         */
        function __construct(settingsHandler $settings, $params = []){

            parent::__construct($settings,$params);


            if(isset($this->defaultSettingsParams['maxQueryLimit']))
                define('MAX_QUERY_LIMIT',$this->defaultSettingsParams['maxQueryLimit']);

            if(!defined('MAX_QUERY_LIMIT'))
                define('MAX_QUERY_LIMIT',10000);

            $this->useCache = isset($this->defaultSettingsParams['useCache'])?
                $this->defaultSettingsParams['useCache'] : false;

            if(isset($params['initiateUser']))
                $initiateUser = $params['initiateUser'];
            else
                $initiateUser = false;

            $this->init($initiateUser);
        }

        /**
         * Initiates the data in the handler.
         * While details, loggedIn and Rank are always initiated, actions and groups are only initiated here if
         *  the relevant parameter is true.
         * @param bool $initiateUser Whether to initiate user actions/groups by default, or only on request
         */
        function init(bool $initiateUser = false)
        {
            //This means we logged in, so the rank (and logged in status) must be accurate
            if(isset($_SESSION['logged_in'],$_SESSION['details'])){
                $this->details = json_decode($_SESSION['details'],true);
                $this->loggedIn=true;
                $this->rank=$this->details['Rank'];
                if($initiateUser)
                    $this->updateUserInfoFromDB(false);

            }
            else{
                $this->loggedIn=false;
                $this->rank=10000;
                $this->details=[];
            }
        }

        /** Updates the user actions and groups. First from the cache (if used), then from the DB.
         * @param mixed $test
         */
        function updateUserInfoFromDB($test = false){
            if(!isset($this->details['ID'])){
                if($test)
                    echo 'Trying to update user info when not logged in!'.EOL;
                return;
            }
            //Get information from cache if asked
            $cachedInfo = $this->getFromCache(['userID'=>$this->details['ID']],$test);
            //Save the groups we need to request from the cache for later
            $tempGroups = [];
            //Get user actions/groups from cache
            if($cachedInfo!=null){
                if(isset($cachedInfo['actions']))
                    $this->actions = $cachedInfo['actions'];
                if(isset($cachedInfo['groups']))
                    $tempGroups = $cachedInfo['groups'];
                $this->lastUpdatedUser = $cachedInfo['lastUpdated'];
            }
            //Get group actions for each group
            foreach($tempGroups as $groupName){
                $this->lastUpdatedGroups[$groupName] = 0;
                $this->groups[$groupName] = [];
                $cachedInfo = $this->getFromCache(['groupName'=>$groupName],$test);
                if($cachedInfo!=null){
                    if(isset($cachedInfo['actions']))
                        $this->groups[$groupName] = $cachedInfo['actions'];
                    $this->lastUpdatedGroups['groupName'] = $cachedInfo['lastUpdated'];
                }
            }

            //Get up-to-date information from the database
            $this->updateFromDB([],$test);

            //At this point the user is initiated
            $this->userInitiated = true;
        }

        /**Updates data user from the database, and updates the cache with the results.
         *
         * Let me first explain the query to the DB.
         * We want to fetch:
         * A) Every action THAT:
         *
         *      1.  Belongs to the right user AND his lastUpdated is bigger than $lastUpdatedUser
         *          OR
         *      2.  Belongs to a group that:
         *              Is one of the groups from the cache
         *              AND
         *              Has its own lastUpdated bigger than $lastUpdatedGroup['groupName']
         *          OUT OF the groups THAT
         *          Belongs to the right user AND his lastUpdated is bigger than $lastUpdatedUser (wasn't removed after caching)
         *          OR
         *      3.  Belongs to a group that isn't in the cache AND does belong to the user (was added but not yet cached)
         *
         * B) Also we want to return the groups (without the actions) that:
         *      Were in the cache
         *      AND
         *      Still tied to the user (unrelated to lastUpdated)
         *    so that we may find out which groups were in the cache but are no longer tied to the user (by omission)
         *
         * We get them as a list:
         * 'actionName'     =>The name of the action, or -1 if this line indicates a group tied to a user.
         * 'groupName'      =>Name of the group that is tied to the user OR the group a valid action is associated to.
         *                      In case of a valid action associated directly to a user, this will be "@".
         * 'userHadGroup'   =>Indicates whether the group belonged to the user. TRUE if yes, FALSE if no.
         *
         * This way:
         *  If the user was not updated since the info was cached, we get nothing.
         *  If the actions changed, we get the up-to-date actions.
         *  If the groups changed:
         *      We get every group that has been updated since the cached info, EXCEPT those which were removed from the user.
         *      We get a list of the groups that were removed from the user.
         * Hopefully the SQL Parser knows to check for the user lastUpdated condition first, once, then do anything else.
         *
         * After that, we update the handler and cache with anything new we found (and remove anything no longer there).
         *
         * This should take care of auto-updating after doing any change, to a group or a user.
         * //TODO Add cache TTL settings (to siteSettings probably)
         *
         * @param array $params - array of the form 'userID'=>userID
         *                        Meant for testing at the moment - running this without test would cause unexpected behaviour
         * @param mixed $test
         * */
        function updateFromDB(array $params = [], $test = false){
            $prefix = $this->sqlHandler->getSQLPrefix();
            $groupTable = $prefix.'GROUPS_AUTH';
            $userTable = $prefix.'USERS_AUTH';
            $actionTable = $prefix.'ACTIONS_AUTH';
            $usersActionsTable = $prefix.'USERS_ACTIONS_AUTH';
            $groupsActionsTable = $prefix.'GROUPS_ACTIONS_AUTH';
            $usersGroupsTable = $prefix.'USERS_GROUPS_AUTH';
            $userLastChanged = $this->lastUpdatedUser;
            if(isset($params['userID']))
                $userID = $params['userID'];
            else
                $userID = $this->details['ID'];
            $updateTime = time();

            //Used in B
            $groupsToCheckCond = [];
            //Used in A.3
            $groupsToIgnoreCond = [];
            //Used in A.2
            $groupsToGetConditions = [];
            if($this->groups != []){
                foreach($this->groups as $groupName => $actions){
                    array_push($groupsToIgnoreCond,[$groupName,'STRING']);
                    array_push($groupsToCheckCond,[$usersGroupsTable.'.Auth_Group',[$groupName,'STRING'],'=']);
                    $tempCond = [];
                    array_push($tempCond,[$groupsActionsTable.'.Auth_Group',[$groupName,'STRING'],'=']);
                    array_push($tempCond,[$groupTable.'.Last_Changed',[(string)$this->lastUpdatedGroups[$groupName],'STRING'],'>=']);
                    array_push($tempCond,'AND');
                    array_push($groupsToGetConditions,$tempCond);
                }
                array_push($groupsToGetConditions,'OR');

                $groupsToGetConditions = [
                    $groupsToGetConditions,
                    [
                        $groupsActionsTable.'.Auth_Group',
                        $this->sqlHandler->selectFromTable(
                            $usersGroupsTable.' INNER JOIN '.$userTable.' ON '.$userTable.'.ID = '.$usersGroupsTable.'.ID',
                            [
                                [$userTable.'.ID',$userID,'='],
                                [$userTable.'.Last_Changed',$userLastChanged,'>'],
                                'AND'
                            ],
                            [$usersGroupsTable.'.Auth_Group'],
                            ['justTheQuery'=>true,'useBrackets'=>true],
                            false
                        ),
                        'IN'
                    ],
                    'AND'
                ];

                if($test)
                    echo 'Groups found: '.json_encode($groupsToIgnoreCond).EOL;

                array_push($groupsToIgnoreCond,'CSV');
                array_push($groupsToCheckCond,'OR');
            }

            //A.3 query depends on whether there were groups to begin with that need to be ignored.
            $newGroupsCondition = [
                [
                    $groupsActionsTable.'.Auth_Group',
                    $this->sqlHandler->selectFromTable(
                        $usersGroupsTable.' INNER JOIN '.$userTable.' ON '.$userTable.'.ID = '.$usersGroupsTable.'.ID',
                        [
                            [$usersGroupsTable.'.ID',$userID,'='],
                            [$userTable.'.Last_Changed',$userLastChanged,'>'],
                            'AND'
                        ],
                        [$usersGroupsTable.'.Auth_Group'],
                        ['justTheQuery'=>true,'useBrackets'=>true],
                        false
                    ),
                    'IN'
                ]
            ];
            //If there were groups to ignore (aka present in the cache), we must add that condition.
            if($this->groups != [])
                array_push($newGroupsCondition,[
                    [
                    $groupsActionsTable.'.Auth_Group',
                    $groupsToIgnoreCond,
                    'IN'
                    ],
                    'NOT'
                ]);
            array_push($newGroupsCondition,'AND');

            //The initial parts of the query are A.1 and A.3 (which always checks for new groups either way)
            $query = $this->sqlHandler->selectFromTable(
                $actionTable,
                [
                    $actionTable.'.Auth_Action',
                    $this->sqlHandler->selectFromTable(
                        $usersActionsTable.' INNER JOIN '.$userTable.' ON '.$userTable.'.ID = '.$usersActionsTable.'.ID',
                        [
                            [$usersActionsTable.'.ID',$userID,'='],
                            [$userTable.'.Last_Changed',$userLastChanged,'>'],
                            'AND'
                        ],
                        [$usersActionsTable.'.Auth_Action'],
                        ['justTheQuery'=>true,'useBrackets'=>true],
                        false
                    ),
                    'IN'
                ],
                [$actionTable.'.Auth_Action as actionName, "@" as groupName, TRUE as userHadGroup'],
                ['justTheQuery'=>true],
                false
                ).' UNION '.
                $this->sqlHandler->selectFromTable(
                    $groupsActionsTable.' JOIN '.$actionTable.' ON '.$groupsActionsTable.'.Auth_Action = '.$actionTable.'.Auth_Action',
                    [
                        $actionTable.'.Auth_Action',
                        $this->sqlHandler->selectFromTable(
                            $groupsActionsTable,
                            $newGroupsCondition,
                            [$groupsActionsTable.'.Auth_Action'],
                            ['justTheQuery'=>true,'useBrackets'=>true],
                            false
                        ),
                        'IN'
                    ],
                    [$actionTable.'.Auth_Action as actionName, '.$groupsActionsTable.'.Auth_Group as groupName, FALSE as userHadGroup'],
                    ['justTheQuery'=>true],
                    false
                )
            ;

            //The other parts of the query are only relevant if there were groups in the cache
            if($this->groups !=[]){
                //Check for A.2
                $query .=' UNION '.$this->sqlHandler->selectFromTable(
                        $groupsActionsTable.' JOIN '.$actionTable.' ON '.$groupsActionsTable.'.Auth_Action = '.$actionTable.'.Auth_Action',
                        [
                            $actionTable.'.Auth_Action',
                            $this->sqlHandler->selectFromTable(
                                $groupsActionsTable.' INNER JOIN '.$groupTable.' ON '.$groupTable.'.Auth_Group = '.$groupTable.'.Auth_Group',
                                $groupsToGetConditions,
                                [$groupsActionsTable.'.Auth_Action'],
                                ['justTheQuery'=>true,'useBrackets'=>true],
                                false
                            ),
                            'IN'
                        ],
                        [$actionTable.'.Auth_Action as actionName, '.$groupsActionsTable.'.Auth_Group as groupName, TRUE as userHadGroup'],
                        ['justTheQuery'=>true],
                        false);


                //Check for B - which groups stayed alive
                $query .=' UNION '.$this->sqlHandler->selectFromTable(
                        $usersGroupsTable,
                        [
                            [$usersGroupsTable.'.ID',$userID,'='],
                            $groupsToCheckCond,
                            'AND'
                        ],
                        ['-1 as actionName, '.$usersGroupsTable.'.Auth_Group as groupName, TRUE as userHadGroup'],
                        ['justTheQuery'=>true],
                        false);
            }

            if($test)
                echo 'Query to send: '.$query.EOL;
            $res = $this->sqlHandler->exeQueryBindParam($query,[],true);
            if($test)
                echo var_dump($res);


            // Now, extract all info from the result:
            // An array of the form <Group Name> => [
            //                                     'actions'    => Array of new actions (or unset if it's a removed group)
            //                                     'indicator'  =>  0 for unchanged group ,
            //                                                      1 for updated group
            //                                                      2 for newly added group
            //                                    ]
            // We will update the cache/handler accordingly
            $groupArray = [];
            foreach($res as $val){
                if(!isset($groupArray[$val['groupName']])){
                    //In case this is just an unchanged group
                    if($val['actionName'] == -1){
                        $groupArray[$val['groupName']]['actions'] = [];
                        $groupArray[$val['groupName']]['indicator'] = 0;
                    }
                    //Else it's an updated group or new
                    else{
                        $groupArray[$val['groupName']]['actions'] = [];
                        $groupArray[$val['groupName']]['indicator'] =
                            ($val['userHadGroup'] == '1') ? 1 : 2;
                        array_push($groupArray[$val['groupName']]['actions'],$val['actionName']);
                    }
                }
                else{
                    //In case we found an unchanged group that actually has new actions (notice in this case actionName can't be -1)
                    if($groupArray[$val['groupName']]['indicator'] == 0){
                        $groupArray[$val['groupName']]['indicator'] = 1;
                        array_push($groupArray[$val['groupName']]['actions'],$val['actionName']);
                    }
                    //If we found a new/changed group, just make sure we're not pushing -1 as an action
                    else{
                        if($val['actionName'] != -1)
                            array_push($groupArray[$val['groupName']]['actions'],$val['actionName']);
                    }
                }
            }
            //Now lets see which (if any) groups were cut from the user
            $removedGroups = [];
            foreach($this->groups as $groupName => $actions){
                if(!isset($groupArray[$groupName]))
                    array_push($removedGroups,$groupName);
            }

            if($test){
                echo 'Group Array: '.json_encode($groupArray).EOL;
                echo 'Removed Array: '.json_encode($removedGroups).EOL;
            }

            //If nothing changed, then there is no reason to keep running this procedure
            if($groupArray == [] && $removedGroups == [])
                return;


            //First, update the handler

            $this->lastUpdatedUser = $updateTime;
            $somethingChanged = false;

            foreach($removedGroups as $groupName){
                $somethingChanged = true;
                if($test)
                    echo 'Unsetting group '.$groupName.EOL;
                else{
                    unset($this->groups[$groupName]);
                    unset($this->lastUpdatedGroups[$groupName]);
                }
            }

            foreach($groupArray as $groupName => $arr){
                //This is the user actions
                if($groupName == '@'){
                    $somethingChanged = true;
                    if($test)
                        echo 'Updating actions '.json_encode( $arr['actions']).EOL;
                    else{
                        $this->actions = $arr['actions'];
                    }
                }
                //This is an actual group
                else{
                    //Obviously only update updated groups..
                    if($arr['indicator'] == '1' || $arr['indicator'] == '2'){
                        $somethingChanged = true;
                        if($test)
                            echo 'Updating group '.$groupName.' with actions '.json_encode( $arr['actions']).EOL;
                        else{
                            $this->groups[$groupName] = $arr['actions'];
                            $this->lastUpdatedGroups[$groupName] = $updateTime;
                        }

                        //Sadly, while I know that updating the group cache shouldn't happen on USER action, it has to be done here,
                        //As any sequential calls will assume the group in the cache is up to date
                        $this->updateCache(
                            [
                                'groupName' => $groupName,
                                'actions' => $arr['actions'],
                                'lastUpdated' => $updateTime
                            ],
                            $test
                        );
                    }
                }
            }

            if($somethingChanged){
                $groupList = [];

                foreach($this->groups as $name=>$v){
                    array_push($groupList,$name);
                }


                //Now, update the cache
                $this->updateCache(
                    [
                        'userID'=>$this->details['ID'],
                        'actions'=>$this->actions,
                        'groups'=>$groupList,
                        'lastUpdated'=>$this->lastUpdatedUser
                    ],
                    $test
                );
            }


        }

        /** Gets requested user groups & user actions / group actions from cache, and updates the handler
         * @param array $params Of the form:
         *                  'userID'  => User ID to get from cache
         *                  'groupName' => Group name to get from cache (whitespaces are replaced by underlines)
         *                  If both are set for some reason, will update user over group.
         * @param mixed $test
         * @returns array [
         *                'actions'     => <Action array> May be empty
         *                'groups'      => <Group array> May be empty if fetching user, is not set if fetching group.
         *                'lastUpdated' => <Time this was updated>
         *                ]
         *                or NULL if user/group does not exist in the cache (or cache is disabled)
        */
        function getFromCache(array $params = [], $test = false){

            $res = null;

            if(!$this->useCache){
                if($test)
                    echo 'Trying to get something from cache when cache is disabled!'.EOL;
                return $res;
            }

            //If we are fetching user info from cache
            if(isset($params['userID'])){
                //Lets see if the user is even in the cache
                $lastUpdated = $this->redisHandler->call('get','_auth_userLastUpdated_'.$params['userID']);
                if($test)
                    echo 'Redis query - get from _auth_userLastUpdated_'.$params['userID'].', result - '.$lastUpdated.EOL;
                //This means the user is not in the cache
                if($lastUpdated === false)
                    return $res;
                else
                    $res['lastUpdated'] = $lastUpdated;

                //If user is in the cache, decode everything else (note he still might have 0 actions/groups)
                $actionsJSON = $this->redisHandler->call('get','_auth_userActions_'.$params['userID']);
                if($test)
                    echo 'Redis query - get from _auth_userActions_'.$params['userID'].', result - '.$actionsJSON.EOL;
                if($actionsJSON !== false)
                    $res['actions'] = json_decode($actionsJSON,true);

                $groupsJSON = $this->redisHandler->call('get','_auth_userGroups_'.$params['userID']);
                if($test)
                    echo 'Redis query - get from _auth_userGroups_'.$params['userID'].', result - '.$groupsJSON.EOL;
                if($groupsJSON !== false)
                    $res['groups'] = json_decode($groupsJSON,true);

                return $res;
            }
            //If we are fetching group info from cache
            elseif(isset($params['groupName'])){
                //Lets see if the group is even in the cache
                $lastUpdated = $this->redisHandler->call('get','_auth_groupLastUpdated_'.$params['groupName']);
                if($test)
                    echo 'Redis query - get from _auth_groupLastUpdated_'.$params['groupName'].', result - '.$lastUpdated.EOL;
                //This means the group is not in the cache
                if($lastUpdated === false)
                    return $res;
                else
                    $res['lastUpdated'] = $lastUpdated;

                //If group is in the cache, decode everything else (still, might be 0 actions)
                $actionsJSON = $this->redisHandler->call('get','_auth_groupActions_'.$params['groupName']);
                if($test)
                    echo 'Redis query - get from _auth_groupActions_'.$params['groupName'].', result - '.$actionsJSON.EOL;
                if($actionsJSON !== false)
                    $res['actions'] = json_decode($actionsJSON,true);

                return $res;
            }
            else
                return null;
        }

        /** Updates either a user cache entry, or a group cache entry
         * @param array $params Of the form:
         *                  'userID'  => User ID. Will update with information present in this handler.
         *                  'groupName' => Group name. Will update with groupActions, setting the lastChanged time to time().
         *                  'actions' => An array of actions
         *                  'groups' => An array of groups
         *                  'lastUpdated' => Is the time we got the above information from the SOT (the DB). Defaults to time()
         *                  If both are set, will update user.
         * @param mixed $test checks user Auth rank versus target
        */
        function updateCache(array $params = [], $test = false){

            if(!$this->useCache){
                if($test)
                    echo 'Trying to get something from cache when cache is disabled!'.EOL;
                return;
            }

            if($test){
                echo 'Running updateCache with parameters '.json_encode($params).EOL;
                return;
            }

            //If we are updating a user
            if(isset($params['userID'])){

                //Update actions if given
                if(isset($params['actions']))
                    $this->redisHandler->call('set',['_auth_userActions_'.$params['userID'],json_encode($params['actions'])]);

                //Update groups if given
                if(isset($params['groups']))
                    $this->redisHandler->call('set',['_auth_userGroups_'.$params['userID'],json_encode($params['groups'])]);

                //Check if we got a lastUpdated date, if no set to default
                if(!isset($params['lastUpdated']))
                    $params['lastUpdated'] = time();
                //Update lastUpdated
                $this->redisHandler->call('set',['_auth_userLastUpdated_'.$params['userID'],$params['lastUpdated']]);

            }
            //If we are updating a group
            elseif(isset($params['groupName'])){

                //Update actions if given
                if(isset($params['actions']))
                    $this->redisHandler->call('set',['_auth_groupActions_'.$params['groupName'],json_encode(['actions'])]);

                //Check if we got a lastUpdated date, if no set to default
                if(!isset($params['lastUpdated']))
                    $params['lastUpdated'] = time();
                //Update lastUpdated
                $this->redisHandler->call('set',['_auth_groupLastUpdated_'.$params['groupName'],$params['lastUpdated']]);

            }

        }

        /** @param int $target checks user Auth rank versus target
         * @returns bool true if the user is authorized below or at target level, false otherwise.
         * */
        function isAuthorized(int $target = 0){
            if($target<0){
                throw(new \Exception('Highest authorization level is 0.'));
            }
            else if($this->loggedIn){
                if($this->rank <= $target)
                    return true;
                else{
                    /* TODO ADD MODULAR LOGGING CONDITION
                    if(log_level_at_least(2,$this->settings))
                        $this->logger->warning('User '.$this->details['ID'].' tried to perform unauthorized action - requires rank '.$target);
                     * */
                    return false;
                }
            }
            else
                return false;
        }

        /*@return integer user's rank, or 10000 if not logged in.
        */
        function getRank(){
            return $this->rank;
        }

        /**@return bool true if the user is logged in, false otherwise.
        */
        function isLoggedIn(){
            return $this->loggedIn;
        }

        /**
         * @param $arg string Gets relevant detail from $this->details
         * @return mixed a specific detail from the details array, or 'unset' if it isn't set.
        */
        function getDetail(string $arg){
            if(isset($this->details[$arg]))
                return $this->details[$arg];
            else
                return null;
        }

        /**@return bool true if the user has an action in their Actions array
        */
        function hasAction(string $target){
            if(!$this->userInitiated)
                $this->updateUserInfoFromDB(false);
            if($this->loggedIn){
                $res = in_array($target,$this->actions);
                if($res)
                    return true;
                else{
                    foreach($this->groups as $groupName=>$groupActions){
                        $res = in_array($target,$groupActions);
                        if($res)
                            return true;
                    }
                }
                return false;
            }
            else
                return false;
        }


        /** Changes the auth rank of a specific user with the id $ID to $newRank.
         * A user can't change somebody's rank to one below 9999 or above his own rank. Cannot change the rank of somebody above yours.
         * A user must either be of rank 0, or own the auth_modifyUserRank or auth_modifyUser actions.
         * @param mixed $identifier Either user ID or user Mail
         * @param int $newRank Rank to assign
         * @param array $params Reserved for later
         * @returns bool
         * */
        function modifyUserRank($identifier, int $newRank, $params = [], $test = false){

            $prefix = $this->sqlHandler->getSQLPrefix();

            //Works both with user mail and ID
            if(gettype($identifier) == 'integer'){
                $identityCond = [$prefix.'USERS.ID',$identifier,'='];
            }
            else{
                $identityCond = [$prefix.'USERS.Email',[$identifier,'STRING'],'='];
            }

            $res = $this->sqlHandler->updateTable(
                $this->sqlHandler->getSQLPrefix().'USERS',
                ['Rank = '.$newRank.''],
                $identityCond,
                [],
                $test
            );

            return $res;
        }

        /**
         * Views specific users, and potentially their actions.
         * @param array $params array of the form:
         *                      [
         *                      'id'|'action'|'group' => Array of Filters of the form [<filter type>, <parameters>].
         *                      'separator' => string, 'AND' or 'OR', logical relation between filters, defaults to 'AND'
         *                      'includeActions' => bool, whether to return only the IDs or all action/group info. Defaults to false.
         *                      'limit' => int, As defined in MySQL 'SELECT' documentation
         *                      'offset' => int, As defined in MySQL 'SELECT' documentation
         *                      'orderByExp' => string, Expression to order results by. Defaults to 'ID'.
         *                      ]
         *          Supported filter types are: 'NOT IN', 'IN', '<', '>', '<=', '>=', '='.
         *          Filter parameters are arrays or single values of type Strings/IDs, depending on filter type - group and actions are STRINGs,
         *          ids are INTs. For 'NOT IN' and 'IN' it's arrays, for the rest single values.
         * @param mixed $test
         * @return array
         *              If fetching user IDs - array of relevant (in respect to filters) user IDs
         *              If fetching user IActions - array of the form: [
         *                                                               <UserID> =>[
         *                                                                            "@" => Array of Actions
         *                                                                            <groupName> => Array of Actions
         *                                                                            ...
         *                                                                           ]
         *                                                               ...
         *                                                              ]
         *              where "@" are actions belonging directly to the user.
         */
        function getUsers(array $params = [], $test = false){

            $prefix = $this->sqlHandler->getSQLPrefix();
            $userTable = $prefix.'USERS_AUTH';
            $usersActionsTable = $prefix.'USERS_ACTIONS_AUTH';
            $groupsActionsTable = $prefix.'GROUPS_ACTIONS_AUTH';
            $usersGroupsTable = $prefix.'USERS_GROUPS_AUTH';

            $selectionFilter = [];

            //Default params
            if(isset($params['separator']))
                $separator = $params['separator'];
            else
                $separator = 'AND';

            if(isset($params['includeActions']))
                $includeActions = $params['includeActions'];
            else
                $includeActions = false;

            if(isset($params['limit']))
                $limit = min((int)$params['limit'],MAX_QUERY_LIMIT);
            else
                $limit = MAX_QUERY_LIMIT;

            if(isset($params['offset']))
                $offset = (int)$params['offset'];
            else
                $offset = null;

            if(isset($params['orderByExp']))
                $orderByExp = $params['orderByExp'];
            else
                $orderByExp = 'ID';

            //Valid parameters - not including filter ones
            $validParams = ['id','action','group'];

            $selectionParams = ['justTheQuery'=>true,'useBrackets'=>true, 'DISTINCT'=>true] ;

            foreach($validParams as $paramName){
                if(isset($params[$paramName]) && is_array($params[$paramName])){

                    //This means the caller passed a single [<filter type>,<target>] pair
                    if(!is_array($params[$paramName][0]))
                        $params[$paramName] = [$params[$paramName]];

                    foreach($params[$paramName] as $filterPair){
                        $target = $filterPair[1];
                        if($paramName != 'id' && is_array($target))
                            foreach($target as $k=>$v)[
                                $target[$k] = [$v,'STRING']
                            ];
                        $operator = $filterPair[0];
                        switch($operator){
                            case 'NOT IN':
                                switch($paramName){
                                    case 'id':
                                        array_push($target,'CSV');
                                        $cond =
                                            [
                                                [
                                                    $userTable.'.ID',
                                                    $target,
                                                    'IN'
                                                ],
                                                'NOT'
                                            ];
                                        array_push($selectionFilter,$cond);
                                        break;
                                    case 'action':
                                        array_push($target,'CSV');
                                        $cond =
                                            [
                                                [
                                                    $userTable.'.ID',
                                                    $this->sqlHandler->selectFromTable(
                                                        $userTable.' JOIN '.$usersActionsTable.' ON '.$userTable.'.ID = '.$usersActionsTable.'.ID',
                                                        [
                                                            $usersActionsTable.'.Auth_Action',
                                                            $target,
                                                            'IN'
                                                        ],
                                                        [$userTable.'.ID'],
                                                        $selectionParams,
                                                        false
                                                    ),
                                                    'IN'
                                                ],
                                                'NOT'
                                            ];
                                        array_push($selectionFilter,$cond);
                                        break;
                                    case 'group':
                                        array_push($target,'CSV');
                                        $cond =
                                            [
                                                [
                                                    $userTable.'.ID',
                                                    $this->sqlHandler->selectFromTable(
                                                        $userTable.' JOIN '.$usersGroupsTable.' ON '.$userTable.'.ID = '.$usersGroupsTable.'.ID',
                                                        [
                                                            $usersGroupsTable.'.Auth_Group',
                                                            $target,
                                                            'IN'
                                                        ],
                                                        [$userTable.'.ID'],
                                                        $selectionParams,
                                                        false
                                                    ),
                                                    'IN'
                                                ],
                                                'NOT'
                                            ];
                                        array_push($selectionFilter,$cond);
                                        break;
                                }
                                break;
                            case 'IN':
                                switch($paramName){
                                    case 'id':
                                        array_push($target,'CSV');
                                        $cond =
                                            [
                                                $userTable.'.ID',
                                                $target,
                                                'IN'
                                            ];
                                        array_push($selectionFilter,$cond);
                                        break;
                                    case 'action':
                                        array_push($target,'CSV');
                                        $cond =
                                            [
                                                $userTable.'.ID',
                                                $this->sqlHandler->selectFromTable(
                                                    $userTable.' JOIN '.$usersActionsTable.' ON '.$userTable.'.ID = '.$usersActionsTable.'.ID',
                                                    [
                                                        $usersActionsTable.'.Auth_Action',
                                                        $target,
                                                        'IN'
                                                    ],
                                                    [$userTable.'.ID'],
                                                    $selectionParams,
                                                    false
                                                ),
                                                'IN'
                                            ];
                                        array_push($selectionFilter,$cond);
                                        break;
                                    case 'group':
                                        array_push($target,'CSV');
                                        $cond =
                                            [
                                                $userTable.'.ID',
                                                $this->sqlHandler->selectFromTable(
                                                    $userTable.' JOIN '.$usersGroupsTable.' ON '.$userTable.'.ID = '.$usersGroupsTable.'.ID',
                                                    [
                                                        $usersGroupsTable.'.Auth_Group',
                                                        $target,
                                                        'IN'
                                                    ],
                                                    [$userTable.'.ID'],
                                                    $selectionParams,
                                                    false
                                                ),
                                                'IN'
                                            ]
                                        ;
                                        array_push($selectionFilter,$cond);
                                        break;
                                }
                                break;
                            case '>':
                            case '<':
                            case '>=':
                            case '<=':
                            case '=':
                            switch($paramName){
                                case 'id':
                                    $cond =
                                        [
                                            $userTable.'.ID',
                                            $target,
                                            $operator
                                        ];
                                    array_push($selectionFilter,$cond);
                                    break;
                                case 'action':
                                    $cond =
                                        [
                                            $userTable.'.ID',
                                            $this->sqlHandler->selectFromTable(
                                                $userTable.' JOIN '.$usersActionsTable.' ON '.$userTable.'.ID = '.$usersActionsTable.'.ID',
                                                [
                                                    $usersActionsTable.'.Auth_Action',
                                                    $target,
                                                    '='
                                                ],
                                                [$userTable.'.ID'],
                                                $selectionParams,
                                                false
                                            ),
                                            'IN'
                                        ];
                                    array_push($selectionFilter,$cond);
                                    break;
                                case 'group':
                                    $cond =
                                        [
                                            $userTable.'.ID',
                                            $this->sqlHandler->selectFromTable(
                                                $userTable.' JOIN '.$usersGroupsTable.' ON '.$userTable.'.ID = '.$usersGroupsTable.'.ID',
                                                [
                                                    $usersGroupsTable.'.Auth_Group',
                                                    $target,
                                                    '='
                                                ],
                                                [$userTable.'.ID'],
                                                $selectionParams,
                                                false
                                            ),
                                            'IN'
                                        ];
                                    array_push($selectionFilter,$cond);
                                    break;
                            }
                        }
                    }
                }
            }

            /*----------------------------------------------------------------------------------------------------------
             *---------------------------------- QUERY BUILDING STARTS HERE --------------------------------------------
             * */

            if($selectionFilter != [])
                array_push($selectionFilter,$separator);

            if($includeActions){
                $query = 'SELECT ID, Auth_Group, Auth_Action FROM (';
                $tables1 = $userTable.' JOIN '.$usersActionsTable.' ON '.$userTable.'.ID = '.$usersActionsTable.'.ID';
                $columns1 = [$userTable.'.ID','"@" as Auth_Group',$usersActionsTable.'.Auth_Action'];
                $tables2 = $userTable.' JOIN '.$usersGroupsTable.' ON '.$userTable.'.ID = '.$usersGroupsTable.'.ID JOIN '.
                    $groupsActionsTable.' ON '.$groupsActionsTable.'.Auth_group = '.$usersGroupsTable.'.Auth_Group';
                $columns2 = [$userTable.'.ID',$usersGroupsTable.'.Auth_Group',$groupsActionsTable.'.Auth_Action'];
            }
            else{
                $query = 'SELECT DISTINCT ID FROM (';
                $tables1 = $userTable;
                $columns1 = [$userTable.'.ID'];
            }

            $query .= $this->sqlHandler->selectFromTable(
                $tables1,
                $selectionFilter,
                $columns1,
                ['justTheQuery'=>true],
                false
            );
            if(isset($tables2)){
                $query .= ' UNION ';
                $query .= $this->sqlHandler->selectFromTable(
                    $tables2,
                    $selectionFilter,
                    $columns2,
                    ['justTheQuery'=>true],
                    false
                );
            }
            $query .= ') as Meaningless_Alias ORDER BY '.$orderByExp;

            if($limit != null){
                if($offset != null)
                    $query .= ' LIMIT '.$offset.','.$limit;
                else
                    $query .= ' LIMIT '.$limit;
            }

            if($test)
                echo 'Query to send: '.$query.EOL;
            $response = $this->sqlHandler->exeQueryBindParam($query,[],true);
            if($test)
                var_dump($response);

            $result = [];

            if($includeActions){
                foreach($response as $row){

                    if(!isset($result[$row['ID']]))
                        $result[$row['ID']] = [];

                    if(!isset($result[$row['ID']][$row['Auth_Group']]))
                        $result[$row['ID']][$row['Auth_Group']] = [];

                    array_push($result[$row['ID']][$row['Auth_Group']],$row['Auth_Action']);
                }
            }
            else{
                foreach($response as $row){
                    array_push($result,$row['ID']);
                }
            }

            return $result;
        }

        /**
         * Views all user actions (can be filtered with $params). Is an alias of getUsers.
         * Note that if allowed to be viewed without enforcing an ID condition t'=' or 'IN' (or at least a similar group
         * condition), the results could reach insane sizes, and the query would be very slow, as there is no way to limit
         * this.
         */
        function getUserActions(array $params = [], $test = false){
            return $this->getUsers(array_merge($params,['includeActions'=>true]),$test);
        }

        /**
         * Returns all the actions.
         *
         * @param array $params Reserved for later
         * @param mixed $test
         * @returns array of the form [
         *                             <Action Name> => <Description>
         *                             ...
         *                            ]
         */
        function getActions(array $params = [], $test = false){

            $prefix = $this->sqlHandler->getSQLPrefix();
            $actionsTable = $prefix.'ACTIONS_AUTH';

            if(isset($params['limit'])){
                if(isset($params['offset']))
                    $params['limit'] = $params['offset'].','.$params['limit'];
            }

            if(isset($params['safeStr']))
                $safeStr = $params['safeStr'];
            else
                $safeStr = true;


            $params['orderBy'] = 'Auth_Action';


            $response = $this->sqlHandler->selectFromTable(
                $actionsTable,
                [],
                ['Auth_Action','Description'],
                $params,
                $test
            );

            $res = [];

            if($safeStr){
                if(!defined('safeSTR'))
                    require __DIR__.'/../_util/safeSTR.php';
            }

            if(is_array($response))
                foreach($response as $row){
                    $res[$row['Auth_Action']] = $safeStr ? safeStr2Str($row['Description']) : $row['Description'];
                }

            return $res;
        }


        /**
         * Create new actions, or modifies existing ones.
         *
         * @param array $actions An array of the form <Action Name> => <Description>|null
         * @param array $params Reserved for later
         * @param mixed $test
         * @returns bool Whether the query succeeded or not.
         */
        function setActions(array $actions, array $params = [], $test = false){

            $prefix = $this->sqlHandler->getSQLPrefix();
            $actionsTable = $prefix.'ACTIONS_AUTH';

            if(isset($params['safeStr']))
                $safeStr = $params['safeStr'];
            else
                $safeStr = true;

            if($safeStr){
                if(!defined('safeSTR'))
                    require __DIR__.'/../_util/safeSTR.php';
            }

            $actionsToInsert = [];

            foreach($actions as $actionName=>$desc){
                if($desc!==null){
                    if($safeStr)
                        $desc = str2SafeStr($desc);
                    $desc = [$desc,'STRING'];
                }
                $actionName = [$actionName,'STRING'];
                array_push($actionsToInsert,[$actionName,$desc]);
            }

            $res = $this->sqlHandler->insertIntoTable(
                $actionsTable,
                ['Auth_Action','Description'],
                $actionsToInsert,
                ['onDuplicateKey'=>true],
                $test
            );

            return $res;
        }

        /**
         * Deletes actions
         *
         * @param array $actions An array of action names
         * @param array $params Reserved for later
         * @param mixed $test
         * @returns bool Whether the query succeeded or not.
         */
        function deleteActions(array $actions, array $params = [], $test = false){

            $prefix = $this->sqlHandler->getSQLPrefix();
            $actionsTable = $prefix.'ACTIONS_AUTH';
            $userTable = $prefix.'USERS_AUTH';
            $groupsActionsTable = $prefix.'GROUPS_ACTIONS_AUTH';
            $usersGroupsTable = $prefix.'USERS_GROUPS_AUTH';
            $usersActionsTable = $prefix.'USERS_ACTIONS_AUTH';

            foreach($actions as $k=>$actionName){
                $actions[$k] = [$actionName,'STRING'];
            }

            $res = $this->sqlHandler->deleteFromTable(
                $actionsTable,
                [
                    'Auth_Action',
                    $actions,
                    'IN'
                ],
                [],
                $test
            );

            if($res)
                $res = $this->sqlHandler->updateTable(
                    $userTable,
                    ['Last_Changed = "'.time().'"'],
                    [
                        'ID',
                        '('.$this->sqlHandler->selectFromTable(
                            $usersActionsTable,
                            ['Auth_Action',$actions,'IN'],
                            ['ID'],
                            ['justTheQuery'=>true,'DISTINCT'=>true],
                            false
                        ).' UNION '.$this->sqlHandler->selectFromTable(
                            $usersGroupsTable.' JOIN '.$groupsActionsTable.' ON '.$usersGroupsTable.'.Auth_Group = '.$groupsActionsTable.'.Auth_Group',
                            ['Auth_Action',$actions,'IN'],
                            [$usersGroupsTable.'.ID'],
                            ['justTheQuery'=>true,'DISTINCT'=>true],
                            false
                        ).')',
                        'IN'
                    ],
                    [],
                    $test
                );
            return $res;

        }



        /**
         * Views specific groups, and potentially their actions.
         * @param array $params Same as for getUsers
         * @param mixed $test
         * @return array
         *              If fetching Groups - array of relevant (in respect to filters) group names.
         *              If fetching Groups Actions - array of the form: [
         *                                                               <Group Name> => Array of Actions
         *                                                               ...
         *                                                              ]
         */
        function getGroups(array $params = [], $test = false){

            $prefix = $this->sqlHandler->getSQLPrefix();
            $userTable = $prefix.'USERS_AUTH';
            $groupTable = $prefix.'GROUPS_AUTH';
            $groupsActionsTable = $prefix.'GROUPS_ACTIONS_AUTH';
            $usersGroupsTable = $prefix.'USERS_GROUPS_AUTH';

            $selectionFilter = [];

            //Default params
            if(isset($params['separator']))
                $separator = $params['separator'];
            else
                $separator = 'AND';

            if(isset($params['includeActions']))
                $includeActions = $params['includeActions'];
            else
                $includeActions = false;

            if(isset($params['limit']))
                $limit = min((int)$params['limit'],MAX_QUERY_LIMIT);
            else
                $limit = MAX_QUERY_LIMIT;

            if(isset($params['offset']))
                $offset = (int)$params['offset'];
            else
                $offset = null;

            if(isset($params['orderByExp']))
                $orderByExp = $params['orderByExp'];
            else
                $orderByExp = 'Auth_Group';

            //Valid parameters - not including filter ones
            $validParams = ['id','action','group'];

            $selectionParams = ['justTheQuery'=>true,'useBrackets'=>true, 'DISTINCT'=>true] ;

            foreach($validParams as $paramName){
                if(isset($params[$paramName]) && is_array($params[$paramName])){

                    //This means the caller passed a single [<filter type>,<target>] pair
                    if(!is_array($params[$paramName][0]))
                        $params[$paramName] = [$params[$paramName]];

                    foreach($params[$paramName] as $filterPair){
                        $target = $filterPair[1];
                        if($paramName != 'id' && is_array($target))
                            foreach($target as $k=>$v)[
                                $target[$k] = [$v,'STRING']
                            ];
                        $operator = $filterPair[0];
                        switch($operator){
                            case 'NOT IN':
                                switch($paramName){
                                    case 'id':
                                        array_push($target,'CSV');
                                        $cond =
                                            [
                                                [
                                                    $groupTable.'.Auth_Group',
                                                    $this->sqlHandler->selectFromTable(
                                                        $userTable.' JOIN '.$usersGroupsTable.' ON '.$userTable.'.ID = '.$usersGroupsTable.'.ID',
                                                        [
                                                            $usersGroupsTable.'.ID',
                                                            $target,
                                                            '='
                                                        ],
                                                        [$usersGroupsTable.'.Auth_Group'],
                                                        $selectionParams,
                                                        false
                                                    ),
                                                    'IN'
                                                ],
                                                'NOT'
                                            ];
                                        array_push($selectionFilter,$cond);
                                        break;
                                    case 'action':
                                        array_push($target,'CSV');
                                        $cond =
                                            [
                                                [
                                                    $groupTable.'.Auth_Group',
                                                    $this->sqlHandler->selectFromTable(
                                                        $groupTable.' JOIN '.$groupsActionsTable.' ON '.$groupTable.'.Auth_Group = '.$groupsActionsTable.'.Auth_Group',
                                                        [
                                                            $groupsActionsTable.'.Auth_Action',
                                                            $target,
                                                            'IN'
                                                        ],
                                                        [$groupsActionsTable.'.Auth_Group'],
                                                        $selectionParams,
                                                        false
                                                    ),
                                                    'IN'
                                                ],
                                                'NOT'
                                            ];
                                        array_push($selectionFilter,$cond);
                                        break;
                                    case 'group':
                                        array_push($target,'CSV');
                                        $cond =
                                            [
                                                [
                                                    $groupTable.'.Auth_Group',
                                                    $target,
                                                    'IN'
                                                ],
                                                'NOT'
                                            ];
                                        array_push($selectionFilter,$cond);
                                        break;
                                }
                                break;
                            case 'IN':
                                switch($paramName){
                                    case 'id':
                                        array_push($target,'CSV');
                                        $cond =
                                            [
                                                $groupTable.'.Auth_Group',
                                                $this->sqlHandler->selectFromTable(
                                                    $userTable.' JOIN '.$usersGroupsTable.' ON '.$userTable.'.ID = '.$usersGroupsTable.'.ID',
                                                    [
                                                        $usersGroupsTable.'.ID',
                                                        $target,
                                                        'IN'
                                                    ],
                                                    [$usersGroupsTable.'.Auth_Group'],
                                                    $selectionParams,
                                                    false
                                                ),
                                                'IN'
                                            ];
                                        array_push($selectionFilter,$cond);
                                        break;
                                    case 'action':
                                        array_push($target,'CSV');
                                        $cond =
                                            [
                                                $groupTable.'.Auth_Group',
                                                $this->sqlHandler->selectFromTable(
                                                    $groupTable.' JOIN '.$groupsActionsTable.' ON '.$groupTable.'.Auth_Group = '.$groupsActionsTable.'.Auth_Group',
                                                    [
                                                        $groupsActionsTable.'.Auth_Action',
                                                        $target,
                                                        'IN'
                                                    ],
                                                    [$groupsActionsTable.'.Auth_Group'],
                                                    $selectionParams,
                                                    false
                                                ),
                                                'IN'
                                            ];
                                        array_push($selectionFilter,$cond);
                                        break;
                                    case 'group':
                                        array_push($target,'CSV');
                                        $cond =
                                            [
                                                $groupTable.'.Auth_Group',
                                                $target,
                                                'IN'
                                            ];
                                        array_push($selectionFilter,$cond);
                                        break;
                                }
                                break;
                            case '>':
                            case '<':
                            case '>=':
                            case '<=':
                            case '=':
                                switch($paramName){
                                    case 'id':
                                        $cond =
                                            [
                                                $groupTable.'.Auth_Group',
                                                $this->sqlHandler->selectFromTable(
                                                    $userTable.' JOIN '.$usersGroupsTable.' ON '.$userTable.'.ID = '.$usersGroupsTable.'.ID',
                                                    [
                                                        $usersGroupsTable.'.ID',
                                                        $target,
                                                        '='
                                                    ],
                                                    [$usersGroupsTable.'.Auth_Group'],
                                                    $selectionParams,
                                                    false
                                                ),
                                                'IN'
                                            ];
                                        array_push($selectionFilter,$cond);
                                        break;
                                    case 'action':
                                        $cond =
                                            [
                                                $groupTable.'.Auth_Group',
                                                $this->sqlHandler->selectFromTable(
                                                    $groupTable.' JOIN '.$groupsActionsTable.' ON '.$groupTable.'.Auth_Group = '.$groupsActionsTable.'.Auth_Group',
                                                    [
                                                        $groupsActionsTable.'.Auth_Action',
                                                        $target,
                                                        '='
                                                    ],
                                                    [$groupsActionsTable.'.Auth_Group'],
                                                    $selectionParams,
                                                    false
                                                ),
                                                'IN'
                                            ];
                                        array_push($selectionFilter,$cond);
                                        break;
                                    case 'group':
                                        $cond =
                                            [
                                                $groupTable.'.Auth_Group',
                                                $target,
                                                $operator
                                            ];
                                        array_push($selectionFilter,$cond);
                                        break;
                                }
                        }
                    }
                }
            }

            /*----------------------------------------------------------------------------------------------------------
             *---------------------------------- QUERY BUILDING STARTS HERE --------------------------------------------
             * */

            if($selectionFilter != [])
                array_push($selectionFilter,$separator);

            if($includeActions){
                $query = 'SELECT Auth_Group, Auth_Action FROM (';
                $tables = $groupTable.' JOIN '.$groupsActionsTable.' ON '.$groupTable.'.Auth_Group = '.$groupsActionsTable.'.Auth_Group';
                $columns = [$groupTable.'.Auth_Group',$groupsActionsTable.'.Auth_Action'];
            }
            else{
                $query = 'SELECT DISTINCT Auth_Group FROM (';
                $tables = $groupTable;
                $columns = [$groupTable.'.Auth_Group'];
            }

            $query .= $this->sqlHandler->selectFromTable(
                $tables,
                $selectionFilter,
                $columns,
                ['justTheQuery'=>true],
                false
            );
            $query .= ') as Meaningless_Alias ORDER BY '.$orderByExp;

            if($limit != null){
                if($offset != null)
                    $query .= ' LIMIT '.$offset.','.$limit;
                else
                    $query .= ' LIMIT '.$limit;
            }

            if($test)
                echo 'Query to send: '.$query.EOL;
            $response = $this->sqlHandler->exeQueryBindParam($query,[],true);
            if($test)
                var_dump($response);


            $result = [];

            if($includeActions){
                foreach($response as $row){

                    if(!isset($result[$row['Auth_Group']]))
                        $result[$row['Auth_Group']] = [];

                    array_push($result[$row['Auth_Group']],$row['Auth_Action']);
                }
            }
            else{
                foreach($response as $row){
                    array_push($result,$row['Auth_Group']);
                }
            }

            return $result;
        }

        /**
         * Views all group actions (can be filtered with $params).
         */
        function getGroupActions(array $params = [], $test = false){
            return $this->getGroups(array_merge($params,['includeActions'=>true]),$test);
        }

        /**
         * Create new groups, or modifies existing ones (description).
         */
        function setGroups(array $groups, array $params = [], $test = false){
            $prefix = $this->sqlHandler->getSQLPrefix();
            $groupsTable = $prefix.'GROUPS_AUTH';

            if(isset($params['safeStr']))
                $safeStr = $params['safeStr'];
            else
                $safeStr = true;

            if($safeStr){
                if(!defined('safeSTR'))
                    require __DIR__.'/../_util/safeSTR.php';
            }

            $groupsToInsert = [];

            foreach($groups as $groupName=>$desc){
                if($desc!==null){
                    if($safeStr)
                        $desc = str2SafeStr($desc);
                    $desc = [$desc,'STRING'];
                }
                $groupName = [$groupName,'STRING'];
                array_push($groupsToInsert,[$groupName,$desc]);
            }

            $res = $this->sqlHandler->insertIntoTable(
                $groupsTable,
                ['Auth_Group','Description'],
                $groupsToInsert,
                ['onDuplicateKey'=>true],
                $test
            );

            return $res;
        }

        /**
         * Deletes groups
         */
        function deleteGroups(array $groups, array $params = [], $test = false){

            $prefix = $this->sqlHandler->getSQLPrefix();
            $userTable = $prefix.'USERS_AUTH';
            $groupsTable = $prefix.'GROUPS_AUTH';
            $usersGroupsTable = $prefix.'USERS_GROUPS_AUTH';

            foreach($groups as $k=>$groupName){
                $groups[$k] = [$groupName,'STRING'];
            }

            $res = $this->sqlHandler->deleteFromTable(
                $groupsTable,
                [
                    'Auth_Group',
                    $groups,
                    'IN'
                ],
                [],
                $test
            );

            if($res)
                $res = $this->sqlHandler->updateTable(
                    $userTable,
                    ['Last_Changed = "'.time().'"'],
                    [
                        'ID',
                        $this->sqlHandler->selectFromTable(
                            $usersGroupsTable,
                            ['Auth_Group',$groups,'IN'],
                            ['ID'],
                            ['justTheQuery'=>true,'useBrackets'=>true,'DISTINCT'=>true],
                            false
                        ),
                        'IN'
                    ],
                    [],
                    $test
                );
            return $res;

        }

        /** Adds/Removes actions to/from a user.
         * @param int $id User ID
         * @param array $actions An array of the form:
         *                      <Action Name> => Bool true/false for set/delete. Will do both insertions and deletions.
         * @param array $params An array currently empty
         */
        function modifyUserActions(int $id, array $actions, array $params = [], $test = false){
            return $this->modifyAuth($id, $actions, ['targetType' => 'userActions'], $test);
        }

        /** Adds/Removes groups to/from a user.
         * @param int $id User ID
         * @param array $groups An array of the form:
         *                      <Groups Name> => Bool true/false for set/delete. Will do both insertions and deletions.
         * @param array $params An array currently empty
         */
        function modifyUserGroups(int $id, array $groups, array $params = [], $test = false){
            return $this->modifyAuth($id, $groups, ['targetType' => 'userGroups'], $test);
        }

        /** Adds/Removes actions to/from a group.
         * @param string $groupName Name of the group
         * @param array $groups An array of the form:
         *                      <Action Name> => Bool true/false for set/delete. Will do both insertions and deletions.
         * @param array $params An array currently empty
         */
        function modifyGroupActions(string $groupName,  array $actions, array $params = [], $test = false){
            return $this->modifyAuth($groupName, $actions, ['targetType' => 'groupActions'], $test);
        }

        function modifyAuth($identifier, array $targets, array $params, $test = false){

            if(!isset($params['targetType']) ||
                ($params['targetType'] != 'userGroups' && $params['targetType'] != 'userActions' && $params['targetType'] != 'groupActions' )
            )
                throw new \Exception('Trying to modify user auth without specifying which - actions or groups!');
            else
                $targetType = $params['targetType'];

            $prefix = $this->sqlHandler->getSQLPrefix();

            if($targetType == 'userGroups')
                $targetTable = $prefix.'USERS_GROUPS_AUTH';
            elseif($targetType == 'userActions')
                $targetTable = $prefix.'USERS_ACTIONS_AUTH';
            else
                $targetTable = $prefix.'GROUPS_ACTIONS_AUTH';

            if($targetType == 'userGroups')
                $targetCol = 'Auth_Group';
            else
                $targetCol = 'Auth_Action';

            if($targetType != 'groupActions')
                $idCol = 'ID';
            else
                $idCol = 'Auth_Group';

            $users = $prefix.'USERS_AUTH';
            $usersGroups = $prefix.'USERS_GROUPS_AUTH';

            $insertArray = [];
            $deleteArray = [];

            foreach($targets as $targetName => $toSet){
                //A reminder - an INT will stay an INT even when passed as a 'STRING' to the PHP Query parser
                if($toSet)
                    array_push($insertArray,[[$identifier,'STRING'],[$targetName,'STRING']]);
                else
                    array_push($deleteArray,[$targetTable.'.'.$targetCol,$targetName,'=']);
            }
            //Insert what we need
            if($insertArray != []){
                $this->sqlHandler->insertIntoTable(
                    $targetTable,
                    [$idCol,$targetCol],
                    $insertArray,
                    ['onDuplicateKey'=>true],
                    $test
                );
            }

            $res = true;

            //Delete what we need
            if($deleteArray != []){
                array_push($deleteArray,'OR');

                $deleteArray = [
                    [$idCol,$identifier,'='],
                    $deleteArray,
                    'AND'
                ];

                $res = $this->sqlHandler->deleteFromTable(
                    $targetTable,
                    $deleteArray,
                    [],
                    $test
                );
            }

            if(!$res)
                return false;

            //If anything was changed, update user Last_Updated
            if($deleteArray != [] && $insertArray != []){
                //This happens when we update a user action/group
                if($targetType != 'groupActions')
                    $res = $this->sqlHandler->updateTable(
                        $users,
                        ['Last_Changed = "'.time().'"'],
                        ['ID',$identifier,'='],
                        [],
                        $test
                    );
                //This happens when we update a a group
                else
                    $res = $this->sqlHandler->updateTable(
                        $users,
                        ['Last_Changed = "'.time().'"'],
                        [
                            'ID',
                            $this->sqlHandler->selectFromTable(
                                $usersGroups,
                                ['Auth_Group',$identifier,'='],
                                ['ID'],
                                ['justTheQuery'=>true,'useBrackets'=>true,'DISTINCT'=>true],
                                false
                            ),
                            'IN'],
                        [],
                        $test
                    );
            }

            return $res;
        }





        /** Views the current actions of a user
         * @param mixed $identifier Either user ID or user Mail
         * @param array $params An array of the form:
         *                      'includeGroups' - bool, whether to include group actions, default true
         *
         * @return String[] Array of all actions the user has (empty if he has no actions or does not exist)
        function viewUserActions($identifier, array $params = [], $test = false){
        $prefix = $this->sqlHandler->getSQLPrefix();
        //set defaults
        if(!isset($params['includeGroups']))
        $includeGroups = true;
        else
        $includeGroups = $params['includeGroups'];

        //Works both with user mail and ID
        if(gettype($identifier) == 'integer'){
        $identityCond = [$prefix.'USERS.ID',$identifier,'='];
        }
        else{
        $identityCond = [$prefix.'USERS.Email',[$identifier,'STRING'],'='];
        }

        $query = $this->sqlHandler->selectFromTable(
        $prefix.'USERS JOIN '.$prefix.'USERS_ACTIONS_AUTH ON '.$prefix.'USERS_ACTIONS_AUTH.ID = '.$prefix.'USERS.ID',
        $identityCond,
        ['Auth_Action'],
        ['justTheQuery'=>true],
        $test
        );

        if($includeGroups)
        $query .= ' UNION '.
        $this->sqlHandler->selectFromTable(
        $prefix.'USERS JOIN '.$prefix.'USERS_GROUPS_AUTH ON '.$prefix.'USERS_GROUPS_AUTH.ID = '.$prefix.'USERS.ID
        JOIN '.$prefix.'GROUPS_ACTIONS_AUTH ON '.$prefix.'USERS_GROUPS_AUTH.Auth_Group = '.$prefix.'GROUPS_ACTIONS_AUTH.Auth_Group',
        $identityCond,
        ['Auth_Action'],
        ['justTheQuery'=>true],
        $test
        );

        $actions = $this->sqlHandler->exeQueryBindParam($query,[],true);

        $res = [];

        foreach($actions as $tuple){
        array_push($res,$tuple['Auth_Action']);
        }

        return $res;
        }

         */
    }

}

?>