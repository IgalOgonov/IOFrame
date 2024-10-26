<?php
namespace IOFrame\Handlers{
    use IOFrame;
    define('IOFrameHandlersRouteHandler',true);

    /*  This class handles every action related to routing.
     *  Also documented altorouter over at http://altorouter.com/usage/mapping-routes.html,
     *  And at the ROUTING_MAP and ROUTING_MATCH sections of procedures/SQLdbInit.php
     *
     *
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */
    class RouteHandler extends \IOFrame\Abstract\DBWithCache
    {

        private IOFrame\Managers\OrderManager $OrderManager;

        /**
         * @var Int Tells us for how long to cache stuff by default.
         * 0 means indefinitely.
         */
        protected mixed $cacheTTL = 3600;

        /* Standard constructor
         *
         * Constructs an instance of the class, getting the main settings file and an existing DB connection, or generating
         * such a connection itself.
         *
         * @param object $settings The standard settings object
         * @param object $conn The standard DB connection object
         * */

        function __construct(\IOFrame\Handlers\SettingsHandler $settings, $params = [])
        {

            parent::__construct($settings,array_merge($params,['logChannel'=>\IOFrame\Definitions::LOG_ROUTING_CHANNEL]));

            //Create new order handler
            $params['name'] = 'route';
            $params['tableName'] = 'CORE_VALUES';
            $params['columnNames'] = [
                0 => 'tableKey',
                1 => 'tableValue'
            ];
            $this->OrderManager = new \IOFrame\Managers\OrderManager($settings, $params);
        }


        /** Creates a new route, or updates an existing route.
         *  All values must not be null if override is false.
         *
         * @param string $ID ID of the route
         * @param string|null $method Method string
         * @param string|null $route Route to match
         * @param string|null $match Match name
         * @param string|null $name Map name - null does not set it to NULL in the db - instead, pass an empty string.
         * @param array $params
         *          'safeStr' => Convert from/to safeStr. Applies to route only!
         *          'activate' => whether to activate route on creation
         *          'update' => bool, default false
         *          'overwrite' => bool, default true
         *
         *  @returns int
         * -1 could not connect to db
         *  0 success
         *   1 - route does not exist!
         *   2 - route already exists
         *
         * */
        function setRoute(
            string $ID,
            string $method = null,
            string $route = null,
            string $match = null,
            string $name = null,
            array $params = []
        ){
            return $this->setRoutes([$ID => [$method,$route,$match,$name]],$params)[$ID];
        }

        /** Creates new routes, or updates existing routes.
         *
         * @param array $inputs Inputs from setRoute
         * @param array $params from setRoute
         *
         *  @returns array of the form ID => <Code from setRoute>
         * */
        function setRoutes(array $inputs, array $params = []): array {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            $update = $params['update'] ?? false;
            $overwrite = $params['overwrite'] ?? true;

            if(!isset($params['safeStr']))
                $safeStr = true;
            else
                $safeStr = $params['safeStr'];

            if(!isset($params['activate']))
                $activate = true;
            else
                $activate = $params['activate'];

            $res = [];
            $IDs = [];
            $toValidate = [];
            $toSet = [];
            $columns = ['ID','Method','Route', 'Match_Name', 'Map_Name'];

            foreach($inputs as $id => $inputArray){
                $IDs[] = $id;
                $toValidate[$id] = $id;
                $res[$id] = -1;
            }

            $existing = $this->getRoutes($IDs,array_merge($params,['updateCache'=>false]));

            //Validate that the requested routes exist!
            if($update || !$overwrite){
                foreach($existing as $id=>$route){
                    if($update && !is_array($route)){
                        if($verbose)
                            echo 'Route '.$id.' does not exist!'.EOL;
                        $res[$id] = 1;
                        unset($inputs[$id]);
                    }
                    elseif (!$overwrite && is_array($route)){
                        if($verbose)
                            echo 'Route '.$id.' already exists!'.EOL;
                        $res[$id] = 2;
                        unset($inputs[$id]);
                    }
                }
            }

            foreach($inputs as $id => $inputArray){

                for($i = 0; $i<4; $i++){
                    $field = match ($i) {
                        0 => 'Method',
                        1 => 'Route',
                        2 => 'Match_Name',
                        3 => 'Map_Name',
                    };
                    if(!isset($inputArray[$i]))
                        $inputArray[$i] = null;

                    if(($inputArray[$i] == '') && ($field === 'Map_Name'))
                        $inputArray[$i] = null;
                    elseif($inputArray[$i] == null)
                        $inputArray[$i] = $existing[$inputArray[0]][$field];
                    else
                        $inputArray[$i] = ($safeStr && ($field === 'Route')) ? \IOFrame\Util\SafeSTRFunctions::str2SafeStr($inputArray[$i]) : $inputArray[$i];
                    if($inputArray[$i] != null)
                        $inputArray[$i] = [$inputArray[$i],'STRING'];
                }
                $toSet[] = [[$id,'STRING'],...$inputArray];
            }


            if(count($inputs)>0){
                $success = $this->SQLManager->insertIntoTable(
                    $this->SQLManager->getSQLPrefix().'ROUTING_MAP',
                    $columns,
                    $toSet,
                    ['test'=>$test,'verbose'=>$verbose,'onDuplicateKey'=>true]
                );

                if($success){
                    foreach($inputs as $id => $inputArray){
                        if(!empty($res[$id]) && ($res[$id] === -1))
                            $res[$id] = 0;
                        if($useCache){
                            if(!$test)
                                $this->RedisManager->call('del',['ioframe_route_'.$id]);
                            if($verbose)
                                echo 'Deleting route '.$id.' from cache!'.EOL;
                        }
                    }
                    if($activate){
                        $this->activateRoutes(array_keys($inputs),$params);
                    }
                }
                else
                    $this->logger->error('Failed to set routes',['items'=>$toSet]);
            }

            return $res;

        }

