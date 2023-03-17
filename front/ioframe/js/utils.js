/**
 * Created by Igal Ogonov on 3/1/2017.
 */

//Returns true if an object is an HTML Element (Node)
function isElement(obj) {
    try {
        //Using W3 DOM2 (works for FF, Opera and Chrome)
        return obj instanceof HTMLElement;
    }
    catch(e){
        //Browsers not supporting W3 DOM2 don't have HTMLElement and
        //an exception is thrown and we end up here. Testing some
        //properties that all elements have (works on IE7)
        return (typeof obj==="object") &&
            (obj.nodeType===1) && (typeof obj.style === "object") &&
            (typeof obj.ownerDocument ==="object");
    }
}

/* Emits a visual alert
 * @param {string} str Alert to emit
 * @param {string} type Alert type (alert class will include 'alert-'+type)
 * @param {string} targetElement Element on top of which the alert will appear
 * @param {Object} params parameters of the form:
 *                  allowSpec: bool, default true - whether to allow html characters
 *                  extraClasses: string, default '' - extra classes to add.
 *                  dismissible: string, default 'button' - how to dismiss the alert. Set to 'click' to do so on click,
 *                                                          or to false to make the alert permenant.
 *                  closeClass: string, default '' - class of the close button (if using one)
 *
*
* */
function alertLog(str, type = 'info', targetElement = document.body, params = {}){

    if(params.allowSpec === undefined)
        params.allowSpec = true;

    if(params.extraClasses === undefined)
        params.extraClasses = 'alert-'+type;
    else
        params.extraClasses += ' alert-'+type;

    if(params.dismissible === undefined)
        params.dismissible = 'click';

    if(params.closeClass === undefined)
        params.closeClass = '';

    if(document.alertHandler === undefined)
        document.alertHandler = new ezAlert('alert');

    document.alertHandler.initAlert(targetElement,str,params);
}

//Checks if a string is Json
function IsJsonString(str) {
    try {
        JSON.parse(str);
    } catch (e) {
        return false;
    }
    return true;
}

//Generates a fingerprint for the user device
function generateFP(attrName, target){
    res = ["123"];
    if(attrName === undefined)
        attrName = "deviceID";
    if(target === undefined)
        target = "localStorage";

    var options = {
        excludeLanguage : true,
        excludeColorDepth : true,
        excludeAvailableScreenResolution: true,
        excludeScreenResolution : true,
        excludeTimezoneOffset : true,
        excludePlugins : true,
        excludeAdBlock: true
    };

    fingerprints = new Fingerprint2(options);

    fingerprints.get(function(result){
        if(target=='localStorage')
            localStorage.setItem(attrName,result);
    });
}

/**
 * Deep merge two objects.
 * @param target
 * @param ...sources
 */
function mergeDeep(target, ...sources) {
    if (!sources.length)
        return target;

    const isObject = function(item){
        return (item && typeof item === 'object' && !Array.isArray(item));
    }

    const source = sources.shift();

    if (isObject(target) && isObject(source)) {
        for (const key in source) {
            if (isObject(source[key])) {
                if (!target[key]) Object.assign(target, { [key]: {} });
                mergeDeep(target[key], source[key]);
            } else {
                if((source[key] !== undefined) && (source[key] !== null))
                    Object.assign(target, { [key]: source[key] });
                else
                    delete target[key];
            }
        }
    }

    return mergeDeep(target, ...sources);
}

// Simple but unreliable function to create string hash by Sergey.Shuchkin [t] gmail.com
// alert( strhash('http://www.w3schools.com/js/default.asp') ); // 6mn6tf7st333r2q4o134o58888888888
function strhash( str ) {
    if (str.length % 32 > 0) str += Array(33 - str.length % 32).join("z");
    var hash = '', bytes = [], i = j = k = a = 0, dict = ['a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','1','2','3','4','5','6','7','8','9'];
    for (i = 0; i < str.length; i++ ) {
        ch = str.charCodeAt(i);
        bytes[j++] = (ch < 127) ? ch & 0xFF : 127;
    }
    var chunk_len = Math.ceil(bytes.length / 32);   
    for (i=0; i<bytes.length; i++) {
        j += bytes[i];
        k++;
        if ((k == chunk_len) || (i == bytes.length-1)) {
            a = Math.floor( j / k );
            if (a < 32)
                hash += '0';
            else if (a > 126)
                hash += 'z';
            else
                hash += dict[  Math.floor( (a-32) / 2.76) ];
            j = k = 0;
        }
    }
    return hash;
}

