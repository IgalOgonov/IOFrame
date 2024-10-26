<?php
namespace IOFrame\Handlers{
    define('IOFrameHandlersIPHandler',true);

    /** This handler is used in order to manage the IP Blacklist/Whitelist.
     * Lets say, for examples in this comment, our example IP is 102.43.196.23
     *
     * Basic functionality includes checking the DB table ipv4_range for 3 possible prefixes of the example IP
     * (aka "102", "102.43", "102.43.196"), and for a hit, checking if the next section of the user's IP matches the range
     * after the prefix (eg, if there is an entry in the list with prefix "102.43", range 132-197, it's a hit). Note that is
     * requires going over all the IP_Ranges rows, so it's best to keep that table as short as possible, even if the
     * range is moved to an in-memory cache.
     *
     * Another core function is checking whether the users IP is in the IP_List. There it is much simpler, as you need to
     * match each section of the user IP with the database, requiring fewer resources.
     *
     * If an IP is specified in the List, that value (IP_Type) will always win over the one in the Range List.
     *
     * If an IP is specified in more than one overlapping Range, the smallest range wins
     * (eg 127.25-100 wins over 126-127, and 127.10-20 wins over 127.0-255)
     *
     * If an IP is specified in more than one similar Range, the one starting at a lower IP wins
     * (eg 127.25-50 wins over 127.26-51)
     *
     * Beside queries and management related to those 2 tables (IP_List, IP_Range), this module does nothing for now.
     *
     * Notice that this module is NOT to be used for the whole site, but for specific areas like login/registration APIs.
     * To protect more frequently accessed pages (like static pages, etc) against bots/DDoS/Bruteforcing,
     * faster tools at the server/network layer exist.
     *
     * TODO - Add IPV6 Range support
     * TODO - Create integration with a faster tool when one becomes available
     * TODO - Rewrite part of the IP check function as a C++ extension and add support for it (yeah, not happening)
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */

    class IPHandler extends \IOFrame\Abstract\DBWithCache
    {

        /** @var bool $useCache Specifies whether we should be using cache */
        protected bool $useCache = false;

        /** @var bool $isTrueIP Whether the IP might be spoofed, or at the least the endpoint is controlled by the client.
         * */
        public bool $isTrueIP;

        /** @var string $directIP IP that is the direct client IP, or is only behind the expectedProxy (local setting)
         * */
        public string $directIP;

        /** @var string $fullIP Full client IP
         * */
        public string $fullIP;

        /** @var bool $checkRangeByDefault Whether to check range by default. Is true only if the relevant setting exists.
         * */
        public bool $checkRangeByDefault = false;

        function __construct(SettingsHandler $localSettings, $params = []){
            parent::__construct($localSettings,array_merge($params,['logChannel'=>\IOFrame\Definitions::LOG_GENERAL_SECURITY_CHANNEL]));

            if(isset($params['siteSettings']))
                $this->siteSettings = $params['siteSettings'];
            else
                $this->siteSettings = new \IOFrame\Handlers\SettingsHandler(
                    $localSettings->getSetting('absPathToRoot').'/'.\IOFrame\Handlers\SettingsHandler::SETTINGS_DIR_FROM_ROOT.'/siteSettings/',
                    $this->defaultSettingsParams
                );

            if($this->siteSettings->getSetting('checkRangeByDefault') !== null)
                $this->checkRangeByDefault = $this->siteSettings->getSetting('checkRangeByDefault');

            //Extracts the IP from the client
            $res = $this->extractClientIP();
            $this->isTrueIP = $res['isTrueIP'];
            $this->directIP = $res['directIP'];
            $this->fullIP = $res['fullIP'];
        }

