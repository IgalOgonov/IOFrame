<?php
namespace IOFrame\Handlers{
    use IOFrame;
    define('RouteHandler',true);
    if(!defined('abstractDBWithCache'))
        require 'abstractDBWithCache.php';
    if(!defined('OrderHandler'))
        require 'OrderHandler.php';
    if(!defined('safeSTR'))
        require __DIR__ . '/../Util/safeSTR.php';

    /*  This class handles every action related to routing.
     *  Also documented altorouter over at http://altorouter.com/usage/mapping-routes.html,
     *  And at the ROUTING_MAP and ROUTING_MATCH sections of procedures/SQLdbInit.php
     *
     *
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */
    class RouteHandler extends IOFrame\abstractDBWithCache
    {

        private $OrderHandler;

        public $AuthHandler;

        /**
         * @var Int Tells us for how long to cache stuff by default.
         * 0 means indefinitely.
         */
        protected $cacheTTL = 3600;

        /* Standard constructor
         *
         * Constructs an instance of the class, getting the main settings file and an existing DB connection, or generating
         * such a connection itself.
         *
         * @param object $settings The standard settings object
         * @param object $conn The standard DB connection object
         * */

        function __construct(SettingsHandler $settings, $params = [])
        {

            parent::__construct($settings, $params);

            if (isset($params['AuthHandler']))
                $this->AuthHandler = $params['AuthHandler'];

            //Create new order handler
            $params['name'] = 'route';
            $params['tableName'] = 'CORE_VALUES';
            $params['columnNames'] = [
                0 => 'tableKey',
                1 => 'tableValue'
            ];
            $this->OrderHandler = new OrderHandler($settings, $params);
        }

        /** Creates a new route, or updates an existing route.
         *  All values must not be null if override is false.
         *
         * @param string|null $method Method string
         * @param string|null $route Route to match
         * @param string|null $match Match name
         * @param string|null $name Map name
         * @param array $params
         *
         *  @returns int
         * -1 - could not connect to db
         *  ID of created route otherwise.
         *
         * */
        function addRoute(
            string $method,
            string $route,
            string $match,
            string $name = null,
            array $params = []
        ){
            return $this->addRoutes([[$method,$route,$match,$name]],$params);
        }

        /** Creates new routes, or updates existing routes.
         *
         * @param array $inputs Inputs from setRoute
         * @param array $params
         *          'activate' => whether to activate route on creation
         *          'safeStr' => Convert from/to safeStr. Applies to route only!
         *          'index' => int, default -1, IF activating - index asin OrderHandler->pushToOrderMultiple() at which to insert route to order
         *
         *  @returns int
         * -1 - could not connect to db
         *  ID of the FIRST created route otherwise (the rest must be inferred).
         * */
        function addRoutes(array $inputs, array $params = []){

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            if(!isset($params['safeStr']))
                $safeStr = true;
            else
                $safeStr = $params['safeStr'];

            if(!isset($params['activate']))
                $activate = true;
            else
                $activate = $params['activate'];

            $assignmentArray = [];

            $res = -1;

            //First check all inputs, and
            foreach($inputs as $index=>$input){
                $input[1] = $safeStr ? IOFrame\Util\str2SafeStr($input[1]) : $input[1];
                //Set default
                if(!isset($input[3]))
                    $input[3] = null;

                //Mark as strings
                $assignmentArray[$index] = [
                    [$input[0],'STRING'],
                    [$input[1],'STRING'],
                    [$input[2],'STRING'],
                    [$input[3],'STRING']
                ];
            }

            $columns = ['Method','Route', 'Match_Name', 'Map_Name'];

            if(
                !$this->SQLHandler->insertIntoTable(
                    $this->SQLHandler->getSQLPrefix().'ROUTING_MAP',
                    $columns,
                    $assignmentArray,
                    ['test'=>$test,'verbose'=>$verbose]
                )
            )
                return $res;
            else
                $res = $test ? 1 : (int)$this->SQLHandler->lastInsertId();

            if($verbose)
                echo 'Inserted new routes with parameters :'.json_encode($inputs).', ID of the first one: '.$res.EOL;

            if($activate){
                $IDs = [];
                for($i = $res; $i< $res + count($inputs); $i++){
                    array_push($IDs,$i);
                }
                $this->activateRoutes($IDs,$params);
            }

            return $res;
        }


