if(eventHub === undefined)
    var eventHub = new Vue();

var users = new Vue({
    el: '#users',
    name: 'users',
    mixins:[sourceURL,eventHubManager,IOFrameCommons],
    data(){
        return {
            configObject: JSON.parse(JSON.stringify(document.siteConfig)),
            //Modes, and array of available operations in each mode
            modes: {
                search:{
                    operations:{
                        'cancel':{
                            title:'Cancel',
                            button:'cancel-1'
                        }
                    },
                    title:'View users'
                },
                tokens:{
                    operations:{
                        'delete':{
                            title:'Delete',
                            button:'negative-1'
                        },
                        'changeUses':{
                            title:'Change Remaining Uses',
                            button:'positive-1'
                        },
                        'createNew':{
                            title:'Create New Invite',
                            button:'positive-2'
                        },
                        'cancel':{
                            title:'Cancel',
                            button:'cancel-1'
                        }
                    },
                    title:'View Registration Tokens'
                },
                edit:{
                    operations:{},
                    title:'Edit user'
                }
            },
            users:{
                //Filters to display for the search list
                filters:[
                    {
                        type:'Group',
                        group: [
                            {
                                name:'usernameLike',
                                title:'Username',
                                placeholder:'Text username includes',
                                type:'String',
                                min:0,
                                max: 64,
                                validator: function(value){
                                    return value.match(/^[\w\.\-\_ ]{1,64}$/) !== null;
                                }
                            },
                            {
                                name:'emailLike',
                                title:'Email',
                                placeholder:'Text email excludes',
                                type:'String',
                                min:0,
                                max: 64,
                                validator: function(value){
                                    return value.match(/^[\w\.\-\_ ]{1,64}$/) !== null;
                                }
                            }
                        ]
                    },
                    {
                        type:'Group',
                        group: [
                            {
                                name:'createdAfter',
                                title:'Created After',
                                type:'Datetime',
                                parser: function(value){ return value ? Math.round(value/1000) : null; }
                            },
                            {
                                name:'createdBefore',
                                title:'Created Before',
                                type:'Datetime',
                                parser: function(value){ return value ? Math.round(value/1000) : null; }
                            }
                        ]
                    },
                    {
                        type:'Group',
                        group: [
                            {
                                name:'orderBy',
                                title:'Order By Column',
                                type:'Select',
                                list:[
                                    {
                                        value:'ID',
                                        title:'User ID'
                                    },
                                    {
                                        value:'Created_On',
                                        title:'Creation Date'
                                    },
                                    {
                                        value:'Email',
                                        title:'Email'
                                    },
                                    {
                                        value:'Username',
                                        title:'Username'
                                    }
                                ],
                            },
                            {
                                name:'orderType',
                                title:'Order',
                                type:'Select',
                                list:[
                                    {
                                        value:'0',
                                        title:'Ascending'
                                    },
                                    {
                                        value:'1',
                                        title:'Descending'
                                    }
                                ],
                            }
                        ]
                    },
                ],
                //Result columns to display, and how to parse them
                columns:[
                    {
                        id:'identifier',
                        title:'ID'
                    },
                    {
                        id:'email',
                        title:'Email'
                    },
                    {
                        id:'username',
                        title:'Username'
                    },
                    {
                        id:'rank',
                        title:'Rank'
                    },
                    {
                        id:'active',
                        title:'Active?',
                        parser:function(active){
                            return active? 'Yes' : 'No';
                        }
                    },
                    {
                        id:'bannedUntil',
                        title:'Banned?',
                        parser:function(timestamp){
                            timestamp *= 1000;
                            return Date.now() >= timestamp? 'No' : 'Yes';
                        }
                    },
                    {
                        id:'suspiciousUntil',
                        title:'Suspicious?',
                        parser:function(timestamp){
                            timestamp *= 1000;
                            return Date.now() >= timestamp? 'No' : 'Yes';
                        }
                    },
                    {
                        id:'created',
                        title:'Date Created',
                        parser:timeStampToReadableFullDate
                    }
                ],
                //SearchList API
                url: document.pathToRoot+ 'api/v1/users',
                //Current page
                page:0,
                //Go to page
                pageToGoTo: 1,
                //Limit
                limit:50,
                //Total available results
                total: 0,
                //Main items
                items: [],
                extraParams: {},
                selected:-1,
                //Whether we are currently loading
                initiated: false,
            },
            tokens:{
                //Filters to display for the search list
                filters:[
                    {
                        type:'Group',
                        group: [
                            {
                                name:'actionLike',
                                title:'Registration Type',
                                type:'Select',
                                default:'^REGISTER_ANY$|^REGISTER_MAIL_',
                                list:[
                                    {
                                        title: 'Any Or Mail',
                                        value: '^REGISTER_ANY$|^REGISTER_MAIL_'
                                    },
                                    {
                                        title: 'Any',
                                        value: '^REGISTER_ANY$'
                                    },
                                    {
                                        title: 'Mail',
                                        value: '^REGISTER_MAIL_'
                                    }
                                ]
                            },
                            {
                                name:'ignoreExpired',
                                title:'Ignore Expired?',
                                type:'Select',
                                default:'0',
                                list:[
                                    {
                                        title: 'No',
                                        value: '0'
                                    },
                                    {
                                        title: 'Yes',
                                        value: '1'
                                    }
                                ]
                            }
                        ]
                    },
                    {
                        type:'Group',
                        group: [
                            {
                                name:'usesAtLeast',
                                title:'At Least This Many Uses Left',
                                placeholder:'Any valid number which is at least 0',
                                validator: function(value){
                                    value = value - 0;
                                    return !((value<0) || (value>9223372036854775807));
                                }
                            },
                            {
                                name:'usesAtMost',
                                title:'At Most This Many Uses Left',
                                placeholder:'Any valid number which is at least 1',
                                validator: function(value){
                                    value = value - 0;
                                    return !((value<1) || (value>9223372036854775807));
                                }
                            }
                        ]
                    }
                ],
                //Result columns to display, and how to parse them
                columns:[
                    {
                        id:'identifier',
                        title:'Token',
                        parser: function(token){
                            return token.length < 25? token : token.substring(0,10)+' ... '+token.substring(token.length - 10);
                        }
                    },
                    {
                        id:'action',
                        title:'Email',
                        parser: function(action){
                            if(action.startsWith('REGISTER_MAIL_'))
                                return action.substr(14);
                            else
                                return '-';
                        }
                    },
                    {
                        id:'uses',
                        title:'Uses Left'
                    },
                    {
                        id:'expires',
                        title:'Expires',
                        parser:function(timestamp){
                            let result = timeStampToReadableFullDate(timestamp);
                            return timestamp * 1000 < Date.now() ?'<span class="expired">'+result+'</span>' :result;
                        }
                    },
                    {
                        id:'qr',
                        custom:true,
                        title:'QR Code',
                        parser: function(item){
                            return `
                                <svg class="item.identifier">
                                    <path d="" fill="#000000" stroke-width="0"></path>
                                </svg>
                                `;
                        }
                    }
                ],
                functions:{
                    'mounted': function(){
                        //This means we re-mounted the component without searching again
                        if(!this.initiate){
                            eventHub.$emit('updateQRCodes');
                        }
                    }
                },
                //SearchList API (and probably the only relevant API) URL
                url: document.pathToRoot+ 'api/v1/tokens',
                //Current page
                page:0,
                //Go to page
                pageToGoTo: 1,
                //Limit
                limit:50,
                //Total available results
                total: 0,
                //Main items
                items: [],
                selected:-1,
                //Whether we are currently loading
                initiated: false,
            },
            //Current Mode of operation
            currentMode:'search',
            //Current operation
            currentOperation: '',
            //Current operation input
            operationInput: '',
            //Token creation input
            tokenInput:{
                token:'',
                mail:'',
                uses:1,
                ttlDays:1,
                override:false,
                sendMail:false
            },
            //Required to unload
            switching:false,
            verbose:false,
            test:false
        }
    },
    created:function(){
        this.registerHub(eventHub);
        this.registerEvent('requestSelection', this.selectElement);
        this.registerEvent('searchResults', this.parseSearchResults);
        this.registerEvent('goToPage', this.goToPage);
        this.registerEvent('updateQRCodes', this.updateQRCodes);
        this.registerEvent('delete', this.handleOperation);
        this.registerEvent('changeUses', this.handleOperation);
        this.registerEvent('createNew', this.handleOperation);
    },
    computed:{
        //Main title TODO
        title:function(){
            switch(this.currentMode){
                case 'search':
                    return 'Browsing Users';
                    break;
                case 'edit':
                    return 'Editing User';
                    break;
                default:
            }
        },
        //Text for current operation TODO
        currentOperationPlaceholder:function(){
            if(this.currentOperationHasInput){
                switch(this.currentOperation){
                    case 'changeUses':
                        return 'Remaining Uses';
                    default:
                        return '';
                }
            }
            return '';
        },
        //Text for current operation TODO
        currentOperationText:function(){
            switch(this.currentOperation){
                case 'changeUses':
                    return 'How many uses to set:';
                default:
                    return '';
            }
        },
        //Whether current operation has input TODO
        currentOperationHasInput:function(){
            switch(this.currentOperation){
                case 'changeUses':
                    return true;
                case 'createNew':
                    return true;
                default:
                    return false;
            }
        },
        //Whether the current mode has operations
        currentModeHasOperations:function(){
            return Object.keys(this.modes[this.currentMode].operations).length>0;
        },
    },
    watch:{
    },
    methods:{
        //Searches again (meant to be invoked after relevant changes) TODO Remove if no searchlist
        searchAgain: function(){
            let target = this.currentMode!=='tokens'?this.users:this.tokens;
            target.items = [];
            target.total = 0;
            target.selected = -1;
            target.initiated = false;
        },
        //Parses search results returned from a search list TODO Remove if no searchlist
        parseSearchResults: function(response){
            if(this.verbose)
                console.log('Received response',response);

            let searchType;

            switch (response.from){
                case 'search-tokens':
                    searchType = 'tokens';
                    break;
                case 'search-users':
                    searchType = 'users';
                    break;
                default:
                    return ;
            }

            let target = this[searchType];

            //Either way, the items should be considered initiated
            target.items = [];
            target.initiated = true;

            //In this case the response was an error code, or the page no longer exists
            if(response.content['@'] === undefined)
                return;

            target.total = (response.content['@']['#'] - 0) ;
            delete response.content['@'];

            for(let k in response.content){
                response.content[k].identifier = k;
                target.items.push(response.content[k]);
            }

            if(searchType === 'tokens')
                target.functions.updated = function(){
                    eventHub.$emit('updateQRCodes');
                };
        },
        //Handles tokens operation
        handleOperation: function(request){
            if(this.verbose)
                console.log('Received request ',request);

            if(!request.from || request.from !== 'users')
                return;

            if(this.test){
                alertLog(request.content);
                return;
            }

            switch (this.currentOperation){
                case 'delete':
                    switch (request.content) {
                        case -1:
                            alertLog('Server error!','error',this.$el);
                            break;
                        case 0:
                            alertLog('Token deleted!','success',this.$el);
                            this.tokens.items.splice(this.tokens.selected,1);
                            this.cancelOperation();
                            break;
                        default:
                            alertLog('Unknown response '+request.content,'error',this.$el);
                            break;
                    }
                    break;
                case 'changeUses':
                    switch (request.content) {
                        case -2:
                            alertLog('Server error - invite token locked, try again!','error',this.$el);
                            break;
                        case -1:
                        case 1:
                            alertLog('Server error!','error',this.$el);
                            break;
                        case 0:
                            alertLog('Token uses changed!','success',this.$el);
                            this.tokens.items[this.tokens.selected].uses = this.operationInput;
                            this.cancelOperation();
                            break;
                        case 2:
                        case 3:
                            alertLog('Token no longer exists!','info',this.$el);
                            this.tokens.items.splice(this.tokens.selected,1);
                            this.cancelOperation();
                            break;
                        default:
                            alertLog('Unknown response '+request.content,'error',this.$el);
                            break;
                    }
                    break;
                case 'createNew':
                    if(this.tokenInput.sendMail)
                        switch (request.content) {
                            case -3:
                                alertLog('Token could not be created!','error',this.$el);
                                break;
                            case -2:
                                alertLog('Mail failed to send!','error',this.$el);
                                break;
                            case -1:
                                alertLog('Server error!','error',this.$el);
                                break;
                            case 1:
                                alertLog('Mail template does not exist (and default setting is not set)','warning',this.$el);
                                break;
                            default:
                                if(request.content.match(/^[\w][\w ]{0,255}$/)){
                                    alertLog('Created new token!','success',this.$el);
                                    this.cancelOperation();
                                    this.searchAgain();
                                }
                                else
                                    alertLog('Unknown response '+request,'error',this.$el);
                                break;
                        }
                    else
                        switch (request.content) {
                            case -2:
                                alertLog('Server error - invite token exists and is locked, try again!','error',this.$el);
                                break;
                            case -3:
                            case -1:
                            case 2:
                            case 3:
                                alertLog('Server error!','error',this.$el);
                                break;
                            case 1:
                                alertLog('Token already exists (and override is false)!','warning',this.$el);
                                break;
                            default:
                                if(request.content.match(/^[\w][\w ]{0,255}$/)){
                                    alertLog('Created new token!','success',this.$el);
                                    this.cancelOperation();
                                    this.searchAgain();
                                }
                                else
                                    alertLog('Unknown response '+request,'error',this.$el);
                                break;
                        }
                    break;
            };

        },
        //Goes to relevant page  TODO Remove if no searchlist
        goToPage: function(page){
            if(!this.initiating){
                let searchType;
                switch (page.from){
                    case 'search-tokens':
                        searchType = 'tokens';
                        break;
                    case 'search-users':
                        searchType = 'users';
                        break;
                    default:
                        return ;
                }
                let target = this[searchType];
                let newPage;
                page = page.content;

                if(page === 'goto')
                    page = target.pageToGoTo-1;

                if(page < 0)
                    newPage = Math.max(this.page - 1, 0);
                else
                    newPage = Math.min(page,Math.ceil(target.total/target.limit));

                if(target.page === newPage)
                    return;

                target.page = newPage;

                target.initiated = false;

                target.selected = -1;
            }
        },
        //Element selection from search list  TODO Remove if no searchlist
        selectElement: function(request){


            let searchType;

            switch (request.from){
                case 'search-tokens':
                    searchType = 'tokens';
                    break;
                case 'search-users':
                    searchType = 'users';
                    break;
                default:
                    return ;
            }

            let target = this[searchType];

            request = request.content;

            if(this.verbose)
                console.log('Selecting item ',request);

            if((target.selected === request) && (this.currentMode === 'search')){
                this.switchModeTo('edit');
            }
            else{
                target.selected = request;
            }
        },
        shouldDisplayMode: function(index){
            if(index==='edit' && (this.users.selected === -1) )
                return false;

            return true;
        },
        //Switches to requested mode
        switchModeTo: function(newMode){
            if(this.currentMode === newMode)
                return;

            //Delayed switch
            if( (newMode === 'search') || (newMode === 'tokens') ){
                if(!this.switching){
                    this.switching = true;
                    setTimeout(this.switchModeTo,100,newMode);
                    return ;
                }
                else
                    this.switching = false;
            }

            if(newMode === 'edit' && this.users.selected===-1){
                alertLog('Please select an item before you view/edit it!','warning',this.$el);
                return;
            };

            if(newMode==='edit'){
                switch (this.currentMode) {
                    case 'search':
                        this.currentMode = 'edit';
                        return;
                    default:
                        return
                }
            }else {
                this.users.selected=-1;
                this.tokens.selected = -1;
            }

            this.currentMode = newMode;
            this.currentOperation = '';
        },
        //Executes the operation
        confirmOperation: function(){
            if(this.test)
                console.log('Current Operation ', this.currentOperation ,'Current input ',this.operationInput);

            let data = new FormData();
            let currentOperation = this.currentOperation;
            let operation = '';
            let api = 'api/v1/tokens';

            if(this.currentMode === 'tokens'){
                switch (currentOperation){
                    case 'delete':
                        data.append('action','deleteTokens');
                        data.append('tokens',JSON.stringify([this.tokens.items[this.tokens.selected].identifier]));
                        operation = 'delete';
                        break;
                    case 'changeUses':
                        //Validate
                        if(!this.operationInput.match(/^\d+$/) || (this.operationInput-0 > 9223372036854775807)){
                            alertLog('Incorrect number of uses! Must be a positive number.','warning',this.$el);
                            return ;
                        }
                        data.append('action','setToken');
                        data.append('token',this.tokens.items[this.tokens.selected].identifier);
                        data.append('uses',this.operationInput);
                        data.append('update','1');
                        operation = 'changeUses';
                        break;
                    case 'createNew':
                        //Validate
                        const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;

                        if(this.tokenInput.token && !this.tokenInput.token.match(/^[\w][\w ]{0,255}$/)){
                            alertLog('Please enter a valid token!','warning',this.$el);
                            return;
                        }

                        if(this.tokenInput.mail && !re.test(String(this.tokenInput.mail).toLowerCase())){
                            alertLog('Please enter a valid email!','warning',this.$el);
                            return;
                        }
                        if(!this.tokenInput.mail)
                            this.tokenInput.sendMail = false;
                        if(!this.tokenInput.token)
                            this.tokenInput.override = true;

                        api = 'api/v1/users';

                        data.append('action',this.tokenInput.sendMail?'sendInviteMail':'createUserInvite');

                        if(this.tokenInput.mail)
                            data.append('mail',this.tokenInput.mail);

                        if(this.tokenInput.token)
                            data.append('token',this.tokenInput.token);

                        data.append('tokenUses',this.tokenInput.uses+'');

                        data.append('overwrite',this.tokenInput.override?'1':'0');

                        data.append('tokenTTL',(this.tokenInput.ttlDays*3600*24)+'');

                        if(this.tokenInput.sendMail && document.selectedLanguage)
                            data.append('language',document.selectedLanguage);

                        operation = 'createNew';
                        break;
                    default:
                        break;
                };

                if(!operation){
                    if(this.verbose)
                        console.log('Returning, no operation set!');
                    return;
                }

                if(this.test)
                    data.append('req','test');

                this.apiRequest(
                    data,
                    api,
                    operation,
                    {
                        identifier:'users',
                        verbose: this.verbose,
                        parseJSON: true
                    }
                );
            }
        },
        //Initiates an operation
        operation: function(operation){

            if(this.test)
                console.log('Operation',operation);
            switch (operation){
                case 'cancel':
                    this.cancelOperation();
                    if(this.currentMode === 'search')
                        this.users.selected = -1;
                    //Tokens mode
                    else if(this.currentMode === 'tokens')
                        this.tokens.selected = -1;
                    this.currentOperation = '';
                    break;
                default:
                    this.currentOperation = operation;
            }
        },
        shouldDisplayOperation: function(index){
            //Search mode
            if(this.currentMode === 'search'){
                if(this.users.selected === -1)
                    return false;
            }
            //Tokens mode
            else if(this.currentMode === 'tokens'){
                if( (index==='createNew') )
                    return (this.tokens.selected === -1);
                else if(this.tokens.selected === -1)
                    return false;
            }
            //Edit mode
            else if(this.currentMode === 'edit'){
            }

            return true;
        },
        //Cancels the operation
        cancelOperation: function(){

            if(this.test)
                console.log('Canceling operation');
            if(this.currentMode === 'search'){
                this.users.selected = -1;
            }
            else if(this.currentMode === 'edit'){
                this.currentMode = 'search';
                this.users.selected = -1;
            }
            else if(this.currentMode === 'tokens'){
                this.currentMode = 'tokens';
                this.tokens.selected = -1;
            }
            this.operationInput= '';
            this.currentOperation = '';
            this.resetTokenInput();
        },
        resetTokenInput:function(){
            Vue.set(this,'tokenInput',{
                token:'',
                mail:'',
                uses:1,
                ttlDays:1,
                override:false,
                sendMail:true
            });
        },
        //Updates QR codes on the tokens searchlist
        updateQRCodes: function(retry = 333, tries = 3){
            if(this.verbose)
                console.log('Redrawing QR Codes!');
            const svgs = this.$el.querySelectorAll('.search-item-property.qr svg');
            if(!svgs.length){
                if(tries > 0)
                    setTimeout(this.renderQR,retry,retry,--tries);
                return;
            }
            if(window.qrcodegen === undefined)
                return ;
            const ecl = qrcodegen.QrCode.Ecc.QUARTILE;
            const minVer = 1;
            const maxVer = 40;
            const mask = -1;
            const boostECC  = true;

            for(let i = 0; i<svgs.length; i++){
                let item = this.tokens.items[i];
                if(!item)
                    continue;

                const text = location.origin+document.rootURI+'api/v1/users?action=checkInvite&token='+this.tokens.items[i].identifier;
                const segs = qrcodegen.QrSegment.makeSegments(text);
                const qr = qrcodegen.QrCode.encodeSegments(segs, ecl, minVer, maxVer, mask, boostECC);
                const code = qr.toSvgString(0);
                const viewBox = / viewBox="([^"]*)"/.exec(code)[1];
                const pathD = / d="([^"]*)"/.exec(code)[1];
                svgs[i].setAttribute("viewBox", viewBox);
                svgs[i].querySelector('svg > path').setAttribute("d", pathD);

            }
        },
    },
    template:`
    <div id="users" class="main-app">
        <div class="loading-cover" v-if="!users.initiated && currentMode==='search'">
        </div>
    
        <h1 v-if="title!==''" v-text="title"></h1>
        
    
        <div class="modes">
            <button
                v-for="(item,index) in modes"
                v-if="shouldDisplayMode(index)"
                v-text="item.title"
                @click="switchModeTo(index)"
                :class="[{selected:(currentMode===index)},(item.button? item.button : ' positive-3')]"
            >
            </button>
        </div>
    
        <div class="operations-container" v-if="currentModeHasOperations">
            <div class="operations-title" v-text="'Actions'"></div>
            <div class="operations" v-if="currentOperation===''">
                <button
                    v-if="shouldDisplayOperation(index)"
                    v-for="(item,index) in modes[currentMode].operations"
                    @click="operation(index)"
                    :class="[index,{selected:(currentOperation===index)},(item.button? item.button : 'positive-3')]"
                >
                    <div v-text="item.title"></div>
                </button>
            </div>
        </div>
    
        <div class="operations" v-if="currentModeHasOperations && currentOperation !==''">
            <label :for="currentOperation" v-text="currentOperationText" v-if="currentOperationText && currentOperation!=='createNew'"></label>
            <input
                v-if="currentOperationHasInput && currentOperation!=='createNew'"
                :name="currentOperation"
                :placeholder="currentOperationPlaceholder"
                v-model:value="operationInput"
                type="text"
            >
            <form v-if="currentOperation==='createNew'" class="token-creation">
                <div class="token">
                    <label for="token" v-text="'Token:'"></label>
                    <input
                        name="token"
                        :placeholder="'Randomly Generated if not specified'"
                        v-model:value="tokenInput.token"
                        type="text"
                        pattern="^[\\w][\\w ]{0,255}$"
                    >
                </div>
                <div class="override" v-if="tokenInput.token">
                    <label for="override" v-text="'Override token if exists?'"></label>
                    <input
                        name="override"
                        v-model:value="tokenInput.override"
                        type="checkbox"
                    >
                </div>
                <div class="mail">
                    <label for="mail" v-text="'Specific Mail?'"></label>
                    <input
                        name="mail"
                        :placeholder="'Only allow registration with this mail'"
                        v-model:value="tokenInput.mail"
                        type="email"
                    >
                </div>
                <div class="sendMail" v-if="tokenInput.mail">
                    <label for="sendMail" v-text="'Mail invite on creation?'"></label>
                    <input
                        name="sendMail"
                        v-model:value="tokenInput.sendMail"
                        type="checkbox"
                    >
                </div>
                <div class="uses" v-if="!tokenInput.mail">
                    <label for="uses" v-text="'Uses'"></label>
                    <input
                        name="uses"
                        v-model:value="tokenInput.uses"
                        type="number"
                        min="1"
                        max="9223372036854775807"
                    >
                </div>
                <div class="ttlDays">
                    <label for="ttlDays" v-text="'Days Valid For (has a system default)'"></label>
                    <input
                        name="ttlDays"
                        v-model:value="tokenInput.ttlDays"
                        type="number"
                        min="1"
                    >
                </div>
                <button class="cancel-2" @click.prevent="resetTokenInput">
                    <div v-text="'Reset Inputs'"></div>
                </button>
            </form>
            <button
                :class="modes[currentMode].operations[currentOperation].button? modes[currentMode].operations[currentOperation].button : 'positive-1'"
                @click="confirmOperation">
                <div v-text="'Confirm'"></div>
            </button>
            <button class="cancel-1" @click="cancelOperation">
                <div v-text="'Cancel'"></div>
            </button>
        </div>
    
        <div is="search-list" class="users"
             v-if="currentMode==='search' && !switching"
             :api-url="users.url"
             api-action="getUsers"
             :extra-params="users.extraParams"
             :page="users.page"
             :limit="users.limit"
             :total="users.total"
             :items="users.items"
             :initiate="!users.initiated"
             :columns="users.columns"
             :filters="users.filters"
             :selected="users.selected"
             :test="test"
             :verbose="verbose"
             identifier="search-users"
        ></div>
    
        <div is="search-list" class="tokens"
             v-else-if="currentMode==='tokens' && !switching"
             :_functions="tokens.functions"
             :api-url="tokens.url"
             api-action="getTokens"
             :extra-params="tokens.extraParams"
             :page="tokens.page"
             :limit="tokens.limit"
             :total="tokens.total"
             :items="tokens.items"
             :initiate="!tokens.initiated"
             :columns="tokens.columns"
             :filters="tokens.filters"
             :selected="tokens.selected"
             :test="test"
             :verbose="verbose"
             identifier="search-tokens"
        ></div>
    
        <div is="users-editor"
             v-else-if="currentMode==='edit'"
             identifier="editor"
             :item="users.items[users.selected]"
             :test="test"
             :verbose="verbose"
        ></div>
    </div>
    `
});