        /** Deletes an existing route.
         *
         * @param string $ID ID of the route to delete
         * @param array $params
         *          'deactivate' => bool, default true - whether to deactivate the route in the order
         *
         *  @returns int
         * -1 - could not connect to db
         *  0 - success
         *  1 - ID does not exist
         * */
        function deleteRoute(string $ID, array $params = []){
            return $this->deleteRoutes([$ID],$params)[$ID];
        }

        /** Deletes existing routes.
         *
         * @param string[] $IDs ID of the routes delete.
         * @param array $params
         *
         * @return array
         */
        function deleteRoutes(array $IDs, array $params = []): array {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            if(!isset($params['deactivate']))
                $deactivate = true;
            else
                $deactivate = $params['deactivate'];

            $routes = $this->getRoutes($IDs, array_merge($params,['updateCache'=>false]));

            $res = [];
            $IDsToDelete = [];

            foreach($IDs as $index=>$ID){
                $res[$ID] = 0;
                if($routes[$ID] == 1 || $routes[$ID] == -1){
                    if($verbose)
                        echo 'Route '.$ID.' does not exist!'.EOL;
                    $res[$ID] = $routes[$ID];
                    unset($IDs[$index]);
                }
                else
                    $IDsToDelete[] = [$ID,'STRING'];
            }

            //If nothing exists, we got no more work
            if(count($IDs)==0)
                return $res;

            $deleteConds = [
                'ID',
                [$IDsToDelete],
                'IN'
            ];

            $success = $this->SQLManager->deleteFromTable(
                $this->SQLManager->getSQLPrefix().'ROUTING_MAP',
                $deleteConds,
                $params
            );

            if($success){
                if($useCache){
                    foreach($IDs as $ID){
                        if($verbose)
                            echo 'Deleting route '.$ID.' from cache!'.EOL;
                        if(!$test)
                            $this->RedisManager->call('del',['ioframe_route_'.$ID]);
                    }
                }
            }
            else{
                foreach($IDs as $ID){
                    $res[$ID] = -1;
                }
                $this->logger->error('Failed to delete routes',['items'=>$IDsToDelete]);
            }

            if($deactivate)
                $this->disableRoutes($IDs,$params);

            return $res;

        }

        /** Gets a single route.
         *
         * @param string $ID ID of the route to get
         * @param array $params
         *
         * @return mixed
         */
        function getRoute(string $ID, array $params = []){
            return $this->getRoutes([$ID],$params)[$ID];
        }

        /** Gets existing routes.
         *
         * @param string[] $IDs ID of the routes get. If empty, will get ALL routes.
         * @param array $params
         *                  'safeStr' => bool, default true - whether to convert back from safeString. Applies to Route only!
         *                  'limit' => int, SQL Limit clause
         *                  'offset' => int, SQL offset clause (only matters if limit is set)
         * @returns array
         *          Array of the form [ <ID> => <result from getRoute()> ] for each ID
         * */
        function getRoutes(array $IDs = [], array $params = []): array {

            if(!isset($params['safeStr']))
                $safeStr = true;
            else
                $safeStr = $params['safeStr'];

            $params['type'] = 'route';

            $res = $this->getFromCacheOrDB(
                $IDs,
                'ID',
                'ROUTING_MAP',
                'ioframe_route_',
                [],
                $params
            );

            if($safeStr){
                foreach($res as $id=>$route){
                    if(is_array($route)){
                        $res[$id]['Route'] = \IOFrame\Util\SafeSTRFunctions::safeStr2Str($route['Route']);
                    }
                }
            }


            return $res;
        }

