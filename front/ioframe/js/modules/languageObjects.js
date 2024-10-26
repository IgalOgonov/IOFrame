if(eventHub === undefined)
    var eventHub = new Vue();

//***************************
//******LanguageObjects APP*******
//***************************//

var languageObjects = new Vue({
    el: '#language-objects',
    name: 'LanguageObjects',
    mixins:[IOFrameCommons,eventHubManager,multipleLanguages,searchListFilterSaver],
    data(){
        return {
            configObject: JSON.parse(JSON.stringify(document.siteConfig)),
            //Modes, and array of available operations in each mode
            modes: {
                search:{
                    operations:{
                        'create':{
                            title:'Create New Language Object(s)',
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
                    title:'View Language Objects'
                },
                edit:{
                    operations:{},
                    title:'Edit Language Object'
                },
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
                            placeholder:'Language Object identifier includes',
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
                            placeholder:'Language Object identifier excludes',
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
            columns: [
                {
                    id:'identifier',
                    title:'ID',
                    parser:function(id){
                        return id;
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
            searchListText:{
                search:'Search',
                pagination:{
                    total:'Total Pages:',
                    goTo:'Go To Page',
                    go:'Go'
                }
            },
            //Items
            objects : [
                /*Item form TODO ADD*/
            ],
            //Whether we are currently loading
            initiated: false,
            //Selected Item
            selected:-1,
            filtersOpen:false,
            //Selected Items (for deletion)
            selectedMultiple:[],
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
                identifier: '',
                object:'',
                overwrite:0
            },
            //All language objects, if we are creating multiple
            newLanguageObjects:[],
            text:{
                'title-edit':'Edit',
                'title-create':'Create',
                'title-actions':'Actions',
                'title-filters-toggle-show':'Show Filters',
                'title-filters-toggle-hide':'Hide Filters',
                'switch-mode-no-item':'Please select an item before viewing/editing it!',
                'operation-delete':'Delete selected?',
                'operation-create-identifier':'Identifier',
                'operation-create-object':'Object',
                'operation-create-overwrite':'Overwrite Existing?',
                'operation-add-to-list':'Add',
                'operation-remove-from-list':'Remove',
                'operation-confirm':'Confirm',
                'operation-cancel':'Cancel',
                'create-missing-input-identifier':'Item must have an identifier',
                'create-missing-input-object':'Item must have an object',
                'create-invalid-input-identifier':'Identifier can contain english characters, digits and _,-',
                'create-invalid-input-object':'Object must be a valid json',
                'create-missing-input-required':'Item must have attribute',
                'create-invalid-input':'Invalid extra parameter',
                'create-duplicate-identifiers':'Multiple items to be created cannot have similar identifiers',
                'response-unknown':'Unknown response',
                'response-db-connection-error':'Server internal connection error',
                'response-create-missing':'Missing inputs for items',
                'response-create-exists':'Identifiers already exist for items',
                'response-create-success':'Items created',
                'response-delete-success':'Items Deleted',
            },
            verbose:false,
            test:false
        }
    },
    created:function(){
        this.registerHub(eventHub);
        this.registerEvent('languageObjectsAPIResponse', this.handleAPIResponse);
        this.registerEvent('searchResults',this.parseSearchResults);
        this.registerEvent('requestSelection',this.selectElement);
        this.registerEvent('cancelOperation',this.cancelOperation);
        this.registerEvent('goToPage',this.goToPage);
        this.registerEvent('searchAgain', this.searchAgain);
        this.configObject.languageObjects = this.configObject.languageObjects ?? {};
        this.setLanguageTitles();
        this._registerSearchListFilters('search',{}, {startListening:true,registerDefaultEvents:true});
    },
    computed:{
        //Main title
        title:function(){
            switch(this.currentMode){
                case 'search':
                    return false;
                case 'edit':
                    return this.text['title-edit'];
                case 'create':
                    return this.text['title-create'];
            }
        },
        //Whether the current mode has operations
        currentModeHasOperations:function(){
            return Object.keys(this.modes[this.currentMode].operations).length>0;
        },
        selectedItem:function () {
            return (this.selected!==-1 && this.selectedMultiple.length < 2) ? this.objects[this.selected]:{};
        },
        //Common text for the editor
        editorText: function(){
            let text = JSON.parse(JSON.stringify(this.text));
            text['response-update-does-not-exist'] = text['response-update-does-not-exist']?? 'Language Object no longer exists';
            text['response-update-success'] = text['response-update-success']?? 'Language Object updated';
            text['info-created'] = text['info-created']?? 'Created';
            text['info-updated'] = text['info-updated']?? 'Updated';
            return text;
        }
    },
    watch:{
        'currentLanguage':function (newVal, oldVal){
            if(newVal !== oldVal)
                this.setLanguageTitles();
        }
    },
    methods:{
        //Sets relevant titles depending on language
        setLanguageTitles: function(){
            if(!this.currentLanguage || !(this.configObject.languageObjects.text??false))
                return;

            //Common Text
            if(this.configObject.languageObjects.text)
                Vue.set(this,'text',mergeDeep(this.text,this.configObject.languageObjects.text));
            //Modes
            if(this.text.modes)
                Vue.set(this,'modes',mergeDeep(this.modes,this.text.modes));

            //Filters
            if(this.configObject.languageObjects.text.filters)
                Vue.set(this,'filters',[
                    {
                        type:'Group',
                        group: [
                            {
                                name:'createdAfter',
                                title:this.text.filters.createdAfter ?? 'Created After',
                                type:'Datetime',
                                parser: function(value){ return Math.round(value/1000); }
                            },
                            {
                                name:'createdBefore',
                                title:this.text.filters.createdBefore ?? 'Created Before',
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
                                title:this.text.filters.changedAfter ?? 'Changed After',
                                type:'Datetime',
                                parser: function(value){ return Math.round(value/1000); }
                            },
                            {
                                name:'changedBefore',
                                title:this.text.filters.changedBefore ?? 'Changed Before',
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
                                title:this.text.filters.includeRegex.title ?? 'Includes',
                                placeholder:this.text.filters.includeRegex.placeholder ?? 'Language Object identifier includes',
                                type:'String',
                                min:0,
                                max: 64,
                                validator: function(value){
                                    return value.match(/^[\w\.\-\_ ]{1,64}$/) !== null;
                                }
                            },
                            {
                                name:'excludeRegex',
                                title:this.text.filters.excludeRegex.title ?? 'Exclude',
                                placeholder:this.text.filters.excludeRegex.placeholder ?? 'Language Object identifier excludes',
                                type:'String',
                                min:0,
                                max: 64,
                                validator: function(value){
                                    return value.match(/^[\w\.\-\_ ]{1,64}$/) !== null;
                                }
                            },
                        ]
                    }
                ]);

            //Columns
            if(this.configObject.languageObjects.text.columns)
                Vue.set(this,'columns',[
                    {
                        id:'identifier',
                        title:this.text.columns.id?? 'ID',
                        parser:function(id){
                            return id;
                        }
                    },
                    {
                        id:'created',
                        title:this.text.columns.created?? 'Date Created',
                        parser:timeStampToReadableFullDate
                    },
                    {
                        id:'updated',
                        title:this.text.columns.updated?? 'Last Changed',
                        parser:timeStampToReadableFullDate
                    }
                ]);

            //Pagination
            if(this.text.searchListText)
                Vue.set(this,'searchListText',mergeDeep(this.searchListText,this.configObject.languageObjects.text.searchListText))

            //Language Object Modes languages loaded from the setting

        },
        resetOperationInput: function(){
            Vue.set(this,'operationInput',{
                identifier: '',
                object: '',
                overwrite: 0
            });
        },
        //Handles API response
        handleAPIResponse : function(response){
            //Creation/Deletion from the search-list
            switch (this.currentOperation) {
                case 'create':
                    if(this.test){
                        alertLog(response,'warning',this.$el);
                        console.log(response);
                        return;
                    }
                    else if(typeof response !== 'object'){
                        alertLog(this.text['response-unknown'],'error',this.$el);
                        console.log(response);
                        return;
                    }

                    let outcomes = {
                        '-1': {
                            identifiers:[],
                            error:true,
                            message:this.text['response-db-connection-error']
                        },
                        '0': {
                            identifiers:[],
                            error:false,
                            message:this.text['response-create-success']
                        },
                        '2': {
                            identifiers:[],
                            error:true,
                            message:this.text['response-create-exists']
                        },
                        '3': {
                            identifiers:[],
                            error:true,
                            message:this.text['response-create-missing']
                        },
                    };
                    let successes = [];
                    for(let i in this.newLanguageObjects){
                        let respIdentifier = this.newLanguageObjects[i].identifier;
                        let itemResponse = response[respIdentifier] + '';
                        if(itemResponse === '1')
                            itemResponse = '-1';
                        if(itemResponse === '0')
                            successes.push(i);
                        outcomes[itemResponse+''].identifiers.push(this.newLanguageObjects[i].identifier);
                    }
                    for(let i in outcomes){
                        if(outcomes[i].identifiers.length > 0)
                            alertLog(
                                outcomes[i].message + ': '+outcomes[i].identifiers.join(','),
                                outcomes[i].error? 'error' : 'success',
                                this.$el,
                                outcomes[i].error? {} : {autoDismiss: 2000},
                            );
                    }

                    for(let i = successes.length - 1; i>=0; i--)
                        this.newLanguageObjects.splice(successes[i],1);

                    if(this.newLanguageObjects.length === 0)
                        this.cancelOperation();

                    if(successes.length > 0)
                        this.searchAgain(false);

                    break;
                case 'delete':
                    switch (response) {
                        case -1:
                            alertLog(this.text['response-db-connection-error'],'error',this.$el);
                            break;
                        case 0:
                            alertLog(this.text['response-delete-success'],'success',this.$el,{autoDismiss:2000});
                            this.cancelOperation();
                            this.searchAgain(false);
                            break;
                        default:this.test?
                            alertLog(response,'warning',this.$el):
                            alertLog(this.text['response-unknown'],'error',this.$el);
                            console.log(response);
                            break;
                    }
                    break;
                default:
                    alertLog(this.text['response-unknown'],'error',this.$el);
                    console.log(response);
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
            this.objects = [];

            for(let k in response.content){
                response.content[k].identifier = k;
                this.objects.push(response.content[k]);
            }
        },
        searchAgain: function(newPage = true){
            if(this.verbose)
                console.log('Searching again!');
            this.objects = [];
            this.total = 0;
            this.selected = -1;
            this.selectedMultiple = [];
            if(newPage){
                this.page = 0;
                this.pageToGoTo = 1;
            }
            this.initiated = false;
        },
        //Whether the mode should be displayed
        shouldDisplayMode: function(index){
            return !( index==='edit' && (this.currentMode !== index) && (this.selected === -1) );
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

            if(this.currentMode === 'search' ){
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
                    this.switchModeTo('edit');
                }
                else{
                    this.selected = request.content;
                    this.selectedMultiple.push(request.content);
                }
            }
        },
        //Switches to requested mode
        switchModeTo: function(newMode){
            if(this.currentMode === newMode)
                return;
            if(newMode === 'edit' && this.selected===-1){
                alertLog(this.text['switch-mode-no-item'],'warning',this.$el);
                return;
            }

            if(newMode==='edit'){
                switch (this.currentMode) {
                    case 'search':
                        this.currentMode = 'edit';
                        return;
                    default:
                        return;
                }
            }else {
                this.selected=-1;
                this.selectedMultiple = [];
            }
            this.currentMode = newMode;
            this.currentOperation = '';
        },
        //Executes the operation
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
                        identifiers.push(this.objects[this.selectedMultiple[i]].identifier);
                    data.append('action','deleteLanguageObjects');
                    data.append('identifiers',JSON.stringify(identifiers));
                    break;
                case 'create':
                    if(this.newLanguageObjects.length === 0)
                        if(!this.addToNewLanguageObjects())
                            return;
                    if(this.verbose)
                        console.log('Creating language objects using:', JSON.stringify(this.newLanguageObjects),this.operationInput.overwrite.toString());
                    let objects = {};
                    for (let i in this.newLanguageObjects){
                        objects[this.newLanguageObjects[i].identifier] = JSON.parse(this.newLanguageObjects[i].object);
                    }
                    data.append('objects',JSON.stringify(objects));
                    data.append('overwrite',this.operationInput.overwrite.toString());
                    data.append('action','setLanguageObjects');
                    break;
                default:
                    break;
            }

            this.apiRequest(data, 'api/language-objects', 'languageObjectsAPIResponse', {
                verbose: this.verbose,
                parseJSON: true
            })
        },
        //Initiates an operation
        operation: function(operation){

            if(this.verbose)
                console.log('Operation',operation);
            switch (operation){
                case 'delete':
                    this.currentOperation = 'delete';
                    break;
                case 'cancel':
                    this.cancelOperation();
                    this.selected = -1;
                    this.selectedMultiple = [];
                    this.currentOperation = '';
                    break;
                default:
                    this.currentOperation = operation;
            }
        },
        shouldDisplayOperation: function(index){
            //Search mode
            if(this.currentMode === 'search'){
                if(this.selectedMultiple.length === 0 && index !== 'create')
                    return false;
                else if(this.selectedMultiple.length > 0 && (index !== 'delete' && index !== 'cancel'))
                    return false;
                else if(this.selectedMultiple.length > 0 && (index !== 'delete' && index !== 'cancel'))
                    return false;
            }
            //Edit mode
            else if(this.currentMode === 'edit'){
                return false;
            }

            return true;
        },
        //Cancels the operation
        cancelOperation: function(){

            if(this.test)
                console.log('Canceling operation');
            if(this.currentMode === 'search' ){
                this.selected = -1;
                this.selectedMultiple = [];
            }
            else if(this.currentMode === 'edit'){
                this.currentMode = 'search';
                this.selected = -1;
                this.selectedMultiple = [];
            }
            Vue.set(this,'newLanguageObjects',[]);
            this.resetOperationInput();
            this.currentOperation = '';

        },
        //Adds thumbnail to list of objects to create
        addToNewLanguageObjects: function(){
            if(!this.checkInputs())
                return false;
            let validInputs = ['identifier','object'];
            let attr = {};
            for(let i in validInputs){
                attr[validInputs[i]] = this.operationInput[validInputs[i]];
            }
            for(let i in this.newLanguageObjects)
                if(this.newLanguageObjects[i].identifier === attr.identifier){
                    alertLog(this.text['create-duplicate-identifiers'],'warning',this.$el);
                    return false;
                }
            this.newLanguageObjects.push(attr);
            return true;
        },
        //Checks inputs for validity
        checkInputs: function(){
            if(!this.operationInput.identifier){
                alertLog(this.text['create-missing-input-identifier'],'warning',this.$el);
                return false;
            }
            if(this.operationInput.identifier.match(/^\w[\w\d-_]{0,127}$/)  === null){
                alertLog(this.text['create-invalid-input-identifier'],'warning',this.$el);
                return false;
            }
            if(!this.operationInput.object){
                alertLog(this.text['create-missing-input-object'],'warning',this.$el);
                return false;
            }
            if(!IsJsonString(this.operationInput.object)){
                alertLog(this.text['create-invalid-input-object'],'warning',this.$el);
                return false;
            }
            return true;
        },
        //Prepends pathToRoot to url
        prependPathToRoot: function(path){
            return document.ioframe.pathToRoot + 'api/' + path;
        },
    },
    template: `
    <div id="language-objects" class="main-app">
    
        <h1 v-if="title" v-text="title"></h1>
    
        <div class="modes">
            <button
                v-for="(item,index) in modes"
                v-if="shouldDisplayMode(index)"
                v-text="item.title"
                @click="switchModeTo(index)"
                :class="{'positive-3':true,selected:(currentMode===index)}"
            >
            </button>
        </div>
    
        <div class="operations-container" v-if="currentModeHasOperations && (currentOperation==='')">
            <div class="operations-title" v-text="text['title-actions']"></div>
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
    
        <div class="operations" v-if="currentModeHasOperations && currentOperation !==''">
            <div class="input-container" v-if="currentOperation === 'create'" >
                <label :for="currentOperation+'identifier'" 
                v-text="text['operation-create-identifier']" ></label>
                <input :name="currentOperation+'identifier'"
                    v-model:value="operationInput.identifier"
                    type="text"
                >
            </div>
            <div class="input-container" v-if="currentOperation === 'create'" >
                <label :for="currentOperation+'object'" 
                v-text="text['operation-create-object']" ></label>
                <textarea :name="currentOperation+'object'"
                    v-model:value="operationInput.object"
                ></textarea>
            </div>
            <div class="input-container" v-if="currentOperation === 'create'" >
                <label :for="currentOperation+'overwrite'" 
                v-text="text['operation-create-overwrite']" ></label>
                <input type="checkbox" :name="currentOperation+'overwrite'"
                    v-model:value="operationInput.overwrite"
                >
            </div>
            <button
                v-if="(currentOperation === 'create')"
                class="positive-1"
                @click="addToNewLanguageObjects">
                <span v-text="text['operation-add-to-list']"></span>
            </button>
            <button
                :class="{'negative-1':(currentOperation === 'delete'), 'positive-1':(currentOperation === 'create')}"
                @click="confirmOperation">
                <div v-text="text['operation-confirm']"></div>
            </button>
            <button class="cancel-1" @click="cancelOperation">
                <div v-text="text['operation-cancel']"></div>
            </button>
            <div class="language-objects-list"  v-if="newLanguageObjects.length">
                <div v-for="item,index in newLanguageObjects">
                    <span class="identifier">
                        <h5 v-text="text['operation-create-identifier']"></h5>
                        <span v-text="item.identifier"></span>
                    </span>
                    <span class="object">
                        <h5 v-text="text['operation-create-object']"></h5>
                        <span v-text="item.object"></span>
                    </span>
                    <button class="negative-1" @click="newLanguageObjects.splice(index,1)">
                        <div v-text="text['operation-remove-from-list']"></div>
                    </button>
                </div>
            </div>
        </div>
    
        <button v-if="(currentMode==='search') && initiated" class="filter-toggle" :class="(filtersOpen?'negative-1':'positive-1')"
         v-text="text['title-filters-toggle-'+(filtersOpen?'hide':'show')]" @click="filtersOpen = !filtersOpen">
            </button>
        <div is="search-list"
             :class="{'no-filters':!filtersOpen}"
             v-if="(currentMode==='search')"
             :api-action="'getLanguageObjects'"
             :current-filters="searchListFilters['search']"
             :api-url="prependPathToRoot('language-objects')"
             :page="page"
             :limit="limit"
             :total="total"
             :items="objects"
             :initiate="!initiated"
             :columns="columns"
             :filters="filters"
             :selected="selectedMultiple.length > 1 ? selectedMultiple : selected"
             :text="searchListText"
             :test="test"
             :verbose="verbose"
             identifier="search"
        ></div>
    
        <div is="language-objects-editor"
             v-if="currentMode==='edit'"
             identifier="language-objects-editor"
             :language-object="selectedItem"
             :common-text="editorText"
             :test="test"
             :verbose="verbose"
        ></div>
    </div>
    `
});

