
//The bridge between each plugin, and the main VM.
if(eventHub === undefined)
    var eventHub = new Vue();

//This component is responsible for the install/uninstall prompt
Vue.component('pluginInstallPrompt', {
    template: '<div :id="optionID" v-html="option"></div>',
    //Yes I WANT a single event handler for all options - and yet, a new one is still created each time =(
    data: {
        popupHandler:''
    },
    props:{
        optionName:String,
        name:String,
        type:String,
        list:Object,
        desc:String,
        placeholder:String,
        maxLength:Number,
        maxNum:Number,
        optional:Boolean
    },
    computed:{
        //This computed property basically renders different options dynamically, depending on the option type
        option: function(){
            let res = '';
            res +='<label>'+this.name+'</label>';
            switch(this.type){
                case 'radio':
                    if(this.list === undefined)     //Must have a list
                        return '';
                    for(let key in this.list){
                        res +='<input type="'+this.type+'" name="'+this.optionName+'" value="'+this.list[key]+'"';
                        if(this.optional !== true)
                            res+= ' required';
                        res +='> '+key;
                    }
                    break;
                case 'select':
                    if(this.list === undefined)     //Must have a list
                        return '';
                    res +='<select name="'+this.optionName+'"';
                    if(this.optional !== true)
                        res+= ' required';
                    res+='>';
                    for(let key in this.list){
                        res+='<option value='+this.list[key]+'>'+key+'</option>';
                    }
                    res +='</select>';
                    break;
                case 'checkbox':
                    if(this.list === undefined)     //Must have a list
                        return '';
                    for(let key in this.list){
                        res +='<input type="'+this.type+'" name="'+this.list[key]+'"';
                        if(this.optional !== true)
                            res+= ' required';
                        res +='> '+key;
                    }
                    break;
                case 'textarea':
                    res +='<textarea name="'+this.optionName+'" ';
                    if(this.maxLength !== undefined){
                        res += 'maxlength="'+this.maxLength+'" ';
                    }
                    if(this.placeholder !== undefined){
                        res += 'placeholder="'+this.placeholder+'" ';
                    }
                    if(this.optional !== true)
                        res+= 'required ';
                    res +='></textarea>';
                    break;
                default:
                    res +='<input type="'+this.type+'" name="'+this.optionName+'" ';
                    if(this.maxLength !== undefined){
                        res += 'maxlength="'+this.maxLength+'" ';
                    }
                    if(this.maxNum !== undefined){
                        res += 'max="'+this.maxNum+'" ';
                    }
                    if(this.placeholder !== undefined){
                        res += 'placeholder="'+this.placeholder+'" ';
                    }
                    if(this.optional !== true)
                        res+= 'required ';
                    res +='>';
            }
            if(this.desc !== undefined){
                res += '<a href="#" id="'+this.optionName.toLowerCase()+'_desc">?</a>';
            }
            if(this.optional === true)
                res += '<a href="#" id="'+this.optionName.toLowerCase()+'_opt">*</a>';
            return  res;
        },
        optionID: function(){
            return this.optionName+'_field';
        }
    },
    mounted: function(){
        //Initiate the class if needed
        if(this.popupHandler === undefined)
            this.popupHandler = new ezPopup("pop-up-tooltip");
        //Initiate desc if exists
        if(this.desc !== undefined)
            this.popupHandler.initPopup(this.optionName.toLowerCase()+'_desc',this.desc,this.optionName.toLowerCase()+'_desctooltip');
        //Mark as optional, if it is
        if(this.optional === true)
            this.popupHandler.initPopup(this.optionName.toLowerCase()+'_opt','Optional',this.optionName.toLowerCase()+'_opttooltip');
    },
    destroyed: function(){
        //Hopefully it helps cleanup?
        Vue.set(this.popupHandler, null);
    }

});

