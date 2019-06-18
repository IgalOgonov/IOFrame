/* This class is meant to easily create alerts, similar to Bootstrap alerts but without the need to include
   Bootstrap or jQuery.

 * There is a way to make it work with Bootstrap css - initiate a class instance with className 'alert', and put the type
   of alert you want (for example - alert-info) inside the extraClasses string when initiating an alert.
   Or write your own css.
* */
class ezAlert{
    //This is the name of the main class that will be assigned to alerts. Use it to control the alerts visual via CSS.
    constructor(className) {
        this.className = className;
    }

    /* The target can be an element ID, or the element itself.
       It will first check whether the target is a string, then whether an id like that exists.
       If the target is not a string, it will check whether it is an object.

    * Content can be any content for the alert. Only if allowSpec is true can the content contain HTML (and most other characters)

    * If dismissible is not false, alert will be dismissible.
      If the value is 'button', will create a button to dismiss (default).
      If the value is 'click', will be dismissible on click.

    * If allowSpec is false, content will only be allowed to contain word characters,',','.','?','!', and '.

    * extraClasses will add more classes to this specific alert.

    * closeClass will specify the name of the button that closes the class (default 'close')
    * */
    initAlert(target, content, dismissible = 'button', allowSpec = true, extraClasses = '', closeClass = ''){
        //If we didn't get an element, maybe we got a string that represents an object ID
        if(!this.isElement(target)){
            if(typeof(target) == 'string'){
                target = document.getElementById(target);
            }
        }
        //Validate extraClasses
        if(typeof(extraClasses) != 'string' )
            extraClasses = '';
        //Handle dismissible
        if(dismissible !== 'button' && dismissible !== 'click'){
            if(dismissible) dismissible = 'button';
        }
        //If we do not allow any special characters
        if(!allowSpec){
            let regex = /\w| |\.|\,|\!|\?|\"'/g;
            let found = content.match(regex);
            if(found.length < content.length){
                console.log('Alert content may not have any characters that do not match ',regex);
                return false;
            }
        }
        //If we still have nothing, the element is invalid
        if(target === undefined || target === null){
            console.log('Invalid element to create alert at!');
            return false;
        }
        //Create the alert
        let alert;
        (dismissible == 'click')?
         alert = document.createElement("a"): alert = document.createElement("div");
        alert.className=this.className+" "+extraClasses;
        alert.innerHTML = content;
        if(dismissible == 'click'){
            alert.href = '#';
            alert.style.display = 'block';
            alert.style.textDecoration = 'none';
            alert.addEventListener('click',e =>{e.target.parentNode.removeChild(e.target)});
        }
        if(dismissible == 'button'){
            let alertClose = document.createElement("a");
            alertClose.innerHTML = 'X';
            alertClose.href = '#';
            (closeClass == '')?
                alertClose.className = 'close'
                : alertClose.className = closeClass;
            alertClose.style.textDecoration = 'none';
            alertClose.style.position = 'relative';
            alertClose.style.float = 'right';
            alertClose.style.padding = '0px 10px 0px 0px';
            alertClose.style.fontWeight = '800';
            alert.appendChild(alertClose);
            alertClose.addEventListener('click',e =>{e.target.parentNode.parentNode.removeChild(e.target.parentNode)});
        }
        target.prepend(alert);
    };

    //This has to be implemented so that this plugin is independant of any outside utility functions
    isElement(obj){
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
    };

};


/*
* myTarget = document.querySelector("#The_old-fashioned_way > span");

myTarget.addEventListener('click',e =>{e.target.parentNode.parentNode.removeChild(e.target.parentNode)})
* */