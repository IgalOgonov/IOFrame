<?php
namespace IOFrame\Handlers{
    define('IOFrameHandlersAuthHandler',true);

    /**Handles user authorization (not authentication!) in IOFrame
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */
    class AuthHandler extends \IOFrame\Abstract\DBWithCache{

        /** @var bool $loggedIn Whether the user is logged in or not
         * */
        protected bool $loggedIn=false;

        /** @var int $rank User rank
         * */
        protected int $rank=10000;

        /** @var string[] $actions User actions
         * */
        protected array $actions=[];

        /** @var array $groups Groups belonging to a user.
         *              An array of the form "groupName" => <Array of Actions> (if groups are initiated)
         * */
        protected array $groups=[];

        /** @var string[] $details User Details array
         * */
        protected array $details=[];

        /** @var bool $useCache Specifies whether we should be using cache
         */
        protected mixed $useCache = false;

        /** @var int $lastUpdated When the user information we got was last updated.
         */
        protected int $lastUpdatedUser = 0;

        /** @var array $lastUpdatedGroups When the group information we got was last updated. Of the form:
         *                                  "groupName" => last update time
         */
        protected array $lastUpdatedGroups = [];

        /** @var bool $userInitiated Whether the user was initiated with all his groups/actions from the DB/Cache
         * */
        protected bool $userInitiated=false;

        protected string $userLastUpdatedCachePrefix = '_auth_userLastUpdated_';
        protected string $userActionsCachePrefix = '_auth_userActions_';
        protected string $userGroupsCachePrefix = '_auth_userGroups_';
        protected string $groupLastUpdatedCachePrefix = '_auth_groupLastUpdated_';
        protected string $groupActionsCachePrefix = '_auth_groupActions_';

        /**
         * Basic construction function
         * @param SettingsHandler $settings local settings handler.
         * @param array $params Typical default settings array, with one extra parameter:
         *                      'initiateUser' => Boolean, whether to initiate actions/groups from the start
         *                                          (default false, actions are initiated only on request)
         */
        function __construct(\IOFrame\Handlers\SettingsHandler $settings, $params = []){

            parent::__construct($settings,array_merge($params,['logChannel'=>\IOFrame\Definitions::LOG_GENERAL_SECURITY_CHANNEL]));

            $this->useCache = $this->defaultSettingsParams['useCache'] ?? false;

            $this->init($params);
        }

        /**
         * Initiates the data in the handler.
         * While details, loggedIn and Rank are always initiated, actions and groups are only initiated here if
         *  the relevant parameter is true.
         * @param array $params of the form:
         *              'initiateUser' - bool, default false - Whether to initiate user actions/groups by default, or only on request
         */
        function init(array $params): void {

            $initiateUser = $params['initiateUser'] ?? false;

            //This means we logged in, so the rank (and logged in status) must be accurate
            if(isset($_SESSION['logged_in']) && isset($_SESSION['details'])){
                $this->details = json_decode($_SESSION['details'],true);
                $this->loggedIn=$_SESSION['logged_in'];
                $this->rank=$this->details['Auth_Rank'];
                if($initiateUser)
                    $this->updateUserInfoFromDB($params);

            }
            else{
                $this->loggedIn=false;
                $this->rank=10000;
                $this->details=[];
            }
        }

        /** Updates the user actions and groups. First from the cache (if used), then from the DB.
         * @param array $params
         */
        function updateUserInfoFromDB(array $params = []): void {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            if(!isset($this->details['ID'])){
                if($verbose)
                    echo 'Trying to update user info when not logged in!'.EOL;
                return;
            }
            //Get information from cache if asked
            $cachedInfo = $this->getFromCache(['userID'=>$this->details['ID'],'test'=>$test,'verbose'=>$verbose]);
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
                $cachedInfo = $this->getFromCache(['groupName'=>$groupName,'test'=>$test,'verbose'=>$verbose]);
                if($cachedInfo!=null){
                    if(isset($cachedInfo['actions']))
                        $this->groups[$groupName] = $cachedInfo['actions'];
                    $this->lastUpdatedGroups['groupName'] = $cachedInfo['lastUpdated'];
                }
            }