//This component is responsible for each plugin
Vue.component('plugin', {
    template: '\
        <tr>\
          <td  class="plugin-icons"><img :src="iconURL"></td>\
          <td class = "plugin-names"><span class="plugin-name">{{computeName}}</span><a href="#" class="real-name-popup" :id="lowerCaseFilename">*</a></td>\
          <td>\
            <span class ="plugin-summary">{{summary}}</span><br>\
            <span class ="plugin-version" v-text="\'Available Version: \'+version"></span> <span class ="plugin-version" v-if="currentVersion" v-text="\'Current Version: \'+currentVersion"></span><br>\
            <span class ="plugin-description">{{description}}</span>    \
          </td>\
          <td class ="plugin-statuses"> {{status}}</td>\
          <td class = "plugin-buttons">\
            <button class="plugin-update plugin-button positive-1"  v-bind:class="{isActive:updateButton(\'once\'), isInactive:!updateButton(\'once\')}" @click="updateOnce">Update Once</button><br>\
            <button class="plugin-update plugin-button positive-1"  v-bind:class="{isActive:updateButton(\'latest\'), isInactive:!updateButton(\'latest\')}" @click="updateLatest">Update To Latest Version</button><br>\
            <button class="plugin-quick-install plugin-button positive-1"  v-bind:class="{isActive:installButtons(\'quick\'), isInactive:!installButtons(\'quick\')}" @click="qinstall">Quick Install</button><br>\
            <button class="plugin-full-install plugin-button positive-1" v-bind:class="{isActive:installButtons(\'full\'), isInactive:!installButtons(\'full\')}" @click="finstall">Full Install</button>\
          </td>\
          <td class = "plugin-buttons">\
            <button class="plugin-quick-uninstall plugin-button negative-1" v-bind:class="{isActive:uninstallButtons(\'quick\'), isInactive:!uninstallButtons(\'quick\')}" @click="quninstall">Quick Uninstall</button><br>\
            <button class="plugin-full-uninstall plugin-button negative-1" v-bind:class="{isActive:uninstallButtons(\'full\'), isInactive:!uninstallButtons(\'full\')}" @click="funinstall">Uninstall</button>\
          </td>\
        </tr>\
        ',
    methods: {
        qinstall: function(){
            eventHub.$emit('qinstall',this.filename);
        },
        finstall: function(){
            eventHub.$emit('finstall',this.filename);
        },
        quninstall: function(){
            eventHub.$emit('quninstall',this.filename);
        },
        funinstall: function(){
            eventHub.$emit('funinstall',this.filename);
        },
        updateOnce: function(){
            eventHub.$emit('updateOnce',this.filename);
        },
        updateLatest: function(){
            eventHub.$emit('updateLatest',this.filename);
        },
        installButtons: function(type){
            if(this.status == 'active')
                return false;
            switch(type){
                case 'quick':
                    return this.installStatus=='quick' || this.installStatus == 'both';
                    break;
                case 'full':
                    return this.installStatus=='full' || this.installStatus == 'both';
                    break;
                default:
                    return true;
            }
        },
        updateButton: function(type){
            if((this.status !== 'active') || (this.hasUpdateFiles === false) || !this.currentVersion)
                return false;
            //Check whether any updates are available
            let hasSingleUpdate = false,
                hasMultipleUpdates = false;
            for(let index = 0; index < this.updateRanges.length; index++){
                let range = typeof this.updateRanges[index] === 'number' ? [this.updateRanges[index],this.updateRanges[index]] : this.updateRanges[index];
                if((this.currentVersion >= range[0]) && (this.currentVersion <= range[1])){
                    if(!hasSingleUpdate){
                        hasSingleUpdate = true;
                        hasMultipleUpdates = (this.updateRanges[index+1] !== undefined);
                        break;
                    }
                }
            }
            return type === 'once' ? hasSingleUpdate : hasMultipleUpdates;

        },
        uninstallButtons: function(type){
            switch(type){
                case 'quick':
                    return this.uninstallStatus=='quick' || this.uninstallStatus == 'both';
                    break;
                case 'full':
                    return this.uninstallStatus=='full' || this.uninstallStatus == 'both';
                    break;
                default:
                    return true;
            }
        }
    },
    props: {
        filename: String,
        status: String,
        version: Number,
        currentVersion: {
            type: Number,
            default: 0
        },
        summary: String,
        name: String,
        description: String,
        uninstallStatus: String,
        installStatus: String,
        icon: String,
        thumbnail: String,
        hasUpdateFiles: Boolean,
        updateRanges: Array,
        installOptions: Object,
        uninstallOptions: Object
    },
    computed:{
        iconURL: function(){
            return document.pathToRoot+this.icon;
        },
        thumbURL: function(){
            return document.pathToRoot+this.thumbnail;
        },
        lowerCaseFilename: function(){
            return this.filename.toLowerCase()+'realname';
        },
        computeName: function(){
            return pluginList.testMode? this.filename+' | '+this.name : this.name;
        }
    },
    mounted: function(){
        //Initiate the class if needed
        if(this.popupHandler === undefined)
            this.popupHandler = new ezPopup("pop-up-tooltip");
        //Initiate desc if exists
        this.popupHandler.initPopup(this.filename.toLowerCase()+'realname','Real name: '+this.filename,this.filename.toLowerCase()+'realName');
    }
});

