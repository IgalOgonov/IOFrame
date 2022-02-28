

//-----------This component is responsible for each plugin
Vue.component('plugin-order', {
    template: '\
        <tr>\
          <td  class="order-indices" :id="dynamicIdIndex">{{index}}</td>\
          <td class = "order-names">{{fileName}}: {{name}}</td>\
          <td  class="order-icons"><img :src="iconURL"  draggable-element="true" class="order-drag" \
                draggable="true"\
                ondragstart="pluginOrderList.dragStart(event)"\
                ondragenter="pluginOrderList.dragEnter(event)"\
                ondragleave="pluginOrderList.dragLeave(event)"\
                ondragover="pluginOrderList.dragOver(event)"\
                ondrop="pluginOrderList.dragDrop(event)"\
                :id="dynamicIdIcon"\></td>\
        </tr>\
        ',
    methods: {
    },
    props: {
        index: Number,
        fileName: String,
        name: String,
        icon: String,
        thumbnail: String
    },
    computed:{
        iconURL: function(){
            return document.pathToRoot+this.icon;
        },
        dynamicIdIcon: function(){
            return 'pluginorder'+this.fileName+'icon';
        },
        dynamicIdIndex: function(){
            return 'pluginorder'+this.fileName+'index';
        }
    },
    mounted: function(){
    }
});