        /** Creates a new route, or updates an existing route.
         *  All values must not be null if override is false.
         *
         * @param int $ID ID of the route
         * @param string|null $method Method string
         * @param string|null $route Route to match
         * @param string|null $match Match name
         * @param string|null $name Map name - null does not set it to NULL in the db - instead, pass an empty string.
         * @param array $params
         *          'validate' => bool, default true - whether to check for existing values.
         *          'safeStr' => Convert from/to safeStr. Applies to route only!
         *
         *  @returns int
         * -1 could not connect to db
         *  0 success
         *  1 - route does not exist!
         *
         * */
        function updateRoute(
            int $ID,
            string $method = null,
            string $route = null,
            string $match = null,
            string $name = null,
            array $params = []
        ){
            return $this->updateRoutes([[$ID,$method,$route,$match,$name]],$params)[$ID];
        }

        /** Creates new routes, or updates existing routes.
         *
         * @param array $inputs Inputs from setRoute
         * @param array $params from setRoute
         *
         *  @returns array of the form ID => <Code from setRoute>
         * */
        function updateRoutes(array $inputs, array $params = []){

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            if(isset($params['useCache']))
                $useCache = $params['useCache'];
            else
                $useCache = $this->defaultSettingsParams['useCache'];

            $validate = isset($params['validate'])? $params['validate'] : true;

            if(!isset($params['safeStr']))
                $safeStr = true;
            else
                $safeStr = $params['safeStr'];

            $res = [];
            $IDs = [];
            $toValidate = [];
            $toSet = [];
            $columns = ['ID','Method','Route', 'Match_Name', 'Map_Name'];

            foreach($inputs as $inputIndex => $inputArray){
                array_push($IDs,$inputArray[0]);
                $toValidate[$inputArray[0]] = $inputIndex;
                $res[$inputArray[0]] = -1;
            }

            $existing = $this->getRoutes($IDs,array_merge($params,['updateCache'=>false]));

            //Validate that the requested routes exist!
            if($validate){
                foreach($existing as $id=>$route){
                    if(is_array($route))
                        unset($toValidate[$id]);
                    else{
                        if($verbose)
                            echo 'Route '.$id.' does not exist!'.EOL;
                        $res[$id] = $route;
                        unset($inputs[$toValidate[$id]]);
                    }
                }
            }

            foreach($inputs as $inputIndex => $inputArray){

                for($i = 1; $i<5; $i++){
                    switch($i){
                        case 1:
                            $field = 'Method';
                            break;
                        case 2:
                            $field = 'Route';
                            break;
                        case 3:
                            $field = 'Match_Name';
                            break;
                        default:
                            $field = 'Map_Name';
                    }
                    if($inputArray[$i] == '' && $i == 4)
                        $inputArray[$i] = null;
                    elseif($inputArray[$i] == null)
                        $inputArray[$i] = $existing[$inputArray[0]][$field];
                    else
                        $inputArray[$i] = ($safeStr && $field == 'Route') ? IOFrame\Util\str2SafeStr($inputArray[$i]) : $inputArray[$i];
                    if($inputArray[$i] != null)
                        $inputArray[$i] = [$inputArray[$i],'STRING'];
                }
                array_push($toSet,$inputArray);
            }


            if(count($inputs)>0){
                $this->SQLHandler->insertIntoTable(
                    $this->SQLHandler->getSQLPrefix().'ROUTING_MAP',
                    $columns,
                    $toSet,
                    ['test'=>$test,'verbose'=>$verbose,'onDuplicateKey'=>true]
                );

                foreach($inputs as $inputArray)
                if($useCache){
                    if(!$test)
                        $this->RedisHandler->call('del',['ioframe_route_'.$inputArray[0]]);
                    if($verbose)
                        echo 'Deleting route '.$inputArray[0].' from cache!'.EOL;
                }
            }

            return $res;

        }