        /**
         * Tries to determine the "real" client IP
         * @param string[] $trustedProxies An array of trusted proxies BEHIND expectedProxy.
         *                              Each member of the array is a string that's an IP address
         * @returns array
         *          'directIP'  => The resulting "direct" client IP (the first one we assume is under his control)
         *          'fullIP'    => The full CSV list of IPs - INCLUDING the expectedProxy
         *          'isTrueIP' =>full IP array, and whether we may be sure
        */
        function extractClientIP(array $trustedProxies = []): array {
            $directIP = '';
            $fullIP = '';

            //Obviously in this case, we can do nothing
            if (!isset ($_SERVER['REMOTE_ADDR'])) {
                return ['directIP' =>$directIP, 'fullIP'=>$fullIP, 'isTrueIP'=> false];
            }

            //Get the expected proxy IP list
            $expectedProxyList = $this->settings->getSetting('expectedProxy');
            if($expectedProxyList == null)
                $expectedProxyList = '';
            else{
                $expectedProxyList = preg_replace('/\s+/', '', $expectedProxyList);
            }

            //First, get the full IP
            if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
                $fullIP .= $_SERVER["HTTP_X_FORWARDED_FOR"];
            }
            elseif(!empty($_SERVER["HTTP_CLIENT_IP"])){
                $fullIP .= $_SERVER["HTTP_CLIENT_IP"];
            }
            if($fullIP !== '')
                $fullIP.= ',';
            $fullIP .= $_SERVER["REMOTE_ADDR"];
            $fullIP = substr($fullIP, 0, 10000);
            $fullIP = preg_replace('/\s+/', '', $fullIP);

            //Remove the expected proxy IPs
            if($expectedProxyList !== ''){
                $offset = strlen($fullIP)-strlen($expectedProxyList);
                if(@strpos($fullIP,$expectedProxyList,$offset))
                    $fullIP = substr($fullIP,0,strpos($fullIP,$expectedProxyList,$offset)-1);
            }

            //Just in case we've reached the true user IP
            if(filter_var($fullIP, FILTER_VALIDATE_IP)){
                $directIP = $fullIP;
                return ['directIP' =>$directIP, 'fullIP'=>$fullIP, 'isTrueIP'=> true];
            }

            //Get the last IP in the IP chain AFTER you cut trusted proxies
            $IPArray = explode(',',$fullIP);
            for($i = count($IPArray)-1; $i>0; $i--){
                //The first IP that isn't a trusted proxy has to be assumed under the control of the client
                if(in_array($IPArray[$i],$trustedProxies))
                    unset($IPArray[$i]);
                else
                    break;
            }

            //This IP is 100% under the client's control. Anything else can't be relied on.
            $directIP = $IPArray[count($IPArray)-1];

            return ['directIP' =>$directIP, 'fullIP'=>$fullIP, 'isTrueIP'=>count($IPArray) == 1];
        }

