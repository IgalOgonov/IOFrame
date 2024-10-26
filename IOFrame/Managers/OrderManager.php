<?php
namespace IOFrame\Managers{

    use IOFrame\Handlers\SettingsHandler;

    define('IOFrameManagersOrderManager',true);

    /*  This class handles orders - originally defined in PluginHandler. The meaning of order here is not as in shopping
     *  order, but "order of things".
     *
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */

    class OrderManager extends \IOFrame\Abstract\DBWithCache
    {


        /**
         * @var string Used for default naming of cache names, table names, local file names..
         */
        protected string $name = '';

        /**
         * @var string Default separator for items inside order. Can be changed to something else.
         */
        protected string $separator = ',';

        /**
         * @var ?string The local file URL - required to do local operations.
         *             In this URL, a folder of the name $name.'_order' will be created if it doesnt exist, with 'order' inside it.
         */
        protected ?string $localURL = null;
        /**
         * @var bool Whether the order should be handled locally be default.
         *             Defaults to true if $localURL is provided at construction, but can be still overridden.
         */
        protected bool $local = false;

        /**
         * @var string The table name - required to do db operations.
         *              Must be a table that has 2 columns $columnNames[0] as key column, $columnNames[1] as value columns.
         *              The key may be a VARCHAR of appropriate length, the value should be at least TEXT
         *              Saved in this table, under the key $name.'_order', with the order as the value.
         */
        protected ?string $tableName = null;

        /**
         * @var string[] The name under which the order will be saved in the target table - $columnNames[0] => key, $columnNames[1] => value
         */
        protected array $columnNames = [];

        /**
         * @var array|string Identifying string in the DB. Defaults to $name.'_order';
         */
        protected array|string $columnIdentifier = [];

        /**
         * @var ?string The cache name - used as a prefix for the full cache name - $cacheName. Defaults to $name.'_order'.
         */
        protected ?string $cacheName = null;

        /** Standard constructor
         *
         * Constructs an instance of the class, getting the main settings file and an existing DB connection, or generating
         * such a connection itself.
         *
         * @param SettingsHandler $settings The standard settings object
         * @param array $params - All parameters share the name/type of the class variables
         */
        function __construct(\IOFrame\Handlers\SettingsHandler $settings, array $params = []){

            parent::__construct($settings,$params);

            if(isset($params['name']))
                $this->name = $params['name'];

            if(isset($params['separator']))
                $this->separator = $params['separator'];

            //If we are provided a db table name, set it
            if(isset($params['cacheName'])){
                $this->cacheName = $params['cacheName'];
            }
            else
                $this->cacheName = $this->name.'_order' ;

            //If we are provided a local URL, set it
            if(isset($params['localURL'])){
                $this->localURL = $params['localURL'];
                $this->local = true;
            }

            if(isset($params['local']))
                $this->local = $params['local'];

            //If we are provided a db table name
            if( isset($params['tableName']) && $params['columnNames'] && is_array($params['columnNames']) ){
                $this->columnNames = $params['columnNames'];
                $this->tableName = $params['tableName'];
                $this->columnIdentifier = $params['columnIdentifier'] ?? $this->name . '_order';
            }

        }

        //Getters and Setters

        function getName(){
            return $this->name;
        }

        function setName(string $name): void {
            $this->name =$name;
        }

        function getSeparator(){
            return $this->separator;
        }

        function setSeparator(string $separator): void {
            $this->separator =$separator;
        }

        function getTableName(){
            return $this->tableName;
        }

        function setTableName(string $tableName): void {
            $this->tableName =$tableName;
        }

        function getColumnNames(): array {
            return $this->columnNames;
        }

        function setColumnNames(array $columnNames): void {
            $this->columnNames =$columnNames;
        }

        function getCacheName(){
            return $this->cacheName;
        }

        function setCacheName(string $cacheName): void {
            $this->cacheName =$cacheName;
        }

        function getLocalURL(){
            return $this->localURL;
        }

        function setLocalURL(string $localURL): void {
            $this->localURL =$localURL;
        }

        function getLocal(){
            return $this->local;
        }

        function setLocal(bool $local): void {
            $this->local = $local;
        }

