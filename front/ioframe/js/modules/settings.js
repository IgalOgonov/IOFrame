if(eventHub === undefined)
    var eventHub = new Vue();

var settings = new Vue({
    el: '#settings',
    name: '#settings',
    mixins:[sourceURL,eventHubManager],
    data(){
        return {
            configObject: JSON.parse(JSON.stringify(document.siteConfig)),
            //Modes, and array of available operations in each mode
            modes: {
                search:{
                    operations:{
                        'cancel':{
                            title:'Cancel'
                        }
                    },
                    title:'View Settings'
                },
                edit:{
                    operations:{},
                    title:'Edit Settings'
                }
            },
            //Filters to display for the search list
            filters:[
            ],
            //Result columns to display, and how to parse them
            columns:[
                {
                    id:'title',
                    title:'Name'
                },
                {
                    id:'local',
                    title:'Local Setting?',
                    parser:function(value){
                        return value? 'Yes' : 'No';
                    }
                },
                {
                    id:'db',
                    title:'Global Setting?',
                    parser:function(value){
                        return value? 'Yes' : 'No';
                    }
                }
            ],
            //SearchList API (and probably the only relevant API) URL
            url:document.rootURI+'api/v1/settings',
            //Main items
            items: [],
            selected:-1,
            //Function to parse the class of each item
            extraClasses: function(x){
                if(x.local && !x.db){
                    return 'message-warning-2';
                }
                else
                    return false;
            },
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
    },
    computed:{
        //Main title TODO
        title:function(){
            switch(this.currentMode){
                case 'search':
                    return 'Available Setting Collections';
                    break;
                case 'edit':
                    return 'Editing Settings Collection';
                    break;
                default:
            }
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
        //Selected setting identifier
        selectedId: function(){
            return (this.selected === -1 ? '' : this.items[this.selected].identifier);
        }
    },
    watch:{
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

            //A valid response in our case is only an object
            if(typeof response !== 'object')
                return;

            for(let k in response.content){
                response.content[k] = JSON.parse(response.content[k]);
                response.content[k].identifier = k;
                this.items.push(response.content[k]);
            }
        },
        shouldDisplayMode: function(index){
            if(index==='edit' && (this.selected === -1) )
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
                if(this.selected === request){
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
        //Executes the operation
        confirmOperation: function(payload){
            if(this.test)
                console.log('Current Operation ', this.currentOperation ,'Current input ',this.operationInput);

            var data = new FormData();
            var test = this.test;
            var verbose = this.verbose;
            var currentOperation = this.currentOperation;
            var thisElement = this.$el;

            if(this.currentMode === 'search'){
                switch (currentOperation){
                    default:
                        break;
                };

                if(this.test)
                    data.append('req','test');

                //TODO Add what's needed
                 this.apiRequest(
                     data,
                      '',
                      '',
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