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

    /* The target
    * @param {string|object} target Can be an element ID, or the element itself.
    *                        It will first check whether the target is a string, then whether an id like that exists.
    *                        If the target is not a string, it will check whether it is an object.
    * @param {string} Content Any content for the alert.
    *                         Only if allowSpec is true can the content contain HTML (and most other characters)
    * @param {Object} params parameters of the form:
    *                  allowSpec: bool, default true - whether to allow html characters
    *                  extraClasses: string, default '' - will add more classes to this specific alert..
    *                  dismissible: string, default 'button' - how to dismiss the alert.
    *                                                If the value is 'button', will create a button to dismiss (default).
    *                                                If the value is 'click', will be dismissible on click.
    *                  closeClass: string, default '' - will specify the name of the button that closes the class (default 'close')
    *                  autoDismiss: int, default null - if present, will auto-dismiss the alert after int milliseconds
    *                  autoDismissTimerClass: int, default '' - will specify the name of the line that shows the remaining time (default 'time-left')
    * */
    initAlert(target, content, params){
        if(params.allowSpec === undefined)
            params.allowSpec = true;
        if(params.extraClasses === undefined)
            params.extraClasses = '';
        if(params.dismissible === undefined)
            params.dismissible = 'button';
        if(params.closeClass === undefined)
            params.closeClass = '';
        if(params.autoDismiss === undefined)
            params.autoDismiss = null;
        if(params.autoDismissTimerClass === undefined)
            params.autoDismissTimerClass = '';
        //If we didn't get an element, maybe we got a string that represents an object ID
        if(!this.isElement(target)){
            if(typeof(target) == 'string'){
                target = document.getElementById(target);
            }
        }
        //Validate extraClasses
        if(typeof(params.extraClasses) != 'string' )
            params.extraClasses = '';
        //Handle dismissible
        if(params.dismissible && (params.dismissible !== 'button' && params.dismissible !== 'click')){
            params.dismissible = 'button';
        }
        //If we do not allow any special characters
        if(!params.allowSpec){
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
        alert = document.createElement("div");
        alert.className=this.className+" "+params.extraClasses;
        alert.innerHTML = content;
        if(params.dismissible == 'click'){
            alert.className +=' click';
            alert.style.display = 'block';
            alert.style.textDecoration = 'none';
            alert.style.cursor = 'pointer';
            alert.addEventListener('click',e =>{e.target.parentNode.removeChild(e.target)});
        }
        if(params.dismissible == 'button'){
            alert.className +=' button';
            let alertClose = document.createElement("p");
            alertClose.innerHTML = 'X';
            alertClose.href = '';
            (params.closeClass == '')?
                alertClose.className = 'close'
                : alertClose.className = params.closeClass;
            alertClose.style.textDecoration = 'none';
            alertClose.style.position = 'relative';
            alertClose.style.bottom = '10px';
            alertClose.style.float = 'right';
            alertClose.style.padding = '0px 10px 0px 0px';
            alertClose.style.fontWeight = '800';
            alertClose.style.cursor = 'pointer';
            alert.appendChild(alertClose);
            alertClose.addEventListener('click',e =>{e.target.parentNode.parentNode.removeChild(e.target.parentNode)});
        }
        if(params.autoDismiss){
            let time = params.autoDismiss - 0;
            alert.style.position = 'absolute';
            let alertTimer = document.createElement("p");
            alertTimer.innerHTML = '';
            (params.autoDismissTimerClass == '')?
                alertTimer.className = 'time-left'
                : alertTimer.className = params.autoDismissTimerClass;
            alertTimer.style.width = '100%';
            alertTimer.style.position = 'absolute';
            alertTimer.style.bottom = '0';
            alertTimer.style.left = '0';
            alertTimer.style.height = '3px';
            alertTimer.style.background = 'rgba(125,125,125,0.5)';
            alertTimer.style.animationName = 'closingWidth';
            alertTimer.style.animationTimingFunction = 'linear';
            alertTimer.style.borderRadius = '800';
            alertTimer.style.animationDuration = (time+10)+'ms';

            alert.appendChild(alertTimer);

            alert.timeoutId = setTimeout(function(){
               alert.parentNode.removeChild(alert);
            },time);
            alert.addEventListener('mouseenter',e =>{
                clearTimeout(alert.timeoutId);
                e.target.querySelector('.'+alertTimer.className).remove();
            });

            alert.addEventListener('mouseleave',e =>{
                alert.appendChild(alertTimer);
                alert.timeoutId = setTimeout(function(){
                    alert.parentNode.removeChild(alert);
                },time);
            });

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