        /** Tries to get the local plugin order.
         * @param array $params Parameters of the form:
         *              'createNew' - bool, default false - Whether to create folder/file if they do not exist.
         * @return false|string Returns false if the order doesn't exist, a string of the order otherwise (or if parameter createNew is true)
         * */
        protected function getLocalOrder(array $params = []): mixed {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $createNew = $params['createNew'] ?? false;

            //If we cannot use the local file system at all..
            if( $this->localURL == null )
                return false;

            $url = $this->localURL.'/'.$this->name.'_order';
            //If order file does not exist,
            if( !is_dir($url) || !is_file($url.'/order') ){
                if($verbose)
                    echo 'Requested directory '.$url.' does not exist!'.EOL;
                if($createNew){
                    //If we can create the dir, do so, else return
                    if(!is_dir($url)){
                        if($verbose)
                            echo 'Creating directory '.$url.EOL;
                        if(!$test)
                            if(!mkdir($url)){
                                $this->logger->error('Could not create order directory',['name'=>$this->name,'localUrl'=>$this->localURL,'url'=>$url,]);
                                return false;
                            }
                    }
                    //If we are here, ensure the file exists, if no create it
                    if(!is_file($this->localURL.'/'.$this->name.'_order/order')){
                        if($verbose)
                            echo 'Creating file '.$url.'/order'.EOL;
                        if(!$test)
                            fclose(fopen($url.'/order','w'));
                    }
                    //The order has to be empty at this point
                    $order = '';
                }
                else{
                    $order = false;
                }
            }
            //If everything is fine, get the order
            else{
                try{
                    $order = \IOFrame\Util\FileSystemFunctions::readFileWaitMutex($this->localURL.'/'.$this->name.'_order/','order');
                }catch (\Exception $e){
                    $this->logger->error('Could not get local order, exception'.$e->getMessage(),['name'=>$this->name,'localUrl'=>$this->localURL,'url'=>$url,'trace'=>$e->getTrace()]);
                }
            }

            return $order ?? false;
        }

        /** Updates order in the cache/DB
         * @param string $newOrder The new order, in form of a string
         * @param array $params Parameters of the form:
         *              'createNew' - bool, default false - Whether to create folder/file if they do not exist.
         *
         * @return bool Whether the file was updated
         *
         */
        protected function updateLocalOrder(string $newOrder, array $params = []): bool {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $createNew = $params['createNew'] ?? false;

            $res = true;

            if($this->localURL == null)
                return false;

            $url = $this->localURL.'/'.$this->name.'_order';

            $filename = 'order';

            if( !is_dir($url) || !is_file($url.'/order') ){
                if($verbose)
                    echo 'Requested directory '.$url.' does not exist!'.EOL;
                if($createNew){
                    //If we can create the dir, do so, else return
                    if(!is_dir($url)){
                        if($verbose)
                            echo 'Creating directory '.$url.EOL;
                        if(!$test)
                            if(!mkdir($url)){
                                $this->logger->error('Could not create order directory',['name'=>$this->name,'localUrl'=>$this->localURL,'url'=>$url,]);
                                return false;
                            }
                    }
                    //If we are here, ensure the file exists, if no create it
                    if(!is_file($this->localURL.'/'.$this->name.'_order/order')){
                        if($verbose)
                            echo 'Creating file '.$url.'/order'.EOL;
                        if(!$test)
                            fclose(fopen($url.'/order','w'));
                    }
                }
                else
                    return false;
            }

            if(!$test){
                try{
                    $res = \IOFrame\Util\FileSystemFunctions::writeFileWaitMutex($url, $filename, $newOrder);
                }catch (\Exception $e){
                    $this->logger->error('Could not update local order, exception'.$e->getMessage(),['name'=>$this->name,'url'=>$url.'/'.$filename,'trace'=>$e->getTrace()]);
                }
            }
            if($verbose)
                echo 'Writing ',$newOrder.' to empty order file'.EOL;

            return $res;
        }

