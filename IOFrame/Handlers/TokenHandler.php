<?php
namespace IOFrame\Handlers{
    use IOFrame;
    define('TokenHandler',true);

    if(!defined('abstractDBWithCache'))
        require 'abstractDBWithCache.php';

    /* Creates various tokens, to be used (once or multiple times or until expiry).
     * For example, tokens for account activation, password change, sending async email, and more.
     *
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */

    class TokenHandler extends IOFrame\abstractDBWithCache
    {
        /**
         * @var String Used for default naming of cache names, and table names
         */
        protected $name;

        /**
         * @var string The table name - required to do db operations - used as a prefix for the full db name $tableName.
         * Defaults to strtoupper($name)
         */
        protected $tableName = null;

        /**
         * @var string The cache name - used as a prefix for the full cache name - $cacheName.'_tokens'.
         * Defaults to strtolower($name).
         */
        protected $cacheName = null;

        /**
         * @var array Extra columns to get/set from/to the DB (for normal resources)
         */
        protected $extraColumns = [];

        /**
         * @var array An associative array for each extra column defining how it can be set.
         *            For each column, if a matching input isn't set (or is null), it cannot be set.
         */
        protected $extraInputs = [
            /**Each extra input is null, or an associative array of the form
             * '<column name>' => [
             *      'input' => <string, name of expected input>,
             *      'default' => <mixed, default null - default thing to be inserted into the DB on creation>,
             * ]
             */
        ];

        /**
         * @var int Default time for newly created / refreshed tokens to live, unless explicitly stated otherwise
         */
        protected $tokenTTL = 3600;

        /** Standard constructor
         *
         * @param object $settings The standard settings object
         * @param array $params - All parameters share the name/type of the class variables
         * */
        function __construct(SettingsHandler $settings, array $params = []){

            parent::__construct($settings,$params);
            if(isset($params['name']))
                $this->name = $params['name'];
            else
                $this->name = 'IOFRAME_TOKENS';

            if(isset($params['extraColumns']))
                $this->extraColumns = $params['extraColumns'];

            if(isset($params['extraInputs']))
                $this->extraInputs = $params['extraInputs'];

            if(isset($params['cacheName']))
                $this->cacheName = $params['cacheName'];
            else
                $this->cacheName = strtolower($this->name) ;

            if(isset($params['tableName']))
                $this->tableName = $params['tableName'];
            else
                $this->tableName = strtoupper($this->name) ;

            if(isset($params['tokenTTL']))
                $this->tokenTTL = $params['tokenTTL'];
            else
                if($this->siteSettings != null && $this->siteSettings->getSetting('tokenTTL'))
                    $this->tokenTTL = $this->siteSettings->getSetting('tokenTTL');
        }

        /** Tries to get a lock on tokens.
         * @param string[] $tokens Array of token identifiers
         * @param array $params
         * @return bool|string Lock used, or false if failed to reach DB
         *
         * */
        function lockTokens(array $tokens, array $params = []){

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $hex_secure = false;
            $lock = '';
            while(!$hex_secure)
                $lock=bin2hex(openssl_random_pseudo_bytes(128,$hex_secure));

            foreach($tokens as $index=>$token){
                $tokens[$index] = [
                    $token,
                    'STRING'
                ];
            }

            $res = $this->SQLHandler->updateTable(
                $this->SQLHandler->getSQLPrefix().$this->tableName,
                ['Session_Lock = "'.$lock.'", Locked_At = "'.time().'"'],
                [
                    [
                        'Token',
                        $tokens,
                        'IN'
                    ],
                    [
                        'Session_Lock',
                        'ISNULL'
                    ],
                    'AND'
                ],
                ['test'=>$test,'verbose'=>$verbose]
            );
            if(!$res){
                if($verbose)
                    echo 'Failed to lock tokens '.json_encode($tokens).'!'.EOL;
                return false;
            }
            else{
                if($verbose)
                    echo 'Tokens '.json_encode($tokens).' locked with lock '.$lock.EOL;
                return $lock;
            }
        }


