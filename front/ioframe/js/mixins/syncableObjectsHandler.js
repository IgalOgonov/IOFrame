/** Get and set sync-able objects
 * **/
const syncableObjectsHandler = {
    mixins:[eventHubManager,IOFrameCommons,indexedDBInterface],
    data: function(){
        return{
            events:[],
            eventHub:{},
            syncableObjects:{
            }
        }
    },
    created: function(){

        //Register eventhub
        this.registerHub(eventHub);

        /* Individual objects a main module/component wants to sync still have to be defined in its own 'created' function,
         * formatted:
              //Objects (or any value) to be syncable
              members: {
              },
              //(RESERVED) Timestamp - when the current members were last updated (on the DB)
              updated: 0,
              //(RESERVED) Whether the user is logged in (relevant for using the API)
              userLoggedIn: document.siteConfig.signedIn && document.siteConfig.active,
              //Whether the members should be considered up to date
              upToDate: false,
              //Interval in which to check if the members were changed in local storage - in seconds. Used for syncing between tabs. Leave 0 to ignore.
              localCheckInterval: 0,
              //Path to search for preloaded members (presumably from the back end) - dot separated, from data root
              //The object should contain 'members' (same structure as above) and 'updated' (date when the user last updated the members).
              pathToPreloaded: 'configObject.'+<objectName>,
              //IndexedDB cache name to be used as default storage for objects
              cacheName: 'User_'+<capitalized objectName>,
              //Api address- set to '' to disable API functionality and keep objects local
              apiName: '',
              //Name of the action to get members from the API
              getActionName: 'get'+<capitalized objectName>,
              //Name of the action to pass members to the API
              setActionName: 'set'+<capitalized objectName>,
              //Name of the parameter members should be passed to the API under
              setParameter: <objectName>,
              //Extra parameters of the form: <key, parameter name>:<value, static value or function that gets passed
              // objectType (self-explanatory),newMembers(self-explanatory in context of function),context ("this", aka the Vue module/component) and params(self-explanatory in context of function) as the 4 arguments>
              extraSetParameters: {},
              //Similar to extraSetParameters without the "newMembers" part for functions
              extraGetParameters: {},
              //Name of the event that is launched when the members are returned (if using the API)
              getEventName:  'get'+<capitalized objectName>+'Response',
              //Name of the event that is launched when the members' set attempt receives a response from the API
              setEventName:  'set'+<capitalized objectName>+'Response',
              //Indexed DB parameters
              //(RESERVED)indexedDBParams:{
              },
              //(RESERVED)Whether the set attempt response was valid (API response)
              setResponseValid: false,
              //(RESERVED)Set attempt response code, if valid
              setResponseCode: undefined,
              //Event that is sent when the members were locally updated (to be used by OTHER modules/components).
              setLocalEventName: 'update'+<capitalized objectName>+'Local',
              //Event that is sent when the members were locally updated (to be used by THIS OR OTHER modules/components).
              syncableObjectsUpdateFinishEventName: 'update'+<capitalized objectName>+'SyncableFinished',
              //function - if not undefined, will add an event listener that handles the api call result (the function would get it as its first parameter).
              //Requires a predefined getEventName. If defined (or true), will default to updating the cache/this object. If false, will do nothing
              ['eventHandler']: undefined

         * Predefined variables take precedence over those passed to getCacheableObjects.
         * Objects with predefined eventName can have an eventHandler.
         */
        if(this.syncableObjects === undefined){
            if(this.verbose)
                console.log('syncableObjects not defined!');
            return;
        }

        let context = this;
        for(let i in this.syncableObjects){
            if((typeof this.syncableObjects[i] !== 'object') || Array.isArray(this.syncableObjects[i]))
                continue;
            let capitalizedObject = i[0].toUpperCase()+i.substring(1);
            //Defaults
            this.syncableObjects[i].members = this.syncableObjects[i].members ?? {};
            this.syncableObjects[i].upToDate = this.syncableObjects[i].upToDate ?? false;
            this.syncableObjects[i].localCheckInterval = this.syncableObjects[i].localCheckInterval ?? 0;
            this.syncableObjects[i].pathToPreloaded = this.syncableObjects[i].pathToPreloaded ?? 'configObject.'+i;
            this.syncableObjects[i].cacheName = this.syncableObjects[i].cacheName ?? 'User_'+capitalizedObject;
            this.syncableObjects[i].apiName = this.syncableObjects[i].apiName ?? '';
            this.syncableObjects[i].getActionName = this.syncableObjects[i].getActionName ?? 'get'+capitalizedObject;
            this.syncableObjects[i].setActionName = this.syncableObjects[i].setActionName ?? 'set'+capitalizedObject;
            this.syncableObjects[i].setParameter = this.syncableObjects[i].setParameter ?? i;
            this.syncableObjects[i].extraSetParameters = this.syncableObjects[i].extraSetParameters ?? {};
            this.syncableObjects[i].extraGetParameters = this.syncableObjects[i].extraGetParameters ?? {};
            this.syncableObjects[i].getEventName = this.syncableObjects[i].getEventName ?? 'get'+capitalizedObject+'Response';
            this.syncableObjects[i].setEventName = this.syncableObjects[i].setEventName ?? 'set'+capitalizedObject+'Response';
            this.syncableObjects[i].setLocalEventName = this.syncableObjects[i].setLocalEventName ?? 'update'+capitalizedObject+'Local';
            this.syncableObjects[i].syncableObjectsUpdateFinishEventName = this.syncableObjects[i].syncableObjectsUpdateFinishEventName ?? 'update'+capitalizedObject+'SyncableFinished';
            this.syncableObjects[i].eventHandler = this.syncableObjects[i].eventHandler ?? undefined;
            //Reserved
            this.syncableObjects[i].userLoggedIn = document.ioframe.loggedIn && document.siteConfig.active;
            this.syncableObjects[i].updated = 0;
            this.syncableObjects[i].setResponseValid = false;
            this.syncableObjects[i].setResponseCode = undefined;
            this.syncableObjects[i].indexedDBParams = this.syncableObjects[i].indexedDBParams ?? {};
            this.syncableObjects[i].indexedDBParams.db = this.syncableObjects[i].indexedDBParams.db ?? {};
            this.syncableObjects[i].indexedDBParams.db.name = this.syncableObjects[i]['cacheName'];

            if (this.syncableObjects[i]['getEventName'] && this.syncableObjects[i]['cacheName']) {
                if (this.syncableObjects[i].userLoggedIn && (this.syncableObjects[i]['eventHandler'] === undefined || this.syncableObjects[i]['eventHandler'] === true))
                    this.registerEvent(this.syncableObjects[i]['getEventName'], function (response) {

                        if (context.identifier && response.from !== context.identifier)
                            return;

                        if (response.from)
                            response = response.content;

                        if (IsJsonString(response)) {
                            let resp = JSON.parse(response);
                            if(!resp.members || !resp.updated){
                                if (context.verbose)
                                    console.log('Got illegal response to '+i+' api call: ', resp);
                                return;
                            }
                            if (context.verbose)
                                console.log('Got response to '+i+' api call, setting cache and contents to: ', resp);
                            let updateTarget = resp;
                            context.updateLocalObjects(i,updateTarget).then(function(res){
                                context.getFromIndexedDB(context.syncableObjects[i].cacheName,['members','updated'],context.syncableObjects[i].indexedDBParams).then(function(res2){
                                    res2.db.close();
                                    if(!res)
                                        updateTarget = JSON.parse(JSON.stringify(res2.items));
                                    //We can get here only if the user had local objects, and never had any DB objects before
                                    if(resp.updated === 0){
                                        if(context.verbose)
                                            console.log('No DB '+i+', updating with local',updateTarget);
                                        context.setSyncableObjects(i,updateTarget.members);
                                    }
                                    if(context.verbose)
                                        console.log('Updating '+i,updateTarget);
                                    Vue.set(context.syncableObjects[i],'members',updateTarget.members);
                                    Vue.set(context.syncableObjects[i],'updated',updateTarget.updated);
                                    Vue.set(context.syncableObjects[i],'upToDate',true);
                                    if(context.verbose)
                                        console.log('Emitting '+context.syncableObjects[i].syncableObjectsUpdateFinishEventName);
                                    eventHub.$emit(context.syncableObjects[i].syncableObjectsUpdateFinishEventName);
                                })
                            });
                        }
                        else {
                            console.log('Got illegal response to '+i+' api call: ', response);
                        }
                    });
                else if (typeof this.syncableObjects[i]['eventHandler'] === "function")
                    this.registerEvent(this.syncableObjects[i]['getEventName'], this.syncableObjects[i]['eventHandler']);
            }
            this.registerEvent(this.syncableObjects[i].setEventName, this['_setSyncableObjectsDynamic']()[i]);
            this.registerEvent(this.syncableObjects[i].setLocalEventName, this['_getSyncableObjectsLocalDynamic']()[i]);
            setTimeout(this['_getSyncableObjectsLocalDynamic']()[i],this.syncableObjects[i].localCheckInterval*1000,{from:this.identifier},i,true);

            this.getSyncableObjects(i);
        }
    },
    methods: {
        //Update local objects in case of more up-to-date objects - returns true if local update is indeed needed
        updateLocalObjects: function(objectType,target){
            let context = this;
            return new Promise(function(resolve, reject) {
                context.getFromIndexedDB(context.syncableObjects[objectType].cacheName,['members','updated'],context.syncableObjects[objectType].indexedDBParams).then(function(res){
                    let localObjects = res.items ?? {};
                    localObjects.updated = localObjects.updated??-1;
                    localObjects.members = localObjects.members?? {};
                    //Check if our objects are new
                    let newObjects = localObjects.updated < target.updated;
                    if(newObjects){
                        let clone = JSON.parse(JSON.stringify(target));
                        if(context.verbose)
                            console.log('Updating local storage '+objectType+' cache with',clone);
                        context.setInIndexedDB(
                            res.db,
                            [
                                {
                                    key:'members',
                                    value:clone.members,
                                    update:false,
                                    overwrite:true,
                                    merge:false
                                },
                                {
                                    key:'updated',
                                    value:clone.updated,
                                    update:false,
                                    overwrite:true,
                                    merge:false
                                }
                            ],
                            context.syncableObjects[objectType].indexedDBParams
                        ).then(function(res2){
                            res2.db.close();
                            if(context.verbose){
                                if(res.outcome)
                                    console.log(objectType+' set in cache',res);
                                else
                                    console.log('Failed to set '+objectType+' in cache',res);
                            }
                            resolve(res.outcome);
                        });
                    }
                    else{
                        res.db.close();
                        resolve(false);
                    }
                });
            });

        },
        /*Dynamic local objects getter*/
        _getSyncableObjectsLocalDynamic: function(){
            let res = [];
            let context = this;
            for(let i in this.syncableObjects)
                res[i] = function(response){
                    if(context.syncableObjects[i].localCheckInterval)
                        setTimeout(context['_getSyncableObjectsLocalDynamic']()[i],context.syncableObjects[i].localCheckInterval*1000,{from:context.identifier},true);
                    if(!context.identifier || (response.from !== context.identifier))
                        return;
                    context.getSyncableObjects(i,{checkPreloaded:false,local:true});
                }
            return res;
        },
        /** Gets relevant cached objects from indexedDB/API.
         *  Updates the relevant object from cache directly, then emits an event once the API call is done.
         *  Note if this.syncableObjects[objectType]['apiName'] isn't defined by the main module, nothing will happen
         *  and a warning will be thrown
         *  the function would re
         *  @var array params - parameters of the form:
         *                      'objectName' - Basically the name of the object - defines the API call, the cache name, and more.
         *                        To give an example, if the name was 'TestObjects', the cacheName would be '_TestObjects_Cache',
         *                        and the getActionName would be 'getTestObjects', and the event name would be 'getTestObjectsResponse'.
         *                      'getActionName' - The name of the action to get objects from the API. By default, it's 'get'+objectName
         *                      'cacheName' - The name of the indexedDB variable that will serve as the cache. By default, it's '_'+objectName+'_Cache'
         *                      'getEventName' - Name of the event to emit once the API is reached. By default it's getActionName+'Response'
         * */
        getSyncableObjects:function( objectType,params = {}){

            //Check validity
            if(!this.syncableObjects[objectType] || typeof this.syncableObjects[objectType].members !== 'object'){
                console.warn('Tried to get '+objectType+' , but it is not properly defined!');
                return false;
            }

            //Set defaults
            let context = this;
            let getActionName = this.syncableObjects[objectType]['getActionName'] ?? (params['getActionName'] ?? this.syncableObjects[objectType].getActionName);
            let getEventName = this.syncableObjects[objectType]['getEventName'] ?? (params['getEventName'] ?? getActionName+'Response');
            let identifier = this.syncableObjects[objectType]['identifier'] ?? (params['identifier'] ?? this.identifier);
            let ignoreLocal = params['ignoreLocal'] ?? false;
            let local =  ignoreLocal ? false : (params['local'] ?? false);
            let checkPreloaded = (params['checkPreloaded'] === undefined) || params['checkPreloaded'];
            let finalResult = false;

            return new Promise(function(resolve, reject) {
                context['_preloadSyncableObjects'](objectType,params,!checkPreloaded || !context.syncableObjects[objectType].pathToPreloaded).then(function(res){
                    finalResult = finalResult || res;
                    context['_getLocalSyncableObjects'](objectType,params,ignoreLocal).then(function(res2){
                        finalResult = finalResult || res2;
                        //Api request
                        if(!local && context.syncableObjects[objectType].userLoggedIn && context.syncableObjects[objectType].apiName && getEventName){
                            let data = new FormData();
                            data.append('action',getActionName);
                            if(context.syncableObjects[objectType]['extraGetParameters'])
                                for(let i in context.syncableObjects[objectType]['extraGetParameters']){
                                    let type = typeof context.syncableObjects[objectType]['extraGetParameters'][i];
                                    switch (type){
                                        case 'function':
                                            data.append(i,context.syncableObjects[objectType]['extraGetParameters'][i](objectType,context,params));
                                            break;
                                        case 'object':
                                            data.append(i,JSON.stringify(context.syncableObjects[objectType]['extraGetParameters'][i]));
                                            break;
                                        default:
                                            data.append(i,context.syncableObjects[objectType]['extraGetParameters'][i]);
                                    }
                                }
                            let requestParams = {
                                verbose: context.verbose,
                                parseJSON: false
                            };
                            if(identifier)
                                requestParams.identifier = identifier;
                            context.apiRequest(data, context.syncableObjects[objectType].apiName, getEventName, requestParams);
                        }
                        resolve(finalResult);
                    });
                });
            });
        },
        _getLocalSyncableObjects: function(objectType,params = {}, skip = false){
            if(skip)
                return new Promise(function(resolve, reject) {
                    resolve(true);
                });
            let context = this;
            return new Promise(function(resolve, reject) {
                context.getFromIndexedDB(context.syncableObjects[objectType].cacheName,['members','updated'],context.syncableObjects[objectType].indexedDBParams).then(function(res){
                    res.db.close();
                    if(res.items.members && (res.items.updated !== undefined) && (context.syncableObjects[objectType].updated <res.items.updated)){
                        if(context.verbose)
                            console.log('Updating '+objectType+' from local storage - ',res);
                        Vue.set(context.syncableObjects[objectType],'members',res.items.members);
                        Vue.set(context.syncableObjects[objectType],'updated',res.items.updated);
                        context.syncableObjects[objectType]['upToDate'] = params['local'] || !context.syncableObjects[objectType].apiName;
                        resolve(res.items.updated);
                        return;
                    }
                    resolve(false);
                });
            });
        },
        _preloadSyncableObjects: function(objectType,params = {}, skip = false){
            if(skip)
                return new Promise(function(resolve, reject) {
                    resolve(true);
                });
            let context = this;
            let target = this;
            let path = this.syncableObjects[objectType].pathToPreloaded.split('.');
            for(let i in path){
                target = target[path[i]];
                if(target === undefined)
                    break;
            }
            return new Promise(function(resolve, reject) {
                if(target && target.members && target.updated)
                    context.updateLocalObjects(objectType,target).then(function(res){
                        if(res){
                            if(context.verbose)
                                console.log('Updating '+objectType+' from preloaded',target);
                            Vue.set(context.syncableObjects[objectType],'members',target.members);
                            context.syncableObjects[objectType]['updated'] = target.updated;
                            context.syncableObjects[objectType]['upToDate'] = true;
                            resolve(true);
                        }
                        //This means the user updated his objects while signed out, or nothing changed
                        else{
                            context.getFromIndexedDB(context.syncableObjects[objectType].cacheName,['members','updated'],context.syncableObjects[objectType].indexedDBParams).then(function(res2){
                                res2.db.close();
                                if(res2.items.updated > target.updated){
                                    if(context.verbose)
                                        console.log('Outdated DB '+objectType+', updating with local',target,res2.items);
                                    context.setSyncableObjects(objectType,res2.items.members);
                                }
                                else{
                                    Vue.set(context.syncableObjects[objectType],'members',res2.items.members);
                                    context.syncableObjects[objectType].updated = res2.items.updated;
                                    context.syncableObjects[objectType]['upToDate'] = true;
                                }
                                resolve(true);
                            });
                        }
                    });
                else
                    resolve(false);
            });
        },
        _addToSyncableObjects:function(objectType, newMembers, params = {}){
            let context = this;
            let local = !this.syncableObjects[objectType].userLoggedIn || params.local;
            if(!this.test){
                Vue.set(this.syncableObjects[objectType],'members',newMembers);
                this.syncableObjects[objectType].updated++;
            }
            if(local){
                if(this.verbose)
                    console.log('Updating '+objectType+' at '+this.syncableObjects[objectType]['cacheName']+' with',JSON.parse(JSON.stringify(this.syncableObjects[objectType].members)));
                this.setInIndexedDB(
                    this.syncableObjects[objectType].cacheName,
                    [
                        {
                            key:'members',
                            value:this.syncableObjects[objectType].members,
                            update:false,
                            overwrite:true,
                            merge:false
                        },
                        {
                            key:'updated',
                            value:this.syncableObjects[objectType].updated,
                            update:false,
                            overwrite:true,
                            merge:false
                        }
                    ],
                    this.syncableObjects[objectType].indexedDBParams
                ).then(function(res){
                    res.db.close();
                    if(context.verbose){
                        if(res.outcome)
                            console.log(objectType+' set in cache',res);
                        else
                            console.log('Failed to set '+objectType+' in cache',res);
                    }
                    eventHub.$emit(context.syncableObjects[objectType].setLocalEventName,{
                        from:context.identifier
                    });
                });
            }
            else{
                this.setSyncableObjects(objectType,newMembers,params);
            }
        },
        //Sets current objects (in the DB)
        setSyncableObjects: function (objectType,newMembers,params={}){
            if(!this.syncableObjects[objectType].userLoggedIn || !this.syncableObjects[objectType].apiName){
                console.warn('User needs to be logged in and active to set '+objectType);
                return;
            }
            if(this.verbose)
                console.log('Updating '+objectType+' API with',newMembers);

            let setActionName = this.syncableObjects[objectType]['setActionName'] ?? (params['setActionName'] ?? this.syncableObjects[objectType].setActionName);
            let setEventName = this.syncableObjects[objectType]['setEventName'] ?? (params['setEventName'] ?? setActionName+'Response');
            let identifier = this.syncableObjects[objectType]['identifier'] ?? (params['identifier'] ?? this.identifier);

            let data = new FormData();
            let context = this;
            data.append('action',setActionName);
            data.append(this.syncableObjects[objectType]['setParameter'],JSON.stringify(newMembers));
            if(this.test)
                data.append('req','test');
            if(this.syncableObjects[objectType]['extraSetParameters'])
                for(let i in this.syncableObjects[objectType]['extraSetParameters']){
                    let type = typeof this.syncableObjects[objectType]['extraSetParameters'][i];
                    switch (type){
                        case 'function':
                            data.append(i,this.syncableObjects[objectType]['extraSetParameters'][i](objectType,newMembers,context,params));
                            break;
                        case 'object':
                            data.append(i,JSON.stringify(this.syncableObjects[objectType]['extraSetParameters'][i]));
                            break;
                        default:
                            data.append(i,this.syncableObjects[objectType]['extraSetParameters'][i]);
                    }
                }
            let requestParams = {
                verbose: this.verbose,
                parseJSON: false
            };
            if(identifier)
                requestParams.identifier = identifier;
            this.apiRequest(data, this.syncableObjects[objectType].apiName, setEventName, requestParams);
        },
        //Handles API response
        _setSyncableObjectsDynamic: function(){
            let res = [];
            let context = this;
            for(let i in this.syncableObjects)
                res[i] = function(response){

                    //A different module/component that has objects can also send such a response
                    let sameOrigin = context.identifier && response.from === context.identifier;

                    if(response.from)
                        response = response.content;

                    if(context.verbose)
                        console.log('Got '+i+' response '+response+' from '+ (sameOrigin?'context':'a different') + ' Vue instance');

                    if(response.match(/^\-?\d$/))
                        response -=0;
                    switch (response){
                        case 'INPUT_VALIDATION_FAILURE':
                        case -1:
                        case 1:
                        case 2:
                            if(sameOrigin){
                                context.syncableObjects[i].setResponseValid = false;
                                context.syncableObjects[i].setResponseCode = response;
                            }
                            break;
                        case 0:
                            if(sameOrigin){
                                context.syncableObjects[i].setResponseValid = true;
                                context.syncableObjects[i].setResponseCode = response;
                            }
                            context.getSyncableObjects(i,{checkPreloaded:false,ignoreLocal:true,local:false});
                            break;
                        default:
                            if(sameOrigin){
                                context.syncableObjects[i].setResponseValid = false;
                                context.syncableObjects[i].setResponseCode = 'illegal';
                                console.warn(response);
                            }
                    }
                };
            return res;
        }
    }
};
