/*-------------------Function to be called on top of each page

* pathToRoot is the same as "document.ioframe.pathToRoot" in the default headers.
*
* Callbacks is an object of functions, where each key represents where the callback is invoked:
* 'notLoggedIn' - Called if you are not logged in.
* 'noRelog' - Called if you are not logged in AND don't have credentials to relog.
* 'loggedIn' - Called if you are logged in
* 'beforeRelog' - Called if you are not logged in, but have credentials to relog, before relog.
* 'afterRelogSuccess' - Called after you successfully tried to relog.
* 'afterRelogFailure' - Called after you tried to relog and failed.
* 'sessionInfoUpdated' - The default function to call after session info is updated - usually just page reload.
*
* */
function initPage(pathToRoot, callbacks = {}){

    if(callbacks['sessionInfoUpdated'] === undefined)
        callbacks['sessionInfoUpdated'] = function(){
            location.reload();
        };

    //Get current time in seconds
    let timenow = new Date().getTime();
    timenow = Math.floor(timenow/1000);
    /*LOCAL STORAGE RELATED*/
    if (typeof(Storage) === "undefined") {
        alertLog('Local storage not enabled! Certain functions will be available to you.', 'warning');
    }
    else{

        //--Crash Handler--//
        /*Add in a listener. It marks 'good_exit' true if the page was closed 'properly' - aka
        not by the browser closing or crashing, and not by the page crashing.*/

        //If good_exit isn't set yet, create it.
        if(!sessionStorage.getItem('good_exit') || sessionStorage.getItem('good_exit') === 'true')
            window.addEventListener('load', function () {
                sessionStorage.setItem('good_exit', 'pending');
            });
        //Here we check if the last time a page in our domain was closed, it was closed properly.
        else{
            //In case of improper closure, we check to see if we are still logged in
            console.log('Possible crash, trying to recover...');
            checkLoggedIn(document.ioframe.pathToRoot, false).then(function(res){
                    //If we are not logged in, log in. Else, carry on
                    if(!res){
                        if(callbacks['notLoggedIn']!==undefined)
                            callbacks['notLoggedIn']();
                        localStorage.setItem("sesInfo","[]");
                        if(localStorage.getItem('myMail')!==undefined){
                            autoLogin(pathToRoot,1,callbacks).then(function(res){
                                sessionStorage.setItem('autologDebug'+Date.now(),'Crash recovery attempt!');
                                updateSesInfo(pathToRoot,callbacks);
                            });
                        }
                        else{
                            sessionStorage.setItem('autologDebug'+Date.now(),'Crash recovery attempt!');
                            updateSesInfo(pathToRoot,callbacks);
                        }
                    }
                    else{
                        if(callbacks['loggedIn']!==undefined)
                            callbacks['loggedIn']();
                        console.log('All clear, still logged in.');
                    }
                }, function(error) {
                    console.error("failed to check loggedIn status!", error);
                }
            );
        }

        //Here, we set 'good_exit' to true on a proper page close
        window.addEventListener('beforeunload', function () {
            sessionStorage.setItem('good_exit', 'true');
        });



        //Check to see if our session timed out since the last time
        if( (localStorage.getItem('lastActionTime')===null) || (localStorage.getItem('lastActionTime')===undefined) ||
            (localStorage.getItem('maxInacTime')===null) || (localStorage.getItem('maxInacTime')===undefined)
            ||
            (timenow > parseInt(localStorage.getItem('lastActionTime')) + parseInt(localStorage.getItem('maxInacTime')) )
            ||
            (document.ioframe.loggedIn === false)
        ){
            //If we did time out, let the client know
            localStorage.setItem("sesInfo","[]");
            if(callbacks['notLoggedIn']!==undefined)
                callbacks['notLoggedIn']();
        }


        //Tell the client that now is the last time he's made an action.
        updateLastActionTime();

        //If no device ID is present, generate one.
        if(localStorage.getItem("deviceID")==null ){
            generateFP();
        }

        //This is where the fun begins
        if(localStorage.getItem("sesInfo")!==null ){
            //If sesInfo is [], it means the last time the server told us we are logged out
            if(localStorage.getItem("sesInfo")=="[]" ){
                //Our "only hope" is that our auto relog info is valid
                if(localStorage.getItem("sesID")!==null && localStorage.getItem("sesIV")!==null ){
                    autoLogin(pathToRoot,0,callbacks).then(function(res){
                        if(res)
                            callbacks['sessionInfoUpdated'] = function(){};
                        updateSesInfo(pathToRoot,callbacks);

                    });
                }
                else{
                    if(callbacks['noRelog']!==undefined)
                        callbacks['noRelog']();
                }
            }
            else{
                if(callbacks['loggedIn']!==undefined)
                    callbacks['loggedIn']();
            }
        }
        else{
            updateSesInfo(pathToRoot,{});
            initPage(pathToRoot);
        }
    }
}

