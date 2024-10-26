if(eventHub === undefined)
    var eventHub = new Vue();

var tokens = new Vue({
    el: '#tokens',
    name: 'tokens',
    mixins:[sourceURL,eventHubManager,IOFrameCommons,searchListFilterSaver],
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
                    title:'View tokens'
                },
                edit:{
                    operations:{},
                    title:'Edit tokens'
                },
                create:{
                    operations:{},
                    title:'Create tokens'
                }
            },
            //Filters to display for the search list //TODO Expend common filters
            filters:[
                {
                    type:'Group',
                    group: [
                        {
                            name:'containsTags',
                            title:'Contains Tags',
                            type:'String',
                            placeholder:'tag1,tag2,...',
                            min:0,
                            max: 240,
                            validator: function(value){
                                value = value.split(',');
                                for(let i in value){
                                    if(!value[i].match(/^[\w][\w _\-]{0,31}$/))
                                        return false;
                                }
                                return true;
                            },
                            parser: function(value){
                                return JSON.stringify(value.split(','));
                            }
                        },
                    ]
                },
                {
                    type:'Group',
                    group: [
                        {
                            name:'tokenLike',
                            title:'Token Includes',
                            type:'String',
                            min:0,
                            max: 128,
                            validator: function(value){
                                return value.match(/^[\w\.\-\_ ]{1,128}$/) !== null;
                            }
                        },
                        {
                            name:'actionLike',
                            title:'Action Includes',
                            type:'String',
                            min:0,
                            max: 128,
                            validator: function(value){
                                return value.match(/^[\w\.\-\_ ]{1,128}$/) !== null;
                            }
                        },
                    ]
                },
                {
                    type:'Group',
                    group: [
                        {
                            name:'usesAtLeast',
                            title:'Minimum Uses Left',
                            type:'Number',
                            min:0
                        },
                        {
                            name:'usesAtMost',
                            title:'Maximum Uses Left',
                            type:'Number',
                            min:0
                        }
                    ]
                },
                {
                    type:'Group',
                    group: [
                        {
                            name:'expiresAfter',
                            title:'Expires After',
                            type:'Datetime',
                            parser: function(value){ return Math.round(value/1000); }
                        },
                        {
                            name:'expiresBefore',
                            title:'Expires Before',
                            type:'Datetime',
                            parser: function(value){ return Math.round(value/1000); }
                        }
                    ]
                },
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
                    title:'Action',
                    parser: function(action){
                        return action.length < 25? action : action.substring(0,10)+' ... '+action.substring(action.length - 10);
                    }
                },
                {
                    id:'uses',
                    title:'Uses Left'
                },
                {
                    id:'tags',
                    title:'Tags',
                    parser:function(tags){
                        let res = '';
                        for(let i in tags){
                            res += '<span class="tag '+tags[i]+'">'+tags[i]+'</span>';
                        }
                        return res;
                    }
                },
                {
                    id:'expires',
                    title:'Expires',
                    parser:function(timestamp){
                        let result = timeStampToReadableFullDate(timestamp);
                        return (timestamp * 1000) < Date.now() ?'<span class="expired">'+result+'</span>' :result;
                    }
                }
            ],
            //SearchList API (and probably the only relevant API) URL
            url: document.ioframe.pathToRoot+ 'api/v1/tokens',
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
                ignoreExpired:false
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
        this.registerEvent('deleteItem', this.handleDeleteRequest);
        this._registerSearchListFilters('search',{}, {startListening:true,registerDefaultEvents:true});
    },
    computed:{
        //Main title TODO
        title:function(){
            switch(this.currentMode){
                case 'search':
                    return 'Browsing Tokens';
                case 'edit':
                    return 'Editing Token';
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
        //Searches again (meant to be invoked after relevant changes) TODO Remove if no searchlist
        searchAgain: function(){
            if(this.verbose)
                console.log('Searching again!');
            this.items = [];
            this.total = 0;
            this.selected = -1;
            this.initiated = false;
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
        //Goes to relevant page  TODO Remove if no searchlist
        goToPage: function(page){
            if(!this.initiating && (page.from === 'search')){
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
        //Handles delete request
        handleDeleteRequest: function(response){

            if(this.verbose)
                console.log('Received handleDeleteRequest',response);

            if (response === 'AUTHENTICATION_FAILURE') {
                alertLog('Not authorized to delete item! Check to see if you are logged in.','error',this.$el);
                return;
            }

             switch (response) {
             case -1:
             alertLog('Server error!','error',this.$el);
             break;
             case 0:
             alertLog('Item deleted!','success',this.$el,{autoDismiss:2000});
                 this.items.splice(this.selected,1);
             break;
             default:
             alertLog('Unknown response '+response,'error',this.$el);
             break;
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
                        data.append('action','deleteTokens');
                        data.append('tokens',JSON.stringify([this.items[this.selected].identifier]));
                        operation = 'deleteItem';
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

                 this.apiRequest(
                     data,
                      'api/v1/tokens',
                     operation,
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
            }
            this.operationInput= '';
            this.currentOperation = '';

        }
    },
    template: `
    <div id="tokens" class="main-app">
        <div class="loading-cover" v-if="!initiated && currentMode==='search'">
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
            <label :for="currentOperation" v-text="currentOperationText" v-if="currentOperationText"></label>
            <input
                v-if="currentOperationHasInput"
                :name="currentOperation"
                :placeholder="currentOperationPlaceholder"
                v-model:value="operationInput"
                type="text"
            >
            <button
                :class="(currentOperation === 'delete' ? 'negative-1' : 'positive-1')"
                @click="confirmOperation">
                <div v-text="'Confirm'"></div>
            </button>
            <button class="cancel-1" @click="cancelOperation">
                <div v-text="'Cancel'"></div>
            </button>
        </div>
    
        <div is="search-list"
             v-if="currentMode==='search'"
             :api-url="url"
             api-action="getTokens"
             :extra-params="extraParams"
             :current-filters="searchListFilters['search']"
             :page="page"
             :limit="limit"
             :total="total"
             :items="items"
             :initiate="!initiated"
             :columns="columns"
             :filters="filters"
             :selected="selected"
             :test="test"
             :verbose="verbose"
             identifier="search"
        ></div>
    
        <div is="tokens-editor"
             v-if="currentMode==='edit'"
             mode="update"
             identifier="editor"
             
             :item="items[selected]"
             :test="test"
             :verbose="verbose"
            ></div>
    
        <div is="tokens-editor"
             v-if="currentMode==='create'"
             mode="create"
             identifier="creator"
             :test="test"
             :verbose="verbose"
            ></div>
    </div>
    `
});