        /** Unlocks tokens locked with a specific session lock.
         * @param string[] $tokens Array of token identifiers
         * @param array $params Parameters of the form:
         *              'key' - string, default null - if not NULL, will only try to unlock tokens that have a specific key.
         * @return bool true if reached DB, false if didn't reach DB
         *
         * */
        protected function unlockTokens(array $tokens, array $params = []){

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $key = $test = isset($params['key'])? $params['key'] : null;

            foreach($tokens as $index=>$token){
                $tokens[$index] = [
                    $token,
                    'STRING'
                ];
            }

            if($key === null)
                $conds = [
                    'Token',
                    $tokens,
                    'IN'
                ];
            else
                $conds = [
                    [
                        'Token',
                        $tokens,
                        'IN'
                    ],
                    [
                        'Session_Lock',
                        [$key,'STRING'],
                        '='
                    ],
                    'AND'
                ];

            $res = $this->SQLHandler->updateTable(
                $this->SQLHandler->getSQLPrefix().$this->tableName,
                ['Session_Lock = NULL, Locked_At = NULL'],
                $conds,
                ['test'=>$test,'verbose'=>$verbose]
            );

            if(!$res){
                if($verbose)
                    echo 'Failed to unlock tokens '.json_encode($tokens).'!'.EOL;
                return false;
            }
            else{
                if($verbose){
                    echo 'Tokens '.json_encode($tokens).' unlocked';
                    echo ($key)? ' with key '.$key.'!' : '!';
                    echo EOL;
                }
                return true;
            }
        }

        /** Gets a token.
         * @param string $name Up to 256 characters, token identifiers.
         * @param array $params
         *
         * @return array|int Array of the token info from db if it exists, "1" if it does not.
        */
        function getToken($name, $params){
            return $this->getTokens([$name],$params)[$name];
        }

        /** Gets tokens.
         * @param array $tokens Array of strings - token identifiers
         * @param array $params Parameters of the form:
         *              'tokenLike' - string, regex pattern, default null - if set, returns results where the token matches this pattern
         *              'actionLike' - string, regex pattern, default null - if set, returns results where the action matches this pattern
         *              'containsTags' - string[], default []] - if set, returns results that contain at least one of the tags
         *              'usesAtLeast' - int, default null - if set, returns results that have at least this many uses left
         *              'usesAtMost' - int, default null - if set, returns results that have at most this many uses left
         *              'expiresBefore' - string, unix timestamp, default null - if set, returns results that expire BEFORE specified timestamp
         *              'expiresAfter' - string, unix timestamp, default null - if set, returns results that expire AFTER specified timestamp
         *              'ignoreExpired' - bool, default true - whether to ignore expired tokens
         *              * NOTE: All of the above override relevant getFromTableByKey param "extraConditions" when passed.
         *              ----
         *              'limit' => SQL parameter LIMIT
         *              'offset'=> SQL parameter OFFSET. Only changes anything if limit is set.
         * @return array the results of the function getFromTableByKey() at abstractDB.php.
         *         Also (on success) adds an item called '@', of the form:
         *          [
         *              '#' => int, number of results without limit,
         *          ]
         *
         * */
        function getTokens(array $tokens ,array $params = []){

            $tokenLike = isset($params['tokenLike'])? $params['tokenLike'] : null;
            $actionLike = isset($params['actionLike'])? $params['actionLike'] : null;
            $containsTags = isset($params['containsTags'])? $params['containsTags'] : [];
            $usesAtLeast = isset($params['usesAtLeast'])? $params['usesAtLeast'] : null;
            $usesAtMost = isset($params['usesAtMost'])? $params['usesAtMost'] : null;
            $expiresBefore = isset($params['expiresBefore'])? $params['expiresBefore'] : null;
            $expiresAfter = isset($params['expiresAfter'])? $params['expiresAfter'] : null;
            $ignoreExpired = isset($params['ignoreExpired'])? $params['ignoreExpired'] : true;

            $extraConditions = isset($params['extraConditions'])? $params['extraConditions'] : [];

            if($tokenLike!== null)
                array_push($extraConditions,['Token',[$tokenLike,'STRING'],'RLIKE']);
            if($actionLike!== null)
                array_push($extraConditions,['Token_Action',[$actionLike,'STRING'],'RLIKE']);
            if($usesAtLeast!== null)
                array_push($extraConditions,['Uses_Left',$usesAtLeast,'>=']);
            if($usesAtMost!== null)
                array_push($extraConditions,['Uses_Left',$usesAtMost,'<=']);
            if($expiresBefore!== null)
                array_push($extraConditions,['Expires',$expiresBefore,'<']);
            if($expiresAfter!== null)
                array_push($extraConditions,['Expires',$expiresAfter,'>']);
            if($ignoreExpired)
                array_push($extraConditions,['Expires',time(),'>']);

            if(count($containsTags)){
                $regexArr = [];
                foreach ($containsTags as $tag)
                    array_push($regexArr,'(#'.$tag.'($|#))');
                array_push($extraConditions,['Tags',[implode('|',$regexArr),'STRING'],'RLIKE']);
            }

            if(count($extraConditions) > 0)
                array_push($extraConditions,'AND');

            $params['extraConditions'] = $extraConditions;

            $existingTokens = $this->getFromTableByKey($tokens, 'Token', $this->tableName, [],$params);
            if(!$existingTokens)
                $existingTokens = [];
            elseif(count($tokens) == 0){
                $count = $this->SQLHandler->selectFromTable(
                    $this->SQLHandler->getSQLPrefix().$this->tableName,
                    $extraConditions,
                    ['COUNT(*)'],
                    array_merge(
                        $params,
                        [
                            'limit'=>null,
                        ]
                    )
                );
                if($count)
                    $existingTokens['@'] =['#' => $count[0][0]];
            }
            return $existingTokens;
        }


