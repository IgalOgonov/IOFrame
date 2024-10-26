/* TODO This is left alone until I can determine whether there is any useful code here that can be reused, as still hasn't been
 * Resolves true on success, rejects false on failure, or resolves the list of changed objects if we updated and got new objects.
 * Parameters:
 *      'updateObjectMap' - bool, default true - whether to update the object map - by default only '@'
 *      'updateObjects' - bool, default true - whether to update the objects gotten from the object maps
 *      'extraMaps' - string[], default [] - Extra maps to update (apart from '@')
 *
* */
function startObjectDB( params = []){
    return new Promise(function(resolve, reject) {
        let updateObjectMap = (params['updateObjectMap'] !== undefined)? params['updateObjectMap'] : true;
        /*INDEXED-DB RELATED*/
        window.indexedDB = window.indexedDB || window.mozIndexedDB || window.webkitIndexedDB || window.msIndexedDB;
        window.IDBKeyRange = window.IDBKeyRange || window.webkitIDBKeyRange || window.msIDBKeyRange;

        if (!window.indexedDB) {
            alertLog("Your browser doesn't support a stable version of IndexedDB. Some features will not be available.", 'warning');
            resolve(false);
            return false;
        }
        else{
            if (typeof(Storage) === "undefined") {
                alertLog('Local storage not enabled! Certain functions will be available to you.', 'warning');
                let hasStorage = false;
            }
            else
                hasStorage = true;
            //initialize the db
            let db;
            let request = indexedDB.open("ObjectCache",2);

            request.onblocked = function(event){
                console.log(event);
                console.log('This event is triggered when the upgradeneeded event should be triggered because of a version change but the database is still in use (i.e. not closed) somewhere, even after the versionchange event was sent.')
            };

            //Handle user not letting you use what you need to use.
            request.onerror = function(event) {
                alertLog("Why didn't you allow my web app to use IndexedDB?!", 'warning');
            };

            /*The structure of objects in this database is:
             * id:           Object ID, number.
             * content:      The content of the object.
             * group:        Group the object belongs to - can be '' for no group
             * canModify:    Whether the client is able to modify the object or not. Starts as 'false' until proven 'true'
             * canView:      Whether the client is able to view the object or not. Starts as 'true' until proven 'false'
             * lastUpdated:  The last time you queried the server for the object.
             * */
            request.onupgradeneeded  = function(event) {
                console.log('Creating Database..');
                db = event.target.result;
                //ID is the index
                try{
                    let objectStore = db.createObjectStore("objects", { keyPath: "id" });
                    //We will often want to select a distinct group from a collection of objects, but it is obviously not unique
                    objectStore.createIndex("group", "group", { unique: false });
                }
                catch(err){
                    console.log('Database creation error: ');
                }
            };

            //What to do if the DB already exists
            request.onsuccess = function(event){
                db = event.target.result;
                //Only relevant if the user has localStorage
                if(hasStorage){
                    //If we are not updating the object map, we are done
                    if(!updateObjectMap){
                        db.close();
                        resolve(true);
                        return true;
                    }
                    else{
                        populateObjectMap(db,params).then(function(res){
                            db.close();
                            resolve(res);
                            return res;
                        },
                        function(rej){
                            db.close();
                            reject(rej);
                            return rej;
                        });
                    }
                }
                else{
                    db.close();
                    resolve(false);
                    return false;
                }
            };
        }
    });
}

/* Populates an existing indxed db with objects from the object map.
 * Parameters listed in main function - updateObjects, extraMaps
 * */
function populateObjectMap(db,params = []){
    return new Promise(function(resolve, reject){
        let updateObjects = (params['updateObjects'] !== undefined)? params['updateObjects'] : true;
        let extraMaps = (params['extraMaps'] !== undefined)? params['extraMaps'] : [];
        let timenow = new Date().getTime();
        timenow = Math.floor(timenow/1000);
        if(localStorage.getItem("objectMap")== null)
            localStorage.setItem("objectMap",'');
        //console.log(extraMaps);
        checkMappedObjects(extraMaps).then(function(res){
            //console.log(res);
            //We either got back a legit JSON array, or a different error
            if(!IsJsonString(res) || typeof JSON.parse(res) != 'object'){
                console.log('Error getting object map, got '+res);
                db.close();
                reject(false);
                return false;
            }

            let parsedResponse = JSON.parse(res);
            let mapsToUpdate = [];
            extraMaps.forEach(function(mapName){
                //Map doesn't exist! So we can do no caching...
                if(parsedResponse[mapName] == 1){
                    assignNewObjects(mapName, timenow ,null);
                    delete(parsedResponse[mapName]);
                }
                //Map is up to date. Keep as is
                else if(parsedResponse[mapName] == 0){
                    mapsToUpdate.push(mapName);
                }
                else{
                    assignNewObjects(mapName, timenow ,JSON.parse(parsedResponse[mapName]));
                    mapsToUpdate.push(mapName);
                }
            });
            //Either we have to update the objects or we don't
            if(updateObjects && mapsToUpdate!=[]){
                updateMappedObjects(mapsToUpdate,db).then(function(res){
                    db.close();
                    resolve(res);
                    return true;
                },function(rej){
                    console.log('Object update failed!');
                    db.close();
                    resolve(false);
                    return false;
                });
            }
            else{
                db.close();
                resolve(true);
                return true;
            }
        });
    });
}