//-------------------Updates client with session info from the server
function updateSesInfo(pathToRoot, callbacks = {}){

    return new Promise(function(resolve, reject) {
        let action;
        action = 'logged_in&Username&Auth_Rank&Actions&maxInacTime&Email&Active&CSRF_token';
        // url
        let url=pathToRoot+"api\/v1\/session";
        //Request itself
        let xhr = new XMLHttpRequest();
        xhr.open('GET', url+'?'+action);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=utf-8;');
        //console.log('To url',url,' , send: ',action);
        //-return;
        xhr.send(null);
        xhr.onreadystatechange = function () {
            let DONE = 4; // readyState 4 means the request is done.
            let OK = 200; // status 200 is a successful return.
            if (xhr.readyState === DONE) {
                if (xhr.status === OK){
                    let response = xhr.responseText;
                    //Notify the console we got a response!
                    //console.log('Got response!',response);
                    //Update local storage
                    localStorage.setItem('sesInfo',response);
                    //Now, we need to do some work with the response, unless it's []
                    if(response!=="[]"){
                        let sesInfo=JSON.parse(response);
                        localStorage.setItem('maxInacTime',sesInfo['maxInacTime']);
                        localStorage.setItem('CSRF_token',sesInfo['CSRF_token']);
                        if(sesInfo['Email'] != undefined)
                            localStorage.setItem('myMail',sesInfo['Email']);
                    }
                    resolve(true);
                    if(callbacks['sessionInfoUpdated'] !== undefined)
                        callbacks['sessionInfoUpdated']();
                }
            } else {
                if(xhr.status < 200 || xhr.status > 299 ){
                    console.log('Error: ' + xhr.status); // An error occurred during the request.
                    resolve(false);
                }
            }
        };
    });
}

//-------------------Updates client with time of last action taken, in seconds since 01.01.1970
function updateLastActionTime(){
    let timenow = new Date().getTime();
    timenow = Math.floor(timenow/1000);
    localStorage.setItem('lastActionTime',timenow);
}

