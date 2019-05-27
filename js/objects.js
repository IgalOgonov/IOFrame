/* Resolves true on success, rejects false on failure, or resolves the list of changed objects if we updated and got new objects
* */

function startObjectDB( params = []){
    return new Promise(function(resolve, reject) {
        var updatePage = (params['updatePage'] !== undefined)? params['updatePage'] : false;
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
                var hasStorage = false;
            }
            else
                hasStorage = true;
            //initialize the db
            var db;
            var request = indexedDB.open("ObjectCache",2);

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
                    var objectStore = db.createObjectStore("objects", { keyPath: "id" });
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
                    //If we are not updating the page, we are done
                    if(!updatePage){
                        db.close();
                        resolve(true);
                        return true;
                    }
                    else{
                        populatePageMap(db,params).then(function(res){
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

/* Populates an existing indxed db with objects from the page map.
 * */
function populatePageMap(db,params = []){
    return new Promise(function(resolve, reject){
        var updateObjects = (params['updateObjects'] !== undefined)? params['updateObjects'] : false;
        var extraPages = (params['extraPages'] !== undefined)? params['extraPages'] : [];
        var timenow = new Date().getTime();
        timenow = Math.floor(timenow/1000);
        if(localStorage.getItem("pageMap")== null)
            localStorage.setItem("pageMap",'');
        //Check to see if there were changes to the objects assigned to this page
        extraPages.push(document.currentPage);
        //console.log(extraPages);
        checkPageObjects(extraPages).then(function(res){
            //console.log(res);
            //We either got back a legit JSON array, or a different error
            if(!IsJsonString(res) || typeof JSON.parse(res) != 'object'){
                console.log('Error getting object map for page, got '+res);
                db.close();
                reject(false);
                return false;
            }

            var parsedResponse = JSON.parse(res);
            var pagesToUpdate = [];
            extraPages.forEach(function(pageName){
                //Page doesn't exist! So we can do no caching...
                if(parsedResponse[pageName] == 1){
                    assignNewObjects(pageName, timenow ,null);
                    delete(parsedResponse[pageName]);
                }
                //Page is up to date. Keep as is
                else if(parsedResponse[pageName] == 0){
                    pagesToUpdate.push(pageName);
                }
                else{
                    assignNewObjects(pageName, timenow ,JSON.parse(parsedResponse[pageName]));
                    pagesToUpdate.push(pageName);
                }
            });
            //Either we have to update the objects or we don't
            if(updateObjects && pagesToUpdate!=[]){
                updatePageObjects(pagesToUpdate,db).then(function(res){
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

/*Checks to see if the object map of the given page is up to date - localStorage vs Server
 * Gets page, a name of a page in the form %20path%20to%20page
 * Returns:     0 - Objects on the page are up to date
 *              1 - page doesn't exist on the server end, or is NULL (no objects are assigned to it)
 *              JSON Array of the form {"<ObjID>":"ObjID"} containing all the objects assigned to "page" - otherwise
 * */
function checkPageObjects(requestedPages = []){

    return new Promise(function(resolve, reject) {
        let storedPages = localStorage.getItem('pageMap');
        requestedPages.push('@');
        var timesUpdated = [];
        //See if our page map even exists
        IsJsonString(storedPages)?
            storedPages = JSON.parse(storedPages): storedPages == null;

        //Prepare to check the server for the actual objects assigned to the current page
        var header =  {
            'Content-Type': 'application/x-www-form-urlencoded;charset=utf-8;'
        };
        //Set parameters to send to the api
        let params = {
            pages:{}
        };
        //In case our page exists/does not exist, get/set timeUpdated
        requestedPages.forEach(function(page){
            if(storedPages[page] === undefined)
                params.pages[page] = 0;
            else{
                params.pages[page] = storedPages[page].timeUpdated;
            }
        });
        let action;
        action = "action=ga&params="+JSON.stringify(params);
        // url
        let url=document.pathToRoot+"_siteAPI\/objectAPI.php";
        //Request itself
        var xhr = new XMLHttpRequest();
        xhr.open('GET', url+'?'+action);
        //console.log(url+'?'+action);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=utf-8;');
        xhr.send(null);
        xhr.onreadystatechange = function () {
            var DONE = 4; // readyState 4 means the request is done.
            var OK = 200; // status 200 is a successful return.
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

    });

}

/* Assign new objects to a page in localStorage.
 * Gets page, the page to assign the objects to, and objJSON, a JSON strong of the form {"<ObjID>":"ObjID"}
 * Replaces the objects on that page with the object string given.
 * IF objJSON = '', deletes the page from localStorage, as it contains no objects.
 * */
function assignNewObjects(page, timenow, obj){
    //If objJSON is '', remove the page entry for that page
    if(obj == null){
        let pageMap = localStorage.getItem('pageMap');
        (pageMap == '' || pageMap == '{}')?
            pageMap = {}  : pageMap = JSON.parse(pageMap);
        if(page in pageMap)
            delete pageMap[page];
        localStorage.setItem('pageMap',JSON.stringify(pageMap));
    }
    //Else, update pageMap on that page
    else if(typeof obj == 'object'){
        let pageMap = localStorage.getItem('pageMap');
        (pageMap == '' || pageMap == '{}')?
            pageMap = {}  : pageMap = JSON.parse(pageMap);
        (pageMap.hasOwnProperty(page))?
            currPage = pageMap[page] : currPage =  {};
        currPage.timeUpdated = timenow;
        currPage.objects = obj;
        pageMap[page] = currPage;
        localStorage.setItem('pageMap',JSON.stringify(pageMap));
    }
}

/* Gets the objects, whose IDs are listed under "page"s in localStorage, from the server,
 * Then updates the objects in the offline database depending on the results.
 * Resolves 0 if no objects were updated, array of updated object IDs otherwise.
 * */
function updatePageObjects(pages,db){
    return new Promise(function(resolve, reject) {
        //console.log('Here! p:',pages);                //TODO REMOVE

        var timenow = new Date().getTime();
        timenow = Math.floor(timenow/1000);
        //get the pageMap, and see which objects this page should have
        let pageMap = JSON.parse(localStorage.getItem('pageMap'));
        // We'll create a temporary array first, to see which objects we need to check
        let objectsIDs = [];
        let objectExist = {};
        pages.forEach(function(page){
            for(let objID in pageMap[page].objects){
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
        var transaction = db.transaction(["objects"], "readonly");
        var objectStore = transaction.objectStore("objects");

        //Fetch the objects
        var objects = {};
        objectsIDs.forEach(function(item){
            //console.log(item);               //TODO DELETE
            var request = objectStore.get(item);

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
                //console.log(objects);               //TODO DELETE
            };
        });

        transaction.oncomplete = function(event) {
            objects = JSON.stringify(objects);
            //Prepare to query the DB for the objects
            let action;
            action = "action=r&params="+objects;
            // url
            let url=document.pathToRoot+"_siteAPI\/objectAPI.php";
            //Request itself
            var xhr = new XMLHttpRequest();
            xhr.open('GET', url+'?'+action);
            //console.log(url+'?'+action);               //TODO DELETE
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=utf-8;');
            xhr.send(null);
            xhr.onreadystatechange = function () {
                var DONE = 4; // readyState 4 means the request is done.
                var OK = 200; // status 200 is a successful return.
                if (xhr.readyState === DONE) {
                    if (xhr.status === OK){
                        let response = xhr.responseText;
                        let resp = {};
                        //console.log(response);               //TODO DELETE
                        if(IsJsonString(response))
                            resp = JSON.parse(response);
                        else
                            console.log('Unexpected response:',response);

                        //console.log(resp);               //TODO DELETE

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
                        //console.log(objectList);               //TODO DELETE
                        //console.log(errorList);               //TODO DELETE
                        //console.log(groupMap);               //TODO DELETE


                        //Open DB transaction to delete outdated objects and add new ones
                        var transaction2 = db.transaction(["objects"], "readwrite");

                        //Delete outdated objects
                        var objectStore1 = transaction2.objectStore("objects");
                        //Go over the errors, delete every object whose error isn't "0"
                        if(errorList !=[]){
                            for (let key in errorList) {
                                if (errorList.hasOwnProperty(key)) {
                                    if (errorList[key] != '0')
                                        var deleteOutdated = objectStore1.delete(key);
                                }
                            }
                            if(deleteOutdated !== undefined)
                                deleteOutdated.onsuccess = function(event) {
                                    //console.log('Deleted object from db! ');               //TODO DELETE ALL THIS
                                    //console.log(event);               //TODO DELETE ALL THIS
                                };
                        }

                        //Add new objects
                        var objectStore2 = transaction2.objectStore("objects");
                        //console.log(objectList);                  //TODO DELETE
                        for (let key in objectList) {
                            if (objectList.hasOwnProperty(key)) {
                                var objectStoreRequest = objectStore2.put({id:key, content:objectList[key], group:groupMap[key],
                                    canModify:false, canView:true, lastUpdated:timenow});
                            }
                        }

                        transaction2.oncomplete = function(event){
                            //Send info on which objects were updated
                            let updates = {};
                            //console.log(objectList);               //TODO DELETE
                            //console.log(errorList);               //TODO DELETE
                            // console.log(groupMap);               //TODO DELETE

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
        };

    });
}