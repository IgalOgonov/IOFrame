
if(eventHub === undefined)
    var eventHub = new Vue();

/** Gets objects - first from their client side cache, if that exists (localstorage), then from the relevant API.
 * ALL
 * **/
const cacheableObjectGetter = {
    mixins:[eventHubManager,indexedDBInterface,IOFrameCommons],
    data: function(){
        return{
            events:[],
            eventHub:{}
        }
    },
    created: function(){
        if(this.verbose)
            console.log('Common object getter added!');

        //Register eventhub
        this.registerHub(eventHub);

        /* Individual objects a main module/component wants to use still have to be defined in its own 'created' function,
         * formatted:
         * objectName: {
         *              'apiName': <path to API relative to site root>,
         *              ['actionName']: <name of the action to pass to the API>,
         *              ['cacheName']: <custom getCacheableObjects cache name to be used as default>,
         *              ['eventName']: <custom getCacheableObjects event name to be used as default>,
         *              ['extraParams']: default {}, object of the form:
         *                                  {
         *                                   <param name>:<value>
         *                                   ...
         *                                  }
         *                                Of extra params to be sent with the API call
         *              ['setUpToDateOnLocalCache']: default true, Sets items as "upToDate" after getting them from local cache, even if we are still
         *                                           querying the DB later.
         *              ['eventHandler']: function - if not undefined, will add an event listener that handles
         *                                the api call result (the function would get it as its first parameter).
         *                                Requires a predefined eventName.
         *                                if undefined (or true), will default to updating the cache/this object.
         *                                if false, will do nothing
         *              (RESERVED) 'contents': [] - empty array to be filled. Will always be created and set to an empty array.
         *              (RESERVED) 'upToDate': bool, whether the object has been updated - always false at start
         *              }
         *
         * Predefined variables take precedence over those passed to getCacheableObjects.
         * Objects with predefined eventName can have an eventHandler.
         */
        if(this.cacheableObjects === undefined){
            if(this.verbose)
                console.log('cacheableObjects not defined!');
            return;
        }

        for (let [objectName,objectProperties] of Object.entries(this.cacheableObjects)) {
            this.cacheableObjects[objectName]['contents'] = [];
            this.cacheableObjects[objectName]['upToDate'] = false;
            let context = this;
            if (this.cacheableObjects[objectName]['eventName'] && this.cacheableObjects[objectName]['cacheName']) {
                if (this.cacheableObjects[objectName]['eventHandler'] === undefined || this.cacheableObjects[objectName]['eventHandler'] === true)
                    this.registerEvent(this.cacheableObjects[objectName]['eventName'], function (response) {

                        if (context.identifier && response.from !== context.identifier)
                            return;

                        if (response.from)
                            response = response.content;

                        if (IsJsonString(response)) {
                            let resp = JSON.parse(response);
                            if (context.verbose)
                                console.log('Got response to ' + objectName + ' api call, setting cache and contents to: ', resp);
                            //Update cache
                            context.setCacheableObjectCache(objectName, resp);
                            //Update contents
                            context.cacheableObjects[objectName]['contents'] = resp;
                            context.cacheableObjects[objectName]['upToDate'] = true;
                        }
                        else {
                            console.log('Got illegal response to ' + objectName + ' api call: ', response);
                        }
                    });
                else if (typeof this.cacheableObjects[objectName]['eventHandler'] === "function")
                    this.registerEvent(this.cacheableObjects[objectName]['eventName'], this.cacheableObjects[objectName]['eventHandler']);
            }
        }
    },
    methods: {
        /** Gets relevant cached objects from localStorage/API.
         *  Updates the relevant object from cache directly, then emits an event once the API call is done.
         *  Note if this.cacheableObjects[objectName]['apiName'] isn't defined by the main module, nothing will happen
         *  and a warning will be thrown
         *  the function would re
         *  @var array params - parameters of the form:
         *                      'objectName' - Basically the name of the object - defines the API call, the cache name, and more.
         *                        To give an example, if the name was 'TestObjects', the cacheName would be '_TestObjects_Cache',
         *                        and the actionName would be 'getTestObjects', and the event name would be 'getTestObjectsResponse'.
         *                      'actionName' - The name of the localstorage variable that will serve as the cache. By default, it's 'get'+objectName
         *                      'cacheName' - The name of the localstorage variable that will serve as the cache. By default, it's '_'+objectName+'_Cache'
         *                      'eventName' - Name of the event to emit once the API is reached. By default it's actionName+'Response'
         *                      'extraParams' - same as described earlier
         *                      'indexedDBParams' - see indexedDBInterface mixin for details
         * */
        getCacheableObjects: function(objectNameOrParams, params = {}){
            if(typeof arguments[0] === 'string')
                this._getCacheableObjects(objectNameOrParams, params);
            else
                for (let i in this.cacheableObjects){
                    this._getCacheableObjects(i, objectNameOrParams);
                }
        },
        _getCacheableObjects:function(objectName, params = {}){
            //Check validity
            if(!this.cacheableObjects[objectName] || !this.cacheableObjects[objectName]['apiName'] ){
                console.warn('Tried to get cacheable object '+objectName+', but it is not properly defined in cacheableObjects');
                return;
            }
            let objectArray = this.cacheableObjects[objectName];
            let context = this;

            //Set defaults
            let actionName = objectArray['actionName'] ?? (params['actionName'] ?? 'get'+objectName);
            let cacheName = objectArray['cacheName'] ?? (params['cacheName'] ?? '_'+objectName+'_Cache');
            let eventName = objectArray['eventName'] ?? (params['eventName'] ?? actionName+'Response');
            let extraParams = objectArray['extraParams'] ?? (params['extraParams'] ?? {});
            let TTL = objectArray['TTL'] ?? (params['TTL'] ?? 0);
            let setUpToDateOnLocalCache = objectArray['setUpToDateOnLocalCache'] ?? (params['setUpToDateOnLocalCache'] ?? true);
            let identifier = objectArray['identifier'] ?? (params['identifier'] ?? this.identifier);

            //Indexed DB Defaults
            let indexedDBParams = params['indexedDBParams'] ?? {};
            indexedDBParams.db = indexedDBParams.db??{};
            indexedDBParams.db.name = 'Cacheable_Objects';

            //Get from local cache
            this.getFromIndexedDB(indexedDBParams.db.name,[cacheName,'_updated'],indexedDBParams).then(function(res){
                //if(context.verbose)
                let updatedFromDB = (res.items && res.items['_updated'] && res.items['_updated'].cacheName) ? res.items['_updated'].cacheName : false;
                if(updatedFromDB){
                    context.cacheableObjects[objectName]['contents'] = res.items[cacheName].contents;
                    context.cacheableObjects[objectName]['upToDate'] = setUpToDateOnLocalCache;
                }
                res.db.close();
                //Api request
                if(!updatedFromDB || ( updatedFromDB + TTL < (Date.now()/1000).toFixed(0) ) ){
                    let data = new FormData();
                    data.append('action',actionName);
                    for(let key in extraParams){
                        data.append(key, extraParams[key]);
                    }
                    let requestParams = {
                        verbose: this.verbose,
                        parseJSON: false
                    };
                    if(identifier)
                        requestParams.identifier = identifier;
                    context.apiRequest(data, objectArray.apiName, eventName, requestParams)
                }
            });
        },
        /** Sets the cacheable object's cache. Can work with IndexedDB, localStorage, or both.
         * */
        setCacheableObjectCache: function(objectName , contents, params = {}){
            if( (typeof contents !== 'object') || Array.isArray(contents) )
                return;
            try{
                let context = this;
                let objectArray = this.cacheableObjects[objectName];
                let cacheName = objectArray['cacheName'] ?? (params['cacheName'] ?? '_'+objectName+'_Cache');
                let indexedDBParams = params['indexedDBParams'] ?? {};
                indexedDBParams.db = indexedDBParams.db??{};
                indexedDBParams.db.name = 'Cacheable_Objects';

                let received = {};
                received[cacheName] = (Date.now()/1000).toFixed(0) - 30000;//30 sec - account for maximum API delay
                contents = JSON.parse(JSON.stringify(contents)); //Just to make sure we aren't touching an existing object

                let items = [
                    {
                        key:cacheName,
                        value:contents,
                        update:false,
                        overwrite:true,
                        merge:false
                    },
                    {
                        key:'_updated',
                        value:received,
                        update:false,
                        overwrite:true,
                        merge:true
                    }
                ];
                this.setInIndexedDB(indexedDBParams.db.name, items, indexedDBParams).then(function(res){
                    res.db.close();
                    if(context.verbose){
                        if(res.outcome)
                            console.log('Objects set in cache',res);
                        else
                            console.log('Failed to set objects in cache',res);
                    }
                });
            }
            catch (e){
                console.warn(e);
            }
        }
    }
};