/*-------------------Tries to automatically log in.
* -------------------Returns true and updates relevant fields on success.
* -------------------Deletes relevant fields and returns false on faliure.
* */
function autoLogin(pathToRoot, timeout = 0, callbacks = {}, test = false){

    return new Promise(function(resolve, reject) {

        if(document.ioframe.loggedIn){
            console.log('Tried to relog despite being logged in!');
            sessionStorage.setItem('debug_'+Date.now(),'Tried to relog despite being logged in!');
            resolve(true);
            return;
        }
        else{
            sessionStorage.setItem('debug_last_autologin_attempt',Date.now());
        }

        if(callbacks['beforeRelog']!==undefined)
            callbacks['beforeRelog']();

        let req;
        if(test)
            req = 'test';
        else
            req = 'new';

        //Data to be sent
        let dataToSend ='action=logUser';
        dataToSend+='&req='+req;
        dataToSend+='&log=temp';
        dataToSend+='&m='+localStorage.getItem('myMail');
        dataToSend+='&userID='+localStorage.getItem('deviceID');

        //Get the kew to use from localstorage
        let keyToUse = stringScrumble(localStorage.getItem('sesID'),localStorage.getItem('sesIV'));

        //This is the encrypted key we need to send to the servervar encrypted = CryptoJS.AES.encrypt(CryptoJS.enc.Utf8.parse(data),
        const tokenToSend = CryptoJS.AES.encrypt(CryptoJS.enc.Utf8.parse(localStorage.getItem('deviceID')),
            CryptoJS.enc.Hex.parse(keyToUse),
            {mode:CryptoJS.mode.ECB, padding:CryptoJS.pad.ZeroPadding});
        updateCSRFToken().then(
            //Continue after getting the CSRF token
            function(token){
                dataToSend +='&sesKey='+tokenToSend.ciphertext.toString(CryptoJS.enc.Hex);

                dataToSend += '&CSRF_token='+token;
                const url = pathToRoot+"api/v1/users";
                const header = 'application/x-www-form-urlencoded;charset=utf-8;';
                //console.log('To url',url,' , send: ',dataToSend);
                //return;
                let xhr = new XMLHttpRequest();
                xhr.open('POST', url+'?'+dataToSend);
                xhr.setRequestHeader('Content-Type', header);
                xhr.send(null);
                xhr.onreadystatechange = function () {
                    let DONE = 4; // readyState 4 means the request is done.
                    let OK = 200; // status 200 is a successful return.
                    let d = new Date();
                    if (xhr.readyState === DONE) {
                        if (xhr.status === OK){
                            let response = xhr.responseText;
                            //console.log('Got ',xhr.responseText);
                            //-return;
                            //Notify the console we got a response! React to each case accodingly.
                            //console.log('Got response trying to auto log in! Response '+response);
                            switch(response){
                                case 'INPUT_VALIDATION_FAILURE':
                                    sessionStorage.setItem('autologDebug_'+d.getTime(),'User auto-login failed - incorrect input');
                                    break;
                                case 'AUTHENTICATION_FAILURE':
                                    sessionStorage.setItem('autologDebug_'+d.getTime(),'User auto-login failed - authentication failure');
                                    break;
                                case 'WRONG_CSRF_TOKEN':
                                    sessionStorage.setItem('autologDebug_'+d.getTime(),'User auto-login failed - CSRF token wrong (somehow)');
                                    break;
                                case '1':
                                    sessionStorage.setItem('autologDebug_'+d.getTime(),'User auto-login failed - username and token combination is wrong!');
                                    //We don't need those if the server told us they are wrong...
                                    localStorage.removeItem("sesID");
                                    localStorage.removeItem("sesIV");
                                    break;
                                case '2':
                                    sessionStorage.setItem('autologDebug_'+d.getTime(),'User auto-login failed - expired!');
                                    //If we cannot use temp login, what use are those parameters?
                                    localStorage.removeItem("sesID");
                                    localStorage.removeItem("sesIV");
                                    break;
                                case '3':
                                    sessionStorage.setItem('autologDebug_'+d.getTime(),'User auto-login failed - tried one-time login when server does not allow it!');
                                    //If we cannot use temp login, what use are those parameters?
                                    localStorage.removeItem("sesID");
                                    localStorage.removeItem("sesIV");
                                    break;
                                default:
                                    if( (response.length === 128 ) &&
                                        (response.match(/(\W)/g)==null) ){
                                        //We got a new sesID
                                        if(validateServer(response,keyToUse)){
                                            if(callbacks['afterRelogSuccess']!==undefined)
                                                callbacks['afterRelogSuccess']();
                                            resolve(true);
                                            return;
                                        }
                                        else sessionStorage.setItem('autologDebug_'+d.getTime(),'Could not perform auto-login: '+response);
                                        //Remember to udate session info0
                                    }
                                    //Means this is something else...
                                    else{
                                        sessionStorage.setItem('autologDebug'+d.getTime(),'User auto-login failed - response '+response+' timeout '+timeout);
                                        localStorage.removeItem("sesID");
                                        localStorage.removeItem("sesIV");
                                    }
                            }
                            if(callbacks['afterRelogFailure']!==undefined)
                                callbacks['afterRelogFailure']();
                            resolve(false);
                            return;
                        }
                    } else {
                        if(xhr.status < 200 || xhr.status > 299 ){
                            //If we have a timeout, recursively call this function after 1.5 sec and try again
                            if((timeout > 0) && (timeout !==undefined) && !isNaN(timeout)){
                                sessionStorage.setItem('autologDebug'+d.getTime(),'Error, logUser not reachable!' +
                                    ' Timeout is '+timeout+', trying again..');
                                setTimeout(function(){
                                    autoLogin(pathToRoot,timeout-1,callbacks,test).then(function(res){
                                        resolve(res);
                                        return;
                                    });
                                }, 1500);
                            }
                            //If we have no timeout, report so and resolve with false.
                            else{
                                sessionStorage.setItem('autologDebug'+d.getTime(),'Error, logUser not reachable!');
                                if(callbacks['afterRelogFailure']!==undefined)
                                    callbacks['afterRelogFailure']();
                                resolve(false);
                                return;
                            }
                        }
                    }
                };
            },
            function(reject){
                alertLog('CSRF token expired. Please refresh the page, or login manually.','danger');
            }
        );
    });

}


/*Part of the auto-login protocol.
 * Validates whether the server sent you the correct sesID that you have, and if so, updates to the next sesID that was sent.
 * */
function validateServer(serRes,keyToUse){
    let params = {
        ciphertext: CryptoJS.enc.Hex.parse(serRes),
        salt: ""
    };
    let decrypted = CryptoJS.AES.decrypt(params,
        CryptoJS.enc.Hex.parse(keyToUse),
        {mode:CryptoJS.mode.ECB, padding:CryptoJS.pad.ZeroPadding});
    let sesID1 = stringDecrumble(decrypted.toString(CryptoJS.enc.Utf8),1);
    let sesID2 = stringDecrumble(decrypted.toString(CryptoJS.enc.Utf8),2);

    if(sesID1 === localStorage.getItem('sesID')){
        localStorage.setItem('sesID',sesID2);
        return true;
    }
    else{
        let message = 'Server validation failed! Your connection could be under attack. Expected '+
            localStorage.getItem('sesID')+', got '+sesID1;
        console.log(message);
        alertLog(message,'danger');
        return false;
    }
}