        /** Activate a route (add to route order)
         *
         * @param string $ID ID of the route to activate
         * @param array $params
         *
         * @return mixed|string
         */
        function activateRoute(string $ID, array $params = []){
            return $this->activateRoutes([$ID],$params)[$ID];
        }

        /** Activates routes (adds to route order)
         *  Duplicate IDs will be ignored!
         *
         * @param string[] $IDs IDs of the routes to activate
         * @param array $params
         *
         * @return array|string
         */
        function activateRoutes(array $IDs, array $params = []): array|string {
            $params['index'] = $params['index']??false;
            return $this->pushToOrderMultiple($IDs,$params);
        }

        /** Disables a route (remove from order)
         *
         * @param string $ID ID of the route to disable
         * @param array $params
         *
         * @return mixed|string
         */
        function disableRoute(string $ID, array $params = []){
            return $this->disableRoutes([$ID],$params)[$ID];
        }


        /** Disables routes (remove from order)
         *  Duplicate IDs will be ignored!
         *
         * @param string[] $IDs IDs of the routes to disable
         * @param array $params
         *
         * @return array|int|string
         */
        function disableRoutes(array $IDs, array $params = []): array|int|string {

            return $this->removeFromOrderMultiple($IDs,'name',$params);

        }

        /** Gets all routes that are active (in their order).
         *
         * @param array $params
         *
         * @return array
         */
        function getActiveRoutes(array $params = []): array {

            $IDs = $this->getOrder($params);

            $routes = $this->getRoutes($IDs,$params);

            foreach($routes as $index=>$route)
                if(!is_array($route))
                    unset($routes[$index]);

            return $routes;

        }


        /** Creates a new match, or update an existing one.
         *  $url must NOT be null if setting a new match.
         *  Any call to set a non-existent match where $url is null will be discarded.
         *
         * @param string $match Name of the match
         * @param array|string|null $url May be a string that represents the URL of the match,
         *                          an associative array of the form:
         *                          [
         *                           'include' => <URL of the match>,
         *                           'exclude' => <Array of regex patterns - if the URL matches one, it's invalid>
         *                          ]
         *                          or an array of strings and associative arrays of the format above.
         *                          What each option does is explained in the documentation, as well as in SQLdbInit.
         * @param array|null $extensions Valid extensions to match with
         * @param bool|null $partial Whether matching a partial route is allowed
         * @param array $params
         *              'override' - bool, default true - Whether to override existing match.
         * @returns int
         *         -1 - failed to connect to db
         *          0 - success
         *          1 - match exists and cannot be overwritten
         *          2 - Trying to create a new match with insufficient values.
         * */
        function setMatch(string $match, array|string $url = null, array $extensions = null, bool $partial = null, array $params = []){
            return $this->setMatches([[$match=>[$url,$extensions,$partial]]],$params)[$match];
        }

