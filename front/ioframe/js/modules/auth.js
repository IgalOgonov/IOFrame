if(eventHub === undefined)
    var eventHub = new Vue();

var auth = new Vue({
    el: '#auth',
    name: 'auth',
    mixins:[sourceURL,eventHubManager,IOFrameCommons],
    data(){
        return {
            configObject: JSON.parse(JSON.stringify(document.siteConfig)),
            //Types of stuff we operate on - actions, groups, users
            types: {
                actions:{
                    title:'Actions'
                },
                groups:{
                    title:'Groups'
                },
                users:{
                    title:'Users'
                }
            },
            //Modes, and array of available operations in each mode
            modes: {
                search:{
                    operations:{
                        'delete':{
                            title:'Delete',
                            button:'negative-1'
                        },
                        'changeDesc':{
                            title:'Change Description',
                            button:'positive-3'
                        },
                        'cancel':{
                            title:'Cancel',
                            button:'cancel-1'
                        }
                    },
                    title:'View'
                },
                edit:{
                    operations:{},
                    title:'Edit'
                },
                create:{
                    operations:{},
                    title:'Create'
                }
            },
            //Filters will be custom per search, and passed through extraParams - columns will be calculated
            filters:[
            ],
            //SearchList API URL
            url: document.rootURI+'api/v1/auth',
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
            //Current Mode of operation
            currentMode:'search',
            //Current type of items
            currentType:'actions',
            //Current operation
            currentOperation: '',
            //Current operation input
            operationInput: '',
            //Whether we are currently loading
            initiated: false,
            //Whether the titles are up to date
            updatedTitles:false,
            verbose:false,
            test:false
        }
    },
    created:function(){
        this.registerHub(eventHub);
        this.registerEvent('requestSelection', this.selectElement);
        this.registerEvent('searchResults', this.parseSearchResults);
        this.registerEvent('goToPage', this.goToPage);
        this.registerEvent('genericAPIAction', this.parseGenericResponse);

    },
    updated:function(){
        if(!this.updatedTitles){
            let suffix = '';
            switch (this.currentType){
                case 'actions':
                    suffix = 'Action';
                    break;
                case 'users':
                    suffix =  'User';
                    break;
                case 'groups':
                    suffix =  'Group';
                    break;
            }
            this.modes.search.title = 'Search '+suffix+'s';
            this.modes.edit.title = 'Edit '+suffix;
            this.modes.create.title = 'Create '+suffix+'s';
            this.updatedTitles = true;
        }
    },
    computed:{
        //Selected ID
        selectedId: function(){
            return this.selected > -1?
                this.items[this.selected].identifier : '';
        },
        //Current searchlist columns
        currentColumns: function(){
            switch (this.currentType){
                case 'actions':
                    return [
                        {
                            id:'identifier',
                            title:'Action Name'
                        },
                        {
                            id:'description',
                            title:'Description'
                        }
                    ];
                case 'groups':
                    return [
                        {
                            id:'identifier',
                            title:'Group Name'
                        },
                        {
                            id:'description',
                            title:'Description'
                        }
                    ];
                case 'users':
                    return [
                        {
                            id:'identifier',
                            title:'User ID'
                        }
                    ];
            }
        },
        //Extra search parameters
        extraParams:function(){

        },
        //Current api action
        currentAction: function(){
            switch (this.currentType){
                case 'actions':
                    return 'getActions';
                case 'users':
                    return 'getUsers';
                case 'groups':
                    return 'getGroups';
            }
        },
        //Main title TODO
        title:function(){
            switch(this.currentMode){
                case 'search':
                    switch (this.currentType){
                        case 'actions':
                            return 'Browsing Actions';
                        case 'users':
                            return 'Browsing Users';
                        case 'groups':
                            return 'Browsing Groups';
                    }
                    break;
                case 'edit':
                    switch (this.currentType){
                        case 'users':
                            return 'Editing User';
                        case 'groups':
                            return 'Editing Group';
                    }
                    break;
                case 'create':
                    switch (this.currentType){
                        case 'actions':
                            return 'Creating Actions';
                        case 'groups':
                            return 'Creating Groups';
                    }
                    break;
            }
            return '';
        },
        //Text for current operation TODO
        currentOperationText:function(){
            switch(this.currentOperation){
                case 'delete':
                    return 'Delete selected?';
                    break;
                default:
                    return '';
            }
        },
        //Text for current operation TODO
        currentOperationPlaceholder:function(){
            if(this.currentOperationHasInput){
                switch(this.currentOperation){
                    case 'changeDesc':
                        return 'New description';
                    default:
                        return '';
                }
            }
            return '';
        },
        //Whether current operation has input TODO
        currentOperationHasInput:function(){
            switch(this.currentOperation){
                case 'changeDesc':
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
        'currentType':function(newVal){
            if(this.verbose)
                console.log('Changing type to '+newVal);
            this.initiated = false;
            this.updatedTitles = false;
            this.selected = -1;
            this.page = 0;
            this.pageToGoTo = 0;
            this.items = [];
        }
    },
    methods:{
        //Parses search results returned from a search list
        parseSearchResults: function(response){
            if(this.verbose)
                console.log('Recieved response',response);

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
                this.items.push(response.content[k]);
            }
        },
        //Parses generic API response
        parseGenericResponse: function(response){
            if(this.verbose)
                console.log('Recieved GENERIC API response',response);

            if(response == '1'){
                alertLog('Operation '+this.currentOperation+' succeed!','success',this.$el);
                this.cancelOperation();
                this.selected = -1;
                this.initiated = false;
            }
            else if(response == '0'){
                alertLog('Operation '+this.currentOperation+' failed!','warning',this.$el);
                this.cancelOperation();
            }
            else{
                alertLog('Unknown response! '+response,'warning',this.$el);
            }
        },
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
        shouldDisplayMode: function(index){
            if(index==='edit' && (this.selected === -1 || this.currentType === 'actions') )
                return false;
            if(index==='create' && (this.selected !== -1 || this.currentType === 'users'))
                return false;

            return true;
        },
        selectElement: function(request){

            if(!request.from || request.from !== 'search')
                return;

            request = request.content;

            if(this.verbose)
                console.log('Selecting item ',request);

            if(this.currentMode === 'search'){
                if(this.selected === request && this.currentType !== 'actions'){
                    this.switchModeTo('edit');
                }
                else{
                    this.selected = request;
                }
            }
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
        confirmOperation: function(){
            if(this.test)
                console.log('Current Type ', this.currentType ,'Current Operation ', this.currentOperation ,'Current input ',this.operationInput);

            let data = new FormData();
            var test = this.test;
            var currentOperation = this.currentOperation;

            if(this.currentMode === 'search'){
                let identifier = this.items[this.selected].identifier;
                let obj = {};
                console.log();
                switch (currentOperation){
                    case 'delete':
                        switch(this.currentType ){
                            case 'groups':
                                data.append('action','deleteGroups');
                                data.append('params',JSON.stringify({
                                    groups:[identifier]
                                }));
                                break;
                            case 'actions':
                                data.append('action','deleteActions');
                                data.append('params',JSON.stringify({
                                    actions:[identifier]
                                }));
                                break;
                        }
                        break;
                    case 'changeDesc':
                        switch(this.currentType ){
                            case 'groups':
                                data.append('action','setGroups');
                                obj[identifier] = this.operationInput;
                                data.append('params',JSON.stringify({
                                    groups:obj
                                }));
                                break;
                            case 'actions':
                                data.append('action','setActions');
                                obj[identifier] = this.operationInput;
                                data.append('params',JSON.stringify({
                                    'actions':obj
                                }));
                                break;
                        }
                        break;
                    default:
                        break;
                };

                if(this.test)
                    data.append('req','test');

                 this.apiRequest(
                     data,
                      'api/v1/auth',
                      'genericAPIAction',
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
                //Renaming is only relevant to actions
                else if(index === 'changeDesc' && this.currentType === 'users')
                    return false;
                //Cannot delete users
                else if(index === 'delete' && this.currentType === 'users')
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