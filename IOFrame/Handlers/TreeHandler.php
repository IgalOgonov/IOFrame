<?php
namespace IOFrame\Handlers{
    use IOFrame;
    define('TreeHandler',true);
    if(!defined('abstractDBWithCache'))
        require 'abstractDBWithCache.php';
    /**
     * A class to handle trees.
     * Trees are implemented as euler tour trees stored in some SQL table (table name provided by the user).
     * This handler supports efficiently displaying the full tree (duh), a subtree of a specific node, and an array of all
     * the parents of a specific node, up to the root.
     * It supports creating a new node as the n-th child of an existing node, and cutting a node/subtree (and optionally pasting it
     * as an n-th child of an existing node in any tree, or creating a new tree for it).
     * For the logic behind an euler tour tree, consult the documentation or various online sources.
     *
     * Euler and Associated trees syntaxes described above relevant arrays (class members).
     *
     * TODO - Rewrite conversion/parsing functions as a C++ extension and add support for it
     *
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license LGPL
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     */

    class TreeHandler extends IOFrame\abstractDBWithCache
    {
        /** @var string[] $treeNames Names of our trees.
         */
        protected $treeNames = [];

        /** @var bool[] $isInitArray Used for lazy tree initiation. */
        public $isInitArray = [];

        /** @var int[] $lastUpdateTimes An array of last times each tree was updated.
         */
        protected $lastUpdateTimes = [];

        /** @var bool[] $isPrivate An array of tree privacy status - by default, false (not private)
         */
        protected $isPrivate = [];

        /** @var bool $useCache Specifies whether we should be using cache */
        protected $useCache = false;

        /** @var mixed $treeArrays A "classic" array of arrays representing the full tree.
         * Of the form :
         *  [
         *   <Node ID> => [
         *      "content" => <Node Content>,
         *      "smallestEdge" => <Smallest Connected Edge>,
         *      "largestEdge" => <Largest Connected Edge>
         *   ],
         *   ...
         * ]
         */
        protected $treeArrays = [];

        /** @var mixed $numberedTreeArrays An array of arrays representing the full tree saving both the numbering and structure.
         * Of the form :
         *  [
         *   <Node ID> => [
         *      "content" => <Node Content>,
         *      "smallestEdge" => <Smallest Connected Edge>,
         *      "largestEdge" => <Largest Connected Edge>
         *   ],
         *   ...
         * ]
         */
        protected $numberedTreeArrays = [];

        /** @var mixed $assocTreeArrays An array of arrays representing the tree without meta information (Edges, Weights...).
         * Of the form:
         * "<content>": {
         *      "<content>":{
         *              ...
         *          },
         *      "<content>":{
         *              ...
         *          },
         *          ...
         *      }
         * Where leaves end in an empty array []
         */
        protected $assocTreeArrays = [];

        /** @var RedisHandler $RedisHandler A RedisHandler so that we may use redis directly as cache. */
        protected $RedisHandler = null;

        /**
         * Basic construction function.
         * Parameters: 'initiate'       => Whether to initiate from DB/Cache on creation.
         *              'useCache'      => Whether to use cache - only works with a RedisHandler provided.
         *              'RedisHandler'  => A RedisHandler (with a valid connection), as defined in RedisHandler.php
         *              +Standard parameters defined in father class.
         *
         * @param mixed $target Name of a single tree, or array of tree names.
         * @param SettingsHandler $settings regular settings handler.
         * @param array $params As defined in the abstract method and this comment
         */
        public function __construct($target, SettingsHandler $settings, $params = []){
            parent::__construct($settings,$params);

            //Set defaults
            if(!isset($params['initiate']))
                $params['initiate'] = true;
            if(!isset($params['ignorePrivate']))
                $params['ignorePrivate'] = true;
            //Notice that useCache affects the default setting creation in this handler as well
            if(!isset($params['useCache']))
                $params['useCache'] = true;

            //Set redis handler if we got one - and if it is initiated
            if(isset($params['RedisHandler']) && $params['RedisHandler']!==null){
                if(isset($params['RedisHandler']->isInit)){
                    if($params['RedisHandler']->isInit){
                        $this->RedisHandler = $params['RedisHandler'];
                    }
                }
            }
            else{
                //Might seem unrelated, but there is no cache without redis
                $params['useCache'] = false;
            }

            //Set the tree name
            if( gettype($target)=='string' ){
                $this->treeNames = [$target];
                $target = [$target=>0];
            }
            elseif(is_array($target) && $target!=[]){
                foreach($target as $treeName => $lastUpdated){
                    array_push($this->treeNames,$treeName);
                }
            }
            else
                ;

            $this->useCache = $params['useCache'];

            //Initiate if we need to
            if($params['initiate']){

                $this->getFromCache($target,$params);
                $this->getFromDB($target,$params);
            }
        }

        /** Returns the type of a tree
         *
         * @param array $tree Euler or Associated tree array.
         * @returns int 1 (Euler) or 0 (Assoc), depending on tree type.
         * */
        private function getTreeType(array $tree){

            //First step to see if it's an euler array
            if(isset($tree[0])){
                //Second step
                if(count($tree[0]) == 3){
                    //Third Step
                    if(isset($tree[0]['content'])){
                        //Final step
                        if(!is_array($tree[0]['content']))
                            return 1;
                    }
                }
            }

            return 0;
        }

        /** Construct an associated array tree from a euler tour tree array
         *
         * @param array $eulerTree Euler tree array.
         * @param bool $resolveConflicts Whether to resolve conflicts - 2 or more children nodes of the same name will be
         *                               named "child", "child(1)", "child(2)" etc.
         * @returns array Associated tree array.
         * */
        function eulerToAssoc($eulerTree, bool $resolveConflicts = true){
            //Final tree
            $assocTree = array();
            //Current node
            $currentNode = 0;

            //Start with the root
            $assocTree[$eulerTree[0]['content']] = [];
            $childrenRemaining = ($eulerTree[0]['largestEdge'])/2;
            //Insert all the children (if any)
            if($childrenRemaining>0)
                $this->populateChildren($assocTree[$eulerTree[0]['content']],$currentNode,$childrenRemaining,$eulerTree,$resolveConflicts);

            return $assocTree;
        }

        //Used to support eulerToAssoc recursively
        private function populateChildren(&$assocTree, &$currentNode, $childrenRemaining, $eulerTree,$resolveConflicts){
            $res = $childrenRemaining;
            while( $res > 0){
                $currentNode++;
                if($resolveConflicts){
                    //If we have duplicate content
                    if(isset($assocTree[$eulerTree[$currentNode]['content']])){
                        $matches = [];
                        //If this is the first time we have a conflict
                        if(!preg_match('/\(\d+\)$/',$eulerTree[$currentNode]['content'])){
                            $eulerTree[$currentNode]['content'] = $eulerTree[$currentNode]['content'].' (1)';
                        }
                        //If this is not the first time we have a conflict
                        else{
                            $duplicate = substr($matches[0],1,-1);
                            $eulerTree[$currentNode]['content'] =
                                substr($eulerTree[$currentNode]['content'],0,-(2+strlen((string)$duplicate))).'('.($duplicate+1).')';
                            $eulerTree[$currentNode]['content'] = $eulerTree[$currentNode]['content'].' (1)';
                        }
                    }
                }
                $assocTree[$eulerTree[$currentNode]['content']] = [];
                $res -= 1;
                //Insert children
                $tempRemaining = ($eulerTree[$currentNode]['largestEdge'] - $eulerTree[$currentNode]['smallestEdge'] - 1)/2;
                $this->populateChildren($assocTree[$eulerTree[$currentNode]['content']], $currentNode, $tempRemaining, $eulerTree,$resolveConflicts);
                $res -= $tempRemaining;
            }

            return true;
        }