// Returns a function, that, as long as it continues to be invoked, will not
// be triggered. The function will be called after it stops being called for
// wait milliseconds.
// This function generates a global scope timer for the function.
// The timer is either based on the hash of the function source, or is given as input.
function debounce(func, wait, timerName = '') {
    if(timerName == '')
        timerName = strhash(func.toString());
    if(document[timerName] === undefined)
        document[timerName] = {
            'function': function(){},
            'lastCalled':Date.now()
        };
    return function() {
        let context = this, args = arguments;
        let later = function() {
            document[timerName].function = function(){};
            func.apply(context, args);
        };
        clearTimeout(document[timerName].function);
        document[timerName].function = setTimeout(later, wait);
    };
}

// Similar to debounce, but instead throttles execution to once per "wait"
function throttle(func, wait, timerName = '') {
    let now = Date.now();
    if(timerName == '')
        timerName = strhash(func.toString());
    if(document[timerName] === undefined)
        document[timerName] = {
            'function': func,
            'lastCalled':now - wait
        };
    return function() {
        let context = this, args = arguments;
        let later = function() {
            document[timerName].function = function(){};
            document[timerName].lastCalled = Date.now();
            func.apply(context, args);
        };
        let correctTimeout;
        let delta = now - document[timerName].lastCalled;
        //If we passed the last wait time, the function was already executed
        //If we called the function earlier that "wait" milliseconds ago, set a timeout to call it in the right time
        if(delta < wait){
            correctTimeout = document[timerName].lastCalled - now + wait;
        }
        //Else, just call the function
        else{
            document[timerName].lastCalled = now;
            correctTimeout = 0;
        }
        clearTimeout(document[timerName].function);
        document[timerName].function = setTimeout(later, correctTimeout);
    };
}

//generates a random id
function makeid(length,characters='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz') {
    var result           = '';
    var charactersLength = characters.length;
    for ( var i = 0; i < length; i++ ) {
        result += characters.charAt(Math.floor(Math.random() * charactersLength));
    }
    return result;
}

//Returns a random int in the range of 0 to max-1
function randomInt(max) {
    return Math.floor(Math.random() * Math.floor(max));
}

//To be used with await
function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

//Checks if we are logged in, returns resault using the dfd because it might need to call the server
function checkLoggedIn(pathToRoot,trustLocalStorage=false){
    return new Promise(function(resolve, reject) {
        //If we are logged in
        let res = false;
        (document.loggedIn === true)?
            res = true : res = false;

        if ((typeof(Storage) !== "undefined") && trustLocalStorage && res) {
            let sesInfo = localStorage.getItem('sesInfo');
            if(IsJsonString(sesInfo)){
                sesInfo = JSON.parse(sesInfo);
                if(sesInfo['Username'] !== undefined)
                    res = sesInfo['Username'];
            }
        }
        resolve(res);
    });
}

//Updated CSRF Token
function updateCSRFToken(consumeExisting = true){

    return new Promise(function(resolve, reject) {
        let currentToken = localStorage.getItem('CSRF_token');
        if(currentToken){
            localStorage.removeItem('CSRF_token');
            resolve(currentToken);
            return;
        }
        else{
            let action;
            action = 'CSRF_token';
            // url
            let url=document.rootURI+"api\/v1\/session";
            //Request itself
            var xhr = new XMLHttpRequest();
            xhr.open('GET', url+'?'+action);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=utf-8;');
            //console.log('To url',url,' , send: ',action);
            //-return;
            xhr.send(null);
            xhr.onreadystatechange = function () {
                var DONE = 4; // readyState 4 means the request is done.
                var OK = 200; // status 200 is a successful return.
                if (xhr.readyState === DONE) {
                    if (xhr.status === OK){
                        let response = xhr.responseText;
                        console.log(response);
                        if(response){
                            var sesInfo=JSON.parse(response);
                            resolve(sesInfo['CSRF_token']);
                        }
                        else
                            reject(false);
                        return;
                    }
                } else {
                    if(xhr.status < 200 || xhr.status > 299 ){
                        console.log('Error: ' + xhr.status); // An error occurred during the request.
                        reject(false);
                        return;
                    }
                }
            };
        }
    });
}

//-------------------Encrypts text given the text, key and IV
function encryptText(data,key,iv){

    var encrypted = CryptoJS.AES.encrypt(CryptoJS.enc.Utf8.parse(data), CryptoJS.enc.Hex.parse(key), { iv: CryptoJS.enc.Hex.parse(iv) });
    return encrypted.ciphertext;
}