        /** Sets one token.
         *
         * @param string $name Up to 256 characters - CANNOT be '@',
         * @param string $action Up to 1024 characters,
         * @param int $uses defaults to 1
         * @param int $ttl TTL in seconds (from creation time)
         * @param array $tags Tags you wish to set (overrides old ones!)
         * @param array $params Parameters of the form:
         *              'overwrite' - bool, default false - Whether to overwrite an existing token, or
         *                                    only allow creation if the same token does not exist.
         * @return int possible codes:
         *         -2 - token already exists, overwrite is true, but the token is locked
         *         -1 - could not reach db
         *          0 - success
         *          1 - token already exists and overwrite is false
         *
        */
        function setToken(string $name,string $action = null,int $uses=null,int $ttl=null,array $tags = null,array $params = []){

            if($ttl<0)
                $ttl = $this->tokenTTL;

            return $this->setTokens(
                [
                    $name => [
                        'action' => $action,
                        'uses' => $uses,
                        'tags' => $tags,
                        'ttl' => $ttl
                    ]
                ],
                $params
            )[$name];
        }

        /** Sets tokens.
         * @param array $tokens of the form:
         *          [
         *          '<token name, string, up to 256 characters>' => [
         *                            'action' => string, up to 1024 characters,
         *                            'uses' => int, defaults to 1,
         *                            'tags' => string[], tags yu wish to set (overrides old ones!)
         *                            'ttl' => int, TTL in seconds (from creation time)
         *                            ]
         *          ...
         *          ]
         * @param array $params Parameters of the form:
         *              'overwrite' - bool, default true - If true, may overwrite existing items
         *              'update' - bool, default false - If true, only updates existing items
         * @return array of the form:
         *          [
         *          '<token name>' => code,
         *          ...
         *          ]
         *          where possible codes are:
         *         -2 - token already exists, overwrite is true, but the token is locked
         *         -1 - could not reach db
         *          0 - success
         *          1 - token already exists and overwrite is false
         *          2 - token doesn't exist and update is true
         *          3 - "action" was not passed, and token did not previously exist
         *
         * */
        function setTokens(array $tokens ,array $params = []){

            if(isset($tokens['@']))
                unset($tokens['@']);

            //Set defaults
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $overwrite = isset($params['overwrite'])? $params['overwrite'] : true;
            $update = isset($params['update'])? $params['update'] : false;

            $tokenNames = [];
            $res = [];

            //Figure out which extra columns to set, and what is their input
            $extraColumns = [];
            $extraInputs = [];
            foreach($this->extraColumns as $index => $extraColumn){
                if($this->extraInputs[$extraColumn]){
                    array_push($extraColumns,$extraColumn);
                    array_push($extraInputs,[
                        'input'=>$this->extraInputs[$extraColumn]['input'],
                        'default'=>isset($this->extraInputs[$extraColumn]['default'])?$this->extraInputs[$extraColumn]['default']:null
                    ]);
                }
            }

            //Assume we couldn't reach the DB until proven otherwise. Also, create a token name array
            foreach($tokens as $tokenName => $tokenArray){
                $res[$tokenName] = -1;
                array_push($tokenNames,$tokenName);
            }

            //Try to lock existing tokens (assuming they exist) and get them
            $key = $this->lockTokens($tokenNames,$params);

            //If we failed to reach the db, return -1 for all tokens
            if(!$key){
                return $res;
            }

            $existingTokens = $this->getTokens($tokenNames,array_merge($params,['ignoreExpired'=>false,'limit'=>0]));
            $tokensToUnlock = [];
            foreach($existingTokens as $tokenName => $token){
                if($tokenName === '@')
                    continue;
                if( is_array($token)){
                    //For existing tokens, check whether overwrite is false
                    if(!$overwrite){
                        $res[$tokenName] = 1;
                        unset($tokens[$tokenName]);
                    }
                    //Check whether overwrite is false, but they are locked with a different session - ignore in tst mode
                    if($token['Session_Lock']!=$key && !$test){
                        //If we are overwriting, we stil cannot overwrite a locked token
                        if($overwrite)
                            $res[$tokenName] = -2;
                        unset($tokens[$tokenName]);
                    }
                    else{
                        //Save tokens you locked for unlock
                        array_push($tokensToUnlock,$tokenName);
                        //Complete missing info
                        if(!isset($tokens[$tokenName]['uses']))
                            $tokens[$tokenName]['uses'] = $token['Uses_Left'];
                        if(!isset($tokens[$tokenName]['action']))
                            $tokens[$tokenName]['action'] = $token['Token_Action'];
                        if(!isset($tokens[$tokenName]['tags']))
                            $tokens[$tokenName]['tags'] = !$token['Tags']? null : explode('#',substr($token['Tags'],1));
                    }
                }
                else{
                    if($update){
                        $res[$tokenName] = 2;
                        unset($tokens[$tokenName]);
                    }
                    elseif(!isset($tokens[$tokenName]['action']) || !$tokens[$tokenName]['action']){
                        $res[$tokenName] = 3;
                        unset($tokens[$tokenName]);
                    }
                }
            }

            //Only continue if there are valid tokens to create
            if(count($tokens) !== 0){
                //Create all valid tokens
                $updateParams =[];
                foreach($tokens as  $tokenName => $tokenArray){
                    if(!isset($tokenArray['uses']))
                        $tokenArray['uses'] = 1;
                    if(!isset($tokenArray['ttl']) || $tokenArray['ttl']<0)
                        $tokenArray['ttl'] = $this->tokenTTL;

                    if(!isset($tokenArray['tags']))
                        $tokenArray['tags'] = null;
                    else{
                        $newTags = '';
                        foreach ($tokenArray['tags'] as $tag)
                            $newTags .= '#'.$tag;
                        $tokenArray['tags'] = $newTags;
                    }

                    $creationArray = [
                        [$tokenName,'STRING'],
                        [$tokenArray['action'],'STRING'],
                        $tokenArray['uses'],
                        [(string)(time()+$tokenArray['ttl']),'STRING'],
                        [$tokenArray['tags'],'STRING']
                    ];

                    foreach($extraInputs as $extraInputArr){
                        if(!isset($tokens[$tokenName][$extraInputArr['input']]))
                            $tokens[$tokenName][$extraInputArr['input']] = $extraInputArr['default'];
                        array_push($creationArray,[$tokens[$tokenName][$extraInputArr['input']],'STRING']);
                    }

                    array_push($updateParams,$creationArray);
                }
                //Update relevant objects
                $columns = array_merge(['Token','Token_Action','Uses_Left','Expires','Tags'],$extraColumns);

                $request = $this->SQLHandler->insertIntoTable(
                    $this->SQLHandler->getSQLPrefix().$this->tableName,
                    $columns,
                    $updateParams,
                    ['test'=>$test,'verbose'=>$verbose,'onDuplicateKey'=>true]
                );

                if($request)
                    foreach($tokens as $tokenName => $tokenArray){
                        if($res[$tokenName] === -1)
                            $res[$tokenName] = 0;
                    }
            }

            //Unlocked the tokens we locked
            if(count($tokensToUnlock) > 0)
                $this->unlockTokens($tokensToUnlock,$params);

            return $res;
        }

