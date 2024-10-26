 if(eventHub === undefined)
    var eventHub = new Vue();


var mails = new Vue({
    el: '#mails',
    name: 'Mails',
    mixins: [IOFrameCommons,eventHubManager,searchListFilterSaver],
    data: {
        configObject: JSON.parse(JSON.stringify(document.siteConfig)),
        language:document.ioframe.selectedLanguage ?? 'eng',
        //Modes, and array of available operations in each mode
        modes: {
            search:{
                operations:{
                    'create':{
                        title:'Create',
                        class:'positive-1'
                    },
                    'update':{
                        title:'Update',
                        class:'positive-1'
                    },
                    'delete':{
                        title:'Delete',
                        class:'negative-1'
                    },
                    'cancel':{
                        title:'Cancel',
                        class:'cancel-1'
                    }
                },
                title:'View MailTemplates'
            }
        },
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
                        name:'includeRegex',
                        title:'Include',
                        placeholder:'Mail Template identifier includes',
                        type:'String',
                        min:0,
                        max: 64,
                        validator: function(value){
                            return value.match(/^[\w\.\-\_ ]{1,64}$/) !== null;
                        }
                    },
                    {
                        name:'excludeRegex',
                        title:'Exclude',
                        placeholder:'Mail Template identifier excludes',
                        type:'String',
                        min:0,
                        max: 64,
                        validator: function(value){
                            return value.match(/^[\w\.\-\_ ]{1,64}$/) !== null;
                        }
                    },
                ]
            }
        ],
        searchListText:{
            search:'Search',
            pagination:{
                total:'Total Pages:',
                goTo:'Go To Page',
                go:'Go'
            }
        },
        //Items
        items : [
            /*Item form TODO ADD*/
        ],
        //Whether we are currently loading
        initiated: false,
        //Selected Item
        selected:-1,
        filtersOpen:false,
        //Selected Items (for deletion)
        selectedMultiple:[],
        columns:[
            {
                id:'ID',
                title:'ID',
                parser:function(id){
                    return id;
                }
            },
            {
                id:'Title',
                title:'Title',
                parser:function(title){
                    return title;
                }
            },
            {
                id:'Content',
                title:'Content',
                parser:function(content){
                    return new Option(content.substring(0,20)).innerHTML + (content.length > 20 ? '...' : '');
                }
            },
            {
                id:'Created',
                title:'Date Created',
                parser:timeStampToReadableFullDate
            },
            {
                id:'Last_Updated',
                title:'Last Changed',
                parser:timeStampToReadableFullDate
            },
        ],
        //Current page
        page:0,
        //page
        pageToGoTo: 1,
        //Limit
        limit:50,
        //Total available results
        total: 0,
        currentMode:'search',
        //Current operation
        currentOperation: '',
        //Current operation input
        operationInput: {
            id:null,
            title:null,
            content:null,
        },
        //All tags, if we are creating multiple
        newMailTemplates:[],
        commonText:{
            'title-update':'Update',
            'title-create':'Create',
            'title-delete':'Delete',
            'title-actions':'Actions',
            'title-filters-toggle-show':'Show Filters',
            'title-filters-toggle-hide':'Hide Filters',
            'switch-mode-no-item':'Please select an item before viewing/editing it!',
            'operation-delete':'Delete selected?',
            'operation-create-identifier':'Identifier',
            'operation-create-title':'Title',
            'operation-create-content':'Content',
            'operation-confirm':'Confirm',
            'operation-cancel':'Cancel',
            'create-missing-input-identifier':'Item must have an identifier',
            'create-missing-input-title':'Item must have a title',
            'create-missing-input-content':'Item must not be empty',
            'create-invalid-input-identifier':'Identifier can contain english characters, digits and _,-',
            'response-unknown':'Unknown response',
            'response-db-connection-error':'Server internal connection error',
            'response-create-missing':'Missing inputs for items',
            'response-create-exists':'Identifiers already exist / no longer exit for items',
            'response-create-success':'Items created',
            'response-delete-success':'Items Deleted',
            'response-delete-failure':'Items Not Deleted',
        },
        verbose:false,
        test:false
    },
    created:function(){
        this.registerHub(eventHub);
        this.registerEvent('mailAPIResponse', this.handleAPIResponse);
        this.registerEvent('searchResults',this.parseSearchResults);
        this.registerEvent('requestSelection',this.selectElement);
        this.registerEvent('cancelOperation',this.cancelOperation);
        this.registerEvent('goToPage',this.goToPage);
        this._registerSearchListFilters('search',{}, {startListening:true,registerDefaultEvents:true});
    },
    computed:{
        //Main title
        title:function(){
            return 'Mail Templates'+(this.currentOperation?(' - '+this.modes.search.operations[this.currentOperation].title):'')
        },
        selectedItem:function () {
            return (this.selected!==-1 && this.selectedMultiple.length < 2) ? this.items[this.selected]:{};
        },
    },
    watch:{
    },
    methods:{
        //Handles API response TODO
        handleAPIResponse : function(response){
            //Creation/Deletion from the search-list
            switch (this.currentOperation) {
                case 'update':
                case 'create':
                    if(this.test){
                        alertLog(response,'warning',this.$el);
                        console.log(response);
                        return;
                    }
                    //TODO Handle missing input msg, although validation is already done here
                    else if( (typeof response !== 'number') && !response.match(/^\d+$/) ){
                        alertLog(this.commonText['response-unknown'],'error',this.$el);
                        console.log(response);
                        return;
                    }
                    else{
                        switch (response-0){
                            case -3:
                            case -2:
                                alertLog( this.commonText['response-create-exists'], 'error', this.$el);
                                break;
                            case -1:
                                alertLog( this.commonText['response-db-connection-error'], 'error', this.$el);
                                break;
                            case 0:
                                alertLog( this.commonText['response-create-success'], 'success', this.$el,{autoDismiss:2000});
                                this.cancelOperation();
                                this.searchAgain(false);
                                break;
                        }
                    }

                    break;
                case 'delete':
                    if(this.test){
                        alertLog(response,'warning',this.$el);
                        console.log(response);
                    }
                    else if(typeof response !== 'object'){
                        alertLog(this.commonText['response-unknown'],'error',this.$el);
                        console.log(response);
                    }
                    else{
                        let msg = '';
                        let success = [];
                        let failures = [];
                        for(let id in response){
                            if(response[id] === 0)
                                success.push(id);
                            else
                                failures.push(id)
                        }
                        if(success.length)
                            msg += this.commonText['response-delete-success']+':'+JSON.stringify(success);
                        if(success.failures)
                            msg += this.commonText['response-delete-failure']+':'+JSON.stringify(failures);
                        alertLog(msg,'success',this.$el,{autoDismiss:2000});
                        this.cancelOperation();
                        this.searchAgain(false);
                    }
                    break;
                default:
                    break;
            }
        },
        //Parses search results
        parseSearchResults: function(response){

            if(this.verbose)
                console.log('Received response',response);

            if(!response.from || response.from !== 'search')
                return;

            //In this case the response was an error code, or the page no longer exists
            if(response.content['@'] === undefined)
                return;

            this.total = (response.content['@']['#'] - 0) ;
            delete response.content['@'];

            this.initiated = true;
            this.items = [];

            for(let k in response.content){
                this.items.push(response.content[k]);
            }
        },
        searchAgain: function(newPage = true){
            if(this.verbose)
                console.log('Searching again!');
            this.items = [];
            this.total = 0;
            this.selected = -1;
            this.selectedMultiple = [];
            if(newPage){
                this.page = 0;
                this.pageToGoTo = 1;
            }
            this.initiated = false;
        },
        goToPage: function(page){
            if(!this.initiating && page.from === 'search'){
                let newPage;
                page = page.content;

                if(page === 'goto')
                    page = this.pageToGoTo-1;

                if(page < 0)
                    newPage = Math.max(this.page - 1, 0);
                else
                    newPage =  Math.min(page,Math.ceil(this.total/this.limit));

                if(this.page === newPage)
                    return;

                this.page = newPage;

                this.initiated = false;
            }
        },
        selectElement: function(request){
            if(this.verbose)
                console.log('Selecting item ',request);

            this.cancelOperation(false);

            if(this.selectedMultiple.length > 1 || (this.selectedMultiple.length === 1 && this.selected !== request.content)){
                let existingIndex = this.selectedMultiple.indexOf(request.content);
                if(existingIndex === -1){
                    if(this.verbose)
                        console.log('Adding '+request.content+' to selection');
                    this.selected = -1;
                    this.selectedMultiple.push(request.content);
                }
                else{
                    if(this.verbose)
                        console.log('Removing '+existingIndex+' from selection');
                    this.selectedMultiple.splice(existingIndex,1);
                    if(this.selectedMultiple.length === 1){
                        if(this.verbose)
                            console.log('Only '+this.selectedMultiple[0]+' left in selection');
                        this.selected = this.selectedMultiple[0];
                    }
                }
            }
            else if(this.selected === request.content){
                this.operation('update');
            }
            else{
                this.selected = request.content;
                this.selectedMultiple.push(request.content);
            }
        },
        //Executes the operation TODO
        confirmOperation: function(payload){
            if(this.verbose)
                console.log('Current Operation ', this.currentOperation ,'Current input ',this.operationInput);

            let data = new FormData();
            let currentOperation = this.currentOperation;
            if(this.test)
                data.append('req','test');

            switch (currentOperation){
                case 'delete':
                    let identifiers = [];
                    for(let i in this.selectedMultiple)
                        identifiers.push(this.items[this.selectedMultiple[i]].ID);
                    data.append('action','deleteTemplates');
                    data.append('ids',JSON.stringify(identifiers));
                    break;
                case 'update':
                case 'create':
                    this.checkInputs();
                    data.append('id',this.operationInput.id);
                    data.append('title',this.operationInput.title);
                    data.append('content',this.operationInput.content);
                    data.append('action',this.currentOperation+'Template');
                    break;
                default:
                    break;
            }

            this.apiRequest(data, 'api/mail', 'mailAPIResponse', {
                verbose: this.verbose,
                parseJSON: true
            })
        },
        //Initiates an operation
        operation: function(operation){

            if(this.verbose)
                console.log('Operation',operation);
            switch (operation){
                case 'create':
                case 'update':
                case 'delete':
                    this.currentOperation = operation;
                    if(operation === 'update'){
                        this.operationInput.id = this.items[this.selected].ID;
                        this.operationInput.title = this.items[this.selected].Title;
                        this.operationInput.content = this.items[this.selected].Content;
                    }
                    break;
                case 'cancel':
                    this.cancelOperation();
                    this.selected = -1;
                    this.selectedMultiple = [];
                    this.currentOperation = '';
                    Vue.set(this,'operationInput',{
                        id:null,
                        title:null,
                        content:null,
                    });
                    break;
                default:
                    this.currentOperation = operation;
            }
        },
        shouldDisplayOperation: function(index){
            if( (this.selectedMultiple.length === 0) && (index !== 'create'))
                return false;
            else if( (this.selectedMultiple.length || (this.selected !== -1)) && (index === 'create'))
                return false;
            else if( (this.selectedMultiple.length >1) && (['create','update'].indexOf(index) !== -1))
                return false;

            return true;
        },
        //Cancels the operation
        cancelOperation: function(resetSelection = true){

            if(this.test)
                console.log('Canceling operation');
            this.currentOperation = '';
            Vue.set(this,'operationInput',{
                id:null,
                title:null,
                content:null,
            });
            if(resetSelection){
                this.selected = -1;
                this.selectedMultiple = [];
            }

        },
        //Checks inputs for validity
        checkInputs: function(){
            if(!this.operationInput.id){
                alertLog(this.commonText['create-missing-input-identifier'],'warning',this.$el);
                return false;
            }
            if(!this.operationInput.title){
                alertLog(this.commonText['create-missing-input-title'],'warning',this.$el);
                return false;
            }
            if(!this.operationInput.content){
                alertLog(this.commonText['create-missing-input-content'],'warning',this.$el);
                return false;
            }
            if(this.operationInput.id.match(/^[a-zA-Z\_][\w\.\-\_]{0,127}$/)  === null){
                alertLog(this.commonText['create-invalid-input-identifier'],'warning',this.$el);
                return false;
            }
            return true;
        },
        //Prepends pathToRoot to url
        prependPathToRoot: function(path){
            return document.ioframe.pathToRoot + 'api/' + path;
        }
    },
    template: `
        <div id="mails" class="main-app">
        
            <h1 v-if="title!==''" v-text="title"></h1>
        
            <div class="operations-container" v-if="(currentOperation==='')">
                <div class="operations-title" v-text="commonText['title-actions']"></div>
                <div class="operations">
                    <button
                        v-if="shouldDisplayOperation(index)"
                        v-for="(item,index) in modes[currentMode].operations"
                        @click="operation(index)"
                        :class="[index,item.class??false,{selected:(currentOperation===index)}]"
                    >
                        <div v-text="item.title"></div>
                    </button>
                </div>
            </div>
        
            <div class="operations" v-if="currentOperation !==''">
                <div class="input-container" v-if="['create','update'].indexOf(currentOperation) !== -1">
                    <label
                    :for="'template-id'"
                     v-text="commonText['operation-create-identifier']"></label>
                     <input name="template-id" v-model="operationInput.id" :disabled="currentOperation==='update'">
                </div>
                <div class="input-container" v-if="['create','update'].indexOf(currentOperation) !== -1">
                    <label
                    :for="'template-title'"
                     v-text="commonText['operation-create-title']"></label>
                     <input name="template-title" v-model="operationInput.title">
                </div>
                <div class="input-container" v-if="['create','update'].indexOf(currentOperation) !== -1">
                    <label
                    :for="'template-content'"
                     v-text="commonText['operation-create-content']"></label>
                     <textarea name="template-content" v-model="operationInput.content"></textarea>
                </div>
                <button
                    :class="{'negative-1':(currentOperation === 'delete'), 'positive-1':['create','update'].indexOf(currentOperation) !== -1}"
                    @click="confirmOperation">
                    <div v-text="commonText['operation-confirm']"></div>
                </button>
                <button class="cancel-1" @click="cancelOperation">
                    <div v-text="commonText['operation-cancel']"></div>
                </button>
            </div>
        
            <button v-if="(currentMode==='search')" class="filter-toggle" :class="(filtersOpen?'negative-1':'positive-1')"
             v-text="commonText['title-filters-toggle-'+(filtersOpen?'hide':'show')]" @click="filtersOpen = !filtersOpen">
                </button>
            <div is="search-list"
                 v-if="(currentMode==='search')"
                 :class="{'no-filters':!filtersOpen}"
                 :api-action="'getTemplates'"
                 :api-url="prependPathToRoot('mail')"
                 :current-filters="searchListFilters['search']"
                 :page="page"
                 :limit="limit"
                 :total="total"
                 :items="items"
                 :initiate="!initiated"
                 :columns="columns"
                 :filters="filters"
                 :selected="selectedMultiple.length > 1 ? selectedMultiple : selected"
                 :text="searchListText"
                 :test="test"
                 :verbose="verbose"
                 identifier="search"
            ></div>
        </div>
    `,
});