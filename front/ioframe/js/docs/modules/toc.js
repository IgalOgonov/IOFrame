if(document.eventHub === undefined)
    document.eventHub = new Vue();

//***************************
//******BAP PRODUCT CATEGORIES APP*******
//***************************//
var IOFrameDocsToC = new Vue({
    el: '#toc',
    data: {
        tocTitle:'Table of Contents',
        menu:{
            '@selectionTree':[]
        }
    },
    created:function(){
        //Check if we got the cached version of the menu
        let cachedMenu = localStorage.getItem('IOFrame_Docs_Menu');

        //If not, get menu from the API
        if(cachedMenu == null){
            let input = {
                "general,General Information":
                {
                    "overview,Framework Overview":[],
                    "requirements,Requirements":[],
                    "installation,Installation":[],
                    "standards, Code Standards":[]
                },
                "structure,Framework Structure":
                {
                    "handlers_structure,Handlers":[],
                    "api_structure,APIs":[],
                    "utilities_structure,Utilities":[],
                    "others_structure,Others":{
                        "settings_structure,Settings":[],
                        "plugins_structure,Plugins":[],
                        "backEnd_structure,(Default) Front End":[],
                        "frontEnd_structure,(Default) Back End":[],
                        "db_structure,Database Structure":[],
                        "misc_structure,Misc":[]
                    }
                },
                "utilities,Utilities":{
                    "helperFunctions_utility,Helper Functions":[],
                    "PHPQueryBuilder_utility,PHP Query Builder":[],
                    "safeSTR_utility,Safe String":[],
                    "hArray_utility,hArray":[],
                    "XMLSerializer_utility,XML Serializer":[],
                    "validator_utility,validator":[],
                    "AltoRouter_utility,AltoRouter":[]
                },
                "handlers,Handlers":
                {
                    "settings_handlers,Settings Handler":[],
                    "sql_handlers,SQL Handler":[],
                    "user_handlers,User Handler":[],
                    "auth_handlers,Authorization Handler":[],
                    "file_handlers,File Handler":[],
                    "lock_handlers,Lock Handler":[],
                    "mail_handlers,Mail Handler":[],
                    "plugin_handlers,Plugin Handler":[],
                    "security_handlers,Security Handler":[],
                    "session_handlers,Session Handler":[],
                    "tree_handlers,Tree Handler":[],
                    "object_handlers,Object Handler":[],
                    "order_handlers,Order Handler":[],
                    "route_handlers,Route Handler":[],
                    "IOFrame_handlers,IOFrame Log Handler (for Monolog)":[],
                    "IP_handlers,IP Handler (Unfinished)":[],
                    "abstract_handlers,Abstract Classes":[],
                    "ext_handlers,External Handlers":{
                        "phpmailer_external,PHPMailer":[],
                        "geoIP_external,GeoIP":[],
                        "monolog_external,Monolog":[],
                        "paresedown_external,Parsedown":[]
                    }
                },
                "api,APIs":
                {
                    "settings_api,Settings API":[],
                    "users_api,User API":[],
                    "auth_api,Authorization API":[],
                    "session_api,Session API":[],
                    "trees_api,Tree API":[],
                    "plugin_api,Plugin API":[],
                    "objects_api,Object API":[],
                    "mail_api,Mail API (internal use)":[]
                },
                "frontEnd,(Default) Front End":
                {
                    "frontEnd_interfaces, Interfaces ":{
                        "frontEnd_admin,Admin Interface":[],
                        "frontEnd_plugins,Plugins Interface":[],
                        "frontEnd_objects,Objects Interface":[]
                    },
                    "frontEnd_js, General Javascript":{
                        "frontEnd_utils_js,JS Utilities":[],
                        "frontEnd_initPage_js,Page Initiation Script":[],
                        "frontEnd_ezAlert_js,ezAlert":[],
                        "frontEnd_ezPopup_js,ezPopup":[],
                        "frontEnd_objects_js,Objects JS File":[],
                        "frontEnd_external_plugins_js,External Plugins":[]
                    },
                    "frontEnd_modules, Modules & Templates":{
                        "frontEnd_logOut_module,Logout module":[],
                        "frontEnd_userLog_module,User Login module":[],
                        "frontEnd_userReg_module,User Registration module":[],
                        "frontEnd_plugins_module,Plugins module":[],
                        "frontEnd_pluginList_module,Plugin List module":[],
                        "frontEnd_objects_module,Objects module":[],
                        "frontEnd_footers_template,Footers Template":[],
                        "frontEnd_headers_template,Headers Template":[]
                    }
                },
                "backEnd,(Default) Back End":
                {
                    "coreInit,System Initiation Procedure":[],
                    "install,Installation File":[],
                    "definitions,Definitions File":[]
                }
            };
            //Recreate menu
            this.recreateMenu(input);
        }
        //Else use the cached version, and check if there is an up-to-date one
        else{
            this.menu = JSON.parse(cachedMenu).menu;
            this.checkForUpdatedMenu().then(function(res){
                return res ? recreateMenu(res) : null;
            })
        }
    },
    mounted:function(){
        //TODO check if the menu is up-to-date from the API.
        //If not, delete old menu, get new input, and populate the menu.

    },
    computed:{
    },
    methods:{
        checkForUpdatedMenu: function(){
            //TODO Will be stub until there is an API
            return new Promise(function (resolve, reject){
                resolve(false);
            });
        },
        recreateMenu: function(input = {}){
            //If input is (), get new one from the API
            //else
            for(let i in input){
                this.populateMenu(input[i],i,[]);
            }
            localStorage.setItem('IOFrame_Docs_Menu',JSON.stringify(
                {
                    'menu':this.menu,
                    'timeUpdated':Math.floor(Date.now()/1000) - document.serverTimeDelta
                }
            ));
        },
        populateMenu: function(item,index,pathToNode){
            let target = this.menu;
            for(let i in pathToNode){
                target = target[pathToNode[i]];
            }
            let tempArray = index.split(",");
            target[tempArray[0]] = {'@title':tempArray[1],'@open':false,'@selected':false};
            //Recursively populate farther
            if(item!=[]){
                let pathToPush = Array.from(pathToNode);
                pathToPush.push(tempArray[0]);
                for(let j in item){
                    this.populateMenu(item[j],j,pathToPush);
                }
            }
        },
        displaySection:function(){

            //Emit event
            document.eventHub.$emit('display',{args:arguments[arguments.length-1],sender:'toc'});

            //Handle old selected button
            let oldTarget = this.menu;
            for(let i in this.menu['@selectionTree']){
                //Close all parents of old selected node
                for(let j in oldTarget){
                    if(oldTarget[j]['@open'])
                        oldTarget[j]['@open'] = false;
                }
                oldTarget = oldTarget[this.menu['@selectionTree'][i]];
            }
            //Remove previously selected
            if(oldTarget != this.menu)
                oldTarget['@selected'] = false;

            //Add selected class
            let target = this.menu;
            for(let k in arguments){
                target = target[arguments[k]];
                //Open parents of selected node, and the node itself (if it's not a leaf)
                if(target['@open'] !== undefined && Object.keys(target).length != 3)
                    target['@open'] = true;
            }
            //Select new
            if(target != this.menu)
                target['@selected'] = true;

            //Save new selection tree
            this.menu['@selectionTree'] = Array.from(arguments);
        },
        toggleMenu:function(){

            let target = this.menu;
            let siblings;

            for(let i in arguments){
                siblings = target;
                target = target[arguments[i]];
            }

            //Open specified menu
            target['@open'] = !target['@open'];

            //Close open siblings
            for(let j in siblings){
                if(siblings[j]!=target && siblings[j]['@open'] && !this.menu['@selectionTree'].includes(j))
                    siblings[j]['@open'] = false;
            }
        },
        isMeta(key){
            return key[0] == '@';
        }
    },
    watch: {
    }
});