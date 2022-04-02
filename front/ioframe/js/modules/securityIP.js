if(eventHub === undefined)
    var eventHub = new Vue();

var securityIP = new Vue({
    el: '#security-ip',
    name: 'Security IP',
    mixins:[sourceURL,eventHubManager,IOFrameCommons],
    data(){
        return {
            configObject: JSON.parse(JSON.stringify(document.siteConfig)), 
            types: {
                IPs:{
                    title: 'Singular IPs'
                },
                IPRanges:{
                    title: 'IP Ranges'
                },
            },
            currentType: '',
            //Modes, and array of available operations in each mode
            modes: {
                search:{
                    operations:{
                        'delete':{
                            title:'Delete',
                            button:'negative-1'
                        },
                        'cancel':{
                            title:'Cancel',
                            button:'cancel-1'
                        }
                    },
                    title:'View Security IP'
                },
                edit:{
                    operations:{}
                },
                create:{
                    operations:{}
                }
            },
            //Filters to display for the search list
            filters:[
            ],
            //Result columns to display, and how to parse them
            columns:[
            ],
            //SearchList API (and probably the only relevant API) URL
            url: document.pathToRoot+ 'api/v1/security',
            //SearchList API (and probably the only relevant API) URL
            apiAction: 'getIPs',
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
            extraParams: {
            },
            selected:-1,
            //Current Mode of operation
            currentMode:'search',
            //Current operation
            currentOperation: '',
            //Current operation input
            operationInput: '',
            //Whether we are currently loading
            initiated: false,
            verbose:false,
            test:false
        }
    },
    created:function(){
        this.registerHub(eventHub);
        this.registerEvent('requestSelection', this.selectElement);
        this.registerEvent('searchResults', this.parseSearchResults);
        this.registerEvent('goToPage', this.goToPage);
        this.registerEvent('searchAgain', this.searchAgain);
        this.registerEvent('returnToMainApp', this.returnToMainApp);
        this.registerEvent('deleteItems', this.handleDeleteItemsResponse);
        this.currentType = 'IPs';
    },
    computed:{
        //Main title TODO
        title:function(){
            switch(this.currentMode){
                case 'search':
                    return this.currentType === 'IPRanges'? 'Browsing IP Ranges' : 'Browsing IPs';
                default:
            }
        },
        //Text for current operation TODO
        currentOperationPlaceholder:function(){
            if(this.currentOperationHasInput){
                switch(this.currentOperation){
                    default:
                        return '';
                }
            }
            return '';
        },
        //Text for current operation TODO
        currentOperationText:function(){
            switch(this.currentOperation){
                default:
                    return '';
            }
        },
        //Whether current operation has input TODO
        currentOperationHasInput:function(){
            switch(this.currentOperation){
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
        'currentType':function(newVal){
            //TODO fix annoying bug on switch that makes the searchlist take the filters from the last type.
            if(this.verbose)
                console.log('Changing type to '+newVal);
            if(newVal === 'IPRanges'){
                this.apiAction = 'getIPRanges';
                this.modes.search.title = 'Browse IP Ranges';
                this.modes.edit.title = 'Edit IP Range';
                this.modes.create.title = 'Create IP Range';

                this.columns = [
                    {
                        id:'identifier',
                        title:'Range',
                        parser:function(range){
                            range = range.split('/');
                            return range[0]+': '+range[1]+' - '+range[2];
                        }
                    },
                    {
                        id:'type',
                        title:'Type',
                        parser: function(type){
                            return type? 'Whitelisted' : 'Blacklisted';
                        }
                    },
                    {
                        id:'expires',
                        title:'Expires at',
                        parser:timeStampToReadableFullDate
                    }
                ];
                this.filters = [
                    {
                        type:'Group',
                        group: [
                            {
                                name:'type',
                                title:'Type',
                                type:'Select',
                                list:[
                                    {
                                        title:'Either',
                                        value:null
                                    },
                                    {
                                        title:'Blacklisted',
                                        value:false
                                    },
                                    {
                                        title:'Whitelisted',
                                        value:true
                                    }
                                ],
                                default: null
                            },
                            {
                                name:'ignoreExpired',
                                title:'Ignore Expired?',
                                type:'Select',
                                list:[
                                    {
                                        title:'Yes',
                                        value:true
                                    },
                                    {
                                        title:'No',
                                        value:false
                                    },
                                ],
                                default: false
                            }
                        ]
                    }
                ];

            }
            else{
                this.apiAction = 'getIPs';
                this.modes.search.title = 'Browse IPs';
                this.modes.edit.title = 'Edit IP';
                this.modes.create.title = 'Create IP';

                this.columns = [
                    {
                        id:'identifier',
                        title:'Security IP'
                    },
                    {
                        id:'type',
                        title:'Type',
                        parser: function(type){
                            return type? 'Whitelisted' : 'Blacklisted';
                        }
                    },
                    {
                        id:'reliable',
                        title:'Reliable?',
                        parser: function(reliable){
                            return reliable? 'Yes' : 'No';
                        }
                    },
                    {
                        id:'expires',
                        title:'Expires at',
                        parser:function(timestamp)timeStampToReadableFullDate
                    }
                ];
                this.filters = [
                    {
                        type:'Group',
                        group: [
                            {
                                name:'type',
                                title:'Type',
                                type:'Select',
                                list:[
                                    {
                                        title:'Either',
                                        value:null
                                    },
                                    {
                                        title:'Blacklisted',
                                        value:false
                                    },
                                    {
                                        title:'Whitelisted',
                                        value:true
                                    }
                                ],
                                default: null
                            },
                            {
                                name:'ignoreExpired',
                                title:'Ignore Expired?',
                                type:'Select',
                                list:[
                                    {
                                        title:'Yes',
                                        value:true
                                    },
                                    {
                                        title:'No',
                                        value:false
                                    }
                                ],
                                default: false
                            },
                            {
                                name:'reliable',
                                title:'Reliable IPs?',
                                type:'Select',
                                list:[
                                    {
                                        title:'Either',
                                        value:null
                                    },
                                    {
                                        title:'Yes',
                                        value:true
                                    },
                                    {
                                        title:'No',
                                        value:false
                                    }
                                ],
                                default: null
                            }
                        ]
                    }
                ];

            }

            this.selected = -1;
            this.page = 0;
            this.pageToGoTo = 0;
            this.items = [];
            this.initiated = false;
        }
    },
    methods:{
        //Returns to main app
        returnToMainApp: function(){
            if(this.verbose)
                console.log('Returning to main app!');
            this.switchModeTo('search');
        },
        //Searches again (meant to be invoked after relevant changes)
        searchAgain: function(){
            if(this.verbose)
                console.log('Searching again!');
            this.items = [];
            this.total = 0;
            this.selected = -1;
            this.initiated = false;
        },
        //Parses search results returned from a search list
        parseSearchResults: function(response){
            if(this.verbose)
                console.log('Received response',response);

            if(!response.from || response.from !== 'search')
                return;

            //Either way, the items should be considered initiated
            this.items = [];
            this.initiated = true;

            //In this case the response was an error code, or the page no longer exists
            if(response.content['@'] === undefined)
                return;

            this.total = (response.content['@']['#'] - 0) ;
            delete response.content['@'];

            for(let k in response.content){
                response.content[k].identifier = k;
                let arr = ['type'];
                if(this.currentType === 'IPs')
                    arr.push('reliable');
                for(let i in arr){
                    response.content[k][arr[i]] -=0;
                    response.content[k][arr[i]] = response.content[k][arr[i]]? true : false;
                }
                if(this.currentType === 'IPRanges'){
                    response.content[k].newFrom = '';
                    response.content[k].newTo = '';
                }
                this.items.push(response.content[k]);
            }
            this.cancelOperation();
        },
        //Goes to relevant page
        goToPage: function(page){
            if(!this.initiating && page.from == 'search'){
                let newPage;
                page = page.content;

                if(page === 'goto')
                    page = this.pageToGoTo-1;

                if(page < 0)
                    newPage = Math.max(this.page - 1, 0);
                else
                    newPage = Math.min(page,Math.ceil(this.total/this.limit));

                if(this.page === newPage)
                    return;

                this.page = newPage;

                this.initiated = false;

                this.selected = -1;
            }
        },
        handleDeleteItemsResponse: function(response){

            if (response === 'AUTHENTICATION_FAILURE') {
                alertLog('Not authorized to delete IP! Check to see if you are logged in.','error',this.$el);
                return;
            }

            switch (response) {
                case 0:
                    alertLog((this.currentType === 'IPRanges'? 'IP Range': 'IP')+' was not deleted - server error, or no longer exists!','error',this.$el);
                    break;
                case 1:
                    alertLog((this.currentType === 'IPRanges'? 'IP Range': 'IP')+' was deleted!','success',this.$el);
                    this.searchAgain();
                    break;
                default:
                    alertLog('Unknown response '+response,'error',this.$el);
            }

        },
        //Element selection from search list
        selectElement: function(request){

            if(!request.from || request.from !== 'search')
                return;

            request = request.content;

            if(this.verbose)
                console.log('Selecting item ',request);

            if(this.currentMode === 'search'){
                if(this.selected === request){
                    this.switchModeTo('edit');
                }
                else{
                    this.selected = request;
                }
            }
        },
        shouldDisplayMode: function(index){
            if(index==='edit' && (this.selected === -1) )
                return false;
            if(index==='create' && (this.selected !== -1))
                return false;

            return true;
        },
        //Switches to requested mode
        switchModeTo: function(newMode){
            if(this.currentMode === newMode)
                return;
            if(newMode === 'edit' && this.selected===-1){
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
                this.selected=-1;
            }
            this.currentMode = newMode;
            this.currentOperation = '';
        },
        //Switches to requested type
        switchTypeTo: function(newType){
            if(this.currentType === newType)
                return;
            this.switchModeTo('search');
            this.currentType = newType;
        },
        //Executes the operation
        confirmOperation: function(payload){
            if(this.test)
                console.log('Current Operation ', this.currentOperation ,'Current input ',this.operationInput);

            let data = new FormData();
            var test = this.test;
            var verbose = this.verbose;
            var currentOperation = this.currentOperation;
            var thisElement = this.$el;

            if(this.currentMode === 'search'){
                switch (currentOperation){
                    case 'delete':
                        if(this.currentType === 'IPRanges'){
                            data.append('action','deleteIPRange');
                            data.append('prefix',this.items[this.selected].prefix);
                            data.append('from',this.items[this.selected].from);
                            data.append('to',this.items[this.selected].to);
                        }
                        else{
                            data.append('action','deleteIP');
                            data.append('ip',this.items[this.selected].IP);
                        }
                        break;
                    default:
                        break;
                };

                if(this.test)
                    data.append('req','test');

                 this.apiRequest(
                     data,
                      'api/v1/security',
                      'deleteItems',
                      {
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
                case 'delete':
                    this.currentOperation = 'delete';
                    break;
                case 'cancel':
                    this.cancelOperation();
                    this.selected = -1;
                    this.currentOperation = '';
                    break;
                default:
                    this.currentOperation = operation;
            }
        },
        shouldDisplayOperation: function(index){
            //Search mode
            if(this.currentMode === 'search'){
                if(this.selected === -1 && index !== 'create')
                    return false;
                else if(this.selected !== -1 && index === 'create')
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
                this.selected = -1;
            }
            else if(this.currentMode === 'edit'){
                this.currentMode = 'search';
                this.selected = -1;
            };
            this.operationInput= '';
            this.currentOperation = '';

        }
    },
});