        /** Tries to get the global order.
         * @param array $params
         *              'cacheTTL' - int, default set by handler - How long the order should be cached for, if gotten from the DB.
         * @return mixed Returns false if the order doesn't exist, a string of the order otherwise (or if parameter createNew is true)
         * */
        protected function getGlobalOrder(array $params = []): mixed {

            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            $cacheTTL = $params['cacheTTL'] ?? $this->cacheTTL;

            if($verbose)
                echo 'Trying to get '.$this->cacheName.' order from cache!'.EOL;

            $order = $useCache? $this->RedisManager->call('get',$this->cacheName) : false;

            if($order === false && $this->tableName !== null){

                //Build selection conditions
                $columns = $this->columnNames[0];
                $identifiers = $this->columnIdentifier;
                $conds = [];

                if(gettype($columns) === 'string')
                    $columns = [$columns];
                if(gettype($identifiers) === 'string')
                    $identifiers = [$identifiers];
                foreach($columns as $index => $column){
                    $identifier = $identifiers[$index];
                    if(gettype( $identifier === 'string'))
                        $identifier = [$identifier,'STRING'];
                    $conds[] = [$column, $identifier, '='];
                }
                if(count($conds)>1)
                    $conds[] = 'AND';

                $order = $this->SQLManager->selectFromTable(
                    $this->SQLManager->getSQLPrefix().$this->tableName,
                    $conds,
                    [$this->columnNames[1]],
                    $params
                );

                if(!is_array($order) || !isset($order[0][$this->columnNames[1]])){
                    if(isset($order[0][$this->columnNames[1]])){
                        $this->logger->error('Could not load db order',['name'=>$this->name,'tableName'=>$this->tableName,'column'=>$this->columnNames[1]]);
                    }
                    $order = false;
                }
                else
                    $order = $order[0][$this->columnNames[1]];

                if($useCache && $order){
                    if(!$test)
                        $this->RedisManager->call('set',[$this->cacheName,$order,$cacheTTL]);
                    if($verbose)
                        echo 'Adding '.$this->cacheName.' order to cache!'.EOL;
                }
            }

            return $order;
        }

        /** Updates order in the cache/DB
         * @param string $newOrder The new order, in form of a string
         * @param array $params Parameters of the form:
         *              'useCache' - overrides the classes 'useCache' if provided
         *
         * @return bool Whether the DB was updated
         *
         */
        protected function updateGlobalOrder(string $newOrder, array $params = []): bool {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $rowExists = $params['rowExists'] ?? false;

            //Set defaults
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            //Build selection conditions
            $columns = $this->columnNames[0];
            $identifiers = $this->columnIdentifier;
            $conds = [];

            if(gettype($columns) === 'string')
                $columns = [$columns];
            if(gettype($identifiers) === 'string')
                $identifiers = [$identifiers];
            foreach($columns as $index => $column){
                $identifier = $identifiers[$index];
                if(gettype( $identifier === 'string'))
                    $identifier = [$identifier,'STRING'];
                $conds[] = [$column, $identifier, '='];
            }
            if(count($conds)>1)
                $conds[] = 'AND';

            if($rowExists || $newOrder != '')
                $res = $this->SQLManager->updateTable(
                        $this->SQLManager->getSQLPrefix().$this->tableName,
                        [$this->columnNames[1].' = "'.$newOrder.'"'],
                        $conds,
                        ['test'=>$test,'verbose'=>$verbose]
                    );
            else
                $res = $this->SQLManager->deleteFromTable(
                        $this->SQLManager->getSQLPrefix().$this->tableName,
                        $conds,
                        ['test'=>$test,'verbose'=>$verbose]
                    );

            if(!$res){
                $this->logger->error('Could not update db order',['name'=>$this->name,'tableName'=>$this->tableName,'operation'=>($rowExists || $newOrder != ''? 'update' : 'delete'), 'conditions'=>$conds]);
            }


            if($useCache && $res){
                if(!$test)
                    $this->RedisManager->call('del',[$this->cacheName]);
                if($verbose)
                    echo 'Deleting '.$this->name.' order cache!'.EOL;
            }

            return $res;

        }

