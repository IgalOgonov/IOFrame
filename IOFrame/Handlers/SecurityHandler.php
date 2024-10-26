<?php
namespace IOFrame\Handlers{
    define('IOFrameHandlersSecurityHandler',true);

    /* Means to handle general security functions related to the framework.
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */
    class SecurityHandler extends \IOFrame\Abstract\DBWithCache{


        /**
         * @var string The table name for the events table
         */
        protected string $rulebookTableName = 'EVENTS_RULEBOOK';

        /**
         * @var string The cache name for single events (type + category)
         */
        protected string $rulebookCacheName = 'ioframe_events_rulebook_event_';

        /**
         * @var array Extra columns to get/set from/to the DB (for normal resources)
         */
        protected array $extraRulebookColumns = [];

        /**
         * @var array An associative array for each extra column defining how it can be set on setResource
         *            For each column, if a matching input isn't set (or is null), it cannot be set.
         */
        protected array $extraRulebookInputs = [
            /**Each extra input is null, or an associative array of the form
             * '<column name>' => [
             *      'input' => <string, name of expected input>,
             *      'default' => <mixed, default null - default thing to be inserted into the DB on creation>,
             * ]
             */
        ];

        /**
         * @var string The table name for the events meta table
         */
        protected string $eventsMetaTableName = 'EVENTS_META';

        /**
         * @var string The cache name for single events meta (type + category)
         */
        protected string $eventsMetaCacheName = 'ioframe_events_meta_';

        /**
         * @var array Similar to $extraRulebookColumns
         */
        protected array $extraMetaColumns = [];

        /**
         * @var array Similar to $extraRulebookInputs
         */
        protected array $extraMetaInputs = [
        ];

        /** @var \IOFrame\Handlers\IPHandler $IPHandler
         */
        public \IOFrame\Handlers\IPHandler $IPHandler;