//-------------------Decrypts text given the text, key and IV
function decryptText(){
    /*
     var data = "519f2f58848f45bd5967a950a08430";
     var key = '0000000000000000000000000000000000000000000000000000000000000000';
     var iv = '795bedf96c3472429055da08b8c5b752';

     var encrypted = CryptoJS.AES.encrypt(CryptoJS.enc.Utf8.parse(data), CryptoJS.enc.Hex.parse(key), { iv: CryptoJS.enc.Hex.parse(iv) });

     console.log( 'Ciphertext: [' + encrypted.ciphertext + ']' );
     console.log( 'Key:        [' + encrypted.key + ']' );

     cipherParams = CryptoJS.lib.CipherParams.create({ciphertext: CryptoJS.enc.Hex.parse(encrypted.ciphertext.toString())});
     var decrypted = CryptoJS.AES.decrypt(cipherParams, CryptoJS.enc.Hex.parse(key), { iv: CryptoJS.enc.Hex.parse(iv) });

     console.log( 'Cleartext:  [' + decrypted.toString(CryptoJS.enc.Utf8) + ']');
     */
}

//Combines each consecutive character of 2 strings, into 1 string. for example, "abc" and "def" will combine into "adbecf".
function stringScrumble(str1, str2){
    var res='';
    if(str1.length!=str2.length){
        return false;
    }
    else{
        for(i = 0; i<str1.length; i++){
            res=res.concat(str1.charAt(i)).concat(str2.charAt(i));
        }
    }
    return res;
}

//Descrumbles the string.
//Mode 0 - returns JSON of both strings
//Mode 1 - returns odd string
//Mode 2 - Returns even string
function stringDecrumble(str,mode){
    if(str.length%2 !=0 )
        return false;
    var res='';
    if(mode===undefined)
        mode = 1;
    switch (mode){
        case 1:
            for(i=0; i<str.length; i+=2){
                res=res.concat(str.charAt(i));
            }
            break;
        case 2:
            for(i=1; i<str.length; i+=2){
                res=res.concat(str.charAt(i));
            }
            break;
    }
    return res;
}


/* Loads an image from an upload "input" element to be the source of a target img element
 *
 * input - either a node, or a query that finds the node using document.querySelector
 * img - either a node, or a query that finds the node using document.querySelector
 *
 * optional parameters:
 *      'checkElements' - checks whether the elements are valid nodes (and assume they are queries if they aren't)
 *
 * Returns a promise that resolves true when the function is done, or resolves false if no file is uploaded
 * (or one of the elements is invalid)
 * */
function displayImageFromInput(input,img, params = {}){

    return new Promise(function(resolve, reject) {

        if(params['checkElements'] === undefined)
            params['checkElements'] = true;


        //Return if we didn't find our elements, or they are invalid
        if(params['checkElements']){
            if(!isElement(input))
                input = document.querySelector(input);

            if(!isElement(img))
                img = document.querySelector(img);

            if(!isElement(input) || !isElement(img) || img.nodeName!== 'IMG' || input.nodeName!== 'INPUT' ){
                console.log('Elements not found or invalid!');
                resolve(false);
                return;
            }
        }

        //Read the uploaded file
        let files = input.files;

        //Return if no files are uploaded
        if(files.length === 0){
            console.log('No files uploaded, cannot generate preview!');
            resolve(false);
            return;
        }

        let file = files[0];

        var imageType = file.type;
        var blob = null;

        file.arrayBuffer().then(function(resolve,reject){
            blob = new Blob( [ resolve ], { type: imageType } );
            let urlCreator = window.URL || window.webkitURL;
            img.src = urlCreator.createObjectURL( blob );
        });

    });
}

/** A shortcut function to bind a specific file upload element to an image element for preview
 * input - input node
 * img - image node
 * params - object of parameters, of form:
 *      'callback' - potential callback to be executed after a click
 *      'bindClick'- Binds the click on the image to the upload field (aka clicking on the image opens the upload)
**/
function bindImagePreview(input,img,params = {}){

    //Optional callback after click
    var callback = (params['callback']!==undefined)?
        params['callback'] : function(){};

    //Whether to make clicks on the image open the upload
    var bindClick = (params['bindClick'])?
        true : false;
    if(bindClick){
        if(isElement(params['bindClick']))
            params['bindClick'].onclick = function () {
                input.click();
            };
        else
            img.onclick = function () {
                input.click();
            };
    }

    input.onchange = function(){
        displayImageFromInput(input,img,{'checkElements':false});
        callback();
    }
}