/*Checks to see if the object map of the given map is up to date - localStorage vs Server
 * Gets an array of maps other than '@'
 * Returns:     0 - Objects on the map are up to date
 *              1 - map doesn't exist on the server end, or is NULL (no objects are assigned to it)
 *              JSON Array of the form {"<ObjID>":"ObjID"} containing all the objects assigned to the map - otherwise
 * */
function checkMappedObjects(requestedMaps = []){

    return new Promise(function(resolve, reject) {
        let storedMaps = localStorage.getItem('objectMap');
        requestedMaps.push('@');
        let timesUpdated = [];
        //See if our object map even exists
        IsJsonString(storedMaps)?
            storedMaps = JSON.parse(storedMaps): storedMaps == null;

        //Prepare to check the server for the actual objects assigned to the current map
        let header =  {
            'Content-Type': 'application/x-www-form-urlencoded;charset=utf-8;'
        };
        //Set parameters to send to the api
        let params = {
            maps:{}
        };
        //In case our map exists/does not exist, get/set timeUpdated
        requestedMaps.forEach(function(map){
            if(storedMaps[map] === undefined)
                params.maps[map] = 0;
            else{
                params.maps[map] = storedMaps[map].timeUpdated;
            }
        });
        let action;
        action = "action=ga&params="+JSON.stringify(params);

        updateCSRFToken().then(
            function(token){
                action += '&CSRF_token='+token;
                // url
                let url=document.ioframe.pathToRoot+"api\/v1\/objects";
                //Request itself
                let xhr = new XMLHttpRequest();
                xhr.open('GET', url+'?'+action);
                //console.log(url+'?'+action);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=utf-8;');
                xhr.send(null);
                xhr.onreadystatechange = function () {
                    let DONE = 4; // readyState 4 means the request is done.
                    let OK = 200; // status 200 is a successful return.
                    if (xhr.readyState === DONE) {
                        if (xhr.status === OK){
                            let response = xhr.responseText;
                            resolve(response);
                            //console.log(response);
                            return 0;
                        }
                    } else {
                        if(xhr.status < 200 || xhr.status > 299 ){
                            resolve(1);
                            return 1;
                        }
                    }
                };
            },
            function(reject){
                alertLog('CSRF token expired. Please refresh the page to submit the form.','danger');
            }
        );

    });

}

/* Assign new objects to a map in localStorage.
 * Gets map, the map to assign the objects to, and objJSON, a JSON strong of the form {"<ObjID>":"ObjID"}
 * Replaces the objects on that map with the object string given.
 * IF objJSON = '', deletes the map from localStorage, as it contains no objects.
 * */
function assignNewObjects(map, timenow, obj){
    //If objJSON is '', remove the object entry for that map
    if(obj == null){
        let objectMap = localStorage.getItem('objectMap');
        (objectMap == '' || objectMap == '{}')?
            objectMap = {}  : objectMap = JSON.parse(objectMap);
        if(map in objectMap)
            delete objectMap[map];
        localStorage.setItem('objectMap',JSON.stringify(objectMap));
    }
    //Else, update objectMap on that map
    else if(typeof obj == 'object'){
        let objectMap = localStorage.getItem('objectMap');
        (objectMap == '' || objectMap == '{}')?
            objectMap = {}  : objectMap = JSON.parse(objectMap);
        let currMap = (objectMap.hasOwnProperty(map))?objectMap[map] : {};
        currMap.timeUpdated = timenow;
        currMap.objects = obj;
        objectMap[map] = currMap;
        localStorage.setItem('objectMap',JSON.stringify(objectMap));
    }
}

/* Gets the objects, whose IDs are listed under "maps"s in localStorage, from the server,
 * Then updates the objects in the offline database depending on the results.
 * Resolves 0 if no objects were updated, array of updated object IDs otherwise.
 * */