            //Get up-to-date information from the database
            $this->updateFromDB(['test'=>$test,'verbose'=>$verbose]);

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
         *
         * @param array $params - array of the form
         *                  'userID'=>int, defaults to session ID - user ID to update auth info from
         *
         * @throws \Exception
         * @throws \Exception
         * @throws \Exception
         */
        function updateFromDB(array $params = []): ?array {
            $prefix = $this->SQLManager->getSQLPrefix();
            $groupTable = $prefix.'GROUPS_AUTH';
            $userTable = $prefix.'USERS_AUTH';
            $actionTable = $prefix.'ACTIONS_AUTH';
            $usersActionsTable = $prefix.'USERS_ACTIONS_AUTH';
            $groupsActionsTable = $prefix.'GROUPS_ACTIONS_AUTH';
            $usersGroupsTable = $prefix.'USERS_GROUPS_AUTH';
            $userLastChanged = $this->lastUpdatedUser;

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $userID = $params['userID'] ?? $this->details['ID'];

            $updateTime = time();

            //Used in B
            $groupsToCheckCond = [];
            //Used in A.3
            $groupsToIgnoreCond = [];
            //Used in A.2
            $groupsToGetConditions = [];
            if($this->groups != []){
                foreach($this->groups as $groupName => $actions){
                    $groupsToIgnoreCond[] = [$groupName, 'STRING'];
                    $groupsToCheckCond[] = [$usersGroupsTable . '.Auth_Group', [$groupName, 'STRING'], '='];
                    $tempCond = [];
                    $tempCond[] = [$groupsActionsTable . '.Auth_Group', [$groupName, 'STRING'], '='];
                    $tempCond[] = [$groupTable . '.Last_Updated', [(string)$this->lastUpdatedGroups[$groupName], 'STRING'], '>='];
                    $tempCond[] = 'AND';
                    $groupsToGetConditions[] = $tempCond;
                }
                $groupsToGetConditions[] = 'OR';

                $groupsToGetConditions = [
                    $groupsToGetConditions,
                    [
                        $groupsActionsTable.'.Auth_Group',
                        $this->SQLManager->selectFromTable(
                            $usersGroupsTable.' INNER JOIN '.$userTable.' ON '.$userTable.'.ID = '.$usersGroupsTable.'.ID',
                            [
                                [$userTable.'.ID',$userID,'='],
                                [$userTable.'.Last_Updated',$userLastChanged,'>'],
                                'AND'
                            ],
                            [$usersGroupsTable.'.Auth_Group'],
                            ['justTheQuery'=>true,'useBrackets'=>true,'test'=>false]
                        ),
                        'IN'
                    ],
                    'AND'
                ];

                if($verbose)
                    echo 'Groups found: '.json_encode($groupsToIgnoreCond).EOL;

                $groupsToIgnoreCond[] = 'CSV';
                $groupsToCheckCond[] = 'OR';
            }

            //A.3 query depends on whether there were groups to begin with that need to be ignored.
            $newGroupsCondition = [
                [
                    $groupsActionsTable.'.Auth_Group',
                    $this->SQLManager->selectFromTable(
                        $usersGroupsTable.' INNER JOIN '.$userTable.' ON '.$userTable.'.ID = '.$usersGroupsTable.'.ID',
                        [
                            [$usersGroupsTable.'.ID',$userID,'='],
                            [$userTable.'.Last_Updated',$userLastChanged,'>'],
                            'AND'
                        ],
                        [$usersGroupsTable.'.Auth_Group'],
                        ['justTheQuery'=>true,'useBrackets'=>true,'test'=>false]
                    ),
                    'IN'
                ]
            ];
            //If there were groups to ignore (aka present in the cache), we must add that condition.
            if($this->groups != [])
                $newGroupsCondition[] = [
                    [
                        $groupsActionsTable . '.Auth_Group',
                        $groupsToIgnoreCond,
                        'IN'
                    ],
                    'NOT'
                ];
            $newGroupsCondition[] = 'AND';

            //The initial parts of the query are A.1 and A.3 (which always checks for new groups either way)
            $query = $this->SQLManager->selectFromTable(
                    $actionTable,
                    [
                        $actionTable.'.Auth_Action',
                        $this->SQLManager->selectFromTable(
                            $usersActionsTable.' INNER JOIN '.$userTable.' ON '.$userTable.'.ID = '.$usersActionsTable.'.ID',
                            [
                                [$usersActionsTable.'.ID',$userID,'='],
                                [$userTable.'.Last_Updated',$userLastChanged,'>'],
                                'AND'
                            ],
                            [$usersActionsTable.'.Auth_Action'],
                            ['justTheQuery'=>true,'useBrackets'=>true,'test'=>false]
                        ),
                        'IN'
                    ],
                    [$actionTable.'.Auth_Action as actionName, "@" as groupName, TRUE as userHadGroup'],
                    ['justTheQuery'=>true]
                ).' UNION '.
                $this->SQLManager->selectFromTable(
                    $groupsActionsTable.' JOIN '.$actionTable.' ON '.$groupsActionsTable.'.Auth_Action = '.$actionTable.'.Auth_Action',
                    [
                        $actionTable.'.Auth_Action',
                        $this->SQLManager->selectFromTable(
                            $groupsActionsTable,
                            $newGroupsCondition,
                            [$groupsActionsTable.'.Auth_Action'],
                            ['justTheQuery'=>true,'useBrackets'=>true,'test'=>false]
                        ),
                        'IN'
                    ],
                    [$actionTable.'.Auth_Action as actionName, '.$groupsActionsTable.'.Auth_Group as groupName, FALSE as userHadGroup'],
                    ['justTheQuery'=>true]
                )
            ;

            //The other parts of the query are only relevant if there were groups in the cache
            if($this->groups !=[]){
                //Check for A.2
                $query .=' UNION '.$this->SQLManager->selectFromTable(
                        $groupsActionsTable.' JOIN '.$actionTable.' ON '.$groupsActionsTable.'.Auth_Action = '.$actionTable.'.Auth_Action',
                        [
                            $actionTable.'.Auth_Action',
                            $this->SQLManager->selectFromTable(
                                $groupsActionsTable.' INNER JOIN '.$groupTable.' ON '.$groupTable.'.Auth_Group = '.$groupTable.'.Auth_Group',
                                $groupsToGetConditions,
                                [$groupsActionsTable.'.Auth_Action'],
                                ['justTheQuery'=>true,'useBrackets'=>true,'test'=>false]
                            ),
                            'IN'
                        ],
                        [$actionTable.'.Auth_Action as actionName, '.$groupsActionsTable.'.Auth_Group as groupName, TRUE as userHadGroup'],
                        ['justTheQuery'=>true]
                    );


                //Check for B - which groups stayed alive
                $query .=' UNION '.$this->SQLManager->selectFromTable(
                        $usersGroupsTable,
                        [
                            [$usersGroupsTable.'.ID',$userID,'='],
                            $groupsToCheckCond,
                            'AND'
                        ],
                        ['-1 as actionName, '.$usersGroupsTable.'.Auth_Group as groupName, TRUE as userHadGroup'],
                        ['justTheQuery'=>true,'test'=>false]
                    );
            }

            if($verbose)
                echo 'Query to send: '.$query.EOL;
            $res = $this->SQLManager->exeQueryBindParam($query,[],['fetchAll'=>true]);
            if(!is_array($res)){
                $this->logger->error('Could not load user auth ',['user'=>$userID,'query'=>$query]);
                return [$userID=>[]];
            }

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
                    $groupArray[$val['groupName']]['actions'] = [];
                    //In case this is just an unchanged group
                    if($val['actionName'] == -1){
                        $groupArray[$val['groupName']]['indicator'] = 0;
                    }
                    //Else it's an updated group or new
                    else{
                        $groupArray[$val['groupName']]['indicator'] =
                            ($val['userHadGroup'] == '1') ? 1 : 2;
                        $groupArray[$val['groupName']]['actions'][] = $val['actionName'];
                    }
                }
                else{
                    //In case we found an unchanged group that actually has new actions (notice in this case actionName can't be -1)
                    if($groupArray[$val['groupName']]['indicator'] == 0){
                        $groupArray[$val['groupName']]['indicator'] = 1;
                        $groupArray[$val['groupName']]['actions'][] = $val['actionName'];
                    }
                    //If we found a new/changed group, just make sure we're not pushing -1 as an action
                    else{
                        if($val['actionName'] != -1)
                            $groupArray[$val['groupName']]['actions'][] = $val['actionName'];
                    }
                }
            }
            //Now lets see which (if any) groups were cut from the user
            $removedGroups = [];
            foreach($this->groups as $groupName => $actions){
                if(!isset($groupArray[$groupName]))
                    $removedGroups[] = $groupName;
            }

            if($verbose){
                echo 'Group Array: '.json_encode($groupArray).EOL;
                echo 'Removed Array: '.json_encode($removedGroups).EOL;
            }

            //If nothing changed, then there is no reason to keep running this procedure
            if($groupArray == [] && $removedGroups == [])
                return null;


            //First, update the handler

            $this->lastUpdatedUser = $updateTime;
            $somethingChanged = false;