        /** Deletes an existing route.
         *
         * @param int $ID ID of the route to delete
         * @param array $params
         *          'deactivate' =>  bool, default true - whether to deactivate the route in the order
         *
         *  @returns int
         * -1 - could not connect to db
         *  0 - success
         *  1 - ID does not exist
         * */
        function deleteRoute(int $ID, array $params = []){
            return $this->deleteRoutes([$ID],$params)[$ID];
        }

        /** Deletes existing routes.
         *
         * @param int[] $IDs ID of the routes delete.
         * @param array $params
         *
         *  @returns array of the form ID => <Code from updateRoutes>
         *  In case of db connection failure, all codes are -1
         * */
        function deleteRoutes(array $IDs, array $params = []){

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            if(isset($params['useCache']))
                $useCache = $params['useCache'];
            else
                $useCache = $this->defaultSettingsParams['useCache'];

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
                    array_push($IDsToDelete,$ID);
            }

            //If nothing exists, we got no more work
            if(count($IDs)==0)
                return $res;

            $deleteConds = [
                'ID',
                [$IDsToDelete],
                'IN'
            ];

            $this->SQLHandler->deleteFromTable(
                $this->SQLHandler->getSQLPrefix().'ROUTING_MAP',
                $deleteConds,
                $params
            );

            if($useCache){
                foreach($IDsToDelete as $ID){
                    if($verbose)
                        echo 'Deleting route '.$ID.' from cache!'.EOL;
                    if(!$test)
                        $this->RedisHandler->call('del',['ioframe_route_'.$ID]);
                }
            }

            if($deactivate)
                $this->disableRoutes($IDs,$params);

            return $res;

        }

        /** Gets a single route.
         *
         * @param int $ID ID of the route to get
         * @param array $params
         *
         * @returns mixed
         *  1 - ID does not exist
         * -1 - Failed to connect to DB
         *  Array of the form:
         *  ['ID'=> INT, 'Method'=> String, 'Route'=> String, 'Match_Name'=> String, 'Map_Name'=> String|Null ]
         *  otherwise.
         *
         */
        function getRoute(int $ID, array $params = []){
            return $this->getRoutes([$ID],$params)[$ID];
        }