        /** Creates new matches, or update existing ones.
         *
         * @param array $inputs Of the form
         *              [
         *                  $matchName => [$url, $extensions]
         *                  ...
         *              ]
         * @param array $params same as setMatch()
         *                  'safeStr' => bool, default true - whether to convert back from safeString. Applies to URL only!
         *
         * @returns int[]
         *          Array of the form [ <Match Name> => <code from setMatch()> ]
         * */
        function setMatches(array $inputs, array $params = []): array {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            if(!isset($params['safeStr']))
                $safeStr = true;
            else
                $safeStr = $params['safeStr'];

            $override = $params['override'] ?? true;

            $res = [];
            $matchNames = [];
            $matchesToSet = [];
            $isFullInput = [];
            $columns = ['Match_Name','URL','Extensions','Match_Partial_URL'];

            foreach($inputs as $matchName=>$input){
                $matchNames[] = $matchName;
                $isFullInput[$matchName] = $input[0]!== null;

                if(gettype($input[0]) === 'array')
                    $inputs[$matchName][0] = json_encode($input[0]);

                if($safeStr && $inputs[$matchName][0]!=null)
                    $inputs[$matchName][0] = \IOFrame\Util\SafeSTRFunctions::str2SafeStr($inputs[$matchName][0]);

                $res[$matchName] = -1;
            }

            $existingMatches = $this->getMatches($matchNames,array_merge($params,['updateCache'=>false]));

            foreach($existingMatches as $matchName=>$matchInfo){
                if(is_array($matchInfo)){
                    //For each existing match, unset the input and push code into result
                    if(!$override){
                        if($verbose)
                            echo 'Match '.$matchName.' exists and can\'t be overridden!'.EOL;
                        unset($inputs[$matchName]);
                        $res[$matchName] = 1;
                    }
                    else{
                        $paramsToSet = [];
                        $paramsToSet[] = [$matchName, 'STRING'];

                        if(!isset($inputs[$matchName][0]))
                            $paramsToSet[] = [$matchInfo['URL'], 'STRING'];
                        else
                            $paramsToSet[] = [$inputs[$matchName][0], 'STRING'];

                        if(!isset($inputs[$matchName][1]))
                            $paramsToSet[] = [$matchInfo['Extensions'], 'STRING'];
                        elseif(isset($inputs[$matchName][2]) && $inputs[$matchName][1] == '')
                            $paramsToSet[] = null;
                        else
                            $paramsToSet[] = [$inputs[$matchName][1], 'STRING'];

                        if(!isset($inputs[$matchName][2]))
                            $paramsToSet[] = (bool)$matchInfo['Match_Partial_URL'];
                        else
                            $paramsToSet[] = (bool)$inputs[$matchName][2];

                        $matchesToSet[] = $paramsToSet;
                    }
                }
                elseif($matchInfo == 1){
                    if(!$isFullInput[$matchName]){
                        if($verbose)
                            echo 'Match '.$matchName.' cannot be created, inputs are missing!'.EOL;
                        unset($inputs[$matchName]);
                        $res[$matchName] = 2;
                    }
                    else{
                        $paramsToSet = [];
                        $paramsToSet[] = [$matchName, 'STRING'];

                        $paramsToSet[] = [$inputs[$matchName][0], 'STRING'];

                        if( $inputs[$matchName][1] === null || $inputs[$matchName][1] === '')
                            $paramsToSet[] = null;
                        else
                            $paramsToSet[] = [$inputs[$matchName][1], 'STRING'];

                        $paramsToSet[] = isset($inputs[$matchName][2]) ? (bool)$inputs[$matchName][2] : false;

                        $matchesToSet[] = $paramsToSet;
                    }
                }
                else{
                    if($verbose)
                        echo 'Match '.$matchName.' cannot be created, failed to connect to db!'.EOL;
                    unset($inputs[$matchName]);
                    $res[$matchName] = -1;
                }
            }

            //If there are no matches to set, return the result
            if(count($matchesToSet) == 0)
                return $res;

            //Else set the matches
            $updated = $this->SQLManager->insertIntoTable(
                $this->SQLManager->getSQLPrefix().'ROUTING_MATCH',
                $columns,
                $matchesToSet,
                ['test'=>$test,'verbose'=>$verbose,'onDuplicateKey'=>true]
            );

            if($updated){
                foreach($inputs as $matchName=>$inputArray){
                    $res[$matchName] = 0;
                    if($useCache){
                        if(!$test)
                            $this->RedisManager->call('del',['ioframe_route_match_'.$matchName]);
                        if($verbose)
                            echo 'Deleting route match '.$matchName.' from cache!'.EOL;
                    }
                }
            }
            else
                $this->logger->error('Failed to update routes matches',['items'=>$matchesToSet]);

            return $res;

        }

        /** Deletes an existing match.
         *
         * @param string $match Names of the match
         * @param array $params
         *              'checkIfExists' - bool, default true. Whether to check if the match exists, or just try to delete it.
         *
         *  @returns int
         * -1 - could not connect to db
         *  0 - success
         *  1 - Name does not exist
         * */
        function deleteMatch(string $match, array $params = []){
            return $this->deleteMatches([$match],$params)[$match];
        }