            foreach($removedGroups as $groupName){
                $somethingChanged = true;
                if($verbose)
                    echo 'Unsetting group '.$groupName.EOL;
                if(!$test){
                    unset($this->groups[$groupName]);
                    unset($this->lastUpdatedGroups[$groupName]);
                }
            }

            foreach($groupArray as $groupName => $arr){
                //This is the user actions
                if($groupName == '@'){
                    $somethingChanged = true;
                    if($verbose)
                        echo 'Updating actions '.json_encode( $arr['actions']).EOL;
                    if(!$test){
                        $this->actions = $arr['actions'];
                    }
                }
                //This is an actual group
                else{
                    //Obviously only update updated groups..
                    if($arr['indicator'] == '1' || $arr['indicator'] == '2'){
                        $somethingChanged = true;
                        if($verbose)
                            echo 'Updating group '.$groupName.' with actions '.json_encode( $arr['actions']).EOL;
                        if(!$test){
                            $this->groups[$groupName] = $arr['actions'];
                            $this->lastUpdatedGroups[$groupName] = $updateTime;
                        }

                        //Sadly, while I know that updating the group cache shouldn't happen on USER action, it has to be done here,
                        //As any sequential calls will assume the group in the cache is up to date
                        $this->updateCache(
                            [
                                'groupName' => $groupName,
                                'actions' => $arr['actions'],
                                'lastUpdated' => $updateTime,
                                'test'=>$test,'verbose'=>$verbose
                            ]
                        );
                    }
                }
            }

            if($somethingChanged){
                $groupList = [];

                foreach($this->groups as $name=>$v){
                    $groupList[] = $name;
                }


                //Now, update the cache
                $this->updateCache(
                    [
                        'userID'=>$this->details['ID'],
                        'actions'=>$this->actions,
                        'groups'=>$groupList,
                        'lastUpdated'=>$this->lastUpdatedUser,
                        'test'=>$test,'verbose'=>$verbose
                    ]
                );
            }