        /**  Converts an associated array to an Euler tree in o(n) time.
         *
         * @param array $assocTree Associated tree array.
         * @returns array Euler tree array.
         */
        function assocToEuler($assocTree){
            $eulerTree = [];
            $currentEdge = 1;
            $count = 0;
            $numberedTree = $this->assocToNumbered($assocTree,$count);
            $eulerTree[0]['content'] = key($numberedTree);
            $eulerTree[0]['smallestEdge'] = 1;
            $eulerTree[0]['largestEdge'] = ($count-1)*2;
            //If we have more than just the root
            if($count > 0)
                $this->traverseChildrenEdges($eulerTree, $currentEdge,$numberedTree[key($numberedTree)]);

            return $eulerTree;
        }

        /**  Converts an associated array to a numbered tree in o(n) time.
         *
         * @param array $assocTree Associated tree array.
         * @param int $count Can be used to return the total number of nodes in the tree
         * @returns array Numbered tree array.
         */
        function assocToNumbered($assocTree,int &$count = null){
            $hybridTree = [];
            $returnCount = ($count !== null)? $count : 0 ;
            $this->traverseChildrenAndCalcWeight($assocTree, $hybridTree, key($assocTree) ,$returnCount);
            if($count !== null)
                $count = $returnCount;
            return $hybridTree;
        }

        //Used to support assocToNumbered recursively - markes the vertices by order and returns total tree weight
        private function traverseChildrenAndCalcWeight($assocTree,&$outputTree, $name ,&$count = 0){
            $id = $count++;
            $outputTree[$id] = ['ID' => $id, 'content'=>$name ,'children' => []];
            if(count($assocTree[$name]) != 0)
                foreach($assocTree[$name] as $key=>$val){
                    $this->traverseChildrenAndCalcWeight($assocTree[$name],$outputTree[$id]['children'],$key,$count);
                }
        }

        //Used to support assocToEuler recursively - traverses the nodes in their order to create the Euler tree.
        private function traverseChildrenEdges(&$eulerTree, &$currentEdge,$assocTree){
            $currentId = $assocTree['ID'];
            $children = $assocTree['children'];
            $content = $assocTree['content'];
            //Update the smallest edge
            if($currentId!=0){
                $eulerTree[$currentId]['smallestEdge'] = $currentEdge++;
                $eulerTree[$currentId]['content'] = $content;
            }
            //Finish with the children
            foreach($children as $childArray){
                $this->traverseChildrenEdges($eulerTree,$currentEdge,$childArray);
            }
            //Update the largest edge
            if($currentId!=0){
                $eulerTree[$currentId]['largestEdge'] = $currentEdge++;
            }
        }

        /** Adds more trees for the Handler to handle (at the same time).
         * Is useful both for organization purposes, and when you want to cut a subtree from one tree and link it to another.
         *
         * @param array $inputs is an associated array of 3-length arrays of the form:
         * [
         * "treeName" => $params of the form:
         *              [
         *               'content' => <Tree Content (may be empty - then creates a root with content <treeName>)>,
         *               'updateDB'=> <bool - default true - Whether to create the tree in the DB or just keep it in the handler>,
         *               'override'=> <bool - default false - Whether to override existing tree>,
         *              ],
         * ...
         * ]
         * @throws \Exception on invalid tree name.
         *
         * @returns mixed 0 on success for all, otherwise an array of the form {"treeName" => ErrorCode}, where the error codes are:
         *          1 - Cannot override existing tree!
        */
        function addTrees($inputs = [], array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $res = [];
            foreach($inputs as $treeName=>$params){
                //Set default
                if(isset($params['override']))
                    $override = $params['override'];
                else
                    $override = true;

                if(isset($params['private']))
                    $private = $params['private'];
                else
                    $private = 0;

                if(isset($params['updateDB']))
                    $updateDB = $params['updateDB'];
                else
                    $updateDB = true;

                //No overriding unless specified
                if(!in_array($treeName,$this->treeNames) || $override){
                    //Check the content
                    $type = $this->getTreeType($params['content']);
                    if($verbose)
                        echo 'Tree type '.$type.EOL;
                    //Set euler/assoc trees accordingly
                    if($type == 1){
                        $eulerTree = $params['content'];
                        $assocTree = $this->eulerToAssoc($eulerTree);
                    }
                    elseif($type == 0){
                        $assocTree = $params['content'];
                        $eulerTree = $this->assocToEuler($assocTree);
                    }
                    else{
                        if(is_array($params['content']))
                            $params['content'] = json_encode($params['content']);
                        throw new \Exception('Invalid tree with the name '.$treeName.'! Contents - '.$params['content']);
                    }
                    //Update the handler
                    if(!$test){
                        array_push($this->treeNames,$treeName);
                        $this->treeArrays[$treeName] = $eulerTree;
                        $this->assocTreeArrays[$treeName] = $assocTree;
                        $this->numberedTreeArrays[$treeName] = $this->assocToNumbered($assocTree);
                        $this->lastUpdateTimes[$treeName] = time();
                        $this->isInitArray[$treeName] = true;
                        $this->isPrivate[$treeName] = $private;
                    }
                    if($verbose){
                        echo 'Updating tree '.$treeName.' at '.time().', dumping Euler and Associated trees:'.EOL;
                        var_dump($eulerTree);
                        var_dump($assocTree);
                    }
                    //If we are updating the database - do it before we update the handler to make sure the trees do not yet exist.
                    if($updateDB) {
                        $dbParams = ['mode'=>'create'];
                        //Delete the tree first if it exists already
                        if(isset($params['override']))
                            $dbParams['override'] = $override;
                        if(isset($params['private']))
                            $dbParams['private'] = $private;
                        $dbParams['test'] = $test;
                        $dbParams['verbose'] = $verbose;

                        $res[$treeName] = $this->updateDB($treeName,$dbParams);
                        if($res[$treeName] === true)
                            unset($res[$treeName]);
                    }
                }
                //In case we cannot override, set the result appropriately and continue.
                if(in_array($treeName,$this->treeNames)){
                    $res[$treeName] = 1;
                    if($verbose)
                        echo 'Cannot override existing tree!'.EOL;
                }
            }

            //Update the cache
            $this->updateCache(['test'=>$test,'verbose'=>$verbose]);

            if($res !== [])
                return $res;
            else
                return 0;
        }

