Vue.component('group-editor', {
    name: 'GroupEditor',
    mixins: [
        eventHubManager,
        IOFrameCommons,
        objectEditor
    ],
    props: {
        mode: {
            type: String,
            default: 'create' //'create' / 'update'
        },
        item: {
            type: Object,
            default: function(){
                return {}
            }
        },
        id: {
            type: String
        },
        passedCommonText: {
            type: Object,
            default: function(){
                return {};
            }
        },
        //Editor
        identifier: {
            type: String
        },
        //Test Mode
        test: {
            type: Boolean,
            default: false
        },
        //Verbose Mode
        verbose: {
            type: Boolean,
            default: false
        }
    },
    data: function () {
        return {
            configObject: JSON.parse(JSON.stringify(document.siteConfig)),
            commonText:{
                'operation-create-identifier':'Identifier',
                'operation-delete-title':'Delete',
                'operation-confirm':'Confirm',
                'operation-cancel':'Cancel',
                'title-users':'Users',
                'operation-users-reset-selection':'Reset Selected Users',
                'operation-users-reset-added':'Reset Added Users',
                'operation-delete-users-title':'Delete Users',
                'operation-add-users-title':'Add Users',
                'title-rules':'Rules',
                'operation-rules-reset-selection':'Reset Selected Rule',
                'operation-rules-reset-added':'Reset Added Rule',
                'operation-delete-rules-title':'Delete Rule',
                'operation-add-rules-title':'Add Rule',
                'params-group-type-title':'Group Type',
                'params-group-id-title':'Group ID',
                'params-group-user-count-title':'User Count',
                'params-group-title-title':'Group Title',
                'params-group-created-title':'Created At',
                'params-group-updated-title':'Updated At',
                'params-users-id-title':'ID',
                'params-users-email-title':'Email',
                'params-users-phone-title':'Phone',
                'params-users-rank-title':'Rank',
                'params-rules-title-title':'Title',
                'params-rules-channel-title':'Channel',
                'params-rules-level-title':'Log Level',
                'params-rules-type-title':'Report Type',
                'create-missing-input-identifier':'Item must have an identifier',
                'create-invalid-input-requires-regex':'must match regex',
                'response-unknown':'Unknown response',
                'response-db-connection-error':'Server internal connection error',
                'response-update-dependency':'Dependency no longer exists',
                'response-update-does-exist':'Item already exists',
                'response-update-does-not-exist':'Item no longer exists',
                'response-update-success':'Item updated',
                'response-creation-success':'Item created',
                'info-created':'Created',
                'info-updated':'Updated'
            },
            //Main item focused on in this component
            mainItem:{
            },
            //Editable portion of the article
            paramMap: {
            },
            //Sometimes, you need to mainally recompute Vue computed properties
            recompute:{
                changed:false,
                paramMap: false
            },
            //Event Parsing Map
            eventParsingMap: {
                //Fully filled as an example
                'handleItemCreate':{
                    identifierFrom:this.identifier,
                    apiResponses:true,
                    alertErrors:true,
                    valid:{
                        type: 'number',
                        value: 0
                    },
                    errorMap:{
                        '-1':{
                            text:'Server error!',
                            warningType: 'error',
                        },
                        '1':{
                            text:'Item already exists',
                            warningType: 'error',
                        },
                    }
                },
                'handleItemUpdate':{
                    apiResponses:true,
                    alertErrors:true,
                    valid:{
                        type: 'number',
                        value: 0
                    },
                    errorMap:{
                        '-1':{
                            text:'Server error!',
                        },
                        '2':{
                            text:'Item no longer exists',
                            warningType: 'error',
                        },
                    }
                },
                'handleUsersSet':{
                    apiResponses:true,
                    alertErrors:true,
                    valid:{
                        type: 'object',
                        value: 0
                    },
                    errorMap:{
                        '-2':{
                            text:'Dependency no longer exists',
                            warningType: 'error',
                        },
                        '-1':{
                            text:'Server error!',
                        },
                        '1':{
                            text:'Item already exists',
                            warningType: 'error',
                        },
                    }
                },
                'handleUsersDelete':{
                    apiResponses:true,
                    alertErrors:true,
                    valid:{
                        type: 'number',
                        value: 0
                    },
                    errorMap:{
                        '-1':{
                            text:'Server error!',
                        },
                    }
                },
                'handleRulesSet':{
                    apiResponses:true,
                    alertErrors:true,
                    valid:{
                        type: 'object',
                        value: 0
                    },
                    errorMap:{
                        '-2':{
                            text:'Dependency no longer exists',
                            warningType: 'error',
                        },
                        '-1':{
                            text:'Server error!',
                        },
                        '1':{
                            text:'Item already exists',
                            warningType: 'error',
                        },
                    }
                },
                'handleRulesDelete':{
                    apiResponses:true,
                    alertErrors:true,
                    valid:{
                        type: 'number',
                        value: 0
                    },
                    errorMap:{
                        '-1':{
                            text:'Server error!',
                        },
                    }
                }
            },
            currentChildMode:'users',
            availableChildModes:{
                'users':'title-users',
                'rules':'title-rules'
            },
            groupUserSearchList:{
                action: 'getGroupUsers',
                //Filters to display for the search list
                filters:[],
                //Result columns to display, and how to parse them
                columns:[
                    {
                        id:'userId',
                        title:'ID'
                    },
                    {
                        id:'email',
                        title:'Email'
                    },
                    {
                        id:'phone',
                        title:'Phone',
                        parser: function (phone){
                            return phone ?? ' - ';
                        }
                    },
                    {
                        id:'created',
                        title:'Created At',
                        parser:timeStampToReadableFullDate
                    },
                    {
                        id:'updated',
                        title:'Updated At',
                        parser:timeStampToReadableFullDate
                    }
                ],
                //SearchList API (and probably the only relevant API) URL
                url: document.ioframe.rootURI+ 'api/v2/logs/reporting-groups',
                //Current page
                page:0,
                //Go to page
                pageToGoTo: 1,
                //Limit
                limit:1000000,
                //Total available results
                total: 0,
                //Main items
                items: [],
                meta:{},
                extraParams: {},
                selectedMultiple:[],
                initiated: false,
            },
            userSearchList:{
                open:false,
                newToAdd: {},
                action: 'getUsers',
                //Filters to display for the search list
                filters:[
                    {
                        type:'Group',
                        group: [
                            {
                                name:'usernameLike',
                                title:'Username',
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
                                        value:'Created',
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
                        id:'id',
                        title:'ID'
                    },
                    {
                        id:'email',
                        title:'Email'
                    },
                    {
                        id:'phone',
                        title:'Phone'
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
                        id:'created',
                        title:'Date Created',
                        parser:timeStampToReadableFullDate
                    }
                ],
                //SearchList API
                url: document.ioframe.pathToRoot+ 'api/v1/users',
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
            groupRuleSearchList:{
                action: 'getGroupRules',
                //Filters to display for the search list
                filters:[],
                //Result columns to display, and how to parse them
                columns:[
                    {
                        id:'title',
                        title:'Title',
                        custom:true,
                        parser:function (item){
                            if(document.ioframe.selectedLanguage === document.ioframe.defaultLanguage)
                                return item.title ?? item['title_'+document.ioframe.defaultLanguage] ?? ' - ';
                            else
                                return item['title_'+document.ioframe.selectedLanguage] ?? item.title ?? ' - ';
                        }
                    },
                    {
                        id:'channel',
                        title:'Channel'
                    },
                    {
                        id:'level',
                        title:'Level'
                    },
                    {
                        id:'reportType',
                        title:'Report Type'
                    },
                    {
                        id:'created',
                        title:'Created At',
                        parser:timeStampToReadableFullDate
                    },
                    {
                        id:'updated',
                        title:'Updated At',
                        parser:timeStampToReadableFullDate
                    }
                ],
                //SearchList API (and probably the only relevant API) URL
                url: document.ioframe.rootURI+ 'api/v2/logs/reporting-groups',
                //Current page
                page:0,
                //Go to page
                pageToGoTo: 1,
                //Limit
                limit:1000000,
                //Total available results
                total: 0,
                //Main items
                items: [],
                meta:{},
                extraParams: {
                    'disableExtraToGet':['Group_Type','Group_ID','Channel','Report_Type','#']
                },
                selectedMultiple:[],
                initiated: false,
            },
            ruleSearchList:{
                open:false,
                newToAdd: {},
                action: 'getRules',
                //Filters to display for the search list
                filters:[
                    {
                        type:'Group',
                        group: [
                            {
                                name:'createdAfter',
                                title:'Created After',
                                type:'Datetime',
                                parser: function(value){ return Math.round(value/1000); }
                            },
                            {
                                name:'createdBefore',
                                title:'Created Before',
                                type:'Datetime',
                                parser: function(value){ return Math.round(value/1000); }
                            }
                        ]
                    },
                    {
                        type:'Group',
                        group: [
                            {
                                name:'changedAfter',
                                title:'Changed After',
                                type:'Datetime',
                                parser: function(value){ return Math.round(value/1000); }
                            },
                            {
                                name:'changedBefore',
                                title:'Changed Before',
                                type:'Datetime',
                                parser: function(value){ return Math.round(value/1000); }
                            }
                        ]
                    },
                    {
                        type:'Group',
                        group: [
                            {
                                name:'reportTypeIs',
                                title:'Report Type',
                                type:'Select',
                                list:[
                                    {
                                        'value':'',
                                        'title':'All Types'
                                    },
                                    {
                                        'value':'email',
                                        'title':'Email'
                                    },
                                    {
                                        'value':'sms',
                                        'title':'SMS'
                                    }
                                ]
                            },
                            {
                                name:'channelIs',
                                title:'Channel',
                                type:'Select',
                                //Updated on search
                                list:[
                                    {
                                        'value':'',
                                        'title':'All Channels'
                                    }
                                ]
                            }
                        ]
                    },
                    {
                        type:'Group',
                        group: [
                            {
                                name:'levelAtLeast',
                                title:'Level At Least',
                                type:'Select',
                                list:[
                                    {'value':100},
                                    {'value':150},
                                    {'value':200},
                                    {'value':250},
                                    {'value':300},
                                    {'value':350},
                                    {'value':400},
                                    {'value':450},
                                    {'value':500},
                                    {'value':550},
                                    {'value':600},
                                    {'value':650},
                                    {'value':700}
                                ]
                            },
                            {
                                name:'levelAtMost',
                                title:'Level At Most',
                                type:'Select',
                                list:[
                                    {'value':700},
                                    {'value':650},
                                    {'value':600},
                                    {'value':550},
                                    {'value':500},
                                    {'value':450},
                                    {'value':400},
                                    {'value':350},
                                    {'value':300},
                                    {'value':250},
                                    {'value':200},
                                    {'value':150},
                                    {'value':100},
                                ]
                            },
                        ]
                    },
                ],
                //Result columns to display, and how to parse them
                columns:[
                    {
                        id:'title',
                        title:'Title',
                        custom:true,
                        parser:function (item){
                            if(document.ioframe.selectedLanguage === document.ioframe.defaultLanguage)
                                return item.title ?? item['title_'+document.ioframe.defaultLanguage] ?? ' - ';
                            else
                                return item['title_'+document.ioframe.selectedLanguage] ?? item.title ?? ' - ';
                        }
                    },
                    {
                        id:'channel',
                        title:'Channel'
                    },
                    {
                        id:'level',
                        title:'Level'
                    },
                    {
                        id:'reportType',
                        title:'Report Type'
                    },
                    {
                        id:'created',
                        title:'Created At',
                        parser:timeStampToReadableFullDate
                    },
                    {
                        id:'updated',
                        title:'Updated At',
                        parser:timeStampToReadableFullDate
                    }
                ],
                //SearchList API
                url: document.ioframe.rootURI+ 'api/v2/logs/reporting-rules',
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
            //Whether the item is up to date
            initiated: true,
            //Whether we are currently initiating the item
            initiating: false,
            //Whether we are currently updating the item
            updating: false,
        };
    },
    created() {
        this.registerHub(eventHub);
        this.registerEvent('setResponse', this.handleItemSet);
        this.registerEvent('childrenSetResponse', this.handleChildrenSet);
        this.registerEvent('childrenDeleteResponse', this.handleChildrenDelete);
        this.registerEvent('rulesSetResponse', this.handleChildrenSet);
        this.registerEvent('rulesDeleteResponse', this.handleChildrenDelete);
        this.registerEvent('searchResults', this.parseSearchResults);
        this.registerEvent('resetSearchPage',this.resetSearchPage);
        this.registerEvent('requestSelection', this.selectElement);
        this.registerEvent('goToPage', this.goToPage);
        this.updateText();
        this.updateParamMap();
        this.setMainItem(this.item);
        if(this.mode === 'edit'){
            this.updateSearchLists();
        }
    },
    mounted: function(){
    },
    computed: {
        modifiedChanged: function () {
            for(let i in this.changed)
                if(this.changed[i])
                    return true;
            return false;
        },
        currentSearchList: function (){
            return this.currentChildMode === 'users'? this.groupUserSearchList : this.groupRuleSearchList;
        },
        currentChildSearchList: function (){
            return this.currentChildMode === 'users'? this.userSearchList : this.ruleSearchList;
        },
        currentlySelectedGroup: function (){
            let res = [];
            for( let i in this.currentSearchList.selectedMultiple){
                const item = this.currentSearchList.items[this.currentSearchList.selectedMultiple[i]];
                if(item)
                    res.push(item);
            }
            return res.length? res : null;
        },
        newToAdd: function (){
            return Object.keys(this.currentChildSearchList.newToAdd).length ? this.currentChildSearchList.newToAdd : null;
        },
    },
    methods: {
        //No need for paramMap reactivity so we can set objects directly
        updateParamMap: function(){
            let titleBase = {
                title:this.commonText['params-group-title-title'],
                required: this.mode === 'create',
                //What to do on item update
                onUpdate: {
                    validate: function(item){
                        return item.length > 0 && item.length < 512;
                    },
                },
                pattern:'^.{1,512}$',
                validateFailureMessage: 'Group title must be between 1 and 512 characters long!'
            };
            this.paramMap.identifier = {
                ignore:true
            };
            this.paramMap.userCount = {
                ignore:this.mode === 'create',
                title:this.commonText['params-group-user-count-title'],
                edit: false,
                type: "number",
                onUpdate: {
                    ignore: true
                }
            };
            this.paramMap.type = {
                title:this.commonText['params-group-type-title'],
                placeholder: "group-type",
                edit: this.mode === 'create',
                required:this.mode === 'create',
                onUpdate: {
                    validate: function(item){
                        return item.match(/^[a-zA-Z0-9][\w\-\._ ]{0,63}$/);
                    },
                },
                pattern: "^[a-zA-Z0-9][\\w\\-\\._ ]{0,63}$"
            };
            this.paramMap.id = {
                title:this.commonText['params-group-id-title'],
                placeholder: "group-id",
                edit: this.mode === 'create',
                required:this.mode === 'create',
                onUpdate: {
                    validate: function(item){
                        return item.match(/^[a-zA-Z0-9][\w\-\._ ]{0,63}$/);
                    },
                },
                pattern: '^[a-zA-Z0-9][\\w\\-\\._ ]{0,63}$'
            };
            this.paramMap.title=JSON.parse(JSON.stringify(titleBase));

            if(document.ioframe.languages && document.ioframe.languages.length)
                for(let i in document.ioframe.languages){
                    let lang = document.ioframe.languages[i];
                    let newObj = JSON.parse(JSON.stringify(titleBase));
                    newObj.required = false;
                    newObj.title += ' '+lang;
                    this.paramMap['title_'+lang] = newObj;
                }

            this.paramMap.created ={
                ignore: this.mode === 'create',
                title:this.commonText['params-group-created-title'],
                edit: false,
                onUpdate: {
                    ignore: true
                },
                parseOnGet: timeStampToReadableFullDate
            };
            this.paramMap.updated ={
                ignore: this.mode === 'create',
                title:this.commonText['params-group-updated-title'],
                edit: false,
                onUpdate: {
                    ignore: true
                },
                parseOnGet: timeStampToReadableFullDate
            };
        },
        updateText: function (){
            for (let i in this.passedCommonText){
                replaceInObjectIfNewValueExists(this.commonText,i,this.passedCommonText[i]);
            }
            for (let event in this.eventParsingMap){
                for (let error in this.eventParsingMap[event].errorMap){
                    const ErrTextMap = {
                        '-2':this.commonText['response-update-dependency'],
                        '-1':this.commonText['response-db-connection-error'],
                        '1':this.commonText['response-update-does-exist'],
                        '2':this.commonText['response-update-does-not-exist'],
                    };
                    if(ErrTextMap[error]){
                        replaceInObjectIfNewValueExists(this.eventParsingMap[event].errorMap[error],'text',ErrTextMap[error]);
                    }
                }
            }
            //TODO Write a proper util function for all this
            for (let i in this.groupUserSearchList.columns){
                this.groupUserSearchList.columns[i].title = {
                    0:this.commonText['params-users-id-title'],
                    1:this.commonText['params-users-email-title'],
                    2:this.commonText['params-users-phone-title'],
                    3:this.commonText['params-group-created-title'],
                    4:this.commonText['params-group-updated-title'],
                }[i] ?? this.groupUserSearchList.columns[i].title;
            }
            //TODO Filters
            for (let i in this.userSearchList.columns){
                this.userSearchList.columns[i].title = {
                    0:this.commonText['params-users-id-title'],
                    1:this.commonText['params-users-email-title'],
                    2:this.commonText['params-users-phone-title'],
                    3:this.commonText['params-users-rank-title'],
                    4:this.commonText['params-group-created-title'],
                }[i] ?? this.userSearchList.columns[i].title;
            }
            for (let i in this.groupRuleSearchList.columns){
                this.groupRuleSearchList.columns[i].title = {
                    0:this.commonText['params-rules-title-title'],
                    1:this.commonText['params-rules-channel-title'],
                    2:this.commonText['params-rules-level-title'],
                    3:this.commonText['params-rules-type-title'],
                    4:this.commonText['params-group-created-title'],
                    5:this.commonText['params-group-updated-title'],
                }[i] ?? this.groupRuleSearchList.columns[i].title;
            }
            //TODO Filters
            for (let i in this.ruleSearchList.columns){
                this.ruleSearchList.columns[i].title = {
                    0:this.commonText['params-rules-title-title'],
                    1:this.commonText['params-rules-channel-title'],
                    2:this.commonText['params-rules-level-title'],
                    3:this.commonText['params-rules-type-title'],
                    4:this.commonText['params-group-created-title'],
                    5:this.commonText['params-group-updated-title'],
                }[i] ?? this.ruleSearchList.columns[i].title;
            }
        },
        updateSearchLists: function(){
            this.groupUserSearchList.url += '/'+this.mainItem.type+'/'+this.mainItem.id+'/users';
            this.groupRuleSearchList.url += '/'+this.mainItem.type+'/'+this.mainItem.id+'/rules';
        },
        //Parses update response
        handleItemSet: function(response){
            let creation = this.mode === 'create';

            let eventResult = this.eventResponseParser(response,creation?'handleItemCreate':'handleItemUpdate',2);

            if(!eventResult)
                return;

            this.updating = false;

            if(eventResult.valid){
                if(creation){
                    this.resetAllMainItems();
                    eventHub.$emit('searchAgain');
                }
                else{
                    alertLog(
                        creation? this.commonText['response-creation-success'] :  this.commonText['response-update-success'],
                        'success',
                        this.$el,
                        {autoDismiss:2000}
                    );
                    this.updateMainItem();
                    eventHub.$emit('searchAgain');
                }
            }
        },
        //Parses user addition response
        handleChildrenSet: function(response){

            let eventResult = this.eventResponseParser(response,this.currentChildMode === 'users' ? 'handleUsersSet' : 'handleRulesSet',2);

            if(!eventResult)
                return;

            this.updating = false;

            if(eventResult.valid.length){
                this.resetAdded();
                this.searchAgain();
                eventHub.$emit('searchAgain');
            }
        },
        handleChildrenDelete: function (response){
            let eventResult = this.eventResponseParser(response,this.currentChildMode === 'users' ? 'handleUsersDelete' : 'handleRulesDelete',2);

            if(!eventResult)
                return;

            this.updating = false;

            if(eventResult.valid){
                this.resetAdded();
                this.searchAgain();
                eventHub.$emit('searchAgain');
            }
        },
        //Resets search result page
        resetSearchPage: function (response){
            if(this.verbose)
                console.log('Received response',response);
            switch (response.from){
                case this.identifier+'-group-users-search':
                    this.groupUserSearchList.page = 0;
                    break;
                case this.identifier+'-group-rules-search':
                    this.groupRuleSearchList.page = 0;
                    break;
                case this.identifier+'-users-search':
                    this.userSearchList.page = 0;
                    break;
                case this.identifier+'-rules-search':
                    this.ruleSearchList.page = 0;
                    break;
                default:
                    return;
            }
        },
        parseSearchResults: function (response){
            if(this.verbose)
                console.log('Received response',response);

            let source = null;
            let target = null;
            switch (response.from){
                case this.identifier+'-group-users-search':
                    source = 'group-users';
                    target = this.groupUserSearchList;
                    break;
                case this.identifier+'-group-rules-search':
                    source = 'group-rules';
                    target = this.groupRuleSearchList;
                    break;
                case this.identifier+'-users-search':
                    source = 'users';
                    target = this.userSearchList;
                    break;
                case this.identifier+'-rules-search':
                    source = 'rules';
                    target = this.ruleSearchList;
                    break;
                default:
                    return;
            }

            //Either way, the items should be considered initiated
            target.items = [];
            target.initiated = true;

            if((source === 'group-users') && response.content['users'] && Object.keys(response.content['users']).length)
                for(let k in response.content['users']){
                    target.items.push(response.content['users'][k]);
                }
            else if((source === 'users') && (typeof response.content === 'object') && response.content['@']){
                target.total = response.content['@']['#'] - 0;
                delete response.content['@'];
                for(let i in response.content){
                    target.items.push(response.content[i]);
                }
            }
            else if((source === 'group-rules') && response.content['rules'] && Object.keys(response.content['rules']).length)
                for(let k in response.content['rules']){
                    target.items.push(response.content['rules'][k]);
                }
            else if((source === 'rules') && (typeof response.content['rules'] === 'object') && response.content['meta']){
                target.meta = JSON.parse(JSON.stringify(response.content['meta']));
                delete response.content['meta'];
                target.total = target.meta['#'] - 0;
                if(target.meta.channels){
                    target.extraParams.disableExtraToGet = target.extraParams.disableExtraToGet??[];
                    target.extraParams.disableExtraToGet.push('Channel');
                    for (let i in target.meta.channels){
                        const channel = target.meta.channels[i];
                        target.filters[2].group[1].list.push({title:channel,value:channel});
                    }
                }
                for(let i in response.content['rules']){
                    target.items.push(response.content['rules'][i]);
                }
            }
        },
        //Goes to relevant page
        goToPage: function(page){
            if(!this.currentChildSearchList.initiating){
                let newPage;
                page = page.content;

                if(page === 'goto')
                    page = this.currentChildSearchList.pageToGoTo-1;

                if(page < 0)
                    newPage = Math.max(this.currentChildSearchList.page - 1, 0);
                else
                    newPage = Math.min(page,Math.ceil(this.currentChildSearchList.total/this.currentChildSearchList.limit));

                if(this.currentChildSearchList.page === newPage)
                    return;

                this.currentChildSearchList.page = newPage;
                this.currentChildSearchList.selected = -1;
                this.currentChildSearchList.initiated = false;
            }
        },
        //Element selection from search list
        selectElement: function(request){

            let source = null;
            let target = null;
            switch (request.from){
                case this.identifier+'-group-users-search':
                    source = 'group-users';
                    target = this.groupUserSearchList;
                    break;
                case this.identifier+'-group-rules-search':
                    source = 'group-rules';
                    target = this.groupRuleSearchList;
                    break;
                case this.identifier+'-users-search':
                    source = 'users';
                    target = this.userSearchList;
                    break;
                case this.identifier+'-rules-search':
                    source = 'rules';
                    target = this.ruleSearchList;
                    break;
                default:
                    return;
            }

            request = request.content;

            if(this.verbose)
                console.log(source,'Selecting item ',request);

            if((source === 'group-users') || (source === 'group-rules')){
                let existingIndex = target.selectedMultiple.indexOf(request);
                if(existingIndex === -1 ){
                    if(source === 'group-users')
                        target.selectedMultiple.push(request);
                    else
                        Vue.set(target,'selectedMultiple',[request]);
                }
                else{
                    target.selectedMultiple.splice(existingIndex,1);
                }
            }
            else if(source === 'users'){
                let newUser = target.items[request];
                if(!this.newToAdd || !this.newToAdd[newUser.id]){
                    let alreadyInGroup = false;
                    for (let i in this.currentSearchList.items)
                        if(this.currentSearchList.items[i].userId === newUser.id)
                            alreadyInGroup = true;
                    if(!alreadyInGroup)
                        Vue.set(target.newToAdd,newUser.id,newUser);
                }
            }
            else if(source === 'rules'){
                let newItem = target.items[request];
                let alreadyInGroup = false;
                for (let i in this.currentSearchList.items){
                    let existingItem = this.currentSearchList.items[i];
                    if(
                        (existingItem.channel === newItem.channel) &&
                        (existingItem.level === newItem.level) &&
                        (existingItem.reportType === newItem.reportType)
                    )
                        alreadyInGroup = true;
                }
                if(!alreadyInGroup){
                    this.currentChildSearchList.open = false;
                    Vue.set(target,'newToAdd',target.items[request]);
                }
            }
        },
        searchAgain: function (){
            this.currentSearchList.items = [];
            this.currentSearchList.selectedMultiple = [];
            this.currentSearchList.initiated = false;
        },
        removeSelected: function(){

            if(this.updating){
                if(this.verbose)
                    console.log('Still updating item!');
                return;
            }

            this.updating = true;
            let targetAPI = this.currentChildMode === 'users'?
                'api/v2/logs/reporting-groups/'+this.mainItem.type+'/'+this.mainItem.id+'/users' :
                'api/v2/logs/reporting-rules/'+this.currentlySelectedGroup[0].channel+'/'+this.currentlySelectedGroup[0].level+'/'+this.currentlySelectedGroup[0].reportType+'/groups' ;

            let queryParams = {};
            let children = [];
            for (let i in this.currentlySelectedGroup)
                if(this.currentChildMode === 'users')
                    children.push(this.currentlySelectedGroup[i].userId);
                else
                    children.push({type:this.currentlySelectedGroup[i].type,id:this.currentlySelectedGroup[i].id});
            children = this.currentChildMode === 'users' ? children.join(',') : JSON.stringify(children);

            queryParams[this.currentChildMode === 'users'? 'users' : 'groups'] = children;

            if(this.test)
                queryParams.req = 'test';

            this.apiRequest(
                null,
                targetAPI,
                'childrenDeleteResponse',
                {
                    method: 'delete',
                    queryParams: queryParams,
                    identifier: this.identifier,
                    verbose: this.verbose,
                    parseJSON: true
                }
            )
        },
        addNewChildren: function(){

            if(this.updating){
                if(this.verbose)
                    console.log('Still updating item!');
                return;
            }

            this.updating = true;

            let targetAPI = this.currentChildMode === 'users'?
                'api/v2/logs/reporting-groups/'+this.mainItem.type+'/'+this.mainItem.id+'/users' :
                'api/v2/logs/reporting-rules/'+this.currentChildSearchList.newToAdd.channel+'/'+this.currentChildSearchList.newToAdd.level+'/'+this.currentChildSearchList.newToAdd.reportType+'/groups' ;


            let data = new FormData();
            let children = [];
            let queryParams = {};
            if(this.currentChildMode === 'users'){
                for (let i in this.currentChildSearchList.newToAdd)
                    children.push(i);
                children = children.join(',');
                queryParams.users = children;
            }
            else{
                children.push({type:this.mainItem.type,id:this.mainItem.id});
                data.append('groups',JSON.stringify(children));
            }

            if(this.test)
                data.append('req','test');

            this.apiRequest(
                data,
                targetAPI,
                'childrenSetResponse',
                {
                    method: 'post',
                    queryParams: queryParams,
                    identifier: this.identifier,
                    verbose: this.verbose,
                    parseJSON: true
                }
            )
        },
        resetAdded: function(){
            Vue.set(this.currentChildSearchList,'newToAdd',{});
        },
        resetSelected: function(){
            Vue.set(this.currentSearchList, 'selectedMultiple', []);
        },
        confirmOperation(){

            if(this.initiating){
                if(this.verbose)
                    console.log('Still getting item info!');
                return;
            }

            if(this.updating){
                if(this.verbose)
                    console.log('Still updating item!');
                return;
            }

            this.updating = true;

            const method = this.mode === 'create' ? 'post' : 'put';

            let toSet = this.prepareMainItemSet();

            if(!toSet.errors || Object.keys(toSet.errors).length)
                return;

            toSet = toSet.result;

            let {type:type, id:id} = toSet;
            delete toSet.type;
            delete toSet.id;

            let data = new FormData();

            for(let i in toSet){
                data.append(i,toSet[i]);
            }

            if(this.test)
                data.append('req','test');

            this.apiRequest(
                data,
                'api/v2/logs/reporting-groups/'+type+'/'+id,
                'setResponse',
                {
                    identifier: this.identifier,
                    method: method,
                    verbose: this.verbose,
                    parseJSON: true
                }
            )
        },
    },
    watch: {
    },
    template: `
    <div class="group-editor" :class="{initiating:initiating,initiated:initiated,updating:updating}">
        <div class="wrapper">
            <form class="main-items">
                <div
                    v-for="(item, key) in mainItem"
                    v-if="!paramMap[key].edit && !paramMap[key].ignore"
                    :class="['main-item','static',key.replace('.','-')]"
                    >
    
                        <span class="title" v-text="paramMap[key].title?? key"></span>
    
                        <div
                         v-if="paramMap[key].type !== 'boolean'"
                         class="item-param"
                         :type="paramMap[key].type"
                         v-text="item"
                        ></div>
    
                        <button
                        v-else-if="paramMap[key].type === 'boolean'"
                        class="item-param"
                        v-text="item?(paramMap[key].button.positive?? 'Yes'):(paramMap[key].button.negative?? 'No')"
                         ></button>
    
                </div>
    
                <div
                v-for="(item, key) in mainItem"
                v-if="paramMap[key].edit && !paramMap[key].ignore"
                :class="['main-item',{changed:(item.current !== item.original || paramMap[key].considerChanged)},key.replace('.','-'),{required:paramMap[key].required},paramMap[key].type]"
                >
    
                    <span class="title" v-text="paramMap[key].title?? key"></span>
    
                    <input
                     v-if="!paramMap[key].type || (['text','date','number','email'].indexOf(paramMap[key].type) !== -1)"
                     class="item-param"
                     :type="paramMap[key].type"
                     :min="(['date','number'].indexOf(paramMap[key].type) !== -1) && (paramMap[key].min ?? false)"
                     :max="(['date','number'].indexOf(paramMap[key].type) !== -1) && (paramMap[key].max ?? false)"
                     :pattern="(['text'].indexOf(paramMap[key].type) !== -1) && (paramMap[key].pattern ?? false)"
                     :placeholder="paramMap[key].placeholder ?? false"
                     v-model:value="item.current"
                     @change="item.current = paramMap[key].parseOnChange($event.target.value);recompute.changed = ! recompute.changed"
                    >
                    <button
                    v-else-if="paramMap[key].type === 'boolean'"
                    class="item-param"
                    v-text="item.current?(paramMap[key].button.positive ?? 'Yes'):(paramMap[key].button.negative ?? 'No')"
                     @click.prevent="item.current = paramMap[key].parseOnChange(!item.current);recompute.changed = ! recompute.changed"
                     ></button>
    
                    <textarea
                    v-else-if="paramMap[key].type === 'textArea'"
                    class="item-param"
                     :placeholder="paramMap[key].placeholder ?? false"
                     v-model:value="item.current"
                     @change="item.current = paramMap[key].parseOnChange($event.target.value);recompute.changed = ! recompute.changed"
                     ></textarea>
    
                    <select
                    v-else-if="paramMap[key].type === 'select'"
                    class="item-param"
                     v-model:value="item.current"
                     @change="item.current = paramMap[key].parseOnChange($event.target.value);recompute.changed = ! recompute.changed"
                     >
                        <option v-for="(title,value) in paramMap[key].list" :value="value" v-text="title"></option>
                     </select>
                     
                    <button
                    v-if="item.current !== item.original"
                    class="reset-changes"
                     @click.prevent="resetSingleMainItem(key)"
                     >
                     <img :src="getMediaUrl('ioframe','icons/refresh-icon.svg')">
                    </button>
                </div>
            </form>
            <div class="operations" v-if="changed">
                <button class="positive-1"
                        @click="confirmOperation"
                >
                    <div v-text="commonText['operation-confirm']"></div>
                </button>
                <button class="cancel-1"
                        @click="resetAllMainItems()"
                >
                    <div v-text="commonText['operation-cancel']"></div>
                </button>
            </div>
            
            <div class="child-modes">
                <button v-for="(text, mode) in availableChildModes"
                :class="['mode','positive-3',mode,{selected:mode===currentChildMode}]"
                @click="currentChildMode = mode"
                v-text="commonText[text]"
                ></button>
            </div>
            
            <div class="operations" :class="currentChildMode" v-if="(mode === 'edit')">
                <button class="positive-3" 
                        @click="currentChildSearchList.open = true"
                >
                    <div v-text="commonText['operation-add-'+currentChildMode+'-title']"></div>
                </button>
                <button class="positive-1" 
                        v-if="newToAdd"
                        @click="addNewChildren()"
                        v-text="commonText['operation-confirm']"
                >
                </button>
                <button class="cancel-1"
                        v-if="newToAdd"
                        @click="resetAdded()"
                        v-text="commonText['operation-'+currentChildMode+'-reset-added']"
                >
                </button>
                <button class="negative-1" 
                         v-if="currentlySelectedGroup"
                         @click="removeSelected()"
                         v-text="commonText['operation-delete-'+currentChildMode+'-title']"
                >
                </button>
                <button class="cancel-1"
                        v-if="currentlySelectedGroup"
                        @click="resetSelected()"
                        v-text="commonText['operation-'+currentChildMode+'-reset-selection']"
                >
                </button>
            </div>
            
            <div v-if="currentChildSearchList.open" class="search-list-wrapper">
                <div class="control-buttons">
                    <button @click="currentChildSearchList.open = false;">
                        <img :src="getMediaUrl('ioframe','icons/cancel-icon.svg')">
                    </button>
                </div>    

                <div is="search-list"
                     :api-url="currentChildSearchList.url"
                     :api-action="currentChildSearchList.action"
                     :extra-params="currentChildSearchList.extraParams"
                     :page="currentChildSearchList.page"
                     :limit="currentChildSearchList.limit"
                     :total="currentChildSearchList.total"
                     :items="currentChildSearchList.items"
                     :initiate="!currentChildSearchList.initiated"
                     :columns="currentChildSearchList.columns"
                     :filters="currentChildSearchList.filters"
                     :test="test"
                     :verbose="verbose"
                     :identifier="identifier+'-'+currentChildMode+'-search'"
                ></div>  
            </div>
                
            <div class="to-add users" v-if="(currentChildMode === 'users') && newToAdd">
                <span v-for="user in newToAdd"
                class="item-to-add user"
                >
                    <span class="id">
                        <span class="title" v-text="commonText['params-users-id-title']"></span>
                        <span class="value" v-text="user.id"></span>
                    </span>
                    <span class="email">
                        <span class="title" v-text="commonText['params-users-email-title']"></span>
                        <span class="value" v-text="user.email"></span>
                    </span>
                    <span class="phone">
                        <span class="title" v-text="commonText['params-users-phone-title']"></span>
                        <span class="value" v-text="user.phone ?? ' - '"></span>
                    </span>
                </span>
            </div>    
            <div class="to-add rule" v-else-if="newToAdd">
                <span class="item-to-add rule">
                    <span class="id">
                        <span class="title" v-text="commonText['params-rules-channel-title']"></span>
                        <span class="value" v-text="ruleSearchList.newToAdd.channel"></span>
                    </span>
                    <span class="email">
                        <span class="title" v-text="commonText['params-rules-level-title']"></span>
                        <span class="value" v-text="ruleSearchList.newToAdd.level"></span>
                    </span>
                    <span class="phone">
                        <span class="title" v-text="commonText['params-rules-type-title']"></span>
                        <span class="value" v-text="ruleSearchList.newToAdd.reportType"></span>
                    </span>
                </span>
            </div>

            <div is="search-list"
                 v-if="(mode === 'edit')"
                 :api-url="currentSearchList.url"
                 :api-action="currentSearchList.action"
                 :extra-params="currentSearchList.extraParams"
                 :page="currentSearchList.page"
                 :limit="currentSearchList.limit"
                 :total="currentSearchList.total"
                 :items="currentSearchList.items"
                 :initiate="!currentSearchList.initiated"
                 :columns="currentSearchList.columns"
                 :filters="currentSearchList.filters"
                 :selected="currentSearchList.selectedMultiple"
                 :test="test"
                 :verbose="verbose"
                 :identifier="identifier+'-group-'+currentChildMode+'-search'"
            ></div>
            
        </div>
    </div>
    `

});