        /** Updates order in the cache/DB
         * @param string $newOrder The new order, in form of a string
         * @param array $params Parameters of the form:
         *              'useCache' - overrides the classes 'useCache' if provided
         *
         * @return bool Whether the DB was updated
         *
         */
        protected function createGlobalOrder(string $newOrder, array $params = []): bool {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            //Set defaults
            $useCache = $params['useCache'] ?? $this->defaultSettingsParams['useCache'];

            //Build key conditions
            $columns = $this->columnNames[0];
            $identifiers = $this->columnIdentifier;
            $columnsToAffect = [];
            $valuesToPush = [];

            if(gettype($columns) === 'string')
                $columns = [$columns];
            if(gettype($identifiers) === 'string')
                $identifiers = [$identifiers];

            foreach($columns as $index => $column){
                $identifier = $identifiers[$index];
                if(gettype( $identifier === 'string'))
                    $identifier = [$identifier,'STRING'];
                $columnsToAffect[] = $column;
                $valuesToPush[] = $identifier;
            }
            //Finally, push the order value and the order
            $columnsToAffect[] = $this->columnNames[1];
            $valuesToPush[] = [$newOrder, 'STRING'];

            $res = $this->SQLManager->insertIntoTable(
                    $this->SQLManager->getSQLPrefix().$this->tableName,
                    $columnsToAffect,
                    [$valuesToPush],
                    ['onDuplicateKey'=>true,'test'=>$test,'verbose'=>$verbose]
                );

            if(!$res){
                $this->logger->error('Could not insert db order',['name'=>$this->name,'tableName'=>$this->tableName,'values'=>$valuesToPush]);
            }

            if($useCache && $res){
                if(!$test)
                    $this->RedisManager->call('del',[$this->cacheName]);
                if($verbose)
                    echo 'Deleting '.$this->name.' order cache!'.EOL;
            }

            return $res;

        }

        /** Gets the current order
         * @param array $params Parameters of the form:
         *              'local' - bool, default true - Get order from local file, or from the Cache/DB.
         *              'cacheTTL' - int, default set by handler - How long the order should be cached for, if gotten from the DB.
         * @return array|false|string[] Returns an empty array if 'order' file is empty or doesn't exist, or false on failure.
         *              Otherwise, the order as an array.
         *
         * */
        function getOrder(array $params = []): mixed {

            //Set defaults
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $local = $params['local'] ?? $this->local;

            $res = [];

            //If we are getting the order locally
            if($local){
                $order = $this->getLocalOrder();
            }
            else{
                $order = $this->getGlobalOrder($params);
            }

            if(empty($order)){
                $res = false;
            }
            else{
                $orderArr = explode($this->separator,$order);
                if(is_array($orderArr))
                    $res = $orderArr;
            }
            if($verbose){
                echo $this->name.' order: '.$order.EOL;
            }
            return $res;
        }

        /** Pushes an item to the bottom/top of the order list, if index is -1/-2, respectively, or
         * to the index (pushing everything below it down)
         * @param string $name Item name
         * @param array $params of the form:
         *              'index' - int, default -1 - As described above.
         *              'pureFunc' => bool, default false - If set to true, overrides other modes and simply returns new order.
         *              'local' => bool, default true - Whether to change the order just locally, or globally too.
         *              'backUp' => bool, default false - Back up local file after changing
         *              'order' => string, default false - Allows passing an order you got earlier
         *              'unique' => bool, default true - Whether to only allow unique items in the order
         * @returns int
         *          0 - success
         *          1 - unique is true, and item already exists in order
         *
         *          3 - couldn't read or write file/db
         * */
        function pushToOrder(string $name, array $params = []){
            $res = $this->pushToOrderMultiple([$name],$params);
            return $res[$name] ?? $res;
        }

        /** Pushes multiple items to the bottom/top of the order list, if index is -1/-2, respectively, or
         * to the index (pushing everything below it down). Pushes them according to their order in the input
         *
         * @param string[] $names Ordered array of the items you wish to push
         * @param array $params of the form:
         *              'index' - int, default -1 - As described above.
         *              'pureFunc' => bool, default false - If set to true, overrides other modes and simply returns new order.
         *              'local' => bool, default $this->local - Whether to change the order just locally, or globally too.
         *              'backUp' => bool, default false - Back up local file after changing
         *              'order' => string, default false - Allows passing an order you got earlier
         *              'unique' => bool, default true - Whether to only allow unique items in the order
         *              'rowExists' => bool, default false - indicates that the row we're updating already exists.
         * @returns int[]|string Array of the form:
         *          [$name => code from pushToOrder]
         *          OR
         *          ['pureFunc' and no other errors] New order - CSV string.
         *
        */
        function pushToOrderMultiple(array $names, array $params = []): array|string {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            //Set defaults
            $index = $params['index'] ?? -1;

            $unique = $params['unique'] ?? true;

            $rowExists = $params['rowExists'] ?? false;

            $local = $params['local'] ?? $this->local;

            $order = $params['order'] ?? $this->getOrder($params);

            $pureFunc = $params['pureFunc'] ?? false;
            if($pureFunc)
                $local = false;

            //The string of names we are pushing
            $namesString = implode($this->separator,$names);

            if($verbose)
                echo 'Pushing '.$namesString.' to '.$this->name.' '.($local?'local':'global').' order at index '.$index.EOL;

            //Result, default 3
            $res = [];
            foreach($names as $name){
                $res[$name] = 3;
            }

            //Order could be an array, or an order string we got from earlier
            $order = is_array($order)? implode($this->separator,$order): (empty($order)? '' : $order);

            //If the old order had nothing, we don't have much work to do
            if($order === ''){
                if($local){
                    if($this->updateLocalOrder($namesString,$params))
                        foreach($names as $name){
                            $res[$name] = 0;
                        }
                }
                else{
                    if($rowExists)
                        $dbRes =  $this->updateGlobalOrder($namesString,$params);
                    else
                        $dbRes =  $this->createGlobalOrder($namesString,$params);

                    if($dbRes)
                        foreach($names as $name){
                            $res[$name] = 0;
                        }
                }
                return $res;
            }
            //If we failed to open the order, return the default 3's
            elseif($order === false){
                if($verbose)
                    echo 'failed to open order'.EOL;
                return $res;
            }
            else{
                //If we are enforcing unique values, check for duplicates and remove them
                if($unique){
                    //Make the order array again!
                    $orderArray = explode($this->separator,$order);

                    //For each name we are adding, check if it's in the order - if it is, unset it and set the result to 1
                    foreach($names as $nameIndex=>$name){
                        if(in_array($name,$orderArray)){
                            if($verbose)
                                echo $name.' exists in order!'.EOL;
                            $res[$name] = 1;
                            unset($names[$nameIndex]);
                        }
                    }

                    //Update the nameString to the new (if anything changed) value
                    $namesString = implode($this->separator,$names);

                    //If there are no names left, return
                    if(count($names) == 0)
                        return $res;
                }

                //If the index is smaller than 0, it's either -2 or -1 - in both cases it's easy.
                if($index<0){
                    $order = ($index == -2)?
                         $namesString . $this->separator . $order : $order . $this->separator . $namesString ;
                }
                //Otherwise, insert all the names in the right place.
                else{
                    $order = explode($this->separator,$order);
                    if($index>count($order))
                        $index = count($order);
                    array_splice($order,$index,0,$names);
                    $order = implode($this->separator,$order);
                }
                if($verbose){
                    echo 'New order: '.$order.EOL;
                }

                //Update order
                if($local){
                    if($this->updateLocalOrder($order,$params))
                        foreach($names as $name){
                            $res[$name] = 0;
                        }
                    return $res;
                }
                elseif(!$pureFunc){
                    if($rowExists)
                        $dbRes =  $this->updateGlobalOrder($order,$params);
                    else
                        $dbRes =  $this->createGlobalOrder($order,$params);

                    if($dbRes)
                        foreach($names as $name){
                            $res[$name] = 0;
                        }

                    return $res;
                }
                else{
                    return $order;
                }

            }
        }

        /** Remove an item from the order.
         *
         * @param mixed $target is the index (number) or name of the item, depending on $type. Range is of format '<from>,<to>'
         *                      in case of multiple targets of the same name, removes the first one found.
         * @param string $type is 'index', 'range' or 'name'
         * @param array $params of the form:
         *              'local' => bool, default true - Whether to change the order just locally, or globally too.
         *              'pureFunc' => bool, default false - If set to true, overrides other modes and simply returns new order.
         *              'backUp' => bool, default false - Back up local file after changing
         *              'indexChecksOnly' => bool, default false - if true, will only check $target and return 0 if it's valid
         *              'order' => string, default false - Allows passing an order you got earlier
         * @returns int
         * 0 - success
         * 1 - index or name don't exist ( or order is empty)
         * 2 - incorrect type
         * 3 - couldn't read or write file/db
         * ['pureFunc' and no other errors] New order - CSV string.
         * */
        function removeFromOrder(mixed $target, string $type, array $params = []){
            $res = $this->removeFromOrderMultiple([$target],$type,$params);
            return $res[$target] ?? $res;
        }


        /** Remove an item from the order.
         *
         * @param array $targets Of the type of removFromOrder()
         * @param string $type is 'index', 'range' or 'name'
         * @param array $params of the form:
         *              'local' => bool, default true - Whether to change the order just locally, or globally too.
         *              'pureFunc' => bool, default false - If set to true, overrides other modes and simply returns new order.
         *              'backUp' => bool, default false - Back up local file after changing
         *              'indexChecksOnly' => bool, default false - if true, will only check $targets and return 0 they are valid
         *              'order' => string, default false - Allows passing an order you got earlier
         *              'rowExists' => bool, default false - indicates that the row we're updating already exists.
         * @returns int[]|string Array of the form:
         *          [$target => code from removeFromOrder]
         *          OR
         *          ['pureFunc' and no other errors] New order - CSV string.
         * */
        function removeFromOrderMultiple(array $targets, string $type, array $params = []): array|int|string {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;

            $local = $params['local'] ?? $this->local;

            $rowExists = $params['rowExists'] ?? false;

            $indexChecksOnly = $params['indexChecksOnly'] ?? false;

            $order = $params['order'] ?? $this->getOrder($params);

            $pureFunc = $params['pureFunc'] ?? false;
            if($pureFunc)
                $local = false;

            if($verbose)
                echo $indexChecksOnly ?
                    'Checking '.json_encode($targets).', indexes of type '.$type.', existence in '.$this->name.' order.'.EOL:
                    'Removing items '.json_encode($targets).', index type '.$type.', from '.$this->name.' order.'.EOL;

            //Result, default 3
            $res = [];
            foreach($targets as $target){
                $res[$target] = 3;
            }

            //Order could be an array, or an order string we got from earlier
            $order = is_array($order)? implode($this->separator,$order): $order;

            if($order === ''){
                if($verbose)
                    echo 'Order empty!'.EOL;
                foreach($targets as $target){
                    $res[$target] = 1;
                }
                return $res;
            }
            elseif($order === false){
                if($verbose)
                    echo 'failed to open order!'.EOL;
                return $res;
            }
            else{

                $order = explode($this->separator, $order);

                //Make sure the order was of the right format
                if(!is_array($order)){
                    if($verbose)
                        echo 'Order is not an array!.'.EOL;
                    return $res;
                }

                //Depending on case, make sure target exists and set target from name to index
                switch($type){
                    case 'index':
                        foreach($targets as $targetIndex => $target){
                            if(!isset($order[$target])){
                                if($verbose)
                                    echo 'Index '.$target.' does not exist in order list!'.EOL;
                                $res[$target] = 1;
                                unset($targets[$targetIndex]);
                            }
                        }

                        //If we are only checking indices
                        if($indexChecksOnly){
                            foreach($targets as $target){
                                $res[$target] = 0;
                            }
                            return $res;
                        }

                        //Unset remaining targets from the order
                        foreach($targets as $target)
                            unset($order[$target]);

                        break;
                    case 'name':
                        //A map of index lists
                        $targetsMap = [];
                        //A map of order indexes
                        $orderMap = [];
                        $targetsArray = $targets;
                        //For each target, push its index into the target map
                        foreach($targetsArray as $targetIndex => $target){
                            //Remember - the same target name may appear more than once!
                            if(!isset($targetsMap[$target]))
                                $targetsMap[$target] = [$targetIndex];
                            else
                                $targetsMap[$target][] = $targetIndex;
                        }

                        //For each order item, if it's one of the targets, pop an index
                        foreach($order as $key => $item){
                            if(isset($targetsMap[$item])){
                                $targetIndex = array_pop($targetsMap[$item]);
                                //Unset the index from the list
                                unset($targetsArray[$targetIndex]);
                                $orderMap[$item] = $key;
                            }
                        }

                        //We check whether we 'handled' all the items in targetsArray
                        if(count($targetsArray) != 0){
                            if($verbose)
                                echo 'Indexes '.json_encode($targetsArray).' do not exist in order list!'.EOL;
                            foreach($targetsArray as $targetIndex => $target){
                                $res[$target] = 1;
                                unset($targets[$targetIndex]);
                            }
                        }

                        //If we are only checking indices
                        if($indexChecksOnly){
                            foreach($targets as $target){
                                if($res[$target] === 3)
                                    $res[$target] = 0;
                            }
                            return $res;
                        }

                        //Unset targets
                        foreach($orderMap as $key)
                            unset($order[$key]);

                        break;
                    case 'range':
                        if($verbose)
                            echo 'Unimplemented type sorry mate.'.EOL; // TODO IMPLEMENT
                        return 2;
                    default:
                        if($verbose)
                            echo 'Incorrect type!'.EOL;
                        return 2;
                }

                //If no targets existed, return
                if(count($targets) == 0)
                    return $res;

                $order = implode($this->separator,$order);

                if($local){
                    if($this->updateLocalOrder($order,$params))
                        foreach($targets as $target){
                            $res[$target] = 0;
                        }
                    return $res;
                }
                elseif(!$pureFunc){
                    if($rowExists)
                        $dbRes =  $this->updateGlobalOrder($order,$params);
                    else
                        $dbRes =  $this->createGlobalOrder($order,$params);

                    if($dbRes)
                        foreach($targets as $target){
                            $res[$target] = 0;
                        }
                    return $res;
                }
                else{
                    return $order;
                }
            }
        }

        /** Moves an item from one index in the order list to another,
         * pushing everything it passes 1 space up/down in the opposite direction
         * @param int $from
         * @param int $to
         * @param array $params of the form:
         *              'local' => bool, default true - Whether to change the order just locally, or globally too.
         *              'pureFunc' => bool, default false - If set to true, overrides other modes and simply returns new order.
         *              'backUp' => bool, default false - Back up local file after changing
         *              'indexChecksOnly' => bool, default false - if true, will only check $from/$to and return 0 if they're valid
         *              'order' => string, default false - Allows passing an order you got earlier
         *              'rowExists' => bool, default false - indicates that the row we're updating already exists.
         * @return int|string
         */
        function moveOrder(int $from, int $to, array $params = []): int|string {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            //Set defaults

            $local = $params['local'] ?? $this->local;

            $rowExists = $params['rowExists'] ?? false;

            $indexChecksOnly = $params['indexChecksOnly'] ?? false;

            $order = $params['order'] ?? $this->getOrder($params);

            $pureFunc = $params['pureFunc'] ?? false;
            if($pureFunc)
                $local = false;

            if($verbose)
                echo $indexChecksOnly ?
                    'Checking, indexes '.$from.' and '.$to.', exist in '.$this->name.' order.'.EOL:
                    'Moving item from '.$from.' to '.$to.' at '.$this->name.' order.'.EOL;

            $order = is_array($order)? implode($this->separator,$order): $order;

            $oldOrder = $order;

            //If order file is empty or cannot be opened, it is of no interest
            if($order === ''){
                if($verbose)
                    echo 'Order file empty'.EOL;
                return 1;
            }
            elseif($order === false){
                if($verbose)
                    echo 'failed to open order file'.EOL;
                return 2;
            }
            //If all went well..
            else{
                $order = explode($this->separator, $order);

                //Make sure the order was of the right format
                if(!is_array($order)){
                    if($verbose)
                        echo 'Order is not an array!.'.EOL;
                    return 2;
                }

                //Make sure from and to are set
                if(!isset($order[$from]) || !isset($order[$to])){
                    if($verbose)
                        echo $from.' or '.$to.' indexes are not set in order array.'.EOL;
                    return 1;
                }

                //If we are only checking indices
                if($indexChecksOnly)
                    return 0;

                //You really think someone would do this?
                if($from == $to)
                    return 0;

                //Save the name!
                $fromName = $order[$from];
                array_splice($order,$from,1);
                array_splice($order,$to,0,$fromName);
                $order = implode($this->separator,$order);

                if($verbose)
                    echo 'New order: '.$order.EOL;

                if($local){
                    if($this->updateLocalOrder($order,$params))
                        return 0;
                    else{
                        return 3;
                    }
                }
                elseif(!$pureFunc){
                    if($rowExists)
                        $dbRes =  $this->updateGlobalOrder($order,$params);
                    else
                        $dbRes =  $this->createGlobalOrder($order,$params);

                    if($dbRes)
                        return 0;
                    else{
                        $this->updateLocalOrder($oldOrder,$params);
                        return 3;
                    }
                }
                else
                    return $order;
            }
        }