        /** Removes trees from tha handler.
         *  Will update the cahce.
         *
         * @param array $inputs is an array of the form:
         * [
         * "treeName" => $params of the form:
         *              [
         *               'updateDB' => <bool - default true - whether to delete the trees in the DB (if they exist) or only in the handler>,
         *               'onlyEmpty' => <bool - default false - will only delete tree if it's empty>,
         *              ],
         * ...
         * ]
         * @param array $params
         * @returns mixed 0 on success for all, otherwise an array of the form {"treeName" => ErrorCode}, where the error codes are:
         *          1 - Cannot override existing tree!
         * */
        function removeTrees($inputs = [], array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $res = [];

            foreach($inputs as $treeName=>$params){
                if(isset($params['updateDB']))
                    $updateDB = $params['updateDB'];
                else
                    $updateDB = true;

                //Tree to be deleted has to exist, or be in the db
                if(in_array($treeName,$this->treeNames)){
                    unset($this->treeNames[array_search($treeName,$this->treeNames)]);
                    unset($this->treeArrays[$treeName]);
                    unset($this->assocTreeArrays[$treeName]);
                    unset($this->numberedTreeArrays[$treeName]);
                    unset($this->lastUpdateTimes[$treeName]);
                    unset($this->isInitArray[$treeName]);
                    $res[$treeName] = 0;
                    //Update the cache
                    $this->updateCache(['remove'=>$treeName,'test'=>$test,'verbose'=>$verbose]);
                }
                if($updateDB){
                    //Set default
                    if(isset($params['onlyEmpty']))
                        $params = ['onlyEmpty'=>$params['onlyEmpty'],'mode'=>'delete'];
                    else
                        $params = ['mode'=>'delete'];
                    $params['test'] = $test;
                    $params['verbose'] = $verbose;
                    $res[$treeName] = $this->updateDB($treeName,$params);

                    if($res[$treeName] === true)
                        unset($res[$treeName]);
                    else{
                        if($res[$treeName] == 2)
                            $res[$treeName] = 1;
                    }
                }
            }


            if($res !== [])
                return $res;
            else
                return 0;
        }

        /** Updates the nodes in the input array of the form [ [<NodeID>,<NewContent>], ... ] with given content, in the specified tree.
         * If $updateDB is true, updates the tree in the db too.
         *
         * @params array $nodeArray - Array of nodes to update of the form [ [<NodeID>,<NewContent>], ... ]
         * @params string $treeName - Name of the tree
         * @param array $params of the form:
         *                      'updateDB' - bool, default true - whether to update the trees in the DB or only in the handler/cache
         *
         * @return int 0
        */
        function updateNodes(array $nodeArray, string $treeName, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $res = true;
            isset($params['updateDB'])?
                $updateDB = $params['updateDB'] : $updateDB = true;
            //Either updates the handler, the db, or both the handler and the db.
            if(isset($this->treeNames[$treeName])){
                foreach($nodeArray as $updatePair){
                    $this->treeArrays[$treeName][$updatePair[0]]['content'] = $updatePair[1];
                }
            }
            if($updateDB){
                $changeTime = (string)time();
                $tname = $this->SQLHandler->getSQLPrefix().strtoupper($treeName.'_TREE');
                $metaname = $tname.'_META';
                $updateTriplets = [];
                foreach($nodeArray as $updatePair){
                    array_push($updateTriplets,[$updatePair[0],[(string)$updatePair[1], 'STRING'], [$changeTime, 'STRING'],0,0]);
                }
                //Make sure to NOT actually update smallestEdge and largestEdge - set onDuplicateKeyExp manually
                $res = $this->SQLHandler->insertIntoTable(
                    $tname,
                    ['ID', 'content', 'lastChanged', 'smallestEdge', 'largestEdge'],
                    $updateTriplets,
                    [
                        'onDuplicateKey' => true,
                        'onDuplicateKeyExp'=> 'ID=VALUES(ID), content=VALUES(content), lastChanged=VALUES(lastChanged)',
                        'test'=>$test,
                        'verbose'=>$verbose
                    ]
                );
                //Update meta table
                if($res)
                    $res = $this->SQLHandler->updateTable(
                        $metaname,
                        ['settingValue = '.time()],
                        ['settingKey','_Last_Updated','='],
                        ['test'=>$test,'verbose'=>$verbose]
                    );
            }
            return $res;
        }

        /** Syncs specified trees from the DB. By default - all trees that are currently in the handler.
         * NOTICE - Unlike most other functions, this function DOES update the handler state with $test on - $test only changes
         *          the output (verbose vs none)
         * @param array $treeNames is of the form ["treeName" => <smallest acceptable lastUpdated of the tree>]
         * @param array $params of the form:
         *                      'ignorePrivate' => bool, default true - will ignore private trees (update nothing with them)
        */
        function getFromDB($treeNames = [], array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            //Set defaults
            if(!isset($params['ignorePrivate']))
                $ignorePrivate = true;
            else
                $ignorePrivate = $params['ignorePrivate'];

            if($treeNames == []){
                foreach($this->treeNames as $treeName){
                    $treeNames[$treeName] = 0;
                }
            }

            if($treeNames === [])
                return;

            //Get all the trees from the db
            $testQuery = '';
            foreach($treeNames as $treeName => $lastUpdated){
                $tname = $this->SQLHandler->getSQLPrefix().strtoupper($treeName.'_tree');
                $metaname = $tname.'_META';

                //Depending on whether we ignore private trees, decide the conditions we query by
                //If we ignore private trees, we only get
                if($ignorePrivate)
                    $queryCond = [
                        [
                            [
                                $metaname,
                                [['settingKey','_Last_Updated', '='],['settingValue',$lastUpdated,'>='],'AND'],
                                ['settingKey','settingValue'],
                                [],
                                'SELECT'
                            ]
                        , 'EXISTS'
                        ],
                        [
                            [
                                $metaname,
                                [['settingKey','_Private', '='],['settingValue',0,'='],'AND'],
                                ['settingKey','settingValue'],
                                [],
                                'SELECT'
                            ]
                            , 'EXISTS'
                        ],
                        "AND"
                    ];
                else
                    $queryCond = [ [$metaname, [['settingKey', '_Last_Updated', '='],['settingValue',$lastUpdated,'>='],'AND'], ['settingKey','settingValue'], [], 'SELECT'], 'EXISTS'];

                $testQuery.= $this->SQLHandler->selectFromTable($tname,
                        $queryCond,
                        ['ID','content','smallestEdge','largestEdge', '\''.$treeName.'\' as Source'],
                        ['justTheQuery'=>true,'test'=>false]
                    ).' UNION ';
            }

            $testQuery =  substr($testQuery,0,-7);
            $updateTime = time();

            if($verbose){
                echo 'Query to send: '.$testQuery.' at '.$updateTime.EOL;
            }
            $temp = $this->SQLHandler->exeQueryBindParam($testQuery, [], ['fetchAll'=>true]);

            if(!$temp)
                $temp = [];

            if($verbose)
                echo json_encode($temp).EOL;

            $treesUpdated = [];

            foreach($temp as $val){
                if(!in_array($val['Source'],$treesUpdated))
                    array_push($treesUpdated,$val['Source']);
                $this->treeArrays[$val['Source']][$val['ID']] = [
                    'content'=> $val['content'],
                    'smallestEdge'=> $val['smallestEdge'],
                    'largestEdge'=> $val['largestEdge'],
                ];
            }
            foreach($treesUpdated as $treeName){
                $this->assocTreeArrays[$treeName] = $this->eulerToAssoc($this->treeArrays[$treeName]);
                $this->numberedTreeArrays[$treeName] = $this->assocToNumbered($this->assocTreeArrays[$treeName]);
                $this->isInitArray[$treeName] = true;
                $this->lastUpdateTimes[$treeName] = time();
                if(!in_array($treeName,$this->treeNames))
                    array_push($this->treeNames,$treeName);
            }
            foreach($this->treeNames as $k=>$treeName){
                if(!in_array($treeName,$treesUpdated)){
                    if(!isset($this->isInitArray[$treeName]))
                        unset($this->treeNames[$k]);
                }
            }
        }