//The plugin list component, which is responsible for everything
var pluginList = new Vue({
    el: '#plugin-list',
    name: 'Plugin list',
    data: {
        updateComplete: false,
        currentOptions: {},     //Currently displayed options in the prompt
        currentPlugin: '',      //Currently active plugin
        currentPluginName: '',  //Current plugin "pretty" name
        currentAction: '',      //Current action, as defined in 'methods'
        testMode: false,        //Indicates we are in test mode
        showPrompt: false,      //Whether to show install prompt or not
        showResponse: false,    //Whether to show server response
        serverResponse: '',     //Response we got from the server
        ghost_test:{            //Test plugin for client side. The fileName is illegal, so it will always return an error when sent.
            name:'Ghost Test Plugin',
            status:'ghost',
            version:1,
            summary:'Test Summary',
            description:'This plugin does not exist. Trying to install it will always return an error. '+
            'This is a long description. Very long. Huge, even! ' +
            'See, it even spans multiple lines! ' +
            ' Who would have thought a simple description could span such a long distance! ' +
            ' Alright, I think you got the point now.',
            uninstallStatus:'quick',
            installStatus:'quick',
            icon:'front/ioframe/img/pluginImages/def_icon.png',
            thumbnail:'front/ioframe/img/pluginImages/def_thumbnail.png',
            installOptions:{"testOption1":{"name":"Test Select","type":"select", list:{"Opt1":1,"Opt2":2}, desc:"Select test"},
                "testOption2":{"name":"Test Number","type":"number", desc:"Number test", maxNum:1000, placeholder:"number bellow 1000"},
                "testOption3":{"name":"Test Textarea","type":"textarea", desc:"Text area test", maxLength:20, placeholder:"up to 20 characters"},
                "testOption4":{"name":"Test Email","type":"email", desc:"Email test", placeholder:"mail"},
                "testOption5":{"name":"Test Password","type":"password", desc:"Password test", placeholder:"password"},
                "testOption6":{"name":"Test Text","type":"text", placeholder:"some text", desc:"Text test"},
                "testOption7":{"name":"Test Radio","type":"radio", list:{"B1":'Button1',"B2":'Button2'}, desc:"Radio test", optional:true},
                "testOption8":{"name":"Test Checkbox","type":"checkbox", list:{"Desc 1":'c1',"Desc 2":'c2'}, desc:"Checkbox test", optional:true}
            },
            uninstallOptions:{}
        },
        plugins: {
        }
    },
    methods: {
        //We are doing a quick install
        qinstall: function (plugin) {
            Vue.set(pluginList , 'currentOptions' ,pluginList.plugins[plugin].installOptions);
            Vue.set(pluginList , 'currentPluginName' , pluginList.plugins[plugin].name);
            Vue.set(pluginList , 'currentPlugin' , plugin);
            Vue.set(pluginList , 'currentAction' , 'install');
            this.showPrompt = true;
        },
        //We are doing a full install
        finstall: function(plugin){
            Vue.set(pluginList , 'currentOptions' ,{});
            Vue.set(pluginList , 'currentPluginName' , pluginList.plugins[plugin].name);
            Vue.set(pluginList , 'currentPlugin' , plugin);
            Vue.set(pluginList , 'currentAction' , 'perform full installation on');
            this.showPrompt = true;
        },
        //We are doing a quick uninstall
        quninstall: function (plugin) {
            Vue.set(pluginList , 'currentOptions' ,pluginList.plugins[plugin].uninstallOptions);
            Vue.set(pluginList , 'currentPluginName' , pluginList.plugins[plugin].name);
            Vue.set(pluginList , 'currentPlugin' , plugin);
            Vue.set(pluginList , 'currentAction' , 'uninstall');
            this.showPrompt = true;
        },
        //We are doing a full uninstall
        funinstall: function(plugin){
            Vue.set(pluginList , 'currentOptions' ,{});
            Vue.set(pluginList , 'currentPluginName' , pluginList.plugins[plugin].name);
            Vue.set(pluginList , 'currentPlugin' , plugin);
            Vue.set(pluginList , 'currentAction' , 'perform full uninstall on');
            this.showPrompt = true;
        },
        //We are updating a plugin
        updateOnce: function(plugin){
            Vue.set(pluginList , 'currentOptions' ,{});
            Vue.set(pluginList , 'currentPluginName' , pluginList.plugins[plugin].name);
            Vue.set(pluginList , 'currentPlugin' , plugin);
            Vue.set(pluginList , 'currentAction' , 'update (once)');
            this.showPrompt = true;
        },
        //We are updating a plugin
        updateLatest: function(plugin){
            Vue.set(pluginList , 'currentOptions' ,{});
            Vue.set(pluginList , 'currentPluginName' , pluginList.plugins[plugin].name);
            Vue.set(pluginList , 'currentPlugin' , plugin);
            Vue.set(pluginList , 'currentAction' , 'update (to latest version)');
            this.showPrompt = true;
        },
        //Toggle server response visibility
        toggleResponse: function(){
            this.showResponse = !this.showResponse;
        },
        //Toggle options prompt
        togglePrompt: function(){
            this.showPrompt = !this.showPrompt;
        },
        //Toggle test prompt - and add/remove ghost_test
        toggleTest: function(){
            this.testMode = !this.testMode;
            if(this.testMode === true){
                Vue.set(this.plugins,'ghost_test',this.ghost_test)
            }
            else{
                Vue.delete(this.plugins,'ghost_test');
            }
        },
        //Main function to handle the install form
        handleForm: function(){
            let expectedOptions = '';
            switch(this.currentAction){
                case 'install':
                    expectedOptions = this.plugins[this.currentPlugin].installOptions;
                    break;
                case 'uninstall':
                    expectedOptions = this.plugins[this.currentPlugin].uninstallOptions;
                    break;
                case 'perform full installation on':
                    expectedOptions = 'fi';
                    break;
                case 'perform full uninstall on':
                    expectedOptions = 'fu';
                    break;
                case 'update (once)':
                case 'update (to latest version)':
                    expectedOptions = 'update';
                    break;
                default:
                    console.log('Wrong action type!',this.currentAction);
                    return;
            }
            if(expectedOptions!== 'fi' && expectedOptions !=='fu' && expectedOptions !=='update'){
                let values = {};
                for(let key in expectedOptions){
                    switch(expectedOptions[key]['type']){
                        case 'select':
                            values[key] =  document.querySelector("select[name='"+key+"']").value;
                            break;
                        case 'textarea':
                            values[key] =  document.querySelector("textarea[name='"+key+"']").value;
                            break;
                        case 'checkbox':
                            for(let key2 in expectedOptions[key]['list']){
                                let valName = expectedOptions[key]['list'][key2];
                                values[key] = {};
                                values[key][valName] = document.querySelector("input[name='"+valName+"']").checked;
                            }
                            break;
                        case 'radio':
                            const temp = document.querySelector("input[name='"+key+"']:checked");
                            (temp !== null)?
                                values[key] = temp.value : values[key] = '' ;
                            break;
                        default:
                            values[key] =  document.querySelector("input[name='"+key+"']").value;
                    }
                }

                if(this.testMode === true) //Test mode
                    console.log(values);

                //Validate values
                let valid = true;
                for(let key in values){
                    this.setOptionBG(key);
                    if( (values[key] == '' || values[key] == []) && (expectedOptions[key]['optional'] === undefined) ){
                        this.setOptionBG(key, 'rgba(255,0,0,0.75)');
                        if(this.testMode === true){
                            //alertLog('Please fill all required fields! Missing: '+expectedOptions[key]['name'],'warning');
                            console.log('Missing field:',key);
                        }
                        valid = false;
                    }
                }
                if(!valid)
                    return;
                //----Time to send the request
                let requestData = {};
                //If we are running in test mode, specify it
                if(this.testMode === true) //Test mode
                    requestData['req'] = 'test';
                //Set plagin name, action, options, and the url
                requestData['action'] = this.currentAction;
                requestData['name'] = this.currentPlugin;
                requestData['options'] = JSON.stringify(values);
                let url=document.pathToRoot+'api\/plugins';
                let sendData = '';
                for (let key in requestData) {
                    sendData += encodeURIComponent(key) + '=' +
                        encodeURIComponent(requestData[key]) + '&';
                };
                updateCSRFToken().then(
                    function(token){
                        sendData += 'CSRF_token='+token;
                        if(pluginList.testMode === true)
                            console.log('Data to send:',sendData, 'Url: ', url);
                        //Request itself
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', url+'?'+sendData);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=utf-8;');
                        xhr.send(null);
                        xhr.onreadystatechange = function () {
                            var DONE = 4; // readyState 4 means the request is done.
                            var OK = 200; // status 200 is a successful return.
                            if (xhr.readyState === DONE) {
                                if (xhr.status === OK){
                                    let response = xhr.responseText;
                                    //How we handle it in test mode
                                    if(pluginList.testMode === true){
                                        pluginList.serverResponse = response;
                                        pluginList.showResponse = true;
                                    }
                                    //How we handle it otherwise:
                                    else switch(response){
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
                                        case "0":
                                            if(pluginList.currentAction == 'install' || pluginList.currentAction == 'perform full installation on'){
                                                alertLog('Plugin installation successful!','success');
                                            }
                                            else{
                                                alertLog('Plugin uninstall successful!','success');
                                            }
                                            pluginList.updatePlugin(pluginList.currentPlugin);
                                            break;
                                        case "1":
                                            if(pluginList.currentAction == 'install' || pluginList.currentAction == 'perform full installation on'){
                                                alertLog('Plugin has already been installed, or is installed incorrectly.','warning');
                                            }
                                            else{
                                                alertLog('Plugin is either uninstalled or does not exist!','warning');
                                            }
                                            break;
                                        case "2":
                                            if(pluginList.currentAction == 'install' || pluginList.currentAction == 'perform full installation on'){
                                                alertLog('Plugin install file is missing, or plugin format is illegal!','warning');
                                            }
                                            else{
                                                alertLog('Plugin uninstall file is missing!','warning');
                                            }
                                            break;
                                        case "3":
                                            if(pluginList.currentAction == 'install' || pluginList.currentAction == 'perform full installation on'){
                                                alertLog('Dependencies missing! Could not install plugin!','warning');
                                            }
                                            else{
                                                alertLog('Dependencies still exist! Could not uninstall plugin!','warning');
                                            }
                                            break;
                                        case "4":
                                            alertLog('Options mismatch - provided options do not match plugin options. Try running in test mode.','warning');
                                            break;
                                        case "5":
                                            if(pluginList.currentAction == 'install' || pluginList.currentAction == 'perform full installation on'){
                                                alertLog('Plugin definitions could not be added - possibly because similar ones already exist!','warning');
                                            }
                                            else{
                                                alertLog('Unable to remove plugin definitions!','warning');
                                            }
                                            break;
                                        case "6":
                                            alertLog('An exception occurred during the (un)installation! Please contact plugin author.','warning');
                                            break;
                                        default :
                                            alertLog('Unknown error occurred, showing at the bottom:.','danger');
                                            pluginList.testMode = true;
                                            pluginList.serverResponse = response;
                                            pluginList.showResponse = true;
                                    }
                                }
                            } else {
                                if(xhr.status < 200 || xhr.status > 299 ){
                                    console.log('Failed to reach plugins, status: ' + xhr.status); // An error occurred during the request.
                                    alertLog('Could not get plugins!','danger');
                                }
                            }
                        };
                    },
                    function(reject){
                        alertLog('CSRF token expired. Please refresh the page to submit the form.','danger');
                    }
                );
            }
            else if(expectedOptions !=='update'){
                let redirect;
                switch (expectedOptions){
                    case 'fi':
                        redirect = location.href;
                        redirect = redirect.substring(0, redirect.indexOf('/plugins')+8);
                        redirect = redirect+'/../../api/v1/plugins?name='+pluginList.currentPlugin+'&action=fullInstall';
                        if(pluginList.testMode)
                            redirect += '&req=test';
                        location.assign(redirect);
                        break;
                    case 'fu':
                        redirect = location.href;
                        redirect = redirect.substring(0, redirect.indexOf('/plugins')+8);
                        redirect = redirect+'/../../api/v1/plugins?name='+pluginList.currentPlugin+'&action=fullUninstall';
                        if(pluginList.testMode)
                            redirect += '&req=test';
                        location.assign(redirect);
                        break;
                    default :
                        alertLog('Wrong installation option!','danger');
                }
            }
            else{
                if(this.testMode)
                    console.log('Updating '+this.currentPlugin+(this.currentAction === 'update (once)' ? ' once' : ' to latest version'));
                //----Time to send the request
                let requestData = {};
                //If we are running in test mode, specify it
                if(this.testMode === true) //Test mode
                    requestData['req'] = 'test';
                //Set plagin name, action, options, and the url
                requestData['action'] = 'update';
                requestData['name'] = this.currentPlugin;
                requestData['once'] = this.currentAction === 'update (once)';
                let url=document.pathToRoot+'api\/plugins';
                let sendData = '';
                for (let key in requestData) {
                    sendData += encodeURIComponent(key) + '=' +
                        encodeURIComponent(requestData[key]) + '&';
                };
                updateCSRFToken().then(
                    function(token){
                        sendData += 'CSRF_token='+token;
                        if(pluginList.testMode === true)
                            console.log('Data to send:',sendData, 'Url: ', url);
                        //Request itself
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', url+'?'+sendData);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=utf-8;');
                        xhr.send(null);
                        xhr.onreadystatechange = function () {
                            var DONE = 4; // readyState 4 means the request is done.
                            var OK = 200; // status 200 is a successful return.
                            if (xhr.readyState === DONE) {
                                if (xhr.status === OK){
                                    let response = xhr.responseText;
                                    //How we handle it in test mode
                                    if(pluginList.testMode === true){
                                        pluginList.serverResponse = response;
                                        pluginList.showResponse = true;
                                    }
                                    //How we handle it otherwise:
                                    else switch(response){
                                        case 'INPUT_VALIDATION_FAILURE':
                                            alertLog('Some of your input is illegal or missing. Try updating again in test mode ' +
                                                'to find the problem, or contact the plugin author.','danger');
                                            break;
                                        case 'AUTHENTICATION_FAILURE':
                                            alertLog('Authentication failure. You are not authorized to update this plugin. Maybe you are logged out?','danger');
                                            break;
                                        case 'WRONG_CSRF_TOKEN':
                                            alertLog('CSRF Token wrong! Try again, or refresh the page on continued failure.','danger');
                                            break;
                                        default :
                                            if(!IsJsonString(response)){
                                                alertLog('Unknown error occurred, showing at the bottom:.','danger');
                                                pluginList.testMode = true;
                                                pluginList.serverResponse = response;
                                                pluginList.showResponse = true;
                                                return;
                                            }
                                            response = JSON.parse(response);
                                            let message = '';
                                            let failure = '';
                                            let messageType = (response['resultType'] !== 'success-partial' ? response['resultType'] : 'info');
                                            switch (response['result']){
                                                case 0:
                                                    if(response['resultType'] === 'error')
                                                        message = 'Plugin no longer seems to be installed!';
                                                    else{
                                                        message = 'Successfully updated to latest version, '+response['newVersion'];
                                                        if(response['moreUpdates'])
                                                            message += `<br>
                                                            Plugin has a more recent version, that requires reinstalling it.`;
                                                    }
                                                    break;
                                                case 1:
                                                    message = 'Plugin update files are no longer valid!';
                                                    break;
                                                case 2:
                                                    messageType = 'info';
                                                    message = 'Plugin is already at latest version!';
                                                    break;
                                                case 3:
                                                    failure = 'updating would violate existing dependencies!';
                                                    if(response['resultType'] !== 'error')
                                                        message = `Plugin was updated up to version `+response['newVersion']+`, then <br>
                                                         updating stopped because `;
                                                    else
                                                        message = 'Updating failed because ';
                                                    message += failure;
                                                    break;
                                                case 4:
                                                    failure = `exception was thrown during update. Exception text: <br>
                                                    `+response['exception'];
                                                    if(response['resultType'] === 'error')
                                                        message = `Plugin was updated up to version `+response['newVersion']+`, then <br>
                                                         updating stopped because `;
                                                        message = 'Updating failed because ';
                                                    message += failure;
                                                    break;
                                                case 5:
                                                    failure = `exception was thrown during update with the text text: <br>
                                                    `+response['exception']+` <br>
                                                    and rollback failed with exception: <br>
                                                    `+response['exceptionInFallback'];
                                                    if(response['resultType'] !== 'error')
                                                        message = `Plugin was updated up to version `+response['newVersion']+`, then <br>
                                                         updating critically failed because `;
                                                    else
                                                        message = 'Updating critically failed because ';
                                                    message += failure;
                                                    break;
                                            }
                                            alertLog(message,messageType);
                                    }
                                }
                            } else {
                                if(xhr.status < 200 || xhr.status > 299 ){
                                    console.log('Failed to reach plugins, status: ' + xhr.status); // An error occurred during the request.
                                    alertLog('Could not get plugins!','danger');
                                }
                            }
                        };
                    },
                    function(reject){
                        alertLog('CSRF token expired. Please refresh the page to submit the form.','danger');
                    }
                );
            }
        },
        //Sets the field of an option to a certein color
        setOptionBG: function(opt, color = 'rgba(0,0,0,0)'){
            let option = document.querySelector('#'+opt+'_field');
            option.style.backgroundColor = color;
        },
        //Updates a plugin's info from the API. If no name is given, updates all plugins.
        updatePlugin: function(name = ''){
            let action;
            action = 'action=getInfo';
            if(name != '')
                action +='&name='+name;
            //---- Get available plugins
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
                            if(resPlugins[i].status == 'active' || resPlugins[i].status == 'legal'){
                                let fileName = resPlugins[i].fileName;
                                resPlugins[i].version = parseInt(resPlugins[i].version);
                                delete resPlugins[i].fileName;
                                //We either create a new plugin, or update an existing one
                                if(pluginList.plugins[fileName] === undefined)
                                    Vue.set(pluginList.plugins,fileName,resPlugins[i]);
                                else{
                                    for(let key in pluginList.plugins[fileName]){
                                        Vue.set(pluginList.plugins[fileName],key,resPlugins[i][key])
                                    }
                                }
                            }
                        }
                        pluginList.updateComplete = true;
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
    created: function(){
        //Listen to events
        eventHub.$on('qinstall', this.qinstall);
        eventHub.$on('finstall', this.finstall);
        eventHub.$on('quninstall', this.quninstall);
        eventHub.$on('funinstall', this.funinstall);
        eventHub.$on('updateOnce', this.updateOnce);
        eventHub.$on('updateLatest', this.updateLatest);
        this.updatePlugin();
    }
});