/** Returns Luminance given RGB values.
 * If perceived  is true, will return perceived luminance, else standard luminance.
 * If percentage is true, will return a number between 0 and 100 - the percentage of Luminance relative to maximum (#fff)
 * **/
function getLuminance(R,G,B, percieved = true, percentage = true){
    let divider = 1;
    let coefficientR = (percieved)? 0.299 : 0.2126;
    let coefficientG = (percieved)? 0.587 : 0.7152;
    let coefficientB = (percieved)? 0.114: 0.0722;
    if(percentage)
        divider = Math.sqrt(coefficientR*Math.pow(255,2) + coefficientG*Math.pow(255,2) + coefficientB*Math.pow(255,2)) / 100;
    const luminance = Math.sqrt(coefficientR*Math.pow(R,2) + coefficientG*Math.pow(G,2) + coefficientB*Math.pow(B,2));
    return (luminance / divider);
}

/** Returns a the size of something, in B/KB/MB/GB/TB, given the size in bites
 * */
function getReadableSize(bytes){
    let magnitude = 0;
    while(bytes > 1000 && magnitude < 5){
        bytes = bytes/1000;
        magnitude++;
    }
    let suffix;
    switch (magnitude){
        case 1:
            suffix = 'KB';
            break;
        case 2:
            suffix = 'MB';
            break;
        case 3:
            suffix = 'GB';
            break;
        case 4:
            suffix = 'TB';
            break;
        default:
            suffix = 'B';
    }
    return Math.round(bytes)+suffix;
}

/* Gets current time - in regard to server offset */
function getCurrentDate(){
    const offset = document.serverTimeDelta*1000;
    const currentTime = Date.now()+offset;
    return new Date(+currentTime);
}

/* Given the (shitty) standard DD-MM-YYYY, returns the unix timestamp */
function dateToTimestamp(date){
    date= date.split("-");
    var newDate =date[0]+","+date[1]+","+date[2];
    let temp = new Date(newDate);
    //Time minus timezone offset (minutes => ms))
    return temp.getTime() - temp.getTimezoneOffset()*1000*60;
}

/* Given the unix timestamp, returns the (shitty) standard DD-MM-YYYY */
function timestampToDate(timestamp){
    //Default date handling in JS is horrible
    let date = new Date(+timestamp);

    let realDate = date.getFullYear() + '-';

    let month =  date.getMonth()+1;
    if(month<10)
        month = '0'+month;
    realDate += month+'-';

    date = date.getDate();
    if(date<10)
        date = '0'+date;
    realDate += date;

    return realDate;
}

/*Converts a common PHP Timestamp into a date*/
function timeStampToFullDate(timestamp){
    timestamp -= 0;
    timestamp += document.serverTimeDelta ?? 0;
    timestamp *= 1000;
    let date = timestampToDate(timestamp);
    let hours = Math.floor(timestamp%(1000 * 60 * 60 * 24)/(1000 * 60 * 60));
    let minutes = Math.floor(timestamp%(1000 * 60 * 60)/(1000 * 60));
    let seconds = Math.floor(timestamp%(1000 * 60)/(1000));
    if(hours < 10)
        hours = '0'+hours;
    if(minutes < 10)
        minutes = '0'+minutes;
    if(seconds < 10)
        seconds = '0'+seconds;
    return {date:date,hours:hours,minutes:minutes,seconds:seconds};
}

/*Converts a common PHP Timestamp into a readable date*/
function timeStampToReadableFullDate(timestamp, round = 3){
    let obj = timeStampToFullDate(timestamp);
    let res = obj.date.split('-').reverse().join('-');
    if(round > 0)
        res += ', ' + obj.hours;
    if(round > 1)
        res += ':'+ obj.minutes;
    if(round > 2)
        res += ':'+obj.seconds;
    return  res;
}

function timeStampToReadable(timestamp){
    return  timeStampToReadableFullDate(timestamp,0);
}
function timeStampToReadableHours(timestamp){
    return  timeStampToReadableFullDate(timestamp,1);
}
function timeStampToReadableMinutes(timestamp){
    return  timeStampToReadableFullDate(timestamp,2);
}

/** Unescape html characters
* @credit https://stackoverflow.com/questions/1912501/unescape-html-entities-in-javascript/1912522#1912522
* */
function htmlDecode(input){
    var e = document.createElement('textarea');
    e.innerHTML = input;
    // handle case of empty input
    return e.childNodes.length === 0 ? "" : e.childNodes[0].nodeValue;
}