        /** Updates the DB with the specified trees. If the tree inside the handler is not set, will try to delete it.
         * Will only delete empty trees if $params['onlyEmpty'] is true (A tree with only a root is considered empty).
         *
         * @param string $treeName Name of the tree to either delete or update
         * @param array $params of the form:
         *              [
         *               'override' => Whether to override the tree that already exists in the db (on creation), or not. Default false.
         *               'onlyEmpty'=> If true, will only delete empty trees. Default false
         *               'private' => int, default 0 - If 1, the tree will be marked as 'private'.
         *               'mode' => string, 'create'|'delete' - Whether we are creating a new tree, from what we have, or
         *                         deleting an existing tree from DB. Defaults to 'create' if $treeName is set in the handler.
         *              ],
         * @returns int
         *          true on success
         *          1 if trying to override an existing tree with override being false
         *          2 if trying to delete a non-empty tree when onlyEmpty is false
         */
        //
        //
        //
        function updateDB(string $treeName, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $res = true;
            //Set defaults
            if(!isset($params['override']))
                $override = false;
            else
                $override = $params['override'];

            if(!isset($params['onlyEmpty']))
                $onlyEmpty = false;
            else
                $onlyEmpty = $params['onlyEmpty'];

            if(isset($params['private']))
                $private = $params['private'];
            else
                $private = 0;

            if(isset($params['mode']))
                $mode = $params['mode'];
            else
                $mode = in_array($treeName,$this->treeNames) ? 'create':'delete';

            //Tree table name
            $tname = $this->SQLHandler->getSQLPrefix().strtoupper($treeName.'_TREE');
            $metaname = $tname.'_META';
            $mapname = strtoupper($this->SQLHandler->getSQLPrefix().'TREE_MAP');
            //Checks whether a table already exists
            $potentialTree = $this->SQLHandler->selectFromTable($tname,[],[],['limit'=>1,'test'=>$test,'verbose'=>$verbose]);

            //This means we are creating a new tree from something we have
            if($mode == 'create'){

                //Create tree map if it does not exist yet
                $mapCreationQuery = 'CREATE TABLE IF NOT EXISTS '.$mapname.' (  treeName varchar(64) PRIMARY KEY
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;
                                                              ';
                if(!$test){
                    $this->SQLHandler->exeQueryBindParam($mapCreationQuery,[]);
                }
                if($verbose)
                    echo 'Map creation query: '.$mapCreationQuery.EOL;
                $needToInsertTreeToMap = true;

                //If the tree exists, either stop or delete it if we are overriding
                if(is_array($potentialTree) && count($potentialTree)>  0){
                    if($verbose)
                        echo 'Tree '.$treeName.' already exists in the DB!'.EOL;
                    if(!$override){
                        if($verbose)
                            echo 'Cannot override  exiting tree!'.EOL;
                        return 1;
                    }
                    else{
                        $needToInsertTreeToMap = false;
                        if(!$test){
                            $this->SQLHandler->exeQueryBindParam('TRUNCATE TABLE '.$tname,[]);
                            $this->SQLHandler->exeQueryBindParam('TRUNCATE TABLE '.$metaname,[]);
                        }
                        if($verbose){
                            echo 'Truncating tables '.$tname.', '.$metaname.EOL;
                            echo $treeName.' wont be inserted to tree map!'.EOL;
                        }
                    }
                }

                $createTableQuery = 'CREATE TABLE IF NOT EXISTS '.$tname.' (  ID int PRIMARY KEY,
                                                              content varchar(10000) NOT NULL,
                                                              smallestEdge int NOT NULL,
                                                              largestEdge int NOT NULL,
                                                              lastChanged varchar(14) DEFAULT "0"
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;
                                                              ';
                $createMetaQuery =
                    'CREATE TABLE IF NOT EXISTS '.$metaname.' (
                                                              settingKey varchar(255) PRIMARY KEY,
                                                              settingValue varchar(255) NOT NULL
                                                              ) ENGINE=InnoDB DEFAULT CHARSET = utf8;
                                                              ';
                //Start with the meta information
                if(!isset($this->lastUpdateTimes[$treeName]))
                    $updateTime = time();
                else
                    $updateTime = $this->lastUpdateTimes[$treeName];
                $toInsert = [
                    [['_Last_Updated',"STRING"],[(string)$updateTime,"STRING"]],
                    [['_Private',"STRING"],[(string)$private,"STRING"]]
                ];

                if($verbose){
                    echo 'Creating tables for '.$tname.EOL;
                    echo 'Query to send: '.$createTableQuery.EOL;
                    echo 'Query to send: '.$createMetaQuery.EOL;
                    echo 'Tree insert query: '.$this->SQLHandler->insertIntoTable(
                            $tname,
                            ['ID','content','smallestEdge','largestEdge'],
                            $toInsert,
                            ['justTheQuery'=>true,'test'=>false]
                        ).EOL;
                    if($needToInsertTreeToMap)
                        echo 'Meta information insert query: '.$this->SQLHandler->insertIntoTable(
                                $mapname,['treeName'],[[[$treeName,"STRING"]]],['justTheQuery'=>true,'test'=>false]
                            ).EOL;
                }

                if($test)
                    return $res;
                //--- FROM HERE TEST IS ALWAYS FALSE ---

                //Create tree table
                $res = $this->SQLHandler->exeQueryBindParam(
                    $createTableQuery,
                    []
                    );

                //Create meta information table
                if($res)
                    $res = $this->SQLHandler->exeQueryBindParam(
                                $createMetaQuery,
                                []
                            );

                if($res)
                    $res = $this->SQLHandler->insertIntoTable($metaname,['settingKey','settingValue'],$toInsert,['verbose'=>$verbose]);

                //Insert the tree itself
                $toInsert = [];
                foreach($this->treeArrays[$treeName] as $key=>$val){
                    $id = $key;
                    $content = $val['content'];
                    $smallest = $val['smallestEdge'];
                    $largest = $val['largestEdge'];
                    array_push($toInsert,[$id,[$content,"STRING"],$smallest,$largest]);
                }
                if($res)
                    $res = $this->SQLHandler->insertIntoTable($tname,['ID','content','smallestEdge','largestEdge'],$toInsert,['verbose'=>$verbose]);

                //Insert tree into map (if needed)
                if($needToInsertTreeToMap)
                    if($res)
                        $res = $this->SQLHandler->insertIntoTable($mapname,['treeName'],[[[$treeName,"STRING"]]],['verbose'=>$verbose]);
            }

            //That means we are asked to delete an existing tree
            else{
                //If the tree exists
                if(is_array($potentialTree) && count($potentialTree)>  0){
                    if($verbose)
                        echo 'Tree '.$treeName.' exists in the DB!'.EOL;
                    if($onlyEmpty){
                        if($verbose)
                            echo 'Cannot delete non-empty tree '.$treeName.EOL;
                        return 2;
                    }
                }
                //Try to delete the tree (it might not exist, but whatever)
                $deleteQuery = 'DROP TABLE IF EXISTS '.$tname.', '.$metaname;
                if($verbose){
                    echo 'Deleting tree '.$treeName.EOL;
                    echo 'Querys to send: '.$deleteQuery.EOL.
                        $this->SQLHandler->deleteFromTable($mapname,['treeName',[$treeName,"STRING"],'='],['justTheQuery'=>true,'test'=>false]).EOL;
                }
                if(!$test){
                    $res =$this->SQLHandler->exeQueryBindParam($deleteQuery);
                    if($res)
                        $res = $this->SQLHandler->deleteFromTable($mapname,['treeName',[$treeName,"STRING"],'='],['test'=>false]);
                }
            }

            return $res;
        }

        /** Tries to load all trees from the cache.
         *
         * @param array $params
         *
         * @returns true on success, false on failure
         *
        */
        function getFromCache($treeNames = [], array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            //Exit if we're not using cache
            if(!$this->useCache)
                return false;

            if($treeNames == []){
                foreach($this->treeNames as $treeName){
                    $treeNames[$treeName] = 0;
                }
            }

            if($treeNames === [])
                return false;

            //Indicates requested everything was found
            $res = true;

            foreach($treeNames as $treeName=>$lastUpdated){
                $treeJSON = $this->RedisHandler->call('get','_tree_'.$treeName);
                $treeMeta = $this->RedisHandler->call('get','_tree_meta_'.$treeName);

                //If the tree was updated earlier than the requested time, it means the user already has the up-to-date tree
                if($treeMeta < $lastUpdated){
                    if($verbose)
                        echo 'Tree '.$treeMeta.' is up to date! Last updated '.($lastUpdated-$treeMeta).' seconds ago. Not getting from cache.'.EOL;
                    continue;
                }

                if($treeJSON && $treeMeta){
                    $assocTree = json_decode($treeJSON,true);
                    if(!$test){
                        $this->assocTreeArrays[$treeName] = $assocTree;
                        $this->numberedTreeArrays[$treeName] = $this->assocToNumbered($this->assocTreeArrays[$treeName]);
                        $this->treeArrays[$treeName] = $this->assocToEuler($assocTree);
                        $this->lastUpdateTimes[$treeName] = $treeMeta;
                        $this->isInitArray[$treeName] = true;
                    }
                    if($verbose)
                        echo 'Tree '.$treeMeta.' updated from cache to '.$treeJSON.', freshness: '.$treeMeta.EOL;
                }
                else
                    $res = false;
            }
            //If everything was found in cache, update return true. Else false.
            if($verbose)
                echo ($res)? 'All trees are initiated!'.EOL : 'Not all trees are initiated!'.EOL ;
            return $res;
        }


        /** Tries to update the cache.
         *
         * @param array $params of the form:
         *                      'remove' => mixed, if set will remove from cache, otherwise add to cache
         *
         * @returns true on success, false on failure
         *
         */
        function updateCache( array $params = []){
            if(!$this->useCache)
                return false;

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            //Removes the tree from cache
            if(isset($params['remove'])){
                if(!$test){
                    $this->RedisHandler->call('del',['_tree_'.$params['remove'],'_tree_meta_'.$params['remove']]);
                }
                if($verbose)
                    echo 'Removing cache of tree '.$params['remove'].EOL;
            }
            //Updates cache with current trees
            else{
                foreach($this->treeNames as $treeName){
                    $treeJSON = json_encode($this->assocTreeArrays[$treeName]);
                    $treeMeta = $this->lastUpdateTimes[$treeName];
                    $treeMetaOld = $this->RedisHandler->call('get','_tree_meta_'.$treeName);
                    if($treeMeta > $treeMetaOld){
                        if(!$test){
                            $this->RedisHandler->call('set',['_tree_'.$treeName,$treeJSON]);
                            $this->RedisHandler->call('set',['_tree_meta_'.$treeName,$treeMeta]);
                        }
                        if($verbose)
                            echo 'Updating cache of tree '.$treeName.' to '.$treeJSON.' at '.$treeMeta.EOL;
                    }
                    else{
                        if($verbose)
                            echo 'Tree cache '.$treeName.' is up to date!'.EOL;
                    }
                }
            }
            return true;
        }

        /** Gets a subtree by ID.
         * The offset is not returned - the user can remember that the offset always equals the $nodeID they provided
         *
         * @param string $treeName Name of tree to get subtree of
         * @param array $params of the form:
         *                              'returnType' string default 'euler' - The resulting tree, either 'euler' (default) or 'assoc'.
         *                              'nodeID' int default 0 - ID of the node to get subtree of. If it's 0, returns the whole tree.
         *
         * @returns array A Euler/Associated tree array
         *
         */
        function getSubtreeByID(string $treeName, array $params = []){
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            isset($params['returnType'])?
                $returnType = $params['returnType'] : $returnType = 'euler';
            isset($params['nodeID'])?
                $nodeID = $params['nodeID'] : $nodeID = 0;

            if(!isset($this->treeArrays[$treeName]))
                return 0;
            $eulerArray = $this->treeArrays[$treeName];
            //If we are returning a subtree, calculate new edges. Else, no need to waste time on adding 0's everywhere.
            if($nodeID != 0){
                if(!isset($eulerArray[$nodeID]))
                    return 0;

                /* ALL THE FOLLOWING IS DONE ONLY TO CALCULATE THE OFFSET CORRECTION*/
                $offsetChainToRoot = [];
                //Variables that need to be added for function sig
                $trueOffsetStart = 0;
                $edgeOffset = 0;
                //Get an assoc tree
                $hybridTree = [];
                $count = 0;
                $this->traverseChildrenAndCalcWeight($this->assocTreeArrays[$treeName], $hybridTree, key($this->assocTreeArrays[$treeName]), $count);
                $childNum = 0;
                $this->findNthChildChain($hybridTree,$this->treeArrays[$treeName],$nodeID,$childNum,$offsetChainToRoot,$trueOffsetStart,$edgeOffset);
                /* ALL THE ABOVE WAS DONE ONLY TO CALCULATE THE OFFSET CORRECTION*/
                $offsetCorrection = count($offsetChainToRoot)-1;

                $tempEulerArray = [];
                $childrenNumber = ($this->treeArrays[$treeName][$nodeID]['largestEdge'] - $this->treeArrays[$treeName][$nodeID]['smallestEdge'] - 1)/2;
                if($verbose)
                    echo 'Number of children for node '.$nodeID.' in tree '.$treeName.' is '.$childrenNumber.EOL;
                //The new root is the given node
                $tempEulerArray[0] = [
                    'content' => $this->treeArrays[$treeName][$nodeID]['content'],
                    'smallestEdge' => min(1,$childrenNumber),
                    'largestEdge' => max(($childrenNumber)*2,0)
                ];
                for($i = $nodeID+1; $i<$nodeID+$childrenNumber+1; $i++){
                    $tempEulerArray[$i-$nodeID] = [
                        'content' => $this->treeArrays[$treeName][$i]['content'],
                        'smallestEdge' => $this->treeArrays[$treeName][$i]['smallestEdge'] - ($nodeID*2 - $offsetCorrection),
                        'largestEdge' => $this->treeArrays[$treeName][$i]['largestEdge'] - ($nodeID*2 - $offsetCorrection)
                    ];
                }
                $eulerArray = $tempEulerArray;
            }
            if($verbose){
                echo 'Resulting euler array for '.$nodeID.' in tree '.$treeName.' is :';
                var_dump($eulerArray);
            }

            switch($returnType){
                case 'euler':
                    return $eulerArray;
                    break;
                case 'assoc':
                    return $this->eulerToAssoc($eulerArray);
                    break;
                case 'numbered':
                    return $this->assocToNumbered($this->eulerToAssoc($eulerArray));
                    break;
                default:
                    return $eulerArray;
            }
        }

        //Finds the offset of the n-th child in a hybrid tree (an assoc tree with numbered vertices) - supports link/cut node functions.
        private function findNthChildChain(
            $hybridTree,
            $eulerTree,
            $targetID,
            $n,
            &$offsetChainToRoot,
            &$trueOffsetStart,
            &$edgeOffset
            ){
            foreach($hybridTree as $node){
                //Push current ID to the chain
                array_push($offsetChainToRoot,$node['ID']);
                //See if you need to go down the chain
                if($node['ID']!=$targetID){
                    $lastChildKey = '';
                    foreach($node['children'] as $key=>$child){
                        //If we found the right node, go to it
                        if($child['ID']==$targetID){
                            $this->findNthChildChain([$key=>$child],$eulerTree,$targetID,$n,$offsetChainToRoot,$trueOffsetStart,$edgeOffset);
                            return;
                        };
                        //If we shot over the $targetID, go to the largest node that didn't overshoot it.
                        if($child['ID']>$targetID){
                            $this->findNthChildChain([$lastChildKey => $node['children'][$lastChildKey]],$eulerTree,$targetID,$n,$offsetChainToRoot,$trueOffsetStart,$edgeOffset);

                            return;
                        };
                        $lastChildKey = $key;
                    }
                    //Maybe our node is larger than all the child nodes (is a child of the rightmost node)
                    if($node['children'] !== []){
                        $this->findNthChildChain([$lastChildKey => $node['children'][$lastChildKey]],$eulerTree,$targetID,$n,$offsetChainToRoot,$trueOffsetStart,$edgeOffset);
                        return;
                    }
                }
                //If not, it means we need to find the true offset
                $i = 0;
                foreach($node['children'] as $child){
                    if($i++<$n){
                        $childSubtreeWeight = ($eulerTree[$child['ID']]['largestEdge'] - $eulerTree[$child['ID']]['smallestEdge'] + 1)/2;
                        $trueOffsetStart += $childSubtreeWeight;
                        //echo 'True offset after child '.$child['ID'].': '.$trueOffsetStart.EOL;
                        //echo 'Child '.$child['ID'].' details:';
                        //var_dump($eulerTree[$child['ID']]);
                        $edgeOffset += $childSubtreeWeight*2;
                    }
                    else{
                        return;
                    }
                }
            }
        }

        /** Add a new node or subtree as the child(ren) of an existing one.
         * @param string $treeName Name of the tree to link nodes to
         * @param array $newNodes format is either euler or assoc, but it MUST be valid!
         * @param int $targetID Existing node is selected by ID. Add as n-th child. (default - first).
         * @param array $params of the form:
         *              [
         *               'childNum' => Which child to attach the nodes to. Default 0 -  Means it will be the leftmost (first).
         *               'updateDB'=> If true, will update the DB.
         *              ]
         *
         * @return int 0 on success
         *             1 If the tree or specified node do not exist
         */
        function linkNodesToID(string $treeName, array $newNodes, int $targetID, array $params = []){

            //Obviously we need an existing tree to link to
            if(array_search($treeName,$this->treeNames) === false)
                return 1;
            if(!isset($this->treeArrays[$treeName][$targetID]))
                return 1;

            //Set defaults
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            if(!isset($params['childNum']))
                $childNum = 0;
            else{
                $childNum = $params['childNum'];
            }

            if(!isset($params['updateDB'])){
                $updateDB = false;
                $tname = '';
                $metaname = '';
            }
            else{
                $updateDB = $params['updateDB'];
                $tname = $this->SQLHandler->getSQLPrefix().strtoupper($treeName.'_TREE');
                $metaname = $tname.'_META';
            }

            if($this->getTreeType($newNodes)){
                $eulerNodes = $newNodes;
            }
            else{
                $eulerNodes = $this->assocToEuler($newNodes);
            }

            //Number of nodes we added
            $offset = count($eulerNodes);

            //Chain of nodes from our new child to root - where only the largestEdge is updated.
            //The rest of the larger nodes are updated normally.
            $offsetChainToRoot = [];
            $trueOffsetStart = $targetID;
            $edgeOffset = $this->treeArrays[$treeName][$targetID]['smallestEdge']+1;

            //Get an assoc tree
            $hybridTree = [];
            $count = 0;
            $this->traverseChildrenAndCalcWeight($this->assocTreeArrays[$treeName], $hybridTree, key($this->assocTreeArrays[$treeName]), $count);
            $this->findNthChildChain($hybridTree,$this->treeArrays[$treeName],$targetID,$childNum,$offsetChainToRoot,$trueOffsetStart,$edgeOffset);

            //Now that we have the chain of IDs which only warrant a largest chain increase, and the true offset, we can finish
            $tempArr = $this->treeArrays[$treeName];
            //Update the largest edge in the chain we found to increase according to number of edges added (twice the num of nodes)
            foreach($offsetChainToRoot as $k=>$id){
                $tempArr[$id]['largestEdge'] += $offset*2;
                if($updateDB){
                    $offsetChainToRoot[$k] = ['ID',$id,'='];
                }
            }
            if($updateDB){
                array_push($offsetChainToRoot,'OR');
                $this->SQLHandler->updateTable($tname,
                    ['largestEdge = largestEdge + '.(string)($offset*2)],
                    $offsetChainToRoot,
                    ['test'=>$test,'verbose'=>$verbose]
                );
            }

            //Update all IDs larger than trueOffsetStart
            //var_dump($tempArr);
            $tempCount = count($this->treeArrays[$treeName]);
            for($i = $trueOffsetStart+1; $i<$tempCount; $i++){
                $tempArr[$i+$offset] = array();
                $tempArr[$i+$offset]['content'] = $this->treeArrays[$treeName][$i]['content'];
                $tempArr[$i+$offset]['smallestEdge'] = $this->treeArrays[$treeName][$i]['smallestEdge'] + $offset*2;
                $tempArr[$i+$offset]['largestEdge'] = $this->treeArrays[$treeName][$i]['largestEdge'] + $offset*2;
            }
            if($updateDB){
                $this->SQLHandler->updateTable($tname,
                    [
                        'ID = ID + '.(string)($offset),
                        'smallestEdge = smallestEdge + '.(string)($offset*2),
                        'largestEdge = largestEdge + '.(string)($offset*2)
                    ],
                    ['ID',$trueOffsetStart,'>'],
                    ['orderBy'=>'ID','orderType'=>'desc','test'=>$test,'verbose'=>$verbose]
                );
            }

            $insertArray = [];
            //var_dump($eulerNodes);
            foreach($eulerNodes as $key=>$node){
                $node['smallestEdge'] += $edgeOffset;
                $node['largestEdge'] += $edgeOffset;
                //God I hate this "root", thinking itself "special"
                if($key == 0){
                    //Edge case that happens with a single node
                    if($node['smallestEdge'] !=$edgeOffset )
                        $node['smallestEdge'] -= 1;
                    $node['largestEdge'] += 1;
                }
                $tempArr[$key+1+$trueOffsetStart] = $node;
                if($updateDB)
                    array_push($insertArray,[$key+1+$trueOffsetStart,[$node['content'],"STRING"],$node['smallestEdge'],$node['largestEdge']]);
            }
            if($updateDB){
                $this->SQLHandler->insertIntoTable($tname,
                    ['ID', 'content', 'smallestEdge', 'largestEdge'],
                    $insertArray,
                    ['test'=>$test,'verbose'=>$verbose]
                );
                $this->SQLHandler->updateTable($metaname,
                    ['settingValue = '.(string)(time())],
                    ['settingKey','_Last_Updated','='],
                    ['test'=>$test,'verbose'=>$verbose]
                );
            }

            //var_dump($tempArr);
            if(!$test){
                $this->treeArrays[$treeName] = $tempArr;
                $this->assocTreeArrays[$treeName] = $this->eulerToAssoc($tempArr);
                $this->numberedTreeArrays[$treeName] = $this->assocToNumbered($this->assocTreeArrays[$treeName]);
            }
            if($verbose){
                echo 'Euler and Assoc arrays:'.EOL;
                var_dump($tempArr);
                var_dump($this->eulerToAssoc($tempArr));
                var_dump($this->assocToNumbered($this->eulerToAssoc($tempArr)));
            }

            //Update the cache
            $this->updateCache(['test'=>$test,'verbose'=>$verbose]);
            return 0;
        }

        /** Cuts nodes out of a tree, by ID. Potentially links the cut node, according to $params.
         *  Always returns cut tree.
         *
         * @param string $treeName Name of the tree to cut nodes from
         * @param int $targetID Existing node is selected by ID.
         * @param array $params of the form:
         *              [
         *               'link' => If it's set. The function called is
         *                      linkNodesToID($link[0],$removedSubtree,$link[1],['childNum'=>$link[2],'updateDB'=>$updateDB])
         *               'updateDB'=> If true, will update the DB.
         *              ]
         *
         * @returns mixed
         *              The cut sub-tree, depending on returnType, if 'link' is not set
         *              0 If link is set and the linking succeeds
         *              1 If the tree to cut from does not exist
         *              2 If the tree to link to does not exist
         *              3 If you try to link to one of the nodes you removed in the same tree
         *              4 DB Error
         */
        function cutNodesByID(string $treeName, int $targetID, array $params = []){

            //Obviously we need an existing tree to cut from
            if(array_search($treeName,$this->treeNames) === false)
                return 1;
            if(!isset($this->treeArrays[$treeName][$targetID]))
                return 1;

            //Set defaults
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            if(!isset($params['link']))
                $link = false;
            else{
                $link = $params['link'];
            }

            if(!isset($params['returnType']))
                $returnType = 'assoc';
            else
                $returnType = $params['returnType'];

            if(!isset($params['updateDB'])){
                $updateDB = false;
                $tname = '';
                $metaname = '';
            }
            else{
                $updateDB = $params['updateDB'];
                $tname = $this->SQLHandler->getSQLPrefix().strtoupper($treeName.'_TREE');
                $metaname = $tname.'_META';
            }

            //In case of linking, make sure the tree exists
            if($link)
                if(array_search($link[0],$this->treeNames) === false)
                    return 2;

            if($targetID == 0){
                $res = $this->treeArrays[$treeName];
                //Link tree if needed, then delete it.
                if(!$test){
                    unset($this->treeNames[array_search($treeName,$this->treeNames)]);
                    unset($this->lastUpdateTimes[$treeName]);
                    unset($this->treeArrays[$treeName]);
                    unset($this->assocTreeArrays[$treeName]);
                    unset($this->numberedTreeArrays[$treeName]);
                    $this->updateCache(['remove'=>$treeName,'test'=>$test,'verbose'=>$verbose]);
                }
                if($verbose)
                    echo 'Deleting tree '.$treeName.'!'.EOL;

                $res = $this->updateDB($treeName,['onlyEmpty'=>false,'override'=>true,'test'=>$test,'verbose'=>$verbose]);
                return $res===true ? 0 : 4;
            }

            //Number of nodes we are removing
            $offset =
                ($this->treeArrays[$treeName][$targetID]['largestEdge'] - $this->treeArrays[$treeName][$targetID]['smallestEdge']+1)/2;

            //Chain of nodes from our new child to root - where only the largestEdge is updated.
            //The rest of the larger nodes are updated normally.
            $offsetChainToRoot = [];
            $trueOffsetStart = $targetID;
            $edgeOffset = $offset*2;

            //Get an assoc tree
            $hybridTree = [];
            $count = 0;
            $this->traverseChildrenAndCalcWeight($this->assocTreeArrays[$treeName], $hybridTree, key($this->assocTreeArrays[$treeName]), $count);
            $childNum = 0;
            $this->findNthChildChain($hybridTree,$this->treeArrays[$treeName],$targetID,$childNum,$offsetChainToRoot,$trueOffsetStart,$edgeOffset);

            //Now that we have the chain of IDs which only warrant a largest chain increase, and the true offset, we can finish
            $tempArr = $this->treeArrays[$treeName];


            //Update the largest edge in the chain we found to decrease according to number of edges to be removed (last node in chain gets removed anyhow)
            foreach($offsetChainToRoot as $k=>$id){
                $tempArr[$id]['largestEdge'] -= $edgeOffset;
                if($updateDB){
                    $offsetChainToRoot[$k] = ['ID',$id,'='];
                }
            }
            if($updateDB){
                array_push($offsetChainToRoot,'OR');
                $this->SQLHandler->updateTable($tname,
                    ['largestEdge = largestEdge - '.(string)($edgeOffset)],
                    $offsetChainToRoot,
                    ['test'=>$test,'verbose'=>$verbose]
                );
            }

            //Remove all nodes that need be removed
            for($i = $targetID; $i<$targetID+$offset; $i++){
                unset($tempArr[$i]);
            }
            if($updateDB){
                $this->SQLHandler->deleteFromTable($tname,
                    [['ID', $targetID, '>='],['ID', $targetID+$offset, '<'],'AND'],
                    ['test'=>$test,'verbose'=>$verbose]
                );
                $this->SQLHandler->updateTable($metaname,
                    ['settingValue = '.(string)(time())],
                    ['settingKey','_Last_Updated','='],
                    ['test'=>$test,'verbose'=>$verbose]
                );
            }

            //Update all IDs larger than trueOffsetStart
            //var_dump($tempArr);
            $tempCount = count($this->treeArrays[$treeName]);
            for($i = $targetID+$offset; $i<$tempCount; $i++){
                $tempArr[$i-$offset] = array();
                $tempArr[$i-$offset]['content'] = $this->treeArrays[$treeName][$i]['content'];
                $tempArr[$i-$offset]['smallestEdge'] = $this->treeArrays[$treeName][$i]['smallestEdge'] - $edgeOffset;
                $tempArr[$i-$offset]['largestEdge'] = $this->treeArrays[$treeName][$i]['largestEdge'] - $edgeOffset;
                unset($tempArr[$i]);
            }
            if($updateDB){
                $this->SQLHandler->updateTable($tname,
                    [
                        'ID = ID - '.(string)($offset),
                        'smallestEdge = smallestEdge - '.(string)($edgeOffset),
                        'largestEdge = largestEdge - '.(string)($edgeOffset)
                    ],
                    ['ID',$trueOffsetStart,'>'],
                    ['test'=>$test,'verbose'=>$verbose]
                );
            }

            //Save subtree to be linked if we are linking the content later
            $removedSubtree = $this->getSubtreeByID($treeName,['returnType'=>$returnType,'nodeID'=>$targetID,'test'=>$test,'verbose'=>$verbose]);

            //var_dump($tempArr);
            if(!$test){
                $this->treeArrays[$treeName] = $tempArr;
                $this->assocTreeArrays[$treeName] = $this->eulerToAssoc($tempArr);
                $this->numberedTreeArrays[$treeName] = $this->assocToNumbered($this->assocTreeArrays[$treeName]);
            }
            if($verbose){
                echo 'Euler and Assoc arrays:'.EOL;
                var_dump($tempArr);
                var_dump($this->eulerToAssoc($tempArr));
                var_dump($this->assocToNumbered($this->eulerToAssoc($tempArr)));
            }

            //Link content if provided proper parameters
            if($link){
                if($link[0] == '')
                    $link[0] = $treeName;
                //If we are testing, we need to do it in an actual temp tree that matches $tempArr
                if($test){
                    array_push($this->treeNames,'@tempTree');
                    $this->treeArrays['@tempTree'] = ($link[0] == $treeName)? $tempArr : $this->treeArrays[$link[0]];
                    $this->assocTreeArrays['@tempTree'] =  ($link[0] == $treeName)? $this->eulerToAssoc($tempArr) : $this->assocTreeArrays[$link[0]];
                    $this->numberedTreeArrays['@tempTree'] = $this->assocToNumbered($this->assocTreeArrays['@tempTree']);
                    $this->lastUpdateTimes['@tempTree'] = time();
                    $this->isInitArray['@tempTree'] = true;
                    $this->isPrivate['@tempTree'] = false;
                    //If we are linking to this same tree:
                    if(($link[0] == $treeName)){
                        if( ($link[1]>=$targetID)){
                            //Trying to link to the removed content would generate an error
                            if($link[1]<$targetID+$offset)
                                return 3;
                            //Update target ID if it's was affected by the removal
                            $link[1] -= $offset;
                        }
                    }
                    $link[2]['updateDB'] = false;
                    $res = $this->linkNodesToID('@tempTree',$removedSubtree,$link[1],['childNum'=>$link[2],'test'=>true,'updateDB'=>$updateDB,'verbose'=>$verbose]);
                    //Remove the temp tree
                    $this->removeTrees(['@tempTree'=>['updateDB'=>false,'onlyEmpty'=>false]],['test'=>false]);
                    //The tree has to exist - so only the node may not exist!
                    return ($res == 0)? 0 : 2;
                }

                if($updateDB)
                    $link[2]['updateDB'] = true;
                //If we are linking to this same tree:
                if(($link[0] == $treeName)){
                    if( ($link[1]>=$targetID)){
                        //Trying to link to the removed content would generate an error
                        if($link[1]<$targetID+$offset)
                            return 3;
                        //Update target ID if it's was affected by the removal
                        $link[1] -= $offset;
                    }
                }
                $res = $this->linkNodesToID($link[0],$removedSubtree,$link[1],['childNum'=>$link[2],'updateDB'=>$updateDB]);
                //The tree has to exist - so only the node may not exist!
                return ($res == 0)? 0 : 2;
            }

            return $removedSubtree;
        }

        /** Returns all Assoc trees
         * @returns string[] Array of available Assoc trees
         */
        function getAssocTrees(){
            return $this->assocTreeArrays;
        }

        /** Returns all Euler trees
         * @returns string[] Array of available Euler trees
         */
        function getTrees(){
            return $this->treeArrays;
        }

        /** Returns all Numbered trees
         * @returns string[] Array of available Numbered trees
         */
        function getNumberedTrees(){
            return $this->numberedTreeArrays;
        }

        /**
         * @param string $name
         * @returns array[] Assoc tree representation
         */
        function getAssocTree(string $name){
            return isset($this->assocTreeArrays[$name])? $this->assocTreeArrays[$name] : null;
        }


        /**
         * @param string $name
         * @returns array[] Euler tree representation
         */
        function getTree(string $name){
            return isset($this->treeArrays[$name])? $this->treeArrays[$name] : null;
        }

        /**
         * @param string $name
         * @returns array[] Numbered tree representation
         */
        function getNumberedTree(string $name){
            return isset($this->numberedTreeArrays[$name])? $this->numberedTreeArrays[$name] : null;
        }

        /** Gets all available trees from the tree map
         * @returns string[] Array of all available trees (in the DB)
        */
        function getTreeMap(){
            $res = [];

            $mapname = strtoupper($this->SQLHandler->getSQLPrefix().'TREE_MAP');
            $trees = $this->SQLHandler->selectFromTable($mapname,[],[],['test'=>false]);
            foreach($trees as $treeArray){
                array_push($res,$treeArray['treeName']);
            }

            return $res;
        }
    }
}
?>