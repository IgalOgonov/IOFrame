if(eventHub === undefined)
    var eventHub = new Vue();

var securityEvents = new Vue({
    el: '#security-events',
    name: 'Security Events',
    mixins:[sourceURL,eventHubManager,IOFrameCommons],
    data(){
        return {
            configObject: JSON.parse(JSON.stringify(document.siteConfig)),
            //Modes, and array of available operations in each mode
            modes: {
                search:{
                    operations:{
                        'rename':{
                            title:'Rename'
                        },
                        'cancel':{
                            title:'Cancel',
                            button:'cancel-1'
                        }
                    },
                    title:'View Security Events'
                },
                edit:{
                    operations:{},
                    title:'Edit Security Events'
                },
                create:{
                    operations:{},
                    title:'Create Security Events'
                }
            },
            //Filters to display for the search list //TODO Expend common filters
            filters:[
            ],
            //Result columns to display, and how to parse them //TODO Expend with more
            columns:[
                {
                    id:'category',
                    title:'Event Category'
                },
                {
                    id:'type',
                    title:'Event Type'
                },
                {
                    id:'name',
                    title:'Name',
                    parser:function(name){
                        return (name !== undefined) ? name: ' - ';
                    }
                }
            ],
            //SearchList API (and probably the only relevant API) URL TODO Edit
            url: document.pathToRoot+ 'api/v1/security',
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
        this.registerEvent('goToPage', this.goToPage);
        this.registerEvent('renameEventType', this.handleRename);
        this.registerEvent('searchAgain', this.searchAgain);
        this.registerEvent('returnToMainApp', this.returnToMainApp);

    },
    computed:{
        //Returns a map of all existing types
        existingTypes: function(){
            let temp = {};
            for(let i in this.items){
                temp[this.items[i].identifier] = true;
            }
            return temp;
        },
        //Main title TODO
        title:function(){
            switch(this.currentMode){
                case 'search':
                    return 'Browsing Events Rulebook';
                    break;
                case 'edit':
                    return 'Events Rulebook';
                    break;
                case 'create':
                    return '';
                    break;
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
                case 'rename':
                    return true;
                    break;
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
        //Returns to main app
        returnToMainApp: function(){
            if(this.verbose)
                console.log('Returning to main app!');
            this.switchModeTo('search');
        },
        //Searches again (meant to be invoked after relevant changes) TODO Remove if no searchlist
        searchAgain: function(){
            if(this.verbose)
                console.log('Searching again!');
            this.items = [];
            this.total = 0;
            this.selected = -1;
            this.initiated = false;
        },
        //Handles rename response
        handleRename: function(response){
            if(typeof response === 'object')
                response = response[this.items[this.selected].identifier];
            switch(response){
                case -1:
                    alertLog('Renaming failed due to server error!','error',this.$el);
                    break;
                case 0:
                    alertLog('Event type renamed!','success',this.$el);
                    this.items[this.selected].name = this.operationInput;
                    this.cancelOperation();
                    break;
                default:
                    alertLog('Unknown error renaming -'+response,'error',this.$el);
                    break;
            }
        },
        //Parses search results returned from a search list TODO Remove if no searchlist
        parseSearchResults: function(response){
            if(this.verbose)
                console.log('Received response',response);

            if(!response.from || response.from !== 'search')
                return;

            //Either way, the items should be considered initiated
            this.items = [];
            this.initiated = true;

            this.total = 0;

            for(let k in response.content){
                let cleanItem = {};
                cleanItem.items = response.content[k];
                cleanItem.identifier = k;
                cleanItem.category = k.split('/')[0];
                cleanItem.type = k.split('/')[1];
                if(cleanItem.items['@']){
                    cleanItem.items['@'] = JSON.parse(cleanItem.items['@']);
                    cleanItem.name = cleanItem.items['@'].name;
                    delete cleanItem.items['@'];
                }
                let temp = [];
                for (let key in cleanItem.items) {
                    cleanItem.items[key].sequence = key - 0;
                    temp.push(cleanItem.items[key]);
                }
                temp.sort(function(a,b){
                    return a.sequence> b.sequence? 1 : -1;
                });
                cleanItem.items = temp;
                this.items.push(cleanItem);
            }
        },
        //Goes to relevant page  TODO Remove if no searchlist
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
        //Element selection from search list  TODO Remove if no searchlist
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
        //Executes the operation
        confirmOperation: function(payload){
            if(this.test)
                console.log('Current Operation ', this.currentOperation ,'Current input ',this.operationInput);

            let data = new FormData();
            var test = this.test;
            var verbose = this.verbose;
            var currentOperation = this.currentOperation;
            var thisElement = this.$el;
            let eventName = '';

            if(this.currentMode === 'search'){
                switch (currentOperation){
                    case 'rename':
                        data.append('action','setEventsMeta');
                        let selected = this.items[this.selected];
                        let input = {
                            'category':selected.category - 0,
                            'type':selected.type - 0,
                            'meta':JSON.stringify({'name':this.operationInput})
                        };
                        data.append('inputs',JSON.stringify([input]));
                        eventName = 'renameEventType';
                        break;
                    default:
                        break;
                };

                if(this.test)
                    data.append('req','test');

                //TODO Add what's needed
                 this.apiRequest(
                     data,
                      'api/v1/security',
                      eventName,
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
                case 'rename':
                    this.operationInput = this.items[this.selected].name;
                    this.currentOperation = 'rename';
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