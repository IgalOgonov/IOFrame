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
         * @var string The table name - required to do db operations - used as a prefix for the full db name $tableName.'_TOKENS'.
         * Defaults to strtoupper($name)
         */
        protected $tableName = null;

        /**
         * @var string The cache name - used as a prefix for the full cache name - $cacheName.'_tokens'.
         * Defaults to strtolower($name).
         */
        protected $cacheName = null;

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
                $this->name = 'IOFRAME';

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

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;

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
                $this->SQLHandler->getSQLPrefix().$this->tableName.'_TOKENS',
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

            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;

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
                $this->SQLHandler->getSQLPrefix().$this->tableName.'_TOKENS',
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
         *              'limit' => SQL parameter LIMIT
         *              'offset'=> SQL parameter OFFSET. Only changes anything if limit is set.
         * @return array the results of the function getFromTableByKey() at abstractDB.php.
         *
         * */
        function getTokens(array $tokens ,array $params = []){
            $existingTokens = $this->getFromTableByKey($tokens, 'Token', $this->tableName.'_TOKENS', [],$params);
            if(!$existingTokens)
                $existingTokens = [];
            return $existingTokens;
        }


        /** Sets one token.
         *
         * @param string $name Up to 256 characters,
         * @param string $action Up to 1024 characters,
         * @param int $uses defaults to 1
         * @param int $ttl TTL in seconds (from creation time)
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
        function setToken(string $name,string $action,int $uses=1,int $ttl=-1,array $params = []){

            if($ttl<0)
                $ttl = $this->tokenTTL;

            return $this->setTokens(
                [
                    $name => [
                        'action' => $action,
                        'uses' => $uses,
                        'ttl' => $ttl
                    ]
                ],
                $params
            );
        }

        /** Sets tokens.
         * @param array $tokens of the form:
         *          [
         *          '<token name, string, up to 256 characters>' => [
         *                            'action' => string, up to 1024 characters,
         *                            'uses' => int, defaults to 1
         *                            'ttl' => int, TTL in seconds (from creation time)
         *                            ]
         *          ...
         *          ]
         * @param array $params Parameters of the form:
         *              'overwrite' - bool, default false - Whether to overwrite an existing token, or
         *                                    only allow creation if the same token does not exist.
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
         *
         * */
        function setTokens(array $tokens ,array $params = []){

            //Set defaults
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;
            $overwrite = isset($params['overwrite'])? $params['overwrite'] : false;

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
            foreach($existingTokens as $tokenName => $token){
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
                    //Save tokens you locked for unlock
                    else
                        array_push($tokensToUnlock,$tokenName);
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
                    $creationArray = [
                        [$tokenName,'STRING'],
                        [$tokenArray['action'],'STRING'],
                        $tokenArray['uses'],
                        [(string)(time()+$tokenArray['ttl']),'STRING'],
                    ];
                    array_push($updateParams,$creationArray);
                }
                //Update relevant objects
                $columns = ['Token','Token_Action','Uses_Left','Expires'];

                $request = $this->SQLHandler->insertIntoTable(
                    $this->SQLHandler->getSQLPrefix().$this->tableName.'_TOKENS',
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
            $test = isset($params['test'])? $params['test'] : false;
            $verbose = isset($params['verbose'])?
                $params['verbose'] : $test ? true : false;

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
                    if( ($usesLeft > 0) && $tokenArray['uses'] > 0)
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
                        $this->SQLHandler->getSQLPrefix().$this->tableName.'_TOKENS',
                        $deleteParams,
                        ['test'=>$test,'verbose'=>$verbose]
                    );

                }

                //Update relevant tokens
                if($updateParams != [] && $request){

                    $columns = ['Token','Uses_Left'];

                    $request = $this->SQLHandler->insertIntoTable(
                        $this->SQLHandler->getSQLPrefix().$this->tableName.'_TOKENS',
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