            return null;
        }

        /** Gets requested user groups & user actions / group actions from cache, and updates the handler
         * @param array $params Of the form:
         *                  'userID'  => User ID to get from cache
         *                  'groupName' => Group name to get from cache (whitespaces are replaced by underlines)
         *                  If both are set for some reason, will get user over group.
         * @returns array [
         *                'actions'     => <Action array> May be empty
         *                'groups'      => <Group array> May be empty if fetching user, is not set if fetching group.
         *                'lastUpdated' => <Time this was updated>
         *                ]
         *                or NULL if user/group does not exist in the cache (or cache is disabled)
         */
        function getFromCache(array $params = []): ?array {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $res = null;

            if(!$this->useCache){
                if($verbose)
                    echo 'Trying to get something from cache when cache is disabled!'.EOL;
                return null;
            }

            //If we are fetching user info from cache
            if(isset($params['userID'])){
                //Lets see if the user is even in the cache
                $lastUpdated = $this->RedisManager->call('get',$this->userLastUpdatedCachePrefix.$params['userID']);
                if($verbose)
                    echo 'Redis query - get from '.$this->userLastUpdatedCachePrefix.$params['userID'].', result - '.$lastUpdated.EOL;
                //This means the user is not in the cache
                if($lastUpdated === false)
                    return null;
                else
                    $res['lastUpdated'] = $lastUpdated;

                //If user is in the cache, decode everything else (note he still might have 0 actions/groups)
                $actionsJSON = $this->RedisManager->call('get',$this->userActionsCachePrefix.$params['userID']);
                if($verbose)
                    echo 'Redis query - get from '.$this->userActionsCachePrefix.', result - '.$actionsJSON.EOL;
                if($actionsJSON !== false)
                    $res['actions'] = json_decode($actionsJSON,true);

                $groupsJSON = $this->RedisManager->call('get',$this->userGroupsCachePrefix.$params['userID']);
                if($verbose)
                    echo 'Redis query - get from '.$this->userGroupsCachePrefix.$params['userID'].', result - '.$groupsJSON.EOL;
                if($groupsJSON !== false)
                    $res['groups'] = json_decode($groupsJSON,true);

                return $res;
            }
            //If we are fetching group info from cache
            elseif(isset($params['groupName'])){
                //Lets see if the group is even in the cache
                $lastUpdated = $this->RedisManager->call('get',$this->groupLastUpdatedCachePrefix.$params['groupName']);
                if($verbose)
                    echo 'Redis query - get from '.$this->groupLastUpdatedCachePrefix.$params['groupName'].', result - '.$lastUpdated.EOL;
                //This means the group is not in the cache
                if($lastUpdated === false)
                    return null;
                else
                    $res['lastUpdated'] = $lastUpdated;

                //If group is in the cache, decode everything else (still, might be 0 actions)
                $actionsJSON = $this->RedisManager->call('get',$this->groupActionsCachePrefix.$params['groupName']);
                if($verbose)
                    echo 'Redis query - get from '.$this->groupActionsCachePrefix.$params['groupName'].', result - '.$actionsJSON.EOL;
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
         *                  'lastUpdated' => Is the time we got the above information from the DB. Defaults to time()
         *
         *                  If both userID and groupName are set, will update user.
         */
        function updateCache(array $params = []): void {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            if(!$this->useCache){
                if($verbose)
                    echo 'Trying to get something from cache when cache is disabled!'.EOL;
                return;
            }

            if($test){
                if($verbose)
                    echo 'Running updateCache with parameters '.json_encode($params).EOL;
                return;
            }

            //If we are updating a user
            if(isset($params['userID'])){

                //Update actions if given
                if(isset($params['actions']))
                    $this->RedisManager->call('set',[$this->userActionsCachePrefix.$params['userID'],json_encode($params['actions'])]);

                //Update groups if given
                if(isset($params['groups']))
                    $this->RedisManager->call('set',[$this->userGroupsCachePrefix.$params['userID'],json_encode($params['groups'])]);

                //Check if we got a lastUpdated date, if no set to default
                if(!isset($params['lastUpdated']))
                    $params['lastUpdated'] = time();
                //Update lastUpdated
                $this->RedisManager->call('set',[$this->userLastUpdatedCachePrefix.$params['userID'],$params['lastUpdated']]);

            }
            //If we are updating a group
            elseif(isset($params['groupName'])){

                //Update actions if given
                if(isset($params['actions']))
                    $this->RedisManager->call('set',[$this->groupActionsCachePrefix.$params['groupName'],json_encode(['actions'])]);

                //Check if we got a lastUpdated date, if no set to default
                if(!isset($params['lastUpdated']))
                    $params['lastUpdated'] = time();
                //Update lastUpdated
                $this->RedisManager->call('set',[$this->groupLastUpdatedCachePrefix.$params['groupName'],$params['lastUpdated']]);

            }

        }

        /** Check whether user has a rank of at most $target (lower rank = better!)
         * @param int $target checks user Auth rank versus target
         * @returns bool true if the user is authorized below or at target level, false otherwise.
         * @throws \Exception If target is less than 0 - that must be a programmer error
         * */
        function isAuthorized(int $target = 0): bool {
            if($target<0){
                throw(new \Exception('Highest authorization level is 0.'));
            }
            else if($this->loggedIn){
                if($this->rank <= $target)
                    return true;
                else{
                    return false;
                }
            }
            else
                return false;
        }

        /*@return integer user's rank, or 10000 if not logged in.
        */
        function getRank(): int {
            return $this->rank;
        }

        /**@return bool true if the user is logged in, false otherwise.
         */
        function isLoggedIn(): bool {
            return $this->loggedIn;
        }

        /**
         * @param string $arg Gets relevant detail from $this->details
         * @return string|null a specific detail from the details array, or 'unset' if it isn't set.
         */
        function getDetail(string $arg): mixed {
            return $this->details[$arg] ?? null;
        }

        /** Check if a user has an action
         * @param string $target Action to check
         * @return bool true if the user has an action in their Actions array
         */
        function hasAction(string $target): bool {
            if(!$this->userInitiated)
                $this->updateUserInfoFromDB();
            if($this->loggedIn){
                $res = in_array($target,$this->actions);
                if($res)
                    return true;
                else{
                    foreach($this->groups as $groupActions){
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
         * @param array $params
         * @return array|bool|int|mixed|string|string[]
         */
        function modifyUserRank(mixed $identifier, int $newRank, array $params = []){

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

            $res = $this->SQLManager->updateTable(
                $this->SQLManager->getSQLPrefix().'USERS',
                ['Auth_Rank = '.$newRank],
                $identityCond,
                ['test'=>$test,'verbose'=>$verbose]
            );

            if(!$res)
                $this->logger->error('Failed to modify user rank',['id'=>$identityCond,'rank'=>$newRank]);

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
         * @return array
         *              If fetching user IDs - array of relevant (in respect to filters) user IDs
         *              If fetching user Actions - array of the form: [
         *                                                               <UserID> =>[
         *                                                                            "@" => Array of Actions
         *                                                                            <groupName> => Array of Actions
         *                                                                            ...
         *                                                                           ]
         *                                                               ...
         *                                                              ]
         *              where "@" are actions belonging directly to the user.
         * @throws \Exception
         * @throws \Exception
         * @throws \Exception
         */
        function getUsers(array $params = []): array {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $prefix = $this->SQLManager->getSQLPrefix();
            $userTable = $prefix.'USERS_AUTH';
            $usersActionsTable = $prefix.'USERS_ACTIONS_AUTH';
            $groupsActionsTable = $prefix.'GROUPS_ACTIONS_AUTH';
            $usersGroupsTable = $prefix.'USERS_GROUPS_AUTH';

            $selectionFilter = [];

            //Default params
            $separator = $params['separator'] ?? 'AND';

            $includeActions = $params['includeActions'] ?? false;

            if(isset($params['limit']))
                $limit = min((int)$params['limit'],$this->defaultQueryLimit);
            else
                $limit = $this->defaultQueryLimit;

            if(isset($params['offset']))
                $offset = (int)$params['offset'];
            else
                $offset = null;

            //Getting actions with a limit/offset would risk getting incomplete data about user actions without knowing it
            if($includeActions){
                $limit = null;
                $offset = null;
            }

            $orderByExp = $params['orderByExp'] ?? 'ID';

            //Valid parameters - not including filter ones
            $validParams = ['id','action','group'];

            $selectionParams = ['justTheQuery'=>true,'useBrackets'=>true, 'DISTINCT'=>true,'test'=>false] ;

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
                                        $target[] = 'CSV';
                                        $cond =
                                            [
                                                [
                                                    $userTable.'.ID',
                                                    $target,
                                                    'IN'
                                                ],
                                                'NOT'
                                            ];
                                        $selectionFilter[] = $cond;
                                        break;
                                    case 'action':
                                        $target[] = 'CSV';
                                        $cond =
                                            [
                                                [
                                                    $userTable.'.ID',
                                                    $this->SQLManager->selectFromTable(
                                                        $userTable.' JOIN '.$usersActionsTable.' ON '.$userTable.'.ID = '.$usersActionsTable.'.ID',
                                                        [
                                                            $usersActionsTable.'.Auth_Action',
                                                            $target,
                                                            'IN'
                                                        ],
                                                        [$userTable.'.ID'],
                                                        $selectionParams
                                                    ),
                                                    'IN'
                                                ],
                                                'NOT'
                                            ];
                                        $selectionFilter[] = $cond;
                                        break;
                                    case 'group':
                                        $target[] = 'CSV';
                                        $cond =
                                            [
                                                [
                                                    $userTable.'.ID',
                                                    $this->SQLManager->selectFromTable(
                                                        $userTable.' JOIN '.$usersGroupsTable.' ON '.$userTable.'.ID = '.$usersGroupsTable.'.ID',
                                                        [
                                                            $usersGroupsTable.'.Auth_Group',
                                                            $target,
                                                            'IN'
                                                        ],
                                                        [$userTable.'.ID'],
                                                        $selectionParams
                                                    ),
                                                    'IN'
                                                ],
                                                'NOT'
                                            ];
                                        $selectionFilter[] = $cond;
                                        break;
                                }
                                break;
                            case 'IN':
                                switch($paramName){
                                    case 'id':
                                        $target[] = 'CSV';
                                        $cond =
                                            [
                                                $userTable.'.ID',
                                                $target,
                                                'IN'
                                            ];
                                        $selectionFilter[] = $cond;
                                        break;
                                    case 'action':
                                        $target[] = 'CSV';
                                        $cond =
                                            [
                                                $userTable.'.ID',
                                                $this->SQLManager->selectFromTable(
                                                    $userTable.' JOIN '.$usersActionsTable.' ON '.$userTable.'.ID = '.$usersActionsTable.'.ID',
                                                    [
                                                        $usersActionsTable.'.Auth_Action',
                                                        $target,
                                                        'IN'
                                                    ],
                                                    [$userTable.'.ID'],
                                                    $selectionParams
                                                ),
                                                'IN'
                                            ];
                                        $selectionFilter[] = $cond;
                                        break;
                                    case 'group':
                                        $target[] = 'CSV';
                                        $cond =
                                            [
                                                $userTable.'.ID',
                                                $this->SQLManager->selectFromTable(
                                                    $userTable.' JOIN '.$usersGroupsTable.' ON '.$userTable.'.ID = '.$usersGroupsTable.'.ID',
                                                    [
                                                        $usersGroupsTable.'.Auth_Group',
                                                        $target,
                                                        'IN'
                                                    ],
                                                    [$userTable.'.ID'],
                                                    $selectionParams
                                                ),
                                                'IN'
                                            ]
                                        ;
                                        $selectionFilter[] = $cond;
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
                                        $selectionFilter[] = $cond;
                                        break;
                                    case 'action':
                                        $cond =
                                            [
                                                $userTable.'.ID',
                                                $this->SQLManager->selectFromTable(
                                                    $userTable.' JOIN '.$usersActionsTable.' ON '.$userTable.'.ID = '.$usersActionsTable.'.ID',
                                                    [
                                                        $usersActionsTable.'.Auth_Action',
                                                        $target,
                                                        '='
                                                    ],
                                                    [$userTable.'.ID'],
                                                    $selectionParams
                                                ),
                                                'IN'
                                            ];
                                        $selectionFilter[] = $cond;
                                        break;
                                    case 'group':
                                        $cond =
                                            [
                                                $userTable.'.ID',
                                                $this->SQLManager->selectFromTable(
                                                    $userTable.' JOIN '.$usersGroupsTable.' ON '.$userTable.'.ID = '.$usersGroupsTable.'.ID',
                                                    [
                                                        $usersGroupsTable.'.Auth_Group',
                                                        $target,
                                                        '='
                                                    ],
                                                    [$userTable.'.ID'],
                                                    $selectionParams
                                                ),
                                                'IN'
                                            ];
                                        $selectionFilter[] = $cond;
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
                $selectionFilter[] = $separator;

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

            $query .= $this->SQLManager->selectFromTable(
                $tables1,
                $selectionFilter,
                $columns1,
                ['justTheQuery'=>true]
            );
            if(isset($tables2)){
                $query .= ' UNION ';
                $query .= $this->SQLManager->selectFromTable(
                    $tables2,
                    $selectionFilter,
                    $columns2,
                    ['justTheQuery'=>true,'test'=>false]
                );
            }
            $query .= ') as Meaningless_Alias ORDER BY '.$orderByExp;

            $queryWithoutLimit = $query;

            if($limit != null){
                if($offset != null)
                    $query .= ' LIMIT '.$offset.','.$limit;
                else
                    $query .= ' LIMIT '.$limit;
            }

            if($verbose)
                echo 'Query to send: '.$query.EOL;
            $response = $this->SQLManager->exeQueryBindParam($query,[],['fetchAll'=>true]);
            if(!is_array($response)){
                $this->logger->error('Could not load user auth ',['params'=>$params,'query'=>$query]);
                return [];
            }


            $result = [];

            if($includeActions){
                foreach($response as $row){

                    if(!isset($result[$row['ID']]))
                        $result[$row['ID']] = [];

                    if(!isset($result[$row['ID']][$row['Auth_Group']]))
                        $result[$row['ID']][$row['Auth_Group']] = [];

                    $result[$row['ID']][$row['Auth_Group']][] = $row['Auth_Action'];
                }
            }
            else{
                foreach($response as $row){
                    $result[$row['ID']] = [];
                }
            }

            //If we ar just getting IDs, we can also get the corrent number of total results
            if(!$includeActions){
                try{
                    $queryWithoutLimit = str_replace('DISTINCT ID','COUNT(DISTINCT ID)',$queryWithoutLimit);
                    $totals = $this->SQLManager->exeQueryBindParam($queryWithoutLimit,[],['fetchAll'=>true]);
                    if($totals)
                        $result['@'] = ['#'=>$totals[0][0]];
                }
                catch (\Exception $e){
                    $this->logger->error('Could not load user auth total, exception '.$e->getMessage(),['params'=>$params,'query'=>$queryWithoutLimit,'trace'=>$e->getTrace()]);
                }
            }

            return $result;
        }

        /**
         * Views all user actions (can be filtered with $params). Is an alias of getUsers.
         * Note that if allowed to be viewed without enforcing an ID condition '=' or 'IN' (or at least a similar group
         * condition), or the limit parameter, the results could reach insane sizes, and the query would be very slow,
         * as there is no way to limit this.
         * @param array $params same as getUsers
         * @returns array same as getUsers
         */
        function getUsersWithActions(array $params = []): array {
            return $this->getUsers(array_merge($params,['includeActions'=>true]));
        }

        /**
         * Returns all the actions.
         *
         * @param array $params
         *                  'safeStr' => bool, default true - Whether to convert descriptions to safeString
         *                  'limit' => int, SQL Limit clause
         *                  'offset' => int, SQL offset clause (only matters if limit is set)
         * @returns array of the form [
         *                             <Action Name> => {
         *                                                  'description' => <Description>
         *                                              }
         *                             ...
         *                            ]
         */
        function getActions(array $params = []): array {

            $prefix = $this->SQLManager->getSQLPrefix();
            $actionsTable = $prefix.'ACTIONS_AUTH';

            if(isset($params['limit']))
                $limit = min((int)$params['limit'],$this->defaultQueryLimit);
            else
                $limit = $this->defaultQueryLimit;

            if(isset($params['offset']))
                $offset = (int)$params['offset'];
            else
                $offset = null;

            $safeStr = $params['safeStr'] ?? true;


            $params['orderBy'] = 'Auth_Action';
            $params['limit'] = $limit;
            $params['offset'] = $offset;

            $response = $this->SQLManager->selectFromTable(
                $actionsTable,
                [],
                ['Auth_Action','Description'],
                $params
            );

            $totals = $this->SQLManager->selectFromTable(
                $actionsTable,
                [],
                ['COUNT(*)'],
                array_merge($params,['limit'=>null,'offset'=>null])
            );
            if(isset($totals[0]))
                $totals = $totals[0][0];

            $res = [];

            if(is_array($response))
                foreach($response as $row){
                    $res[$row['Auth_Action']] = [
                        'description' => isset($row['Description'])? ( $safeStr ? \IOFrame\Util\SafeSTRFunctions::safeStr2Str($row['Description']) : $row['Description'] ) : null
                    ];
                }

            $res['@'] = ['#'=>$totals];

            return $res;
        }


        /**
         * Create new actions, or modifies existing ones.
         *
         * @param array $actions An array of the form <Action Name> => <Description>|null
         * @param array $params
         *                  'safeStr' => bool, default true - Whether to convert descriptions to safeString
         * @returns bool Whether the query succeeded or not.
         */
        function setActions(array $actions, array $params = []): array|bool|int|string|null {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $prefix = $this->SQLManager->getSQLPrefix();
            $actionsTable = $prefix.'ACTIONS_AUTH';

            $safeStr = $params['safeStr'] ?? true;

            $actionsToInsert = [];

            foreach($actions as $actionName=>$desc){
                if($desc!==null){
                    if($safeStr)
                        $desc = \IOFrame\Util\SafeSTRFunctions::str2SafeStr($desc);
                    $desc = [$desc,'STRING'];
                }
                $actionName = [$actionName,'STRING'];
                $actionsToInsert[] = [$actionName, $desc];
            }

            return $this->SQLManager->insertIntoTable(
                $actionsTable,
                ['Auth_Action','Description'],
                $actionsToInsert,
                ['onDuplicateKey'=>true,'test'=>$test,'verbose'=>$verbose]
            );
        }

        /**
         * Deletes actions
         *
         * @param array $actions An array of action names
         * @param array $params
         * @return array|bool|int|mixed|string|string[]
         */
        function deleteActions(array $actions, array $params = []){

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $prefix = $this->SQLManager->getSQLPrefix();
            $actionsTable = $prefix.'ACTIONS_AUTH';
            $userTable = $prefix.'USERS_AUTH';
            $groupsActionsTable = $prefix.'GROUPS_ACTIONS_AUTH';
            $usersGroupsTable = $prefix.'USERS_GROUPS_AUTH';
            $usersActionsTable = $prefix.'USERS_ACTIONS_AUTH';

            foreach($actions as $k=>$actionName){
                $actions[$k] = [$actionName,'STRING'];
            }

            $res = $this->SQLManager->deleteFromTable(
                $actionsTable,
                [
                    'Auth_Action',
                    $actions,
                    'IN'
                ],
                ['test'=>$test,'verbose'=>$verbose]
            );

            $conds = [
                'ID',
                '('.$this->SQLManager->selectFromTable(
                    $usersActionsTable,
                    ['Auth_Action',$actions,'IN'],
                    ['ID'],
                    ['justTheQuery'=>true,'DISTINCT'=>true,'test'=>false]
                ).' UNION '.$this->SQLManager->selectFromTable(
                    $usersGroupsTable.' JOIN '.$groupsActionsTable.' ON '.$usersGroupsTable.'.Auth_Group = '.$groupsActionsTable.'.Auth_Group',
                    ['Auth_Action',$actions,'IN'],
                    [$usersGroupsTable.'.ID'],
                    ['justTheQuery'=>true,'DISTINCT'=>true,'test'=>false]
                ).')',
                'IN'
            ];

            if($res){
                $res = $this->SQLManager->updateTable(
                    $userTable,
                    ['Last_Updated = "'.time().'"'],
                    $conds,
                    ['test'=>$test,'verbose'=>$verbose]
                );
                if(!$res)
                    $this->logger->error('Could not update users after actions change',['conditions'=>$conds]);
            }
            else{
                $this->logger->error('Could not delete actions ',['actions'=>$actions]);
            }
            return $res;

        }


        /**
         * Views specific groups, and potentially their actions.
         * @param array $params Same as for getUsers
         * @return array
         *              If fetching Groups - array of relevant (in respect to filters) group names.
         *              If fetching Groups Actions - array of the form: [
         *                                                               <Group Name> => Array of Actions
         *                                                               ...
         *                                                              ]
         * @throws \Exception
         * @throws \Exception
         * @throws \Exception
         */
        function getGroups(array $params = []): array {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $safeStr = $params['safeStr'] ?? true;

            $prefix = $this->SQLManager->getSQLPrefix();
            $userTable = $prefix.'USERS_AUTH';
            $groupTable = $prefix.'GROUPS_AUTH';
            $groupsActionsTable = $prefix.'GROUPS_ACTIONS_AUTH';
            $usersGroupsTable = $prefix.'USERS_GROUPS_AUTH';

            $selectionFilter = [];

            //Default params
            $separator = $params['separator'] ?? 'AND';

            $includeActions = $params['includeActions'] ?? false;

            if(isset($params['limit']))
                $limit = min((int)$params['limit'],$this->defaultQueryLimit);
            else
                $limit = $this->defaultQueryLimit;

            if(isset($params['offset']))
                $offset = (int)$params['offset'];
            else
                $offset = null;

            //Getting actions with a limit/offset would risk getting incomplete data about group actions without knowing it
            if($includeActions){
                $limit = null;
                $offset = null;
            }

            $orderByExp = $params['orderByExp'] ?? 'Auth_Group';

            //Valid parameters - not including filter ones
            $validParams = ['id','action','group'];

            $selectionParams = ['justTheQuery'=>true,'useBrackets'=>true, 'DISTINCT'=>true,'test'=>false] ;

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
                                        $target[] = 'CSV';
                                        $cond =
                                            [
                                                [
                                                    $groupTable.'.Auth_Group',
                                                    $this->SQLManager->selectFromTable(
                                                        $userTable.' JOIN '.$usersGroupsTable.' ON '.$userTable.'.ID = '.$usersGroupsTable.'.ID',
                                                        [
                                                            $usersGroupsTable.'.ID',
                                                            $target,
                                                            '='
                                                        ],
                                                        [$usersGroupsTable.'.Auth_Group'],
                                                        $selectionParams
                                                    ),
                                                    'IN'
                                                ],
                                                'NOT'
                                            ];
                                        $selectionFilter[] = $cond;
                                        break;
                                    case 'action':
                                        $target[] = 'CSV';
                                        $cond =
                                            [
                                                [
                                                    $groupTable.'.Auth_Group',
                                                    $this->SQLManager->selectFromTable(
                                                        $groupTable.' JOIN '.$groupsActionsTable.' ON '.$groupTable.'.Auth_Group = '.$groupsActionsTable.'.Auth_Group',
                                                        [
                                                            $groupsActionsTable.'.Auth_Action',
                                                            $target,
                                                            'IN'
                                                        ],
                                                        [$groupsActionsTable.'.Auth_Group'],
                                                        $selectionParams
                                                    ),
                                                    'IN'
                                                ],
                                                'NOT'
                                            ];
                                        $selectionFilter[] = $cond;
                                        break;
                                    case 'group':
                                        $target[] = 'CSV';
                                        $cond =
                                            [
                                                [
                                                    $groupTable.'.Auth_Group',
                                                    $target,
                                                    'IN'
                                                ],
                                                'NOT'
                                            ];
                                        $selectionFilter[] = $cond;
                                        break;
                                }
                                break;
                            case 'IN':
                                switch($paramName){
                                    case 'id':
                                        $target[] = 'CSV';
                                        $cond =
                                            [
                                                $groupTable.'.Auth_Group',
                                                $this->SQLManager->selectFromTable(
                                                    $userTable.' JOIN '.$usersGroupsTable.' ON '.$userTable.'.ID = '.$usersGroupsTable.'.ID',
                                                    [
                                                        $usersGroupsTable.'.ID',
                                                        $target,
                                                        'IN'
                                                    ],
                                                    [$usersGroupsTable.'.Auth_Group'],
                                                    $selectionParams
                                                ),
                                                'IN'
                                            ];
                                        $selectionFilter[] = $cond;
                                        break;
                                    case 'action':
                                        $target[] = 'CSV';
                                        $cond =
                                            [
                                                $groupTable.'.Auth_Group',
                                                $this->SQLManager->selectFromTable(
                                                    $groupTable.' JOIN '.$groupsActionsTable.' ON '.$groupTable.'.Auth_Group = '.$groupsActionsTable.'.Auth_Group',
                                                    [
                                                        $groupsActionsTable.'.Auth_Action',
                                                        $target,
                                                        'IN'
                                                    ],
                                                    [$groupsActionsTable.'.Auth_Group'],
                                                    $selectionParams
                                                ),
                                                'IN'
                                            ];
                                        $selectionFilter[] = $cond;
                                        break;
                                    case 'group':
                                        $target[] = 'CSV';
                                        $cond =
                                            [
                                                $groupTable.'.Auth_Group',
                                                $target,
                                                'IN'
                                            ];
                                        $selectionFilter[] = $cond;
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
                                                $this->SQLManager->selectFromTable(
                                                    $userTable.' JOIN '.$usersGroupsTable.' ON '.$userTable.'.ID = '.$usersGroupsTable.'.ID',
                                                    [
                                                        $usersGroupsTable.'.ID',
                                                        $target,
                                                        '='
                                                    ],
                                                    [$usersGroupsTable.'.Auth_Group'],
                                                    $selectionParams
                                                ),
                                                'IN'
                                            ];
                                        $selectionFilter[] = $cond;
                                        break;
                                    case 'action':
                                        $cond =
                                            [
                                                $groupTable.'.Auth_Group',
                                                $this->SQLManager->selectFromTable(
                                                    $groupTable.' JOIN '.$groupsActionsTable.' ON '.$groupTable.'.Auth_Group = '.$groupsActionsTable.'.Auth_Group',
                                                    [
                                                        $groupsActionsTable.'.Auth_Action',
                                                        $target,
                                                        '='
                                                    ],
                                                    [$groupsActionsTable.'.Auth_Group'],
                                                    $selectionParams
                                                ),
                                                'IN'
                                            ];
                                        $selectionFilter[] = $cond;
                                        break;
                                    case 'group':
                                        $cond =
                                            [
                                                $groupTable.'.Auth_Group',
                                                $target,
                                                $operator
                                            ];
                                        $selectionFilter[] = $cond;
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
                $selectionFilter[] = $separator;

            if($includeActions){
                $query = 'SELECT Auth_Group, Auth_Action FROM (';
                $tables = $groupTable.' JOIN '.$groupsActionsTable.' ON '.$groupTable.'.Auth_Group = '.$groupsActionsTable.'.Auth_Group';
                $columns = [$groupTable.'.Auth_Group',$groupsActionsTable.'.Auth_Action'];
            }
            else{
                $query = 'SELECT DISTINCT Auth_Group,Description FROM '.$groupTable.' WHERE Auth_Group IN(';
                $tables = $groupTable;
                $columns = [$groupTable.'.Auth_Group'];
            }

            $query .= $this->SQLManager->selectFromTable(
                $tables,
                $selectionFilter,
                $columns,
                ['justTheQuery'=>true,'test'=>false]
            );

            $query .= ')';
            if($includeActions)
                $query .= 'as Meaningless_Alias';
            $query .= ' ORDER BY '.$orderByExp;


            $queryWithoutLimit = $query;

            if($limit != null){
                if($offset != null)
                    $query .= ' LIMIT '.$offset.','.$limit;
                else
                    $query .= ' LIMIT '.$limit;
            }

            if($verbose)
                echo 'Query to send: '.$query.EOL;
            $response = $this->SQLManager->exeQueryBindParam($query,[],['fetchAll'=>true]);

            $result = [];

            if(!is_array($response)){
                $this->logger->error('Could not load group auth ',['params'=>$params,'query'=>$query]);
                return $result;
            }

            if($includeActions){
                foreach($response as $row){

                    if(!isset($result[$row['Auth_Group']]))
                        $result[$row['Auth_Group']] = [];

                    $result[$row['Auth_Group']][] = $row['Auth_Action'];
                }
            }
            else{
                foreach($response as $row){
                    $result[$row['Auth_Group']] = [
                        'description'=> isset($row['Description'])? ( $safeStr ? \IOFrame\Util\SafeSTRFunctions::safeStr2Str($row['Description']) : $row['Description'] ) : null
                    ];
                }
            }

            //If we ar just getting IDs, we can also get the corrent number of total results
            if(!$includeActions){
                try{
                    $queryWithoutLimit = str_replace('DISTINCT Auth_Group','COUNT(DISTINCT Auth_Group)',$queryWithoutLimit);
                    $totals = $this->SQLManager->exeQueryBindParam($queryWithoutLimit,[],['fetchAll'=>true]);
                    if($totals)
                        $result['@'] = ['#'=>$totals[0][0]];
                }
                catch (\Exception $e){
                    $this->logger->error('Could not load group auth total, exception '.$e->getMessage(),['params'=>$params,'query'=>$queryWithoutLimit,'trace'=>$e->getTrace()]);
                }
            }

            return $result;
        }

        /**
         * Views all group actions (can be filtered with $params). Basically just calls getGroups with 'includeActions'
         * @param array $params same as getGroups
         *@returns array same as getGroups
         */
        function getGroupActions(array $params = []): array {
            return $this->getGroups(array_merge($params,['includeActions'=>true]));
        }

        /**
         * Create new groups, or modifies existing ones (description).
         * @param array $groups Array of the form {<Group Name> => <Description>}
         * @param array $params of the form:
         *                  'safeStr' => bool, default true - Whether to convert descriptions to safeString
         * @returns bool
         */
        function setGroups(array $groups, array $params = []): array|bool|int|string|null {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $prefix = $this->SQLManager->getSQLPrefix();
            $groupsTable = $prefix.'GROUPS_AUTH';

            $safeStr = $params['safeStr'] ?? true;

            $groupsToInsert = [];

            foreach($groups as $groupName=>$desc){
                if($desc!==null){
                    if($safeStr)
                        $desc = \IOFrame\Util\SafeSTRFunctions::str2SafeStr($desc);
                    $desc = [$desc,'STRING'];
                }
                $groupName = [$groupName,'STRING'];
                $groupsToInsert[] = [$groupName, $desc];
            }

            $res = $this->SQLManager->insertIntoTable(
                $groupsTable,
                ['Auth_Group','Description'],
                $groupsToInsert,
                ['onDuplicateKey'=>true,'test'=>$test,'verbose'=>$verbose]
            );
            if(!$res)
                $this->logger->error('Could not set auth groups',['toSet'=>$groupsToInsert]);

            return $res;
        }

        /**
         * Deletes groups
         * @param string[] $groups Group names
         * @param array $params
         * @return array|bool|int|mixed|string|string[]
         */
        function deleteGroups(array $groups, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $prefix = $this->SQLManager->getSQLPrefix();
            $userTable = $prefix.'USERS_AUTH';
            $groupsTable = $prefix.'GROUPS_AUTH';
            $usersGroupsTable = $prefix.'USERS_GROUPS_AUTH';

            foreach($groups as $k=>$groupName){
                $groups[$k] = [$groupName,'STRING'];
            }

            $res = $this->SQLManager->deleteFromTable(
                $groupsTable,
                [
                    'Auth_Group',
                    $groups,
                    'IN'
                ],
                ['test'=>$test,'verbose'=>$verbose]
            );
            if(!$res)
                $this->logger->error('Could not delete auth groups',['groups'=>$groups]);

            if($res){
                $cond = [
                    'ID',
                    $this->SQLManager->selectFromTable(
                        $usersGroupsTable,
                        ['Auth_Group',$groups,'IN'],
                        ['ID'],
                        ['justTheQuery'=>true,'useBrackets'=>true,'DISTINCT'=>true]
                    ),
                    'IN'
                ];
                $res = $this->SQLManager->updateTable(
                    $userTable,
                    ['Last_Updated = "'.time().'"'],
                    $cond,
                    ['test'=>$test,'verbose'=>$verbose]
                );

                if(!$res)
                    $this->logger->error('Could not update users after auth groups change',['groups'=>$groups,'cond'=>$cond]);
            }
            return $res;

        }

        /** Adds/Removes actions to/from a user.
         * @param int $id User ID
         * @param array $actions An array of the form:
         *                      <Action Name> => Bool true/false for set/delete. Will do both insertions and deletions.
         * @param array $params An array currently empty
         * @returns bool
         * @throws \Exception
         * @throws \Exception
         */
        function modifyUserActions(int $id, array $actions, array $params = []){
            $params['targetType'] = 'userActions';
            return $this->modifyAuth($id, $actions, $params);
        }

        /** Adds/Removes groups to/from a user.
         * @param int $id User ID
         * @param array $groups An array of the form:
         *                      <Groups Name> => Bool true/false for set/delete. Will do both insertions and deletions.
         * @param array $params
         * @return array|bool|int|mixed|string|string[]
         * @throws \Exception
         */
        function modifyUserGroups(int $id, array $groups, array $params = []){
            $params['targetType'] = 'userGroups';
            return $this->modifyAuth($id, $groups, $params);
        }

        /** Adds/Removes actions to/from a group.
         * @param string $groupName Name of the group
         * @param array $actions An array of the form:
         *                      <Action Name> => Bool true/false for set/delete. Will do both insertions and deletions.
         * @param array $params
         * @return array|bool|int|mixed|string|string[]
         * @throws \Exception
         */
        function modifyGroupActions(string $groupName,  array $actions, array $params = []){
            $params['targetType'] = 'groupActions';
            return $this->modifyAuth($groupName, $actions, $params);
        }

        /** Basically a common function for modifyGroupActions, modifyUserGroups and modifyUserActions
         */
        protected function modifyAuth($identifier, array $targets, array $params = []){

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            if(!isset($params['targetType']) ||
                ($params['targetType'] != 'userGroups' && $params['targetType'] != 'userActions' && $params['targetType'] != 'groupActions' )
            )
                throw new \Exception('Trying to modify user auth without specifying which - actions or groups!');
            else
                $targetType = $params['targetType'];

            $prefix = $this->SQLManager->getSQLPrefix();

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
                    $insertArray[] = [[$identifier, 'STRING'], [$targetName, 'STRING']];
                else
                    $deleteArray[] = [$targetTable . '.' . $targetCol, $targetName, '='];
            }
            //Insert what we need
            if($insertArray != []){
                $res = $this->SQLManager->insertIntoTable(
                    $targetTable,
                    [$idCol,$targetCol],
                    $insertArray,
                    ['onDuplicateKey'=>true,'test'=>$test,'verbose'=>$verbose]
                );

                if(!$res)
                    $this->logger->error('Could not insert new auth',['table'=>$targetTable,'id'=>[$idCol,$targetCol],'insert'=>$insertArray]);
            }

            $res = true;

            //Delete what we need
            if($deleteArray != []){
                $deleteArray[] = 'OR';

                $deleteArray = [
                    [$idCol,$identifier,'='],
                    $deleteArray,
                    'AND'
                ];

                $res = $this->SQLManager->deleteFromTable(
                    $targetTable,
                    $deleteArray,
                    ['test'=>$test,'verbose'=>$verbose]
                );

                if(!$res)
                    $this->logger->error('Could not delete auth',['table'=>$targetTable,'conditions'=>$deleteArray]);
            }

            if(!$res)
                return false;

            //If anything was changed, update user Last_Updated
            if($deleteArray != [] && $insertArray != []){

                //This happens when we update a user action/group
                if($targetType != 'groupActions'){
                    $loggingId = $identifier;
                    $res = $this->SQLManager->insertIntoTable(
                        $users,
                        ['ID', 'Last_Updated'],
                        [[$loggingId,[(string)time(),'STRING']]],
                        ['test'=>$test,'verbose'=>$verbose,'onDuplicateKey'=>true]
                    );
                }
                //This happens when we update a group
                else{
                    $loggingId = $this->SQLManager->selectFromTable(
                        $usersGroups,
                        ['Auth_Group',$identifier,'='],
                        ['ID','"'.time().'" AS Last_Updated'],
                        ['justTheQuery'=>true,'useBrackets'=>false,'DISTINCT'=>true]
                    );
                    $res = $this->SQLManager->insertIntoTable(
                        $users,
                        ['ID', 'Last_Updated'],
                        $loggingId,
                        [
                            'test'=>$test,
                            'verbose'=>$verbose,
                            'onDuplicateKey'=>true,
                            'onDuplicateKeyExp'=>'ID=VALUES(ID), Last_Updated=VALUES(Last_Updated)'
                        ]
                    );
                }

                if(!$res)
                    $this->logger->error('Could not update users last changed after auth modification',['type'=>$targetType,'id'=>$loggingId]);
            }

            return $res;
        }
    }

}