        /** Deletes existing matches.
         *
         * @param string[] $matches Names of the matches
         * @param array $params
         *
         * @return array
         */
        function deleteMatches(array $matches, array $params = []): array {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            $checkIfExists = !isset($params['checkIfExists']) || $params['checkIfExists'];

            $res = [];

            if($checkIfExists){
                $existingMatches = $this->getMatches($matches,array_merge($params,['updateCache'=>false]));
                foreach($existingMatches as $matchName=>$matchInfo){
                    if(!is_array($matchInfo)){
                        if($verbose)
                            echo 'Route match '.$matchName.' does not exist!'.EOL;
                        $res[$matchName] = $matchInfo;
                    }
                }
            }

            $dbMatches = [];
            foreach($matches as $matchName)
                $dbMatches[] = [$matchName, 'STRING'];

            $request = $this->SQLManager->deleteFromTable(
                $this->SQLManager->getSQLPrefix().'ROUTING_MATCH',
                [
                    'Match_Name',
                    [$dbMatches],
                    'IN'
                ],
                ['test'=>$test,'verbose'=>$verbose]
            );
            //If we succeeded
            if($request){
                foreach($matches as $matchName){
                    $res[$matchName] = $res[$matchName] ?? 0;
                    if($useCache){
                        if(!$test)
                            $this->RedisManager->call('del',['ioframe_route_match_'.$matchName]);
                        if($verbose)
                            echo 'Deleting route match '.$matchName.' from cache!'.EOL;
                    }
                }
            }
            else{
                foreach($matches as $matchName)
                    $res[$matchName] = $res[$matchName] ?? -1;
                $this->logger->error('Failed to delete routes matches',['items'=>$matches]);
            }

            return $res;
        }

        /** Gets a single route.
         *
         * @param string $match Names of the match
         * @param array $params
         *
         * @return mixed
         */
        function getMatch(string $match, array $params = []){
            return $this->getMatches([$match],$params)[$match];
        }

        /** Gets existing matches.
         *
         * @param string[] $matches Names of the matches. If empty, will get ALL matches.
         * @param array $params
         *                  'safeStr' => bool, default true - whether to convert back from safeString. Applies to Route only!
         *
         * @returns array
         *          Array of the form [ <Match Name> => <result from getMatch()> ] for each match name
         * */
        function getMatches(array $matches = [], array $params = []): array {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            if(!isset($params['safeStr']))
                $safeStr = true;
            else
                $safeStr = $params['safeStr'];

            $res = $this->getFromCacheOrDB(
                $matches,
                'Match_Name',
                'ROUTING_MATCH',
                'ioframe_route_match_',
                [],
                $params
            );

            //Finally, use safeStr
            if($safeStr){
                foreach($res as $matchName=>$match){
                    if(is_array($match)){
                        $res[$matchName]['URL'] = \IOFrame\Util\SafeSTRFunctions::safeStr2Str($match['URL']);
                    }
                }
            }

            return $res;
        }

        /** See OrderManager moveOrder() documentation
         * @param int $from
         * @param int $to
         * @param array $params
         * @return int|string
         */
        function moveOrder(int $from, int $to, array $params = []): int|string {
            return $this->OrderManager->moveOrder($from, $to, $params);
        }

        /** See OrderManager swapOrder() documentation
         * @param int $num1
         * @param int $num2
         * @param array $params
         * @return int|string
         */
        function swapOrder(int $num1, int $num2, array $params = []): int|string {
            return $this->OrderManager->moveOrder($num1, $num2, $params);
        }

        /** See OrderManager documentation
         * @param array $params
         * @return mixed
         *
         * */
        protected function getOrder(array $params = []): mixed {
            return $this->OrderManager->getOrder($params);
        }

        /** See OrderManager documentation
         * @param string $name
         * @param array $params
         * @return array|mixed|string
         */
        protected function pushToOrder(string $name, array $params = [])
        {
            return $this->OrderManager->pushToOrder($name, $params);
        }


        /**  See OrderManager documentation
         * @param array $names
         * @param array $params
         * @return array|string
         */
        protected function pushToOrderMultiple(array $names, array $params = []): array|string {
            return $this->OrderManager->pushToOrderMultiple($names, $params);
        }

        /**  See OrderManager documentation
         * @param string $target
         * @param string $type
         * @param array $params
         * @return array|int|mixed|string
         */
        protected function removeFromOrder(string $target, string $type, array $params = [])
        {
            return $this->OrderManager->removeFromOrder($target, $type, $params);
        }

        /**  See OrderManager documentation
         * @param array $targets
         * @param string $type
         * @param array $params
         * @return array|int|string
         */
        protected function removeFromOrderMultiple(array $targets, string $type, array $params = []): array|int|string {
            return $this->OrderManager->removeFromOrderMultiple($targets,$type, $params);
        }


    }
}