        /**
         * Returns whether an IP is whitelisted or blacklisted.
         * @param array $params of the form 'ip' => string representing user IP. Defaults to $this->directIP
         *                                  'checkRange' => whether to check range list too. Defaults to false
         *                                  'blacklisted' => Whether to check for blacklisted (default) or whitelisted IPs.
         * @returns bool|null
         */
        function checkIP(array $params = []): ?bool {
            //set defaults

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            $ip = $params['ip'] ?? $this->directIP;

            $checkRange = $params['checkRange'] ?? $this->checkRangeByDefault;

            $blacklisted = $params['blacklisted'] ?? true;


            //First, check the cache for the IP if we're using cache
            if($useCache){

                $cachedRes = $this->RedisManager->call('get','_IP_'.$ip);

                //Build a 2x2 truth table to understand this return statement -
                //$cachedRes is 1 if the IP is whitelisted, 0 if blacklisted, false if IP isn't in cache
                if($cachedRes !== false)
                    return ($cachedRes==1) xor $blacklisted;
            }

            //Table names
            $t_list = $this->SQLManager->getSQLPrefix()."IP_LIST";
            $t_range = $this->SQLManager->getSQLPrefix()."IPV4_RANGE";

            //Check whether we are given a valid IPV4
            if(preg_match('/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/',$ip))
                $ipArray = explode('.',$ip);
            else
                $ipArray = null;
            if($ipArray!=null)
                $prefix = implode('.',[$ipArray[0],$ipArray[1],$ipArray[2]]);
            else
                $prefix = 0;

            //The check query depends on whether we're checking the range table or not
            $query = $this->SQLManager->selectFromTable(
                $t_list,
                [
                    ['IP',$ip,'='],
                    [['Expires',time(),'>='],['Expires',0,'='],'OR'],
                    'AND'
                ],
                ['IP_Type','Expires','0 as Level','0 as IP_Range','"'.$prefix.'" as IP_Range_Start'],
                ['justTheQuery'=>true,'test'=>false]
            );
            if($checkRange){
                //If we were given an IPV4 address, we may query the range table
                if($ipArray!=null)
                    for($i=0; $i<4; $i++){

                        switch($i){
                            case 0:
                                $prefix = '';
                                $suffix = (int)$ipArray[0];
                                break;
                            case 1:
                                $prefix = $ipArray[0];
                                $suffix = (int)$ipArray[1];
                                break;
                            case 2:
                                $prefix = $ipArray[0].'.'.$ipArray[1];
                                $suffix = (int)$ipArray[2];
                                break;
                            default:
                                $prefix = $ipArray[0].'.'.$ipArray[1].'.'.$ipArray[2];
                                $suffix = (int)$ipArray[3];
                                break;
                        }

                        $query .= ' UNION '.$this->SQLManager->selectFromTable(
                                $t_range,
                                [
                                    ['Prefix',[$prefix,'STRING'],'='],
                                    ['IP_From',$suffix,'<='],
                                    ['IP_To',$suffix,'>='],
                                    [['Expires',time(),'>='],['Expires',0,'='],'OR'],
                                    'AND'
                                ],
                                ['IP_Type','Expires',(4-$i).' as Level',$t_range.'.IP_To - '.$t_range.'.IP_From as IP_Range', 'IP_From as IP_Range_Start'],
                                ['justTheQuery'=>true,'test'=>false]
                            );
                    }
            }

            if($verbose)
                echo 'Query to send: '.$query.EOL;

            $resArray = [];

            try {
                $res = $this->SQLManager->exeQueryBindParam($query,[],['fetchAll'=>true]);
            }
            catch (\Exception $e){
                $this->logger->error('Could not get IPs from table, exception '.$e->getMessage(),['query'=>$query,'trace'=>$e->getTrace()]);
                return null;
            }
            if(!is_array($res)){
                $this->logger->error('Could not get IPs from table',['query'=>$query]);
                return null;
            }

            foreach($res as $k=>$v){
                //For $verbose convenience
                if($verbose)
                    for($i=0;$i<5;$i++)
                        unset($res[$k][$i]);

                $res[$k]['IP_Type'] = (int)$res[$k]['IP_Type'];

                //If the relevant level is unset, set it
                if(!isset($resArray[$res[$k]['Level']]))
                    $resArray[$res[$k]['Level']] =
                        [
                            'type'=>$res[$k]['IP_Type'],
                            'range'=>$res[$k]['IP_Range'],
                            'start'=>$res[$k]['IP_Range_Start'],
                            'expires'=>$res[$k]['Expires']
                        ];
                //Else check if the current result "wins" over the previously set one
                else{
                    //Lower range wins
                    if($resArray[$res[$k]['Level']]['range']!=$res[$k]['IP_Range']){
                        if($resArray[$res[$k]['Level']]['range']>$res[$k]['IP_Range'])
                            $resArray[$res[$k]['Level']] =
                                [
                                    'type'=>$res[$k]['IP_Type'],
                                    'range'=>$res[$k]['IP_Range'],
                                    'start'=>$res[$k]['IP_Range_Start'],
                                    'expires'=>$res[$k]['Expires']
                                ];
                    }
                    //..if equal, lower starting IP wins
                    else{
                        if($resArray[$res[$k]['Level']]['start']>$res[$k]['IP_Range_Start'])
                            $resArray[$res[$k]['Level']] =
                                [
                                    'type'=>$res[$k]['IP_Type'],
                                    'range'=>$res[$k]['IP_Range'],
                                    'start'=>$res[$k]['IP_Range_Start'],
                                    'expires'=>$res[$k]['Expires']
                                ];
                    }
                }
            }

            if($verbose){
                echo 'Hits: '.json_encode($res).EOL;
                echo 'Final Results: '.json_encode($resArray).EOL;
            }

            for($i=0; $i<5; $i++){
                if(isset($resArray[$i])){
                    //If the level is 0 (aka we got it from the IP list), and it's not in the cache, put it into the cache
                    if($i==0 && $useCache){
                        if(!$test)
                            $this->RedisManager->call('setEx',['_IP_'.$ip,$resArray[$i]['expires']-time(),$resArray[$i]['type']]);
                        if($verbose)
                            echo 'Setting cache '.'_IP_'.$ip.' to '.$resArray[$i]['type'].' for '.($resArray[$i]['expires']-time()).EOL;
                    }
                    //Build a 2x2 truth table to understand this return statement
                    return ($resArray[$i]['type']==1) xor $blacklisted;
                }
            }

            return false;
        }