        //Default constructor
        function __construct(SettingsHandler $localSettings,  $params = []){
            parent::__construct($localSettings,array_merge($params,['logChannel'=>\IOFrame\Definitions::LOG_GENERAL_SECURITY_CHANNEL]));

            if(isset($params['rulebookTableName']))
                $this->rulebookTableName = $params['rulebookTableName'];

            if(isset($params['rulebookCacheName']))
                $this->rulebookCacheName = $params['rulebookCacheName'];

            if(isset($params['extraRulebookColumns']))
                $this->extraRulebookColumns = $params['extraRulebookColumns'];

            if(isset($params['extraRulebookInputs']))
                $this->extraRulebookInputs = $params['extraRulebookInputs'];

            if(isset($params['eventsMetaTableName']))
                $this->eventsMetaTableName = $params['eventsMetaTableName'];

            if(isset($params['eventsMetaCacheName']))
                $this->eventsMetaCacheName = $params['eventsMetaCacheName'];

            if(isset($params['extraMetaColumns']))
                $this->extraMetaColumns = $params['extraMetaColumns'];

            if(isset($params['extraMetaInputs']))
                $this->extraMetaInputs = $params['extraMetaInputs'];

            if(isset($params['siteSettings']))
                $this->siteSettings = $params['siteSettings'];
            else
                $this->siteSettings = new \IOFrame\Handlers\SettingsHandler(
                    $localSettings->getSetting('absPathToRoot').'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/siteSettings/',
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

        /** Check the session to see whether the user is banned.
         * @returns bool|string, returns false if the user isn't banned, or the timestamp of until when he is banned.
         */
        function checkBanned(){
            if (isset($_SESSION['details'])) {
                $details = json_decode($_SESSION['details'], true);
                if ($details['Banned_Until']!= null && $details['Banned_Until'] > time()) {
                    return $details['Banned_Until'];
                }
            }
            return false;
        }

        /** Commits an action by an IP to the IP_ACTIONS table.
         * @param int $eventCode The code of the action
         * @param array $params
         *
         * @return bool
         * @throws \Exception
         */
        function commitEventIP(int $eventCode, array $params = []): bool {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $weight = $params['weight'] ?? 1;
            $markOnLimit = $params['markOnLimit'] ?? true;

            if(isset($params['IP'])){
                $IP = $params['IP'];
                $fullIP = $params['fullIP'] ?? $IP;
                $isTrueIP = $params['isTrueIP'] ?? true;
            }
            else{
                $IP = $this->IPHandler->directIP;
                $fullIP = $this->IPHandler->fullIP;
                $isTrueIP = $this->IPHandler->isTrueIP;
            }
            //In case the IP is invalid, might as well return false
            if(!filter_var($IP,FILTER_VALIDATE_IP))
                return false;

            $query = 'SELECT '.$this->SQLManager->getSQLPrefix().'commitEventIP("'.$IP.'",'.(int)$eventCode.','.(bool)$isTrueIP.',"'.$fullIP.'",'.(int)$weight.','.(bool)$markOnLimit.')';

            if($verbose){
                echo 'Query to send: '.$query.EOL;
            }
            $result = $test || $this->SQLManager->exeQueryBindParam( $query );
            if(!$result)
                $this->logger->critical('failed-to-commit-ip-event',['code'=>$eventCode,'ip'=>$IP,'fullIP'=>$fullIP,'isTrueIP'=>$isTrueIP,'params'=>$params]);
            return true;
        }

        /**  Commits an action by/on a user to the USER_ACTIONS table.
         * @param int $eventCode The code of the event
         * @param int $id The user ID
         * @param array $params
         *                      'weight' => int, default 1 - how much the action is "worth" in terms of violations
         *                      'susOnLimit' => boolean, default true - whether to mark user as suspicious when action limit is reached
         *                      'banOnLimit' => boolean, default false - whether to ban user when action limit is reached
         *                      'lockOnLimit' => boolean, default false - whether to lock user when action limit is reached
         * @returns bool
         * @throws \Exception
         * @throws \Exception
         */
        function commitEventUser(int $eventCode, int $id, array $params = []): bool {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $weight = $params['weight'] ?? 1;
            $susOnLimit = $params['susOnLimit'] ?? true;
            $banOnLimit = $params['banOnLimit'] ?? false;
            $lockOnLimit = $params['lockOnLimit'] ?? false;

            $query = 'SELECT '.$this->SQLManager->getSQLPrefix().'commitEventUser('.(int)$id.','.(int)$eventCode.','.(int)$weight.','.(int)$susOnLimit.','.(int)$banOnLimit.','.(int)$lockOnLimit.')';

            if($verbose){
                echo 'Query to send: '.$query.EOL;
                return true;
            }
            $result = $test || $this->SQLManager->exeQueryBindParam(
                $query,
                [],
                ['returnError'=>$verbose]
            );
            if(!$result)
                $this->logger->critical('failed-to-commit-user-event',['code'=>$eventCode,'id'=>$id,'params'=>$params]);

            return $result;
        }


        /** Gets all the Events rulebook categories.
         * @param array $params of the form:
         *              'includeMeta'       - bool, default true - whether to get meta-information regarding various categories.
         * @returns array of ints the form:
         *          [
         *              <int, event category> => [
         *                  [includeMeta]'@' => string | null, JSON encoded meta information
         *              ],
         *              <int, event category>,
         *              ...
         *          ]
         */
        function getRulebookCategories( array $params = []): array {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $includeMeta = !isset($params['includeMeta']) || $params['includeMeta'];

            $res = $this->SQLManager->selectFromTable(
                $this->SQLManager->getSQLPrefix().$this->rulebookTableName,
                [],
                ['Event_Category'],
                array_merge($params,['DISTINCT'=>true])
            );
            if(!$res)
                return [];
            else{
                $results = array();
                $eventCategories = [];
                foreach($res as $result){
                    $results[(string)$result['Event_Category']] = [
                    ];
                    $eventCategories[] = ['category' => $result['Event_Category']];
                }
                if($includeMeta){
                    $meta = $this->getEventsMeta(
                        $eventCategories,
                        ['test'=>$test]
                    );

                    if($meta)
                        foreach($meta as $arr){
                            if(!is_array($arr))
                                continue;
                            else
                                $results[(string)$arr['Event_Category']]['@'] = $arr['Meta'];
                        }
                }
                return $results;
            }
        }


        /** Gets the whole Events rulebook.
         * @param array $params of the form:
         *              'category' - Event Category filter, defaults to null - available ones are 0 for IP, 1 for User, but others may be defined
         *              'type'     - Event Type filter, defaults to null - if set, returns events of specific type
         *                         *note: If both category and type are set, will be able to use cache for much faster results.
         *              'safeStr'           - bool, default true. Whether to convert Meta to a safe string
         *              'includeMeta'       - bool, default true - whether to get meta-information regarding various event types.
         *              'extraDBFilters'    - array, default [] - Do you want even more complex filters than the ones provided?
         *                                  This array will be merged with $extraDBConditions before the query, and passed
         *                                  to getFromCacheOrDB() as the 'extraConditions' param.
         *                                  Each condition needs to be a valid PHPQueryBuilder array.
         *              'extraCacheFilters' - array, default [] - Same as extraDBFilters but merged with $extraCacheConditions
         *                                  and passed to getFromCacheOrDB() as 'columnConditions'.
         * @returns array Object of arrays the form:
         *          [
         *              <int, event category>/<int, event type> => [
         *                  [
         *                      <int, sequence number> => [
         *                          'Blacklist_For' => <int, how many seconds the IP/User would get blacklisted for if he reaches this number of events>,
         *                          'Add_TTL' => <int, number of seconds to "remember" the IP/user event for.
         *                                      Added to any remaining time if an unexpired event of the type exists for the user/IP.>,
         *                          'Meta' => <string, JSON Encoded array - optional information about this specific sequence>
         *                      ],
         *
         *                      <int, sequence number> => [
         *                          ...
         *                      ],
         *
         *                      ...,
         *
         *                      [includeMeta]'@' => <Array akin to getEventsMeta() with the inputs being the ones we got here>
         *                  ]
         *              ],
         *              <int, event category>/<int, event type> => [
         *                  ...
         *              ]
         *          ]
         */
        function getRulebookRules( array $params = []): array {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $category = $params['category'] ?? null;
            $type = $params['type'] ?? null;
            $extraDBFilters = $params['extraDBFilters'] ?? [];
            $extraCacheFilters = $params['extraCacheFilters'] ?? [];
            $safeStr = !isset($params['safeStr']) || $params['safeStr'];
            $includeMeta = !isset($params['includeMeta']) || $params['includeMeta'];

            $retrieveParams = $params;
            $extraDBConditions = [];
            $extraCacheConditions = [];
            $retrieveParams['useCache'] = $category !== null && $type !== null;
            $retrieveParams['groupByFirstNKeys'] = 2;
            $retrieveParams['extraKeyColumns'] = ['Sequence_Number'];
            $keyCol = ['Event_Category','Event_Type'];

            $columns = array_merge(['Event_Category','Event_Type','Sequence_Number','Blacklist_For','Add_TTL','Meta'],$this->extraRulebookColumns);

            if($category!== null){
                $cond = ['Event_Category',$category,'='];
                $extraCacheConditions[] = $cond;
                $extraDBConditions[] = $cond;
            }

            if($type!== null){
                $cond = ['Event_Type',$type,'='];
                $extraCacheConditions[] = $cond;
                $extraDBConditions[] = $cond;
            }

            $extraDBConditions = array_merge($extraDBConditions,$extraDBFilters);
            $extraCacheConditions = array_merge($extraCacheConditions,$extraCacheFilters);

            if($extraCacheConditions!=[]){
                $extraCacheConditions[] = 'AND';
                $retrieveParams['columnConditions'] = $extraCacheConditions;
            }
            if($extraDBConditions!=[]){
                $extraDBConditions[] = 'AND';
                $retrieveParams['extraConditions'] = $extraDBConditions;
            }

            if(!$retrieveParams['useCache']){
                $results = [];
                $res = $this->SQLManager->selectFromTable(
                    $this->SQLManager->getSQLPrefix().$this->rulebookTableName,
                    $extraDBConditions,
                    $columns,
                    $retrieveParams
                );
                if(is_array($res)){
                    $length = -1;
                    foreach($res as $resultArray){
                        if($length == -1)
                            $length = count($resultArray);
                        for($i = 0; $i < $length/2; $i++)
                            unset($resultArray[$i]);

                        if($safeStr && $resultArray['Meta'] !== null)
                            $resultArray['Meta'] = \IOFrame\Util\SafeSTRFunctions::safeStr2Str($resultArray['Meta']);
                        if(!isset($results[$resultArray['Event_Category'].'/'.$resultArray['Event_Type']]))
                            $results[$resultArray['Event_Category'].'/'.$resultArray['Event_Type']] = [];
                        //I know this includes redundant information, but it hardly matters
                        $results[$resultArray['Event_Category'].'/'.$resultArray['Event_Type']][$resultArray['Sequence_Number']] = $resultArray;
                    }
                }
            }
            else{
                $results = $this->getFromCacheOrDB(
                    [[$category,$type]],
                    $keyCol,
                    $this->rulebookTableName,
                    $this->rulebookCacheName,
                    $columns,
                    $retrieveParams
                );
            }
            if($includeMeta){
                $eventTypes = [];
                foreach($results as $key => $sequences){
                    $keyArr = explode('/',$key);
                    if(count($keyArr) < 2)
                        continue;
                    $category = $keyArr[0];
                    $type = $keyArr[1];
                    $eventTypes[] = ['category' => $category, 'type' => $type];
                }
                $meta = $this->getEventsMeta($eventTypes);
                if($meta)
                    foreach($meta as $key => $arr){
                        if(!is_array($arr))
                            continue;
                        else
                            $results[$key]['@'] = $arr['Meta'];
                }
            }

            return $results;
        }

        /** Sets Event rules into the Events rulebook
         * @param array $inputs of arrays the form:
         *              'category' => int, Event category (required)
         *              'type'     => int, Event Type (required)
         *              'sequence' => int, Sequence number (required)
         *              'addTTL'     =>int, default 0 - For how long this action will prolong the "memory" of the current event sequence
         *              'blacklistFor' =>int, default 0 - For how long an IP/User will get blacklisted after the rule is reached (for default categories)
         *              'meta' => string, default null - JSON string that gets recursively merged with with anything that exists, just written otherwise.
         * @param array $params of the form:
         *              'override' => bool, default true, Will override existing events (defined by 'category','type' and 'sequence')
         *              'update' => bool, default false, Will only update existing events (defined by 'category','type' and 'sequence')
         *              'safeStr'           - bool, default true. Whether to convert Meta to a safe string
         * @return array of codes of the form:
         *          [
         *              <int, event category>/<int, event type>/<int, sequence number> => <int Code>
         *          ]
         *          where each code is:
         *          -1 server error (would be the same for all)
         *           0 rule changed successfully
         *           1 rule exists and 'override' is false
         *           2 rule does not exist and 'update' is true
         */
        function setRulebookRules(array $inputs, array $params = []): array {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $override = $params['override'] ?? true;
            $update = $params['update'] ?? false;
            $safeStr = !isset($params['safeStr']) || $params['safeStr'];
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            $relevantCategories = [];
            $relevantTypes = [];
            $updatedIdentifiers = [];
            $stuffToSet = [];
            $res = [];

            //Figure out which extra columns to set, and what is their input
            $extraColumns = [];
            $extraInputs = [];
            foreach($this->extraRulebookColumns as $extraColumn){
                if($this->extraRulebookInputs[$extraColumn]){
                    $extraColumns[] = $extraColumn;
                    $extraInputs[] = [
                        'input' => $this->extraRulebookInputs[$extraColumn]['input'],
                        'default' => $this->extraRulebookInputs[$extraColumn]['default'] ?? null
                    ];
                }
            }

            //See if we can at least limit our fetch to a single category or
            foreach($inputs as $inputArray){
                $relevantCategories[] = $inputArray['category'];
                $relevantTypes[] = $inputArray['type'];
                //Here, we save the NUMBER OF SEQUENCES each category/type combo has left.
                if(!isset($updatedIdentifiers[$inputArray['category'].'/'.$inputArray['type']]))
                    $updatedIdentifiers[$inputArray['category'].'/'.$inputArray['type']] = 1;
                else
                    $updatedIdentifiers[$inputArray['category'].'/'.$inputArray['type']]++;
                $res[$inputArray['category'].'/'.$inputArray['type'].'/'.$inputArray['sequence']] = -1;
            }

            if(isset($params['existing']))
                $existing = $params['existing'];
            else{
                //Who knows, we might have a single category/type and use cache.
                $category = count($relevantCategories) === 1 ? $relevantCategories[0] : null;
                $type = count($relevantTypes) === 1 ? $relevantTypes[0] : null;
                $existing = $this->getRulebookRules(array_merge($params,['category'=>$category,'type'=>$type]));
            }
            //See which rules exist
            foreach($inputs as $index => $inputArray){
                $identifier = $inputArray['category'].'/'.$inputArray['type'];
                //Either identifier AND RELEVANT SEQUENCE exist
                if(isset($existing[$identifier][$inputArray['sequence']])){

                    if(!$override){
                        $res[$inputArray['category'].'/'.$inputArray['type'].'/'.$inputArray['sequence']] = 1;
                        if($updatedIdentifiers[$inputArray['category'].'/'.$inputArray['type']] > 1)
                            $updatedIdentifiers[$inputArray['category'].'/'.$inputArray['type']]--;
                        else
                            unset($updatedIdentifiers[$inputArray['category'].'/'.$inputArray['type']]);
                        unset($inputs[$index]);
                        continue;
                    }

                    //defaults
                    if(!isset($inputArray['addTTL']))
                        $inputs[$index]['addTTL'] = $existing[$identifier][$inputArray['sequence']]['Add_TTL'];
                    //blacklistFor
                    if(!isset($inputs[$index]['blacklistFor']))
                        $inputs[$index]['blacklistFor'] = $existing[$identifier][$inputArray['sequence']]['Blacklist_For'];
                    //meta
                    if(!isset($inputs[$index]['meta']))
                        $inputs[$index]['meta'] = $existing[$identifier][$inputArray['sequence']]['Meta'];
                    else{
                        //This is where we merge the arrays as JSON if they are both valid json
                        if( \IOFrame\Util\PureUtilFunctions::is_json($inputs[$index]['meta']) &&
                            \IOFrame\Util\PureUtilFunctions::is_json($existing[$identifier][$inputArray['sequence']]['Meta'])
                        ){
                            $inputJSON = json_decode($inputs[$index]['meta'],true);
                            $existingJSON = json_decode($existing[$identifier][$inputArray['sequence']]['Meta'],true);
                            if($inputJSON == null)
                                $inputJSON = [];
                            if($existingJSON == null)
                                $existingJSON = [];
                            $inputs[$index]['meta'] =
                                json_encode(\IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct($existingJSON,$inputJSON,['deleteOnNull'=>true]));
                            if($inputs[$index]['meta'] == '[]')
                                $inputs[$index]['meta'] = null;
                        }
                        //Here we convert back to safeString
                        if($safeStr && $inputs[$index]['meta'] !== null)
                            $inputs[$index]['meta'] = \IOFrame\Util\SafeSTRFunctions::str2SafeStr($inputs[$index]['meta']);
                    }

                    $arrayToSet = [
                        $inputs[$index]['category'],
                        $inputs[$index]['type'],
                        $inputs[$index]['sequence'],
                        $inputs[$index]['blacklistFor'],
                        $inputs[$index]['addTTL'],
                        [$inputs[$index]['meta'],'STRING']
                    ];
                    foreach($extraInputs as $extraInputArr){
                        if(!isset($inputs[$index][$extraInputArr['input']]))
                            $inputs[$index][$extraInputArr['input']] = $extraInputArr['default'];
                        $arrayToSet[] = [$inputs[$index][$extraInputArr['input']], 'STRING'];
                    }
                    //Add the resource to the array to set
                    $stuffToSet[] = $arrayToSet;
                }
                //Or identifier / sequence does not exist
                else{

                    if($update){
                        $res[$inputArray['category'].'/'.$inputArray['type'].'/'.$inputArray['sequence']] = 2;
                        if($updatedIdentifiers[$inputArray['category'].'/'.$inputArray['type']] > 1)
                            $updatedIdentifiers[$inputArray['category'].'/'.$inputArray['type']]--;
                        else
                            unset($updatedIdentifiers[$inputArray['category'].'/'.$inputArray['type']]);
                        unset($inputs[$index]);
                        continue;
                    }

                    //defaults
                    //addTTL
                    if(!isset($inputArray['addTTL']))
                        $inputs[$index]['addTTL'] = 0;
                    //blacklistFor
                    if(!isset($inputs[$index]['blacklistFor']))
                        $inputs[$index]['blacklistFor'] = 0;
                    //meta
                    if(!isset($inputs[$index]['meta']))
                        $inputs[$index]['meta'] = null;
                    elseif($safeStr)
                        $inputs[$index]['meta'] = \IOFrame\Util\SafeSTRFunctions::str2SafeStr($inputs[$index]['meta']);

                    $arrayToSet = [
                        $inputs[$index]['category'],
                        $inputs[$index]['type'],
                        $inputs[$index]['sequence'],
                        $inputs[$index]['blacklistFor'],
                        $inputs[$index]['addTTL'],
                        [$inputs[$index]['meta'],'STRING']
                    ];
                    foreach($extraInputs as $extraInputArr){
                        if(!isset($inputs[$index][$extraInputArr['input']]))
                            $inputs[$index][$extraInputArr['input']] = $extraInputArr['default'];
                        $arrayToSet[] = [$inputs[$index][$extraInputArr['input']], 'STRING'];
                    }
                    //Add the resource to the array to set
                    $stuffToSet[] = $arrayToSet;
                }
            }

            //If we got nothing to set, return
            if($stuffToSet==[])
                return $res;

            $query = $this->SQLManager->insertIntoTable(
                $this->SQLManager->getSQLPrefix().$this->rulebookTableName,
                array_merge(['Event_Category','Event_Type','Sequence_Number','Blacklist_For','Add_TTL','Meta'],$extraColumns),
                $stuffToSet,
                array_merge($params,['onDuplicateKey'=>true])
            );

            //If we succeeded, set results to success and remove them from cache
            if($query){
                foreach($res as $identifier => $code){
                    if($code == -1)
                        $res[$identifier] = 0;
                }
                if($updatedIdentifiers != []){
                    $arrayOfIdentifiers = [];
                    foreach($updatedIdentifiers as $identifier => $timesAffected){
                        $arrayOfIdentifiers[] = $this->rulebookCacheName . $identifier;
                    }

                    if($verbose)
                        echo 'Deleting rulebook events '.json_encode($arrayOfIdentifiers).' from cache!'.EOL;

                    if(!$test && $useCache)
                        $this->RedisManager->call('del',[$arrayOfIdentifiers]);
                }
            }
            else
                $this->logger->error('Failed to insert rulebook events into table',['items'=>$stuffToSet]);

            return $res;
        }

        /** Deletes Event rules from the Events rulebook
         * @param array $inputs of arrays the form:
         *              'category' => int, Event category (required)
         *              'type'     => int, Event Type (required)
         *              'sequence' => int, default null, Sequence number.
         *                            If even one of those isn't set per category/type duo, deletes that whole event type.
         * @param array $params
         * @return Int codes:
         *          -1 server error (would be the same for all)
         *           0 success (does not check if items do not exist)
         */
        function deleteRulebookRules(array $inputs, array $params = []): int {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            $relevantTypes = [];
            $eventTypesToDelete = [];
            $sequencesToDelete = [];

            //See if we can at least limit our fetch to a single category or
            foreach($inputs as $inputArray){

                if(!isset($inputArray['category']) || !isset($inputArray['type']))
                    continue;

                if(isset($inputArray['sequence']))
                    $sequencesToDelete[] = [$inputArray['category'], $inputArray['type'], $inputArray['sequence']];
                else
                    $eventTypesToDelete[] = [$inputArray['category'], $inputArray['type']];

                $relevantTypes[$inputArray['category'].'/'.$inputArray['type']] = true;
            }

            //If we got nothing to set, return
            if(count($eventTypesToDelete) === 0 && count($sequencesToDelete) === 0)
                return 0;

            $conds = [];
            if(count($sequencesToDelete)){
                $sequencesToDelete[] = 'CSV';
                $conds[] = [
                    [
                        'Event_Category',
                        'Event_Type',
                        'Sequence_Number',
                        'CSV'
                    ],
                    $sequencesToDelete,
                    'IN'
                ];
            }
            if(count($eventTypesToDelete)){
                $eventTypesToDelete[] = 'CSV';
                $conds[] = [
                    [
                        'Event_Category',
                        'Event_Type',
                        'CSV'
                    ],
                    $eventTypesToDelete,
                    'IN'
                ];
            }

            if(count($conds) > 1)
                $conds[] = 'OR';

            $query = $this->SQLManager->deleteFromTable(
                $this->SQLManager->getSQLPrefix().$this->rulebookTableName,
                $conds,
                $params
            );

            //If we succeeded, set results to success and remove them from cache
            if($query){
                if(count($relevantTypes) > 0){

                    $arrayOfIdentifiers = [];

                    foreach($relevantTypes as $identifier => $yes){
                        $arrayOfIdentifiers[] = $this->rulebookCacheName . $identifier;
                    }

                    if($verbose)
                        echo 'Deleting rulebook events '.json_encode($arrayOfIdentifiers).' from cache!'.EOL;

                    if(!$test && $useCache)
                        $this->RedisManager->call('del',[$arrayOfIdentifiers]);
                }
                return 0;
            }
            else{
                $this->logger->error('Failed to delete rulebook events from table',['conditions'=>$conds]);
                return -1;
            }
        }


        /** Gets Events rulebook meta.
         * @param array $inputs of the form:
         *              [
         *                  [
         *                      'category' => int, event category to get
         *                      'type' => int, default -1 - event type to get
         *                  ],
         *                  ...
         *              ]
         * @param array $params of the form:
         *              'limit'     - int, default null -standard SQL limit
         *              'offset'    - int, default null, standard SQL offset
         *              'safeStr'           - bool, default true. Whether to convert Meta to a safe string
         * @returns array Object of arrays OR INT CODES the form:
         *          [
         *              <int, event category>[/<int, event type>] => [
         *                  'meta' => JSON string, meta information regarding the event category or type
         *              ]
         *              OR
         *              <int, event category>[/<int, event type>] =>
         *                  CODE where the possible codes are:
         *                  -1 - server error
         *                   1 - item does not exist,
         *              ... ,
         *              '@' => [
         *                  '#' => <int, number of results without limit>
         *              ]
         *          ]
         */
        function getEventsMeta(array $inputs = [], array $params = []): array {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $safeStr = !isset($params['safeStr']) || $params['safeStr'];

            $retrieveParams = $params;
            $keyCol = ['Event_Category','Event_Type'];

            $columns = array_merge(['Event_Category','Event_Type','Meta'],$this->extraMetaColumns);

            $stuffToGet = [];

            foreach($inputs as $input){
                if(!isset($input['category']))
                    continue;
                if(!isset($input['type']) || $input['type'] < 0)
                    $input['type'] = -1;
                $stuffToGet[] = [$input['category'], $input['type']];
            }

            if($inputs == []){
                $results = [];
                $res = $this->SQLManager->selectFromTable(
                    $this->SQLManager->getSQLPrefix().$this->eventsMetaTableName,
                    [],
                    $columns,
                    $params
                );
                if(is_array($res)){
                    $count = $this->SQLManager->selectFromTable(
                        $this->SQLManager->getSQLPrefix().$this->eventsMetaTableName,
                        [],
                        ['COUNT(*)'],
                        array_merge($params,['limit'=>null])
                    );

                    $resCount = isset($res[0]) ? count($res[0]) : 0;
                    foreach($res as $resultArray){
                        for($i = 0; $i<$resCount/2; $i++)
                            unset($resultArray[$i]);
                        if($safeStr && $resultArray['Meta'] !== null)
                            $resultArray['Meta'] = \IOFrame\Util\SafeSTRFunctions::safeStr2Str($resultArray['Meta']);
                        $results[$resultArray['Event_Category'].'/'.$resultArray['Event_Type']] = $resultArray;
                    }
                    $results['@'] = array('#' => $count[0][0]);
                }
            }
            else{

                $results = $this->getFromCacheOrDB(
                    $stuffToGet,
                    $keyCol,
                    $this->eventsMetaTableName,
                    $this->eventsMetaCacheName,
                    $columns,
                    $retrieveParams
                );
                if($safeStr){
                    foreach($results as $identifier => $arr)
                        if(is_array($arr))
                            $results[$identifier]['Meta'] = \IOFrame\Util\SafeSTRFunctions::safeStr2Str($arr['Meta']);
                }
            }

            return $results;

        }

        /** Sets Event rules into the Events rulebook
         * @param array $inputs of arrays the form:
         *              'category' => int, Event category (required)
         *              'type'     => int, default null - Event Type
         *              'meta' => string, default null - JSON string that gets recursively merged with with anything that exists, just written otherwise.
         * @param array $params of the form:
         *              'override' => bool, default true, Will override existing events (defined by 'category' and optionally 'type' )
         *              'update' => bool, default false, Will only update existing events (defined by 'category' and optionally 'type' )
         *              'safeStr'           - bool, default true. Whether to convert Meta to a safe string
         * @return array of codes of the form:
         *          [
         *              <int, event category>[/<int, event type>] => <int Code>
         *          ]
         *          where each code is:
         *          -1 server error (would be the same for all)
         *           0 item set successfully
         *           1 item does not exist and 'update' is true
         *           2 item exists and 'override' is false
         */
        function setEventsMeta(array $inputs, array $params = []): array {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];
            $override = $params['override'] ?? true;
            $update = $params['update'] ?? false;
            $safeStr = !isset($params['safeStr']) || $params['safeStr'];

            $relevantItems = [];
            $updatedIdentifiers = [];
            $stuffToSet = [];
            $res = [];

            //Figure out which extra columns to set, and what is their input
            $extraColumns = [];
            $extraInputs = [];
            foreach($this->extraMetaColumns as $extraColumn){
                if($this->extraMetaInputs[$extraColumn]){
                    $extraColumns[] = $extraColumn;
                    $extraInputs[] = [
                        'input' => $this->extraMetaInputs[$extraColumn]['input'],
                        'default' => $this->extraMetaInputs[$extraColumn]['default'] ?? null
                    ];
                }
            }

            //See if we can at least limit our fetch to a single category or
            foreach($inputs as $index => $inputArray){
                if(!isset($inputArray['type']) || $inputArray['type'] < 0)
                    $inputs[$index]['type'] = -1;
                $relevantItems[] = ['category' => $inputArray['category'], 'type' => $inputs[$index]['type']];
                $updatedIdentifiers[] = $inputArray['category'] . '/' . $inputs[$index]['type'];
                $res[$inputArray['category'].'/'.$inputs[$index]['type']] = -1;

            }

            $existing = $params['existing'] ?? $this->getEventsMeta($relevantItems, $params);
            //See which rules exist
            foreach($inputs as $index => $inputArray){
                $identifier = $inputArray['category'].'/'.$inputArray['type'];
                //If the meta information exists
                if(is_array($existing[$identifier])){

                    if(!$override){
                        $res[$identifier] = 2;
                        unset($updatedIdentifiers[array_search($identifier,$updatedIdentifiers)]);
                        unset($inputs[$index]);
                        continue;
                    }

                    if(!isset($inputArray['meta']))
                        $inputs[$index]['meta'] = $existing[$identifier]['Meta'];
                    else{
                        //This is where we merge the arrays as JSON if they are both valid json
                        if( \IOFrame\Util\PureUtilFunctions::is_json($inputArray['meta']) &&
                            \IOFrame\Util\PureUtilFunctions::is_json($existing[$identifier]['Meta'])
                        ){
                            $inputJSON = json_decode($inputArray['meta'],true);
                            $existingJSON = json_decode($existing[$identifier]['Meta'],true);
                            if($inputJSON == null)
                                $inputJSON = [];
                            if($existingJSON == null)
                                $existingJSON = [];
                            $inputs[$index]['meta'] =
                                json_encode(\IOFrame\Util\PureUtilFunctions::array_merge_recursive_distinct($existingJSON,$inputJSON,['deleteOnNull'=>true]));
                            if($inputs[$index]['meta'] == '[]')
                                $inputs[$index]['meta'] = null;
                        }
                        //Here we convert back to safeString
                        if($safeStr && $inputs[$index]['meta'] !== null)
                            $inputs[$index]['meta'] = \IOFrame\Util\SafeSTRFunctions::str2SafeStr($inputs[$index]['meta']);
                    }

                    $arrayToSet = [
                        $inputs[$index]['category'],
                        $inputs[$index]['type'],
                        [$inputs[$index]['meta'],'STRING']
                    ];
                    foreach($extraInputs as $extraInputArr){
                        if(!isset($inputs[$index][$extraInputArr['input']]))
                            $inputs[$index][$extraInputArr['input']] = $extraInputArr['default'];
                        $arrayToSet[] = [$inputs[$index][$extraInputArr['input']], 'STRING'];
                    }
                    //Add the resource to the array to set
                    $stuffToSet[] = $arrayToSet;
                }
                //If the meta information does not exist
                else{
                    if($existing[$identifier] === -1)
                        return $res;

                    if($update){
                        $res[$identifier] = 1;
                        unset($updatedIdentifiers[array_search($identifier,$updatedIdentifiers)]);
                        unset($inputs[$index]);
                        continue;
                    }

                    //defaults
                    //meta
                    if(!isset($inputArray['meta']))
                        $inputs[$index]['meta'] = null;
                    elseif($safeStr)
                        $inputs[$index]['meta'] = \IOFrame\Util\SafeSTRFunctions::str2SafeStr($inputArray['meta']);

                    $arrayToSet = [
                        $inputs[$index]['category'],
                        $inputs[$index]['type'],
                        [$inputs[$index]['meta'],'STRING']
                    ];
                    foreach($extraInputs as $extraInputArr){
                        if(!isset($inputs[$index][$extraInputArr['input']]))
                            $inputs[$index][$extraInputArr['input']] = $extraInputArr['default'];
                        $arrayToSet[] = [$inputs[$index][$extraInputArr['input']], 'STRING'];
                    }
                    //Add the resource to the array to set
                    $stuffToSet[] = $arrayToSet;
                }
            }

            //If we got nothing to set, return
            if($stuffToSet==[])
                return $res;

            $query = $this->SQLManager->insertIntoTable(
                $this->SQLManager->getSQLPrefix().$this->eventsMetaTableName,
                array_merge(['Event_Category','Event_Type','Meta'],$extraColumns),
                $stuffToSet,
                array_merge($params,['onDuplicateKey'=>true])
            );

            //If we succeeded, set results to success and remove them from cache
            if($query){
                foreach($res as $identifier => $code){
                    if($code == -1)
                        $res[$identifier] = 0;
                }
                if($updatedIdentifiers != []){

                    foreach($updatedIdentifiers as $index => $identifier){
                        $updatedIdentifiers[$index] = $this->eventsMetaCacheName.$identifier;
                    }

                    if($verbose)
                        echo 'Deleting rulebook meta '.json_encode($updatedIdentifiers).' from cache!'.EOL;

                    if(!$test && $useCache)
                        $this->RedisManager->call('del',[$updatedIdentifiers]);
                }
            }
            else{
                $this->logger->error('Failed to set events meta',['items'=>$stuffToSet]);
            }

            return $res;
        }

        /** Deletes Event rules from the Events rulebook
         * @param array $inputs of arrays the form:
         *              'category' => int, Event category (required)
         *              'type'     => int, default null - Event Type (required)
         * @param array $params
         * @return Int codes:
         *          -1 server error (would be the same for all)
         *           0 success (does not check if items do not exist)
         */
        function deleteEventsMeta(array $inputs, array $params = []): Int {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            $relevantTypes = [];
            $eventTypesToDelete = [];

            //See if we can at least limit our fetch to a single category or
            foreach($inputs as $index => $inputArray){

                if(!isset($inputArray['category']))
                    continue;

                if(!isset($inputArray['type']) || $inputArray['type'] < 0)
                    $inputs[$index]['type'] = -1;

                $eventTypesToDelete[] = [$inputArray['category'], $inputs[$index]['type']];

                $relevantTypes[$inputArray['category'].'/'.$inputs[$index]['type']] = true;
            }

            //If we got nothing to set, return
            if(count($eventTypesToDelete) === 0)
                return 0;

            $conds = [];
            $eventTypesToDelete[] = 'CSV';
            $conds[] = [
                [
                    'Event_Category',
                    'Event_Type',
                    'CSV'
                ],
                $eventTypesToDelete,
                'IN'
            ];

            if(count($conds) > 1)
                $conds[] = 'OR';

            $query = $this->SQLManager->deleteFromTable(
                $this->SQLManager->getSQLPrefix().$this->eventsMetaTableName,
                $conds,
                $params
            );

            //If we succeeded, set results to success and remove them from cache
            if($query){
                if(count($relevantTypes) > 0){

                    $arrayOfIdentifiers = [];

                    foreach($relevantTypes as $identifier => $yes){
                        $arrayOfIdentifiers[] = $this->eventsMetaCacheName . $identifier;
                    }

                    if($verbose)
                        echo 'Deleting rulebook events '.json_encode($arrayOfIdentifiers).' from cache!'.EOL;

                    if($useCache && !$test)
                        $this->RedisManager->call('del',[$arrayOfIdentifiers]);
                }
                return 0;
            }
            else{
                $this->logger->error('Failed to delete events meta',['conditions'=>$conds]);
                return -1;
            }
        }


    }

}