        /** Deletes tokens
         * @param string[] $tokens Tokens to delete
         * @param array $params
         * @returns int Code of the form:
         *         -1 - Failed to connect to db
         *          0 - All good
         */
        function deleteTokens(array $tokens, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $itemsToDelete = [];

            if($tokens == []){
                if($verbose)
                    echo 'Nothing to delete, exiting!'.EOL;
                return 0;
            }
            else{
                foreach($tokens as $token){
                    array_push($itemsToDelete,[$token,'STRING']);
                }
            }

            $result = $this->SQLHandler->deleteFromTable(
                $this->SQLHandler->getSQLPrefix().$this->tableName,
                [
                    'Token',
                    $itemsToDelete,
                    'IN'
                ],
                $params
            );

            return $result === false? -1 : 0;
        }

        /** Deletes all expired tokens
         * @param array $params
         *              'time' => int, default time() - expiry time
         * @returns int Code of the form:
         *         -1 - Failed to connect to db
         *          0 - All good
         */
        function deleteExpiredTokens(array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $time = isset($params['time'])? $params['time'] : time();

            $result = $this->SQLHandler->deleteFromTable(
                $this->SQLHandler->getSQLPrefix().$this->tableName,
                [
                    'Expires',
                    $time,
                    '<'
                ],
                $params
            );

            return $result === false? -1 : 0;
        }