        /**  Gets all - or some - IPs (not ranges!)
         * @param array $inputs, default [], of the form:
         *              [
         *                  <string, valid IP>,
         *                  ...
         *              ]
         * @param array $params of the form:
         *              'reliable'     - bool, default null - if set, only returns results which are or aren't reliable
         *              'type'    - bool, default null - if set, only returns results which are blacklisted (false) or whitelisted (true)
         *              'ignoreExpired' - bool, default true. Whether to ignore expired results
         *              'safeStr' - bool, default true. Whether to convert Meta to a safe string
         *              -- if $inputs are not set --
         *              'limit'     - int, default null -standard SQL limit
         *              'offset'    - int, default null, standard SQL offset
         * @returns array Object of arrays OR INT CODES the form:
         *          [
         *              <string, the IP> => Array of DB columns
         *              OR
         *              <string, the IP> =>
         *                  CODE where the possible codes are:
         *                  -1 - server error
         *                   1 - item does not exist,
         *              ... ,
         *              '@' => [
         *                  '#' => <int, number of results without limit>
         *              ]
         *          ]
         */
        function getIPs(array $inputs = [], array $params = []): array {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $reliable = $params['reliable'] ?? null;
            $type = $params['type'] ?? null;
            $ignoreExpired = !isset($params['ignoreExpired']) || $params['ignoreExpired'];
            $safeStr = !isset($params['safeStr']) || $params['safeStr'];
            $limit = $params['limit'] ?? null;
            $offset = $params['offset'] ?? null;

            $retrieveParams = array_merge($params,['limit'=>$limit,'offset'=>$offset]);
            $extraDBConditions = [];
            $extraCacheConditions = [];
            $keyCol = ['IP'];

            $columns = array_merge(['IP','Is_Reliable','IP_Type','Expires','Meta']);


            if($reliable!== null){
                $cond = ['Is_Reliable',$reliable,'='];
                $extraCacheConditions[] = $cond;
                $extraDBConditions[] = $cond;
            }
            if($type!== null){
                $cond = ['IP_Type',$type,'='];
                $extraCacheConditions[] = $cond;
                $extraDBConditions[] = $cond;
            }
            if($ignoreExpired){
                $cond = ['Expires',time(),'>'];
                $extraCacheConditions[] = $cond;
                $extraDBConditions[] = $cond;
            }

            if($extraCacheConditions!=[]){
                $extraCacheConditions[] = 'AND';
                $retrieveParams['columnConditions'] = $extraCacheConditions;
            }
            if($extraDBConditions!=[]){
                $extraDBConditions[] = 'AND';
                $retrieveParams['extraConditions'] = $extraDBConditions;
            }

            if($inputs == []){
                $results = [];

                $res = $this->SQLManager->selectFromTable(
                    $this->SQLManager->getSQLPrefix().'IP_LIST',
                    $extraDBConditions,
                    $columns,
                    $retrieveParams
                );

                if(is_array($res)){
                    $count = $this->SQLManager->selectFromTable(
                        $this->SQLManager->getSQLPrefix().'IP_LIST',
                        $extraDBConditions,
                        ['COUNT(*)'],
                        array_merge($retrieveParams,['limit'=>null])
                    );

                    $results['@'] = array('#' => $count[0][0]);

                    $resCount = isset($res[0]) ? count($res[0]) : 0;
                    foreach($res as $resultArray){
                        for($i = 0; $i<$resCount/2; $i++)
                            unset($resultArray[$i]);
                        if($safeStr && $resultArray['Meta'] !== null)
                            $resultArray['Meta'] = \IOFrame\Util\SafeSTRFunctions::safeStr2Str($resultArray['Meta']);
                        $results[$resultArray['IP']] = $resultArray;
                    }
                }
            }
            else{
                $results = $this->getFromCacheOrDB(
                    $inputs,
                    $keyCol,
                    'IP_LIST',
                    'ioframe_ip_',
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


        /**  Gets all -or some - IP ranges
         * @param array $inputs, default [], of the form:
         *              [
         *                  [
         *                      'prefix'=><string, default '' - valid IPv4 prefix (e.g "123", "124.546", "121.125.215")>
         *                      'from'=><int, 0-255 - required>
         *                      'to'=><int, 0-255 - required>
         *                  ]
         *              ]
         * @param array $params of the form:
         *              'type'    - bool, default null - if set, only returns results which are blacklisted (false) or whitelisted (true)
         *              'ignoreExpired' - bool, default true. Whether to ignore expired results
         *              -- if $inputs are not set --
         *              'limit'     - int, default null -standard SQL limit
         *              'offset'    - int, default null, standard SQL offset
         * @returns array Object of arrays OR INT CODES the form:
         *          [
         *              <prefix>.<from>_<prefix>.<to> => Array of DB columns
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
        function getIPRanges(array $inputs = [], array $params = []): array {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $type = $params['type'] ?? null;
            $ignoreExpired = !isset($params['ignoreExpired']) || $params['ignoreExpired'];
            $limit = $params['limit'] ?? null;
            $offset = $params['offset'] ?? null;

            $retrieveParams = array_merge($params,['limit'=>$limit,'offset'=>$offset]);
            $extraDBConditions = [];
            $extraCacheConditions = [];
            $keyCol = ['Prefix','IP_From','IP_To'];

            $columns = array_merge(['IP_Type','Prefix','IP_From','IP_To','Expires']);

            if($type!== null){
                $cond = ['IP_Type',$type,'='];
                $extraCacheConditions[] = $cond;
                $extraDBConditions[] = $cond;
            }
            if($ignoreExpired){
                $cond = ['Expires',time(),'>'];
                $extraCacheConditions[] = $cond;
                $extraDBConditions[] = $cond;
            }

            if($extraCacheConditions!=[]){
                $extraCacheConditions[] = 'AND';
                $retrieveParams['columnConditions'] = $extraCacheConditions;
            }
            if($extraDBConditions!=[]){
                $extraDBConditions[] = 'AND';
                $retrieveParams['extraConditions'] = $extraDBConditions;
            }

            if($inputs == []){
                $results = [];

                $res = $this->SQLManager->selectFromTable(
                    $this->SQLManager->getSQLPrefix().'IPV4_RANGE',
                    $extraDBConditions,
                    $columns,
                    $retrieveParams
                );
                if(is_array($res)){
                    $count = $this->SQLManager->selectFromTable(
                        $this->SQLManager->getSQLPrefix().'IPV4_RANGE',
                        $extraDBConditions,
                        ['COUNT(*)'],
                        array_merge($retrieveParams,['limit'=>null])
                    );

                    $results['@'] = array('#' => $count[0][0]);

                    $resCount = isset($res[0]) ? count($res[0]) : 0;
                    foreach($res as $resultArray){
                        for($i = 0; $i<$resCount/2; $i++)
                            unset($resultArray[$i]);
                        $results[$resultArray['Prefix'].'/'.$resultArray['IP_From'].'/'.$resultArray['IP_To']] = $resultArray;
                    }
                }
            }
            else{
                $stuffToGet = [];
                foreach($inputs as $input){
                    $stuffToGet[] = [
                        $input['prefix'],
                        $input['from'],
                        $input['to'],
                    ];
                }

                $results = $this->getFromCacheOrDB(
                    $stuffToGet,
                    $keyCol,
                    'IPV4_RANGE',
                    'ioframe_ip_range',
                    $columns,
                    $retrieveParams
                );
            }

            return $results;

        }


        /* Adds a new IP to the list
         * @param string $ip            IP represented in a string
         * @param bool $type            Whitelist/Blacklist
         * @param array $params of the form:
         *              'override' - bool, default false - Whether to override existing IPs - default false
         *              'reliable' - bool, default true - Whether the IP is considered reliable
         *              'ttl' - int, default 0 - How long the listing should exist before it expires. 0 means indefinitely
         *
         * @returns bool
         *          true - Success
         *          false - $override is false and an IP already exists
         * */
        function addIP(string $ip, bool $type , array $params = []): array|bool|int|string|null {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            isset($params['override'])?
                $override = $params['override'] : $override = false;

            isset($params['reliable'])?
                $reliable = $params['reliable'] : $reliable = true;

            isset($params['ttl'])?
                $ttl = (int)$params['ttl'] : $ttl = 0;

            $expires = time()+ ( ($ttl!==0)? $ttl : 1000000000 );

            $res = $this->SQLManager->insertIntoTable(
                $this->SQLManager->getSQLPrefix().'IP_LIST',
                ['IP_Type','Is_Reliable','IP','Expires'],
                [[(int)$type,$reliable,[$ip,'STRING'], $expires]],
                ['onDuplicateKey'=>$override,'test'=>$test,'verbose'=>$verbose]
            );
            if($res){
                if($verbose)
                    echo 'Deleting IP '.$ip.' from cache!'.EOL;
                if(!$test && $useCache)
                    $this->RedisManager->call('del',[[$ip]]);
            }
            else{
                $this->logger->error('Could not add IP',['ip'=>$ip,'type'=>$type,'params'=>$params]);
            }

            return $res;
        }

        /** Updates an IP in the list with a new $ttl or $type or reliability
         * @param string $ip            IP to update represented in a string
         * @param bool $type            Whitelist/Blacklist
         * @param array $params of the form:
         *              'reliable' - bool, default true - Whether the IP is considered reliable
         *              'ttl' - int, default 0 - How long the listing should exist before it expires. 0 means indefinitely
         *
         * @returns bool
         *          true - Success
         *          false - IP does not exist
         * */
        function updateIP(string $ip, bool $type = null , array $params = []){

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            $reliable = $params['reliable'] ?? true;

            $ttl = isset($params['ttl'])? (int)$params['ttl'] : null;

            $assignments = [];

            if($type !== null)
                $assignments[] = 'IP_Type = ' . (int)$type;

            if($ttl !== null){
                $expires = time()+ ( ($ttl!==0)? $ttl : 1000000000 );
                $assignments[] = 'Expires = ' . $expires;
            }

            if(!$reliable)
                $assignments[] = 'Is_Reliable = ' . ($reliable ? 'TRUE' : 'FALSE');

            if($assignments == [])
                return false;

            $ip = $this->SQLManager->selectFromTable(
                $this->SQLManager->getSQLPrefix().'IP_LIST',
                ['IP',[$ip,'STRING'],'=']
                ,
                [],
                ['test'=>$test,'verbose'=>$verbose]
            );

            if(!is_array($ip) || count($ip) == 0)
                return false;

            $res = $this->SQLManager->updateTable(
                $this->SQLManager->getSQLPrefix().'IP_LIST',
                $assignments,
                ['IP',[$ip[0]['IP'],'STRING'],'='],
                ['test'=>$test,'verbose'=>$verbose]
            );

            if($res){
                if($verbose)
                    echo 'Deleting IP '.$ip[0]['IP'].' from cache!'.EOL;
                if(!$test && $useCache)
                    $this->RedisManager->call('del',[[$ip[0]['IP']]]);
            }
            else
                $this->logger->error('Could not update IP',['ip'=>$ip,'type'=>$type,'params'=>$params]);

            return $res;

        }

        /** Deletes an $ip from the list
         * @param string $ip IP to delete, represented in a string
         * @param array $params
         *
         * @return array|bool|int|mixed|string|string[]
         */
        function deleteIP(string $ip, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            $ip = $this->SQLManager->selectFromTable(
                $this->SQLManager->getSQLPrefix().'IP_LIST',
                ['IP',[$ip,'STRING'],'='],
                [],
                ['test'=>$test,'verbose'=>$verbose]
            );

            if(!is_array($ip) || count($ip) == 0)
                return false;

            $res = $this->SQLManager->deleteFromTable(
                $this->SQLManager->getSQLPrefix().'IP_LIST',
                ['IP',[$ip[0]['IP'],'STRING'],'='],
                ['test'=>$test,'verbose'=>$verbose]
            );

            if($res){
                if($verbose)
                    echo 'Deleting IP '.$ip[0]['IP'].' from cache!'.EOL;
                if(!$test && $useCache)
                    $this->RedisManager->call('del',[[$ip[0]['IP']]]);
            }
            else
                $this->logger->error('Could not delete IP',['ip'=>$ip,'params'=>$params]);

            return $res;
        }

        /* Adds a new range to the list
         *
         * @param string $prefix        IPV4 Prefix ('','xxx','xxx.xxx','xxx.xxx.xxx')
         * @param int $from             Range (0-255)
         * @param int $to               Range (0-255)
         * @param bool $type            Whitelist/Blacklist
         * @param int $ttl              How long the listing should exist before it expires. 0 means indefinitely
         * @param array $params         Array of the form:
         *                      'override' - bool, default false - Whether to override existing IPs - default false
         *
         * @returns bool true on success, false if override is false and IP exists
         * */
        function addIPRange(string $prefix, int $from, int $to, bool $type, int $ttl, array $params = []): array|bool|int|string|null {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            isset($params['override'])?
                $override = $params['override'] : $override = false;

            $expires =  time()+ ( ($ttl!==0)? $ttl : 1000000000 );

            $res = $this->SQLManager->insertIntoTable(
                $this->SQLManager->getSQLPrefix().'IPV4_RANGE',
                ['IP_Type','Prefix','IP_From','IP_To','Expires'],
                [[(int)$type,[$prefix,'STRING'], $from, $to, $expires]],
                ['onDuplicateKey'=>$override,'test'=>$test,'verbose'=>$verbose]
            );

            if($res){
                if($verbose)
                    echo 'Deleting IP '.$prefix.'/'.$from.'/'.$to.' from cache!'.EOL;
                if(!$test && $useCache)
                    $this->RedisManager->call('del',[[$prefix.'/'.$from.'/'.$to]]);
            }
            else
                $this->logger->error('Could not add IP range',['prefix'=>$prefix,'from'=>$from,$to=>'to','type'=>$type,'ttl'=>$ttl,'override'=>$override]);

            return $res;
        }

        /** Updates a range in the list
         *
         * @param string $prefix        IPV4 Prefix ('','xxx','xxx.xxx','xxx.xxx.xxx')
         * @param int $from             Range (0-255)
         * @param int $to               Range (0-255)
         * @param array $params         Array of the form
         *                      [
         *                          'from' => int 0-255,
         *                          'to' => int 0-255,
         *                          'type' => true/false for white/black list,
         *                          'ttl' => int, time-to-live in Seconds. 0 means indefinitely
         *                      ]
         *
         * @returns bool
         *
         * */
        function updateIPRange(string $prefix, int $from, int $to, array $params = []){

            $assignments = [];

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            if(isset($params['type']))
                $assignments[] = 'IP_Type = ' . (int)$params['type'];

            if(isset($params['ttl'])){
                $expires =  time()+ ( ($params['ttl']!==0)? $params['ttl'] : 1000000000 );
                $assignments[] = 'Expires = ' . $expires;
            }

            if(isset($params['from']))
                $assignments[] = 'IP_From = ' . $params['from'];

            if(isset($params['to']))
                $assignments[] = 'IP_To = ' . $params['to'];

            if($assignments == [])
                return false;

            $ip = $this->SQLManager->selectFromTable(
                $this->SQLManager->getSQLPrefix().'IPV4_RANGE',
                [
                    ['Prefix',$prefix,'='],
                    ['IP_From',$from,'='],
                    ['IP_To',$to,'='],
                    'AND'
                ],
                [],
                ['test'=>$test,'verbose'=>$verbose]
            );

            if(!is_array($ip) || count($ip) == 0)
                return false;

            $res = $this->SQLManager->updateTable(
                $this->SQLManager->getSQLPrefix().'IPV4_RANGE',
                $assignments,
                [
                    ['Prefix',$prefix,'='],
                    ['IP_From',$from,'='],
                    ['IP_To',$to,'='],
                    'AND'
                ],
                ['test'=>$test,'verbose'=>$verbose]
            );

            if($res){
                if($verbose)
                    echo 'Deleting IP '.$prefix.'/'.$from.'/'.$to.' from cache!'.EOL;
                if(!$test && $useCache)
                    $this->RedisManager->call('del',[[$prefix.'/'.$from.'/'.$to]]);
            }
            else
                $this->logger->error('Could not update IP range',['prefix'=>$prefix,'from'=>$from,$to=>'to']);

            return $res;

        }

        /** Deletes an ip range from the list
         * @param string $prefix IPV4 Prefix ('','xxx','xxx.xxx','xxx.xxx.xxx')
         * @param int $from Range (0-255)
         * @param int $to Range (0-255)
         * @param array $params
         * @return array|bool|int|mixed|string|string[]
         */
        function deleteIPRange(string $prefix, int $from, int $to, array $params = []){

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            $ip = $this->SQLManager->selectFromTable(
                $this->SQLManager->getSQLPrefix().'IPV4_RANGE',
                [
                    ['Prefix',$prefix,'='],
                    ['IP_From',$from,'='],
                    ['IP_To',$to,'='],
                    'AND'
                ],
                [],
                ['test'=>$test,'verbose'=>$verbose]
            );

            if(!is_array($ip) || count($ip) == 0)
                return false;

            $res = $this->SQLManager->deleteFromTable(
                $this->SQLManager->getSQLPrefix().'IPV4_RANGE',
                [
                    ['Prefix',$prefix,'='],
                    ['IP_From',$from,'='],
                    ['IP_To',$to,'='],
                    'AND'
                ],
                ['test'=>$test,'verbose'=>$verbose]
            );

            if($res){
                if($verbose)
                    echo 'Deleting IP '.$prefix.'/'.$from.'/'.$to.' from cache!'.EOL;
                if(!$test && $useCache)
                    $this->RedisManager->call('del',[[$prefix.'/'.$from.'/'.$to]]);
            }
            else
                $this->logger->error('Could not delete IP range',['prefix'=>$prefix,'from'=>$from,$to=>'to']);

            return $res;
        }

        /** Deletes expired IPs
         * @param array $params of the form:
         *      'range' bool, default true - whether to delete from the IP_RANGE table (true) or IP_LIST (false)
         * @returns bool
         */
        function deleteExpired(array $params = []){

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $range = $params['range'] ?? null;

            $tname = $range?
                $this->SQLManager->getSQLPrefix().'IPV4_RANGE' : $this->SQLManager->getSQLPrefix().'IP_LIST' ;


            $res = $this->SQLManager->deleteFromTable(
                $tname,
                [['Expires',time(),'<=']],
                $params
            );

            if(!$res)
                $this->logger->error('Could not delete expired IPs ',['isRange'=>$range]);

            return $res;
        }

    }



}