//------------The plugin list component, which is responsible for everything
var pluginOrderList = new Vue({
    el: '#plugin-order-list',
    name: 'Plugin order list',
    data: {
        temp: {},
        replyInTransit: false,
        isVisible: false,
        initiated: false,
        movementType: 'move',
        pluginOrder: []
    },
    methods: {

        //STARTS HERE --- All of the functions bellow handle drag and drop
        dragStart: function(ev){
            // Add the target element's id to the data transfer object
            ev.dataTransfer.setData("text/plain", ev.target.id);
            //console.log('Starting to drag element ',ev.target.id);
        },
        dragEnter: function(ev){
            ev.preventDefault();
            let draggedID = ev.dataTransfer.getData("text/plain");
            let targetID = ev.target.id;
            if(draggedID == targetID)
                return;
            ev.target.parentElement.parentElement.className = 'active-order';
            //console.log('Element ',draggedID,' entered element ',targetID);
        },
        dragLeave: function(ev){
            let draggedID = ev.dataTransfer.getData("text/plain");
            let targetID = ev.target.id;
            if(draggedID == targetID)
                return;
            ev.target.parentElement.parentElement.className = '';
            //console.log('Element ',draggedID,' exited element ',targetID);
        },
        dragOver: function(ev){
            ev.preventDefault();
        },
        dragDrop: function(ev){
            ev.preventDefault();
            let draggedID = ev.dataTransfer.getData("text/plain");
            draggedIndexID = draggedID.substring(0,draggedID.length-4)+'index';
            let indexDragged = document.getElementById(draggedIndexID).innerHTML;
            let targetID = ev.target.id;
            targetIndexID = targetID.substring(0,targetID.length-4)+'index';
            let indexTarget = document.getElementById(targetIndexID).innerHTML;
            if(draggedID == targetID)
                return;
            ev.target.parentElement.parentElement.className = '';
            switch(pluginOrderList.movementType){
                case 'move':
                    pluginOrderList.move(indexDragged,indexTarget);
                    break;
                case 'swap':
                    pluginOrderList.swap(indexDragged,indexTarget);
                    break;
                default :
                    console.log('Wrong order movement mode!');
            }
        },
        //END HERE -- All of the functions above handle drag and drop

        setMovement: function(type){
            this.movementType = type;
        },
        toggleVisible: function(){
            this.isVisible = !this.isVisible;
        },

        //---- Get plugin order
        getOrder: function(){
            let action;
            action = 'action=getOrder';
            //Api url
            let url=document.pathToRoot+'api\/plugins';
            //Request itself
            var xhr = new XMLHttpRequest();
            xhr.open('GET', url+'?'+action);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=utf-8;');
            xhr.send(null);
            xhr.onreadystatechange = function () {
                var DONE = 4; // readyState 4 means the request is done.
                var OK = 200; // status 200 is a successful return.
                if (xhr.readyState === DONE) {
                    if (xhr.status === OK){
                        //Handle the response, then populate the plugins with their info
                        pluginOrderList.pluginOrder = JSON.parse( xhr.responseText );
                        pluginOrderList.populatePluginOrder(true);
                    }
                } else {
                    if(xhr.status < 200 || xhr.status > 299 )
                        console.log('Error: ' + xhr.status); // An error occurred during the request.
                }
            };
        },

        //---- Populate plugins inside "order" with the info we need
        populatePluginOrder: function(forceAPICall = false, timeOut = 0, maxTimeOut = 10){
            //First try to get info from the plugins module, which could be in the same page as this module
            if(pluginList.updateComplete != undefined && !forceAPICall){
                //Wait up to timeOut seconds
                if((maxTimeOut-timeOut>0) && (pluginList.updateComplete == false)){
                    setTimeout(function(){
                        pluginOrderList.populatePluginOrder(timeOut+1,maxTimeOut);
                    },1000);
                    return;
                }
                //If we did not manage that, try to get plugins info from the api
                if(pluginList.updateComplete == false){
                    console.log('Getting plugins timed out after '+(timeOut)+' seconds!');
                    pluginOrderList.populatePluginOrder(true);
                }
                //If the info is available, use it.
                else{
                    let pInfo;
                    for(let i = 0; i < pluginOrderList.pluginOrder.length; i++){
                        pInfo = pluginList.plugins[pluginOrderList.pluginOrder[i]];
                        Vue.set(pluginOrderList.pluginOrder, i, {'fileName':pluginOrderList.pluginOrder[i]});
                        Vue.set(pluginOrderList.pluginOrder[i], 'name', pInfo['name']);
                        Vue.set(pluginOrderList.pluginOrder[i], 'icon', pInfo['icon']);
                        Vue.set(pluginOrderList.pluginOrder[i], 'thumbnail', pInfo['thumbnail']);
                    }
                    pluginOrderList.initiated = true;
                }
            }
            //If we could not get plugin info from pluginList, get it from the API
            else{
                let action;
                action = 'action=getInfo';
                //Api url
                let url=document.pathToRoot+'api\/plugins';
                //Request itself
                var xhr = new XMLHttpRequest();
                xhr.open('GET', url+'?'+action);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=utf-8;');
                xhr.send(null);
                xhr.onreadystatechange = function () {
                    var DONE = 4; // readyState 4 means the request is done.
                    var OK = 200; // status 200 is a successful return.
                    if (xhr.readyState === DONE) {
                        if (xhr.status === OK){
                            if(!IsJsonString(xhr.responseText)){
                                console.log(xhr.responseText);
                                alertLog('Could not get plugins!'+xhr.responseText,'danger');
                                return;
                            }
                            let resPlugins = JSON.parse(xhr.responseText);
                            for(let i=0; i<resPlugins.length; i++){
                                (resPlugins[i].icon)?
                                    resPlugins[i].icon = 'front/ioframe/img/pluginImages/'+resPlugins[i].fileName+'/icon.'+resPlugins[i].icon :
                                    resPlugins[i].icon = 'front/ioframe/img/pluginImages/def_icon.png';
                                (resPlugins[i].thumbnail)?
                                    resPlugins[i].thumbnail = 'front/ioframe/img/pluginImages/'+resPlugins[i].fileName+'/thumbnail.'+resPlugins[i].thumbnail :
                                    resPlugins[i].thumbnail = 'front/ioframe/img/pluginImages/def_thumbnail.png';
                                delete resPlugins[i].status;
                                delete resPlugins[i].version;
                                delete resPlugins[i].summary;
                                delete resPlugins[i].description;
                                delete resPlugins[i].uninstallStatus;
                                delete resPlugins[i].installStatus;
                                delete resPlugins[i].uninstallOptions;
                                delete resPlugins[i].installOptions;
                                //find index we need to replace
                                let targetIndex = pluginOrderList.pluginOrder.indexOf(resPlugins[i].fileName);
                                if(typeof targetIndex == 'number')
                                    Vue.set(pluginOrderList.pluginOrder,targetIndex,resPlugins[i])
                            }
                            pluginOrderList.initiated = true;
                        }
                    } else {
                        if(xhr.status < 200 || xhr.status > 299 ){
                            alertLog('Could not get plugins!'+xhr.responseText,'danger');
                            console.log('Error: ' + xhr.status); // An error occurred during the request.
                        }
                    }
                };
            }
        },
        swap: function(num1,num2, uiOnly = false){
            num1 = Number(num1);
            num2 = Number(num2);
            //console.log('swapping numbers ',num1,num2);
            //Call the API for a swap
            if(!uiOnly){
                updateCSRFToken().then(
                    function(token){
                        let action;
                        action = 'action=swapOrder&p1='+num1+'&p2='+num2;
                        action += '&CSRF_token='+token;
                        //---- Get available plugins
                        //Api url
                        let url=document.pathToRoot+'api\/plugins';
                        //Request itself
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', url+'?'+action);
                        //console.log('swapping numbers, sending',action);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=utf-8;');
                        pluginOrderList.replyInTransit = true;
                        xhr.send(null);
                        xhr.onreadystatechange = function () {
                            var DONE = 4; // readyState 4 means the request is done.
                            var OK = 200; // status 200 is a successful return.
                            if (xhr.readyState === DONE) {
                                if (xhr.status === OK){
                                    if(!IsJsonString(xhr.responseText) || xhr.responseText.length<3){   //Remember a 1/2 digits are valid JSON!
                                        switch (xhr.responseText){
                                            case 'INPUT_VALIDATION_FAILURE':
                                                alertLog('Some of your input is illegal or missing. Try installing/uninstalling again in test mode ' +
                                                    'to find the problem, or contact the plugin author.','danger');
                                                break;
                                            case 'AUTHENTICATION_FAILURE':
                                                alertLog('Authentication failure. You are not authorized to install/uninstall this plugin','danger');
                                                break;
                                            case 'WRONG_CSRF_TOKEN':
                                                alertLog('CSRF Token wrong! Refresh the page','danger');
                                                break;
                                            case '0':
                                                pluginOrderList.swap(num1,num2,true);
                                                break;
                                            case '1':
                                                console.log('one of the indices is not set (or empty order file)');
                                                alertLog('Unexpected error changing plugin order - see console!','danger');
                                                break;
                                            case '2':
                                                console.log('couldn\'t open order file, or order is not an array)');
                                                alertLog('Unexpected error changing plugin order - see console!','danger');
                                                break;
                                            default :
                                                console.log(xhr.responseText);
                                                alertLog('Unknown response changing plugin order!'+xhr.responseText,'danger');
                                        }
                                        pluginOrderList.replyInTransit = false;
                                    }
                                    else{
                                        let conflict = JSON.parse(xhr.responseText);
                                        for(let key in conflict){
                                            alertLog('Cannot change order! '+key+' depends on '+conflict[key],'warning');
                                        }
                                        pluginOrderList.replyInTransit = false;
                                    }
                                }
                            } else {
                                if(xhr.status < 200 || xhr.status > 299 ){
                                    alertLog('Could not get plugins!'+xhr.responseText,'danger');
                                    console.log('Error: ' + xhr.status); // An error occurred during the request.
                                    pluginOrderList.replyInTransit = false;
                                }
                            }
                        };
                    },
                    function(reject){
                        alertLog('CSRF token expired. Please refresh the page to submit the form.','danger');
                    }
                );
            }
            //Do the UI swap if successful
            else{
                Vue.set(this,'temp',this.pluginOrder[num2]);
                Vue.set(this.pluginOrder,num2,this.pluginOrder[num1]);
                Vue.set(this.pluginOrder,num1,this.temp);
            }
        },
        move: function(from,to, uiOnly = false){
            from = Number(from);
            to = Number(to);
            //console.log('moving numbers ',from,to);
            //Call the API for a move
            //--
            //Do the UI move if successful
            /**/
            if(!uiOnly){
                updateCSRFToken().then(
                    function(token){
                        let action;
                        action = 'action=moveOrder&from='+from+'&to='+to;
                        action += '&CSRF_token='+token;
                        //---- Get available plugins
                        //Api url
                        let url=document.pathToRoot+'api\/plugins';
                        //Request itself
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', url+'?'+action);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=utf-8;');
                        pluginOrderList.replyInTransit = true;
                        xhr.send(null);
                        xhr.onreadystatechange = function () {
                            var DONE = 4; // readyState 4 means the request is done.
                            var OK = 200; // status 200 is a successful return.
                            if (xhr.readyState === DONE) {
                                if (xhr.status === OK){
                                    if(!IsJsonString(xhr.responseText) || xhr.responseText.length<3){  //Remember a 1/2 digits are valid JSON!
                                        switch (xhr.responseText){
                                            case '-2':
                                                alertLog('Authentication failure! Make sure you are authorized to change plugin order.','danger');
                                                break;
                                            case '-1':
                                                console.log('input validation failure for order change');
                                                alertLog('Unexpected error changing plugin order - see console!','danger');
                                                break;
                                            case '0':
                                                pluginOrderList.move(from,to,true);
                                                break;
                                            case '1':
                                                console.log('one of the indices is not set (or empty order file)');
                                                alertLog('Unexpected error changing plugin order - see console!','danger');
                                                break;
                                            case '2':
                                                console.log('couldn\'t open order file, or order is not an array)');
                                                alertLog('Unexpected error changing plugin order - see console!','danger');
                                                break;
                                            default :
                                                console.log(xhr.responseText);
                                                alertLog('Unknown response changing plugin order!'+xhr.responseText,'danger');
                                        }
                                        pluginOrderList.replyInTransit = false;
                                    }
                                    else{
                                        let conflict = JSON.parse(xhr.responseText);
                                        for(let key in conflict){
                                            alertLog('Cannot change order! '+key+' depends on '+conflict[key],'warning');
                                        }
                                        pluginOrderList.replyInTransit = false;
                                    }
                                }
                            } else {
                                if(xhr.status < 200 || xhr.status > 299 ){
                                    alertLog('Could not get plugins!'+xhr.responseText,'danger');
                                    console.log('Error: ' + xhr.status); // An error occurred during the request.
                                    pluginOrderList.replyInTransit = false;
                                }
                            }
                        };
                    },
                    function(reject){
                        alertLog('CSRF token expired. Please refresh the page to submit the form.','danger');
                    }
                );
            }
            //Do the UI swap if successful
            else {
                if (from > to) {
                    for (let i = from; i > to; i--)
                        this.swap(i, i - 1, true);
                }
                else {
                    for (let i = from; i < to; i++)
                        this.swap(i, i + 1, true);
                }
            }
        }
    },
    created: function(){
        this.getOrder();
    }
});