        /** Consumes a specific number of uses for a single token.
         *
         * @param string $name Up to 256 characters, token name.
         * @param string $expectedAction Defaults to ''. If not empty, will only consume a token where the action
         *                                    matches the regex pattern provided (it can just be the exact action expected).
         * @param int $uses defaults to 1 - if negative, will delete the token.
         * @param array $params
         *
         * @return int codes:
         *         -2 - token exists, but is locked
         *         -1 - could not reach db
         *          0 - success
         *          1 - token does not exist or action invalid
         *          2 - token does not have enough uses left to consume
         *          3 - token expired
         */
        function consumeToken(string $name, int $uses = 1, string $expectedAction = '', array $params = []){
            return $this->consumeTokens([$name=>['uses'=>$uses,'action'=>$expectedAction]], $params)[$name];
        }

        /** Consumes a number of uses left for tokens, then deletes each of them that has 0 uses left.
         * @param string[] $tokens Array of token identifiers with how many uses to consume, of the form:
         *          [
         *          '<token name>' => [
         *                              'uses' => <number of uses to consume, default 1>,
         *                              'action'=> <Regex pattern that the action needs to match for the token
         *                                               to be consumed, defaults to ''>
         *                            ],
         *          '<token name>' => [],
         *          ...
         *          ]
         *          if the number of uses to consume is negative, always deletes the token.
         * @param array $params
         * @return array of the form:
         *          [
         *          '<token name>' => code,
         *          ...
         *          ]
         *          where possible codes are:
         *         -2 - token exists, but is locked
         *         -1 - could not reach db
         *          0 - success
         *          1 - token does not exist or action invalid
         *          2 - token does not have enough uses left to consume
         *          3 - token expired
         * */
        function consumeTokens(array $tokens ,array $params = []){

            //Set defaults
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $tokenNames = [];
            $res = [];

            //Assume we couldn't reach the DB until proven otherwise. Also, create a token name array
            foreach($tokens as $tokenName => $tokenArray){
                $res[$tokenName] = -1;
                array_push($tokenNames,$tokenName);
            }

            //Try to lock existing tokens (assuming they exist) and get them
            $key = $this->lockTokens($tokenNames,$params);

            //If we failed to reach the db, return -1 for all tokens
            if(!$key){
                return $res;
            }

            $existingTokens = $this->getTokens($tokenNames,$params);
            $tokensToUnlock = [];
            foreach($tokens as $tokenName => $tokenArray){
                //Set default uses
                if(!isset($tokens[$tokenName]['uses']))
                    $tokens[$tokenName]['uses'] = 1;
                if(!isset($tokens[$tokenName]['action']))
                    $tokens[$tokenName]['action'] = 1;

                //This means the token exists
                if( is_array($existingTokens[$tokenName])){
                    //Check whether overwrite is false, but they are locked with a different session - ignore in tst mode
                    if($existingTokens[$tokenName]['Session_Lock']!=$key && !$test){
                        $res[$tokenName] = -2;
                        unset($tokens[$tokenName]);
                    }
                    //Save tokens you locked for unlock
                    else{
                        array_push($tokensToUnlock,$tokenName);
                        //Check that the token has enough uses to be consumed
                        if($existingTokens[$tokenName]['Uses_Left'] - $tokens[$tokenName]['uses'] < 0){
                            $res[$tokenName] = 2;
                            unset($tokens[$tokenName]);
                        }
                        //Check that the token has the expected action
                        elseif(
                            $tokens[$tokenName]['action'] !== '' &&
                            !preg_match('/'.$tokens[$tokenName]['action'].'/',$existingTokens[$tokenName]['Token_Action'])
                        ){
                            $res[$tokenName] = 1;
                            unset($tokens[$tokenName]);
                        }
                        //Make sure the token has not yet expired
                        elseif($existingTokens[$tokenName]['Expires'] < time()){
                            $res[$tokenName] = 3;
                            unset($tokens[$tokenName]);
                        }
                    }
                }
                //This means the token does not exist
                else{
                    $res[$tokenName] = 1;
                    unset($tokens[$tokenName]);
                }
            }

            //Only continue if there are valid tokens to consume
            if(count($tokens) !== 0){
                //Consume the uses of all existing tokens.
                $updateParams =[];
                $deleteParams =[];
                foreach($tokens as  $tokenName => $tokenArray){
                    $usesLeft = $existingTokens[$tokenName]['Uses_Left'] - $tokenArray['uses'];
                    //Update the tokens that'll have some uses left
                    if( ($usesLeft > 0) && ($tokenArray['uses'] > -1) )
                        array_push($updateParams,[[$tokenName,'STRING'],$usesLeft]);
                    //Delete the tokens that will have no uses left
                    else
                        array_push($deleteParams,[$tokenName,'STRING']);
                }

                $request = true;

                //Delete relevant tokens
                if($deleteParams != []){

                    $deleteParams = [
                        'Token',
                        $deleteParams,
                        'IN'
                    ];

                    $request = $this->SQLHandler->deleteFromTable(
                        $this->SQLHandler->getSQLPrefix().$this->tableName,
                        $deleteParams,
                        ['test'=>$test,'verbose'=>$verbose]
                    );

                }

                //Update relevant tokens
                if($updateParams != [] && $request){

                    $columns = ['Token','Uses_Left'];

                    $request = $this->SQLHandler->insertIntoTable(
                        $this->SQLHandler->getSQLPrefix().$this->tableName,
                        $columns,
                        $updateParams,
                        ['test'=>$test,'verbose'=>$verbose,'onDuplicateKey'=>true]
                    );

                }

                if($request)
                    foreach($tokens as $tokenName => $tokenArray){
                        if($res[$tokenName] === -1)
                            $res[$tokenName] = 0;
                    }
            }

            //Unlocked the tokens we locked
            if(count($tokensToUnlock) > 0)
                $this->unlockTokens($tokensToUnlock,$params);

            return $res;
        }

    }
}