function updateMappedObjects(maps,db){
    return new Promise(function(resolve, reject) {

        let timenow = new Date().getTime();
        timenow = Math.floor(timenow/1000);
        //get the objectMap, and see which objects this maps should have
        let objectMap = JSON.parse(localStorage.getItem('objectMap'));
        // We'll create a temporary array first, to see which objects we need to check
        let objectsIDs = [];
        let objectExist = {};
        maps.forEach(function(map){
            for(let objID in objectMap[map].objects){
                if(objectExist[objID] === undefined){
                    objectExist[objID] = 1;
                    objectsIDs.push(objID);
                }
            }
        });
        //console.log(objectsIDs);
        /*We will need an object, representing a 2D array.
         The object will be of the form
         "#": "{"<objectID1>":"<timeObj1Updated>", ...}",
         "<groupName>": "{"@":"<timeGroupUpdated>", "<objectID2>":"<timeObj2Updated>", ...}",
         where timeGroupUpdated is the earliest time an object was updated on the group.
         */

        //Prepare to get what we need from the database
        let transaction = db.transaction(["objects"], "readonly");
        let objectStore = transaction.objectStore("objects");

        //Fetch the objects
        let objects = {};
        objectsIDs.forEach(function(item){
            let request = objectStore.get(item);

            request.onsuccess = function(event) {
                //Since the item exists on the client side, put it in the right place
                if(request.result !== undefined){
                    let objGroup;
                    //If object has a group, and that group doesn't yet exist in Objects
                    if(request.result.group !== undefined)
                        objGroup = request.result.group;
                    else{
                        objGroup ='@';
                    }
                    if(objects[objGroup] === undefined)
                        objects[objGroup] = {};
                    //If this is the first time we are accessing this group
                    if(objects[objGroup]['@'] === undefined && objGroup!='@')
                        objects[objGroup]={"@":10000000000};
                    objects[objGroup][item] = request.result.lastUpdated;
                    if(objects[objGroup][item]<objects[objGroup]['@'])
                        objects[objGroup]['@'] = objects[objGroup][item];
                }
                //Means the item doesn't exist on the client side
                else{
                    if(objects['@'] === undefined)
                        objects['@']={};
                    objects['@'][item] = 0;
                }
            };
        });

        transaction.oncomplete = function(event) {
            objects = JSON.stringify(objects);
            //Prepare to query the DB for the objects
            let action;
            action = "action=r&params="+objects;
            updateCSRFToken().then(
                function(token){
                    action += '&CSRF_token='+token;
                    // url
                    let url=document.ioframe.pathToRoot+"api\/v1\/objects";
                    //Request itself
                    let xhr = new XMLHttpRequest();
                    xhr.open('GET', url+'?'+action);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=utf-8;');
                    xhr.send(null);
                    xhr.onreadystatechange = function () {
                        let DONE = 4; // readyState 4 means the request is done.
                        let OK = 200; // status 200 is a successful return.
                        if (xhr.readyState === DONE) {
                            if (xhr.status === OK){
                                let response = xhr.responseText;
                                let resp = {};
                                if(IsJsonString(response))
                                    resp = JSON.parse(response);
                                else
                                    console.log('Unexpected response:',response);

                                //Get the lists of errors and group map
                                let errorList = resp['Errors'];
                                let groupMap = resp['groupMap'];
                                delete(resp['Errors']);
                                delete(resp['groupMap']);

                                let objectList = {};                    //List of objects to update
                                //Put every object and error in its place
                                for (let key in resp) {
                                    if (resp.hasOwnProperty(key)) {
                                        objectList[key] = resp[key];
                                    }
                                }


                                //Open DB transaction to delete outdated objects and add new ones
                                let transaction2 = db.transaction(["objects"], "readwrite",{durability:"strict"});

                                //Delete outdated objects
                                let objectStore1 = transaction2.objectStore("objects");
                                //Go over the errors, delete every object whose error isn't "0"
                                if(errorList !=[]){
                                    let deleteOutdated;
                                    for (let key in errorList) {
                                        if (errorList.hasOwnProperty(key)) {
                                            if (errorList[key] != '0')
                                                deleteOutdated = objectStore1.delete(key);
                                        }
                                    }
                                    if(deleteOutdated !== undefined)
                                        deleteOutdated.onsuccess = function(event) {
                                        };
                                }

                                //Add new objects
                                let objectStore2 = transaction2.objectStore("objects");
                                for (let key in objectList) {
                                    if (objectList.hasOwnProperty(key)) {
                                        let objectStoreRequest = objectStore2.put({id:key, content:objectList[key], group:groupMap[key],
                                            canModify:false, canView:true, lastUpdated:timenow});
                                    }
                                }

                                transaction2.oncomplete = function(event){
                                    //Send info on which objects were updated
                                    let updates = {};

                                    for (let key in errorList) {
                                        if (errorList.hasOwnProperty(key)) {
                                            if(errorList[key] !== 0)
                                                updates[key] = errorList[key];
                                        }
                                    }

                                    for (let key in objectList) {
                                        if (objectList.hasOwnProperty(key)) {
                                            updates[key] = 'new';
                                        }
                                    }
                                    resolve( JSON.stringify(updates) );
                                    return JSON.stringify(updates) ;
                                };
                            }
                        } else {
                            if(xhr.status < 200 || xhr.status > 299 ){
                                console.log('Failed to get objects!',xhr);
                                reject();
                                return false;
                            }
                        }
                    };
                },
                function(reject){
                    alertLog('CSRF token expired. Please refresh the page to submit the form.','danger');
                }
            );
        };

    });
}