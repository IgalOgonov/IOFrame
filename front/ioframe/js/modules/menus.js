if(eventHub === undefined)
    var eventHub = new Vue();

var menus = new Vue({
    el: '#menus',
    name: 'menus',
    mixins:[sourceURL,eventHubManager,IOFrameCommons],
    data(){
        return {
            configObject: JSON.parse(JSON.stringify(document.siteConfig)), 
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
                    title:'View menu'
                },
                edit:{
                    operations:{},
                    title:'Edit Menu'
                },
                create:{
                    operations:{},
                    title:'Create menu'
                }
            },
            //Filters to display for the search list
            filters:[
            ],
            //Result columns to display, and how to parse them
            columns:[
                {
                    id:'identifier',
                    title:'Identifier'
                },
                {
                    id:'title',
                    title:'Title',
                    parser:function(title){
                        return title === null? ' - ' : title;
                    }
                },
                {
                    id:'created',
                    title:'Date Created',
                    parser:timeStampToReadableFullDate
                },
                {
                    id:'updated',
                    title:'Last Changed',
                    parser:timeStampToReadableFullDate
                }
            ],
            //SearchList API (and probably the only relevant API) URL
            url: document.ioframe.pathToRoot+ 'api/v1/menu',
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
        this.registerEvent('operationRequest', this.handleOperationResponse);
        this.registerEvent('goToPage', this.goToPage);
        this.registerEvent('searchAgain', this.searchAgain);
        this.registerEvent('returnToMainApp', this.returnToMainApp);
    },
    computed:{
        //Main title TODO
        title:function(){
            switch(this.currentMode){
                case 'search':
                    return 'Browsing Menus';
                case 'edit':
                    return 'Editing Menus';
                case 'create':
                    return '';
                default:
            }
        },
        //Text for current operation TODO
        currentOperationPlaceholder:function(){
            if(this.currentOperationHasInput){
                switch(this.currentOperation){
                    case 'temp':
                        return 'temp';
                    default:
                        return '';
                }
            }
            return '';
        },
        //Text for current operation TODO
        currentOperationText:function(){
            switch(this.currentOperation){
                case 'delete':
                    return 'Delete selected?';
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
    watch:{},
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
                response.content[k].meta = response.content[k].meta ? response.content[k].meta : {};
                response.content[k].menu = response.content[k].menu ? response.content[k].menu : {};
                this.items.push(response.content[k]);
            }
        },
        //Handles operation response
        handleOperationResponse: function(request){
            if(this.verbose)
                console.log('Received response',request);

            if(!request.from)
                return;

            let content = request.content;

            //Common contents
            if(content === 'INPUT_VALIDATION_FAILURE'){
                alertLog('Operation input validation failed!','error',this.$el);
            }
            else if(content === 'AUTHENTICATION_FAILURE'){
                alertLog('Operation not authorized! Check if you are logged in.','error',this.$el);
            }
            else if(content === 'OBJECT_AUTHENTICATION_FAILURE'){
                alertLog('Not authorized to view/modify object!','error',this.$el);
            }
            else if(content === 'WRONG_CSRF_TOKEN'){
                alertLog('CSRF token wrong. Try refreshing the page if this continues.','warning',this.$el);
            }
            else if(content === 'SECURITY_FAILURE'){
                alertLog('Security related operation failure.','warning',this.$el);
            }

            switch (request.from){
                case 'delete':
                    switch (content){
                        case -1:
                            alertLog('Deletion failed due to server error!','error',this.$el);
                            break;
                        case 0:
                            alertLog('Deletion successful!','success',this.$el,{autoDismiss:2000});
                            this.items.splice(this.selected,1);
                            this.selected = -1;
                            break;
                        default:
                            alertLog('Unknown response to deletion, '+content,'error',this.$el);
                    }
                    break;
                default:
                    alertLog('Unknown operation '+this.from+' response, '+content,'error',this.$el);
            }
        },
        //Goes to relevant page
        goToPage: function(page){
            if(!this.initiating && (page.from === 'search') ){
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
            }

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
        //Executes the operation
        confirmOperation: function(payload){
            if(this.test)
                console.log('Current Operation ', this.currentOperation ,'Current input ',this.operationInput);

            let data = new FormData();
            let test = this.test;
            let verbose = this.verbose;
            let currentOperation = this.currentOperation;
            let thisElement = this.$el;
            let operation = '';

            if(this.currentMode === 'search'){
                switch (currentOperation){
                    case 'delete':
                        operation = currentOperation;
                        data.append('action','deleteMenus');
                        data.append('menus',JSON.stringify([this.items[this.selected].identifier]));
                        break;
                    default:
                        break;
                }

                if(!operation){
                    if(this.verbose)
                        console.log('Returning, no operation set!');
                    return;
                }

                if(this.test)
                    data.append('req','test');

                //TODO Add what's needed
                 this.apiRequest(
                     data,
                      'api/v1/menu',
                      'operationRequest',
                      {
                         verbose: this.verbose,
                         parseJSON: true,
                         identifier: operation
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
            }
            this.operationInput= '';
            this.currentOperation = '';

        }
    },
});