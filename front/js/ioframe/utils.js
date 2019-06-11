/**
 * Created by IO on 3/1/2017.
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

//Creates an bootstrap alert with the content of str, type of type, above errorLog.
//types are success, info, warning, and danger.
function alertLog(str, type = 'info', allowSpec = true, closeClass = ''){
    if(document.alertHandler === undefined)
        document.alertHandler = new ezAlert('alert');
    document.alertHandler.initAlert(document.body,str,'button',allowSpec,'alert-'+type,closeClass);
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