        /** Gets existing routes.
         *
         * @param int[] $IDs ID of the routes get. If empty, will get ALL routes.
         * @param array $params
         *                  'safeStr' => bool, default true - whether to convert back from safeString. Applies to Route only!
         *                  'limit' => int, SQL Limit clause
         *                  'offset' => int, SQL offset clause (only matters if limit is set)
         * @returns array
         *          Array of the form [ <ID> => <result from getRoute()> ] for each ID
         * */
        function getRoutes(array $IDs = [], array $params = []){

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
                        $res[$id]['Route'] = IOFrame\Util\safeStr2Str($route['Route']);
                    }
                }
            }


            return $res;
        }

        /** Activate a route (add to route order)
         *
         * @param int $ID ID of the route to activate
         * @param array $params
         *
         * @returns int
         *      -1 - could not get order
         *       0 - success
         *       1 - route is already in order!
        */
        function activateRoute(int $ID, array $params = []){
            return $this->activateRoutes([$ID],$params)[$ID];
        }

        /** Activates routes (adds to route order)
         *  Duplicate IDs will be ignored!
         *
         * @param int[] $IDs IDs of the routes to activate
         * @param array $params
         *
         * @returns int[] of the form ID => <code from activateRoute>
         */
        function activateRoutes(array $IDs, array $params = []){

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $index = isset($params['index'])? $params['index'] : false;

            $this->pushToOrderMultiple($IDs,['test'=>$test,'verbose'=>$verbose, 'index'=>$index]);

        }

        /** Disables a route (remove from order)
         *
         * @param int $ID ID of the route to disable
         * @param array $params
         *
         * @returns int
         *      -1 - could not connect to db
         *       0 - success
         *       1 - route does not exist in order!
         */
        function disableRoute(int $ID, array $params = []){
            return $this->disableRoutes([$ID],$params)[$ID];
        }


        /** Disables routes (remove from order)
         *  Duplicate IDs will be ignored!
         *
         * @param int[] $IDs IDs of the routes to disable
         * @param array $params
         *
         * @returns int[] of the form ID => <code from disableRoute>
         */
        function disableRoutes(array $IDs, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            return $this->removeFromOrderMultiple($IDs,'name',$params);

        }

        /** Gets all routes that are active (in their order).
         *
         * @param array $params
         *
         * @returns array
         *  Array of the form:
         *    <ORDER INDEX (NOT ID!!)> =>
         *          ['ID'=> INT, 'Method'=> String, 'Route'=> String, 'Match_Name'=> String, 'Map_Name'=> String|Null ]
         */
        function getActiveRoutes(array $params = []){

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

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
         * @param string|array|null $url May be a string that represents the URL of the match,
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
        function setMatch(string $match, $url = null, array $extensions = null, bool $partial = null, array $params = []){
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
        function setMatches(array $inputs, array $params = []){

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            if(isset($params['useCache']))
                $useCache = $params['useCache'];
            else
                $useCache = $this->defaultSettingsParams['useCache'];

            if(!isset($params['safeStr']))
                $safeStr = true;
            else
                $safeStr = $params['safeStr'];

            $override = isset($params['override'])? $params['override'] : true;

            $res = [];
            $matchNames = [];
            $matchesToSet = [];
            $isFullInput = [];
            $columns = ['Match_Name','URL','Extensions','Match_Partial_URL'];

            foreach($inputs as $matchName=>$input){
                array_push($matchNames,$matchName);
                $isFullInput[$matchName] = ($input[0]!== null)? true : false;

                if(gettype($inputs[$matchName][0]) === 'array')
                    $inputs[$matchName][0] = json_encode($inputs[$matchName][0]);

                if($safeStr && $inputs[$matchName][0]!=null)
                    $inputs[$matchName][0] = IOFrame\Util\str2SafeStr($inputs[$matchName][0]);

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
                        array_push($paramsToSet,[$matchName,'STRING']);

                        if(!isset($inputs[$matchName][0]))
                            array_push($paramsToSet,[$matchInfo['URL'],'STRING']);
                        else
                            array_push($paramsToSet,[$inputs[$matchName][0],'STRING']);

                        if(!isset($inputs[$matchName][1]))
                            array_push($paramsToSet,[$matchInfo['Extensions'],'STRING']);
                        elseif(isset($inputs[$matchName][2]) && $inputs[$matchName][1] == '')
                            array_push($paramsToSet,null);
                        else
                            array_push($paramsToSet,[$inputs[$matchName][1],'STRING']);

                        if(!isset($inputs[$matchName][2]))
                            array_push($paramsToSet,(bool)$matchInfo['Match_Partial_URL']);
                        else
                            array_push($paramsToSet,(bool)$inputs[$matchName][2]);

                        array_push($matchesToSet,$paramsToSet);
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
                        array_push($paramsToSet,[$matchName,'STRING']);

                        array_push($paramsToSet,[$inputs[$matchName][0],'STRING']);

                        if( $inputs[$matchName][1] === null || $inputs[$matchName][1] === '')
                            array_push($paramsToSet,null);
                        else
                            array_push($paramsToSet,[$inputs[$matchName][1],'STRING']);

                        array_push($paramsToSet,isset($inputs[$matchName][2]) ? (bool)$inputs[$matchName][2] : false);

                        array_push($matchesToSet,$paramsToSet);
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
            $updated = $this->SQLHandler->insertIntoTable(
                $this->SQLHandler->getSQLPrefix().'ROUTING_MATCH',
                $columns,
                $matchesToSet,
                ['test'=>$test,'verbose'=>$verbose,'onDuplicateKey'=>true]
            );

            if($updated){
                foreach($inputs as $matchName=>$inputArray){
                    $res[$matchName] = 0;
                    if($useCache){
                        if(!$test)
                            $this->RedisHandler->call('del',['ioframe_route_match_'.$matchName]);
                        if($verbose)
                            echo 'Deleting route match '.$matchName.' from cache!'.EOL;
                    }
                }
            }

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
         * @returns int[]
         *          Array of the form [ <Match Name> => <code from deleteMatch()> ]
         * */
        function deleteMatches(array $matches, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            if(isset($params['useCache']))
                $useCache = $params['useCache'];
            else
                $useCache = $this->defaultSettingsParams['useCache'];

            $checkIfExists = isset($params['checkIfExists'])? $params['checkIfExists'] : true;

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
                array_push($dbMatches,[$matchName,'STRING']);

            $request = $this->SQLHandler->deleteFromTable(
                $this->SQLHandler->getSQLPrefix().'ROUTING_MATCH',
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
                    $res[$matchName] = isset($res[$matchName]) ? $res[$matchName] : 0 ;
                    if($useCache){
                        if(!$test)
                            $this->RedisHandler->call('del',['ioframe_route_match_'.$matchName]);
                        if($verbose)
                            echo 'Deleting route match '.$matchName.' from cache!'.EOL;
                    }
                }
            }
            else
                foreach($matches as $matchName)
                    $res[$matchName] = isset($res[$matchName]) ? $res[$matchName] : -1 ;

            return $res;
        }

        /** Gets a single route.
         *
         * @param string $match Names of the match
         * @param array $params
         *
         * @returns mixed
         *  1 - Name does not exist
         *  Array of the form [ 'Match_Name'=> String,  'URL'=> String,  'EXTENSIONS'=> CSV String | NULL ] otherwise.
         *
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
        function getMatches(array $matches = [], array $params = []){

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
                        $res[$matchName]['URL'] = IOFrame\Util\safeStr2Str($match['URL']);
                    }
                }
            }

            return $res;
        }

        /** See OrderHandler moveOrder() documentation
         * @param int $from
         * @param int $to
         * @param array $params
         * @returns int
         * */
        function moveOrder(int $from, int $to, $params = [])
        {
            return $this->OrderHandler->moveOrder($from, $to, $params);
        }

        /** See OrderHandler swapOrder() documentation
         * @param int $num1
         * @param int $num2
         * @param array $params
         * @returns int
         * */
        function swapOrder(int $num1, int $num2, array $params = [])
        {
            return $this->OrderHandler->moveOrder($num1, $num2, $params);
        }

        /** See OrderHandler documentation
         * @param array $params
         * @return mixed
         *
         * */
        protected function getOrder($params = [])
        {
            return $this->OrderHandler->getOrder($params);
        }

        /** See OrderHandler documentation
         * @param string $name
         * @param array $params
         * @returns int
         * */
        protected function pushToOrder(string $name, $params = [])
        {
            return $this->OrderHandler->pushToOrder($name, $params);
        }


        /**  See OrderHandler documentation
         * @param array $names
         * @param array $params
         * @returns int
         * */
        protected function pushToOrderMultiple(array $names, $params = [])
        {
            return $this->OrderHandler->pushToOrderMultiple($names, $params);
        }

        /**  See OrderHandler documentation
         * @param string $target
         * @param string $type
         * @param array $params
         * @returns int
         * */
        protected function removeFromOrder(string $target, string $type, array $params = [])
        {
            return $this->OrderHandler->removeFromOrder($target, $type, $params);
        }

        /**  See OrderHandler documentation
         * @param array $targets
         * @param string $type
         * @param array $params
         * @returns int
         * */
        protected function removeFromOrderMultiple(array $targets, string $type, $params = [])
        {
            return $this->OrderHandler->removeFromOrderMultiple($targets,$type, $params);
        }


    }
}






?>