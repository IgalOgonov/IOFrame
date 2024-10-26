/** Common Site functions
 * **/
const indexedDBInterface = {
    data: function(){
        return {
        }
    },
    methods: {
        /** Promise that gets items from a specific DB
         *  @param {string|IDBOpenDBRequest} Either - database name, or an already open request to a database.
         *  @param {string[]} items - items to get from the database(by ID)
         *  @param {object} params Parameters of the form:
         *              {
         *                  //In the function that return "outcome", an alternative function needs to return value as well,
         *                  //where the value determines whether the function keeps running or resolves (although it can be left undefined, and still resolves/return)
         *                  db: {
         *                      version:   int, default 1. If opening a new IDBOpenDBRequest, this version will be used.
         *                      name:      string, defaults to first argument. Required if you pass IDBOpenDBRequest as the first argument.
         *                      noDB:      function to be called if IndexedDB isn't supported on this system.
         *                                 By default, prints a warning to the console and resolves with object {outcome:false,reason:"no-db"}.
         *                      onblocked: function to be called as the IDBOpenDBRequest property of the same name.
         *                                 By default, prints a warning to the console and resolves with object {outcome:false,reason:"db-blocked"}.
         *                      onerror:   function to be called as the IDBOpenDBRequest property of the same name.
         *                                 By default, prints a warning to the console and resolves with object {outcome:false,reason:"db-error"}.
         *                      onupgradeneeded:   function to be called as the IDBOpenDBRequest property of the same name.
         *                                         By default, creates a database by provided name, with indexes based on the next parameter
         *                      keyPath:    string|string[], default "". If set, database created with onupgradeneeded will have this parameter on creation.
         *                      autoIncrement:      bool, default false. Whether created db has auto-incrementing keys.
         *                      indexes:    Object. If onupgradeneeded() is called, this object is used to create the indexes. The structure is:
         *                                  {
         *                                      <string - index name> : { unique: <bool>, keyPath:<string|string[], defaults to index name>}
         *                                  }
         *                      creationError:   function to be called if DB creation - inside the default onupgradeneeded() - fails.
         *                                       By default, prints a warning to the console and resolves with object {outcome:false,reason:"db-creation-error"}.
         *                  }
         *                  transaction:{
         *                      onGetError:   function to be called when a single get request fails.
         *                                    By default, prints a warning to the console.
         *                  }
         *              }
         *
         *  @return {null} Typically resolves once with an object of the form {
         *          outcome:<bool, whether the items were fetched>,
         *          reason:<string, if outcome was "false", states the reason for failure>,
         *          items: {
         *              <item ID>:<item contents>
         *          },
         *          db: <IDBOpenDBRequest, returned if we got to the transaction part>
         *      }
         */
        getFromIndexedDB: function(db, items, params = {}){
            let context = this;
            let dbRequestOpen = (typeof db !== 'string');
            let dbRequest = db;

            /* Set default params */
            this._parseParams(params);

            //If the user passed an open reqeust but didn't pass the DB name, this function is invalid
            if(!params.db.name && dbRequestOpen){
                return new Promise(function(resolve, reject) {
                    console.warn('Invalid function inputs!');
                    resolve({outcome:false,reason:"db-error"});
                });
            }

            /* Main promise starts here*/
            return new Promise(function(resolve, reject) {
                context._parseParams(params,resolve,db);

                if (!window.indexedDB) {
                    if(!params.db.noDB())
                        return;
                }

                if(!dbRequestOpen){
                    dbRequest = indexedDB.open(params.db.name,params.db.version);
                    dbRequest.onblocked = params.db.onblocked;
                    dbRequest.onerror = params.db.onerror;
                    dbRequest.onupgradeneeded = params.db.onupgradeneeded;
                    dbRequest.onsuccess = function(event){
                        db = event.target.result;
                        context._getFromIndexedDB(db,items,resolve,params);
                    };
                }
                else{
                    context._getFromIndexedDB(db,items,resolve,params);
                }

            });
        },
        /** Promise that sets items to in specific DB
         *  @param {string|IDBOpenDBRequest} Either - database name, or an already open request to a database.
         *  @param {object[]} items - Array of objects of the form:
         *                      {
         *                          [key]:<string, item key>,
         *                          value:<item itself>,
         *                          params:{
         *                              overwrite: <bool, default true - if true will overwrite existing items (requires key)>
         *                              update:<bool, default false - if true will only update items (requires key)>
         *                              merge:<bool, default false - another object with similar key exists, (deep)merges the two instead of overwriting>
         *                          }
         *                      }
         *  @param {object} params Similar to getFromIndexedDB
         *  @return {null} Typically resolves once with an object of the form {
         *          outcome:<bool, whether the items were fetched>,
         *          reason:<string, if outcome was "false", states the reason for failure>,
         *          items: {
         *              <item ID>:<int code, possible codes are possible: -1 - DB error, 0 - success, 1 - key exists and override is false, 2 - key doesnt exist and update is true >
         *          },
         *          db: <IDBOpenDBRequest, returned if we got to the transaction part>
         * */
        setInIndexedDB: function(db, items, params = {}){
            let context = this;
            let dbRequestOpen = (typeof db !== 'string');
            let dbRequest = db;

            /* Set default params */
            this._parseParams(params);

            //If the user passed an open request but didn't pass the DB name, this function is invalid
            if(!params.db.name && dbRequestOpen){
                return new Promise(function(resolve, reject) {
                    console.warn('Invalid function inputs!');
                    resolve({outcome:false,reason:"db-error"});
                });
            }

            /* Main promise starts here*/
            return new Promise(function(resolve, reject) {
                context._parseParams(params,resolve,db);

                if (!window.indexedDB) {
                    if(!params.db.noDB())
                        return;
                }

                if(!dbRequestOpen){
                    dbRequest = indexedDB.open(params.db.name,params.db.version);
                    dbRequest.onblocked = params.db.onblocked;
                    dbRequest.onerror = params.db.onerror;
                    dbRequest.onupgradeneeded = params.db.onupgradeneeded;
                    dbRequest.onsuccess = function(event){
                        db = event.target.result;
                        context._setInIndexedDB(db,items,resolve,params);
                    };
                }
                else{
                    context._setInIndexedDB(db,items,resolve,params);
                }

            });
        },
        /** Parses DB params - same for get and set*/
        _parseParams: function(params, resolve = null,db =null){
          let context = this;
          if(!resolve){
              //DB
              params.db = params.db ?? {};
              params.db.version = params.db.version ?? 1;
              params.db.name = params.db.name ?? (dbRequestOpen? null : db);
              params.db.keyPath = params.db.keyPath ?? undefined;
              params.db.autoIncrement = params.db.autoIncrement ?? false;
              params.db.indexes = params.db.indexes ?? {};

              //Transaction
              params.transaction = params.transaction ?? {};
              params.transaction.onGetError = params.transaction.onGetError ?? function(event){
                  console.warn("Error getting an indexed DB item.", event);
              };
          }
          else{
              //Set default functions
              params.db.creationError = params.db.creationError ?? function(event){
                  console.warn("Error creating required DB.", event);
                  resolve({outcome:false,reason:"db-creation-error"});
                  return false;
              };
              params.db.noDB = params.db.noDB ?? function(event){
                  console.warn("Your browser doesn't support a stable version of IndexedDB. Some features will not be available.", event);
                  resolve({outcome:false,reason:"no-db"});
                  return false;
              };
              params.db.onerror = params.db.onerror ?? function(event){
                  console.warn("User probably didn't allow this web app to use IndexedDB.", event);
                  resolve({outcome:false,reason:"db-error"});
                  return false;
              };
              params.db.onblocked = params.db.onblocked ?? function(event){
                  console.warn('This event is triggered when the upgradeneeded event should be triggered because of a version change but the database is still in use (i.e. not closed) somewhere, even after the versionchange event was sent.', event);
                  resolve({outcome:false,reason:"db-blocked"});
                  return false;
              };
              params.db.onupgradeneeded = params.db.onupgradeneeded ?? function(event) {
                  if(context.verbose)
                      console.log('Creating Database',params.db.name,' when version was ',params.db.version);
                  db = event.target.result;
                  try{
                      const objectStore = db.createObjectStore(params.db.name, { keyPath: params.db.keyPath, autoIncrement: params.db.autoIncrement });
                      for(let index in params.db.indexes)
                          objectStore.createIndex(index, params.db.indexes[index].keyPath??index, { unique: params.db.indexes[index].unique??false });
                  }
                  catch(err){
                      params.db.creationError();
                  }
              };
          }
        },
        /** Continuation of getFromIndexedDB. Uses the opened DB, and initialized params, to open the transaction and get requested items.
         * */
        _getFromIndexedDB: function (db,items,resolve,params){
            let transaction = db.transaction([params.db.name], "readonly");
            let objectStore = transaction.objectStore(params.db.name);
            //Fetch the objects
            let objects = {};
            items.forEach(function(item){
                let request = objectStore.get(item);
                request.onsuccess = function(event) {
                    if(request.result !== undefined)
                        objects[item] = request.result;
                };
                request.onerror = params.transaction.onGetError;
            });
            //When the last object fetch request returned
            transaction.oncomplete = function(event) {
                items.forEach(function(item){
                    if(objects[item] === undefined)
                        objects[item] = null;
                });
                resolve({outcome:true,items:objects,db:db});
            }
        },
        /** Continuation of setInIndexedDB. Uses the opened DB, and initialized params, to open the transaction and get requested items.
         * */
        _setInIndexedDB: function(db,items,resolve,params){
            //Check existing items
            let transaction = db.transaction([params.db.name], "readonly");
            let objectStore = transaction.objectStore(params.db.name);

            //For every item that has keys, check if it exists
            for(let i in items){
                let item = items[i];
                //Set defaults
                item.params = item.params??{};
                item.params.update = item.params.update??false;
                item.params.overwrite = item.params.update || (item.params.overwrite??true);
                item.params.merge = item.params.merge??false;
                if(!item.key)
                    continue;
                item.dbResult = -1;
                let getRequest = objectStore.get(item.key);
                getRequest.onsuccess = function(event) {
                    if(getRequest.result !== undefined){
                        item.dbResult = item.params.overwrite ? true: 1;
                        if(item.dbResult)
                            item.dbValue = getRequest.result;
                    }
                    else{
                        item.dbResult = item.params.update ? 2 : true;
                        if(item.dbResult)
                            item.dbValue = {};
                    }
                };
                getRequest.onerror = params.transaction.onGetError;
            }
            //When the last object fetch request returned
            transaction.oncomplete = function(event) {
                //Set/add new items
                let transaction2 = db.transaction([params.db.name], "readwrite",{durability:"strict"});
                let objectStore2 = transaction2.objectStore(params.db.name);
                for(let i in items){
                    let item = items[i];
                    if(typeof item.dbResult === 'number')
                        continue;
                    let canMerge = item.merge && typeof item.dbValue === 'object' && typeof item.value === 'object';
                    let newValue =JSON.parse(JSON.stringify( canMerge? mergeDeep(item.dbValue,item.value) : item.value));
                    let setRequest = item.key ? objectStore2.put(newValue,item.key) : objectStore2.add(newValue);
                    setRequest.onsuccess = function(event) {
                        item.dbResult = 0;
                    };
                    setRequest.onerror = params.transaction.onGetError;
                }
                transaction2.oncomplete = function(event){
                    let result = {};
                    for(let i in items){
                        result[i] = items[i].dbResult;
                    }
                    resolve({outcome:true,items:result,db:db});
                }
            }
        }
    }
};