        /** Swaps 2 items in the order
         * @param int $num1
         * @param int $num2
         * @param array $params of the form:
         *              'local' => bool, default true - Whether to change the order just locally, or globally too.
         *              'pureFunc' => bool, default false - If set to true, overrides other modes and simply returns new order.
         *              'backUp' => bool, default false - Back up local file after changing
         *              'indexChecksOnly' => bool, default false - if true, will only check $num1/$num2 and return 0 if they're valid
         *              'order' => string, default false - Allows passing an order you got earlier
         *              'rowExists' => bool, default false - indicates that the row we're updating already exists.
         * @return int|string
         */
        function swapOrder(int $num1,int $num2, array $params = []): int|string {
            $test = $params['test']?? false;
            $verbose = $params['verbose'] ?? $test;
            //Set defaults

            $local = $params['local'] ?? $this->local;

            $rowExists = $params['rowExists'] ?? false;

            $indexChecksOnly = $params['indexChecksOnly'] ?? false;

            $order = $params['order'] ?? $this->getOrder($params);

            $pureFunc = $params['pureFunc'] ?? false;
            if($pureFunc)
                $local = false;

            $order = is_array($order)? implode($this->separator,$order): $order;

            $oldOrder = $order;

            if($verbose)
                echo $indexChecksOnly ?
                    'Checking, indexes '.$num1.' and '.$num1.', exist in '.$this->name.' order.'.EOL:
                    'Swapping items '.$num1.' and '.$num2.' at '.$this->name.' order.'.EOL;

            //Make sure order isnt empty
            if($order === ''){
                if($verbose)
                    echo 'Order file empty'.EOL;
                return 1;
            }
            //Make sure we opened the file
            elseif($order === false){
                if($verbose)
                    echo 'failed to open order file'.EOL;
                return 2;
            }
            else{
                $order = explode($this->separator, $order);
                //Make sure order is an array
                if(!is_array($order)){
                    if($verbose)
                        echo 'Order is not an array!.'.EOL;
                    return 2;
                }
                //Make sure both numbers are set
                if(!isset($order[$num1]) || !isset($order[$num2])){
                    if($verbose)
                        echo $num1.' or '.$num2.' indexes are not set in order array.'.EOL;
                    return 1;
                }

                //If we are only checking indices
                if($indexChecksOnly)
                    return 0;

                //Handle edge cases and sort the 2 numbers - num1 is now always smaller
                if($num1 == $num2)
                    return 0;
                if($num1>$num2){
                    $temp = $num1;
                    $num1 = $num2;
                    $num2 = $temp;
                }

                //Swap the plugins
                $temp = $order[$num1];
                $order[$num1] = $order[$num2];
                $order[$num2] = $temp;
                $order = implode($this->separator,$order);

                if($verbose)
                    echo 'New order: '.$order.EOL;

                if($local){
                    if($this->updateLocalOrder($order,$params))
                        return 0;
                    else{
                        return 3;
                    }
                }
                elseif(!$pureFunc){
                    if($rowExists)
                        $dbRes =  $this->updateGlobalOrder($order,$params);
                    else
                        $dbRes =  $this->createGlobalOrder($order,$params);

                    if($dbRes)
                        return 0;
                    else{
                        $this->updateLocalOrder($oldOrder,$params);
                        return 3;
                    }
                }
                else
                    return $order;
            }

        }


    }
}