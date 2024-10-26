if(eventHub === undefined)
    var eventHub = new Vue();

//***************************
//******Tags APP*******
//***************************//

var tags = new Vue({
    el: '#tags',
    name: 'Tags',
    mixins:[IOFrameCommons,eventHubManager,searchListFilterSaver],
    data(){
        return {
            configObject: JSON.parse(JSON.stringify(document.siteConfig)),
            language:document.ioframe.selectedLanguage ?? 'eng',
            //Modes, and array of available operations in each mode
            modes: {
                search:{
                    operations:{
                        'create':{
                            title:'Create New Tag(s)',
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
                    title:'View Tags'
                },
                edit:{
                    operations:{},
                    title:'Edit Tag'
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
                            placeholder:'Tag identifier includes',
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
                            placeholder:'Tag identifier excludes',
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
            tags : [
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
            //Category modes
            tagModes: {
                '@':{
                    initiated:false
                },
                /*'default-article-tags':{
                    title:'Special Articles',
                    img:true,
                    extraMetaParameters:{
                        'eng'={
                            'title'=>'Tag Title',
                            ['searchColumn']:true,
                            ['valid']:'^\w[\w\d\-_\\\' ]{0,128}$',
                            ['required']:true
                        }
                    }
                },*/
            },
            tagMode: '',
            tagCategory: '',
            currentMode:'search',
            //Current operation
            currentOperation: '',
            //Current operation input
            operationInput: {
            },
            //Needed during image selection
            mediaSelector: {
                open:false,
                hidden:false,
                keepOpen:false,
                selectMultiple:false,
                quickSelect:true,
                mode:null,
                selection:{}
            },
            //All tags, if we are creating multiple
            newTags:[],
            //Various categories we might need to choose from
            categories:{
                /*
                'exampleIdentifier':{
                    initiated:false,
                    error:false,
                    details:{
                        'identifier':'exampleIdentifier',
                        'api':'path/from/api/root',
                        'action':'getCategoriesAction',
                        'id':null, default null, null to treat object keys as category IDs, can be a string - key of category ID inside each object
                        'titlePath':"",  default '', path to either category title, or language object. '' for all object, could be something like "title.text"
                        'titleIsLangObject':true default true, if true signifies to treat titlePath as path to the language object root, rather then path to a string
                    },
                    items:{}
                }
                */
            },
            isLoading:false,
            commonText:{
                'categories-loading':'Loading Categories...',
                'categories-error':'Error getting categories',
                'categories-all':'All Categories',
                'title-edit':' - Edit',
                'title-create':' - Create',
                'title-type':'Item Type',
                'title-category':'Item Category',
                'title-actions':'Actions',
                'title-filters-toggle-show':'Show Filters',
                'title-filters-toggle-hide':'Hide Filters',
                'switch-mode-no-item':'Please select an item before viewing/editing it!',
                'operation-delete':'Delete selected?',
                'operation-create-identifier':'Identifier',
                'operation-create-category':'Category',
                'operation-add-to-list':'Add',
                'operation-remove-from-list':'Remove',
                'operation-thumbnail-reset':'Reset Thumbnail',
                'operation-confirm':'Confirm',
                'operation-cancel':'Cancel',
                'create-missing-input-identifier':'Item must have an identifier',
                'create-missing-input-category':'Item must have a category',
                'create-invalid-input-identifier':'Identifier can contain english characters, digits and _,-',
                'create-invalid-input-category':'Category must be a number',
                'create-invalid-input-color':'Invalid color hex',
                'create-missing-input-required':'Item must have attribute',
                'create-invalid-input':'Invalid extra parameter',
                'create-invalid-input-requires-regex':'regex to match',
                'create-duplicate-identifiers':'Multiple items to be created cannot have similar identifiers',
                'response-unknown':'Unknown response',
                'response-db-connection-error':'Server internal connection error',
                'response-create-missing':'Missing inputs for items',
                'response-create-dependency':'Image no longer exists for items',
                'response-create-exists':'Identifiers already exist for items',
                'response-create-success':'Items created',
                'response-delete-success':'Items Deleted',
                'media-selector-reset':'Reset On Close',
                'media-selector-keep-open':'Dont Reset On Close',
            },
            verbose:false,
            test:false
        }
    },
    created:function(){
        this.registerHub(eventHub);
        this.registerEvent('tagAPIResponse', this.handleAPIResponse);
        this.registerEvent('searchResults',this.parseSearchResults);
        this.registerEvent('initiateTags',this._initiateTags);
        this.registerEvent('requestSelection',this.selectElement);
        this.registerEvent('cancelOperation',this.cancelOperation);
        this.registerEvent('categoryAPIResponse',this.parseCategory);
        this.registerEvent('goToPage',this.goToPage);
        this.registerEvent('media-selector-selection-event' ,this.thumbnailSelection);
        this.registerEvent('tagUpdated',this.tagUpdated);
        this.initiateTags();
        if(this.tagModes['@'].initiated)
            this.setLanguageTitles();
        this._registerSearchListFilters('search',{}, {startListening:true,registerDefaultEvents:true});
    },
    computed:{
        //Searchlist parans
        extraParams: function(){
            let params = {
                getMeta:true,
            };
            if(this.tagMode && this.tagModes[this.tagMode].categories && this.tagCategory)
                params.category = this.tagCategory;
            params.type = this.tagMode;
            return params;
        },
        //SearchList columns
        columns: function(){
            let context = this;
            let columns = [
                {
                    id:'identifier',
                    title:'ID',
                    parser:function(id){
                        return id;
                    }
                },
            ];
            if(this.tagMode && this.tagModes[this.tagMode].categories && !this.tagCategory)
                columns.push(
                    {
                        id:'category',
                        title:'Category',
                        parser:function(category){
                            let fullDetails = JSON.parse(JSON.stringify(context.currentCategoryDetails));
                            if(fullDetails.items[category]){
                                let target = fullDetails.items[category];
                                if(fullDetails.details.titlePath){
                                    let pathArr = titlePath.split('.');
                                    for(let i in pathArr)
                                        if(target[i])
                                            target = target[i];
                                }
                                if( (fullDetails.details.titleIsLangObject === null) || (fullDetails.details.titleIsLangObject === undefined) )
                                    fullDetails.details.titleIsLangObject = true;
                                if(fullDetails.details.titleIsLangObject)
                                    return fullDetails.items[category][context.language] ?? fullDetails.items[category]['eng'];
                                else
                                    return target ?? target.title ?? '?';
                            }
                            else
                                return '';
                        }
                    }
                );
            if(this.tagMode && this.tagModes[this.tagMode].img)
                columns.push(
                    {
                        id:'img',
                        title:'Image',
                        parser:function(img){
                            if(img && img.address)
                                return '<img src="'+context.extractImageAddress(img)+'">';
                            else
                                return '';
                        }
                    }
                );
            if(this.tagMode && this.tagModes[this.tagMode].extraMetaParameters)
                for (let i in this.tagModes[this.tagMode].extraMetaParameters){
                    let extraParam = this.tagModes[this.tagMode].extraMetaParameters[i];
                    if(extraParam.searchColumn){
                        let parserFunction;
                        if(extraParam.color)
                            parserFunction = function(color){
                                return `<span class="color" style="background-color:#`+color+`"></span>`;
                            };
                        else
                            parserFunction = function(param){
                                return param;
                            }
                        columns.push(
                            {
                                id:i,
                                title:extraParam.title,
                                parser:parserFunction
                            });
                    }
                }
            columns.push(
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
            );
            return columns;
        },
        //Main title
        title:function(){
            switch(this.currentMode){
                case 'search':
                    return this.tagMode && this.tagModes[this.tagMode].title;
                case 'edit':
                    return this.tagMode && this.tagModes[this.tagMode].title + this.commonText['title-edit'];
                case 'create':
                    return this.tagMode && this.tagModes[this.tagMode].title + this.commonText['title-create'];
                default:
                    return this.tagMode && this.tagModes[this.tagMode].title;
            }
        },
        //Whether the current mode has operations
        currentModeHasOperations:function(){
            return Object.keys(this.modes[this.currentMode].operations).length>0;
        },
        selectedItem:function () {
            return (this.selected!==-1 && this.selectedMultiple.length < 2) ? this.tags[this.selected]:{};
        },
        thumbnailEmpty:function(){
            if(!this.operationInput.thumbnail)
                return true;
            return !Object.keys(this.operationInput.thumbnail).length;
        },
        currentCategoryDetails: function(){
            if(!this.tagMode  || !this.tagModes[this.tagMode].categories)
                return false;
            else
                return this.categories[this.tagModes[this.tagMode].categories.identifier];
        },
        //Common text for the editor
        commonTextEditor: function(){
            let text = JSON.parse(JSON.stringify(this.commonText));
            text['response-update-dependency'] = text['response-create-dependency'];
            text['response-update-does-not-exist'] = text['response-update-does-not-exist']?? 'Tag no longer exists';
            text['response-update-success'] = text['response-update-success']?? 'Tag updated';
            text['info-created'] = text['info-created']?? 'Created';
            text['info-weight'] = text['info-weight']?? 'Tag Weight';
            text['info-updated'] = text['info-updated']?? 'Updated';
            text['remove-image'] = text['remove-image']?? 'Remove Image';
            return text;
        }
    },
    watch:{
        tagMode: function(newValue){
            this.cancelOperation();
            this.currentMode = 'search';
            if(this.tagModes[newValue].categories && this.tagModes[newValue].categories.identifier && this.categories[this.tagModes[newValue].categories.identifier] && !this.categories[this.tagModes[newValue].categories.identifier].initiated)
                this.getCategories(this.tagModes[newValue].categories.identifier);
            else
                this.searchAgain();
        },
        tagCategory: function(newValue){
            this.searchAgain();
        }
    },
    methods:{
        //Initiates tags from the api, or preloaded tags on this page
        initiateTags: function(){
            if(this.configObject.tags && this.configObject.tags.tagSettings)
                this['_initiateTags'](this.configObject.tags.tagSettings);
            else {

                let data = new FormData();
                data.append('action','getManifest');

                this.apiRequest(data, 'api/v1/tags', 'initiateTags', {
                    verbose: this.verbose,
                    parseJSON: true
                });
            }
        },
        //Initiates tags from the api, or preloaded tags on this page
        _initiateTags: function(response){
            if(!response.availableTagTypes && !response.availableCategoryTagTypes)
                return;

            if(!response.availableTagTypes)
                response.availableTagTypes = {};

            let availableTags = response.availableTagTypes;

            if(response.availableCategoryTagTypes){
                for(let i in response.availableCategoryTagTypes){
                    if(!response.availableCategoryTagTypes[i].categories)
                        delete response.availableCategoryTagTypes[i];
                    let categoryInfo = response.availableCategoryTagTypes[i].categories;
                    Vue.set(this.categories,categoryInfo.identifier,{
                        initiated:false,
                        error:false,
                        details:JSON.parse(JSON.stringify(categoryInfo)),
                        items:{}
                    });
                }
                if (Object.keys(response.availableCategoryTagTypes).length)
                    mergeDeep(availableTags,response.availableCategoryTagTypes);
            }

            if (!Object.keys(availableTags).length)
                return;

            Vue.set(this,'tagModes',mergeDeep(this.tagModes,availableTags))
            this.tagMode = Object.keys(availableTags)[0];
            this.tagModes['@'].initiated = true;
        },
        //Sets relevant titles depending on language
        setLanguageTitles: function(){
            if(!this.language || (this.language === 'eng') || !(this.configObject.tags.text??false))
                return;
            //Modes
            if(this.configObject.tags.text.modes)
                Vue.set(this,'modes',mergeDeep(this.modes,this.configObject.tags.text.modes))

            //Filters
            if(this.configObject.tags.text.filters)
                Vue.set(this,'filters',[
                    {
                        type:'Group',
                        group: [
                            {
                                name:'createdAfter',
                                title:this.configObject.tags.text.filters.createdAfter ?? 'Created After',
                                type:'Datetime',
                                parser: function(value){ return Math.round(value/1000); }
                            },
                            {
                                name:'createdBefore',
                                title:this.configObject.tags.text.filters.createdBefore ?? 'Created Before',
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
                                title:this.configObject.tags.text.filters.changedAfter ?? 'Changed After',
                                type:'Datetime',
                                parser: function(value){ return Math.round(value/1000); }
                            },
                            {
                                name:'changedBefore',
                                title:this.configObject.tags.text.filters.changedBefore ?? 'Changed Before',
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
                                title:this.configObject.tags.text.filters.includeRegex.title ?? 'Includes',
                                placeholder:this.configObject.tags.text.filters.includeRegex.placeholder ?? 'Tag identifier includes',
                                type:'String',
                                min:0,
                                max: 64,
                                validator: function(value){
                                    return value.match(/^[\w\.\-\_ ]{1,64}$/) !== null;
                                }
                            },
                            {
                                name:'excludeRegex',
                                title:this.configObject.tags.text.filters.excludeRegex.title ?? 'Exclude',
                                placeholder:this.configObject.tags.text.filters.excludeRegex.placeholder ?? 'Tag identifier excludes',
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

            //Pagination
            if(this.configObject.tags.text.searchListText)
                Vue.set(this,'searchListText',mergeDeep(this.searchListText,this.configObject.tags.text.searchListText))

            //Tag Modes languages loaded from the setting

            //Common Text
            if(this.configObject.tags.text.commonText)
                Vue.set(this,'commonText',mergeDeep(this.commonText,this.configObject.tags.text.commonText))

        },
        //Resets media selector
        resetMediaSelector: function(){
            Vue.set(this,'mediaSelector',{
                open:false,
                hidden:false,
                keepOpen:false,
                selectMultiple:false,
                quickSelect:true,
                mode:null,
                selection:{}
            });
        },
        resetOperationInput: function(){
            if(!this.tagMode || this.tagModes[this.tagMode] || !this.tagModes[this.tagMode].extraMetaParameters)
                return;
            let newInput = {
                identifier: '',
                thumbnail: {}
            };
            if(this.tagModes[this.tagMode].extraMetaParameters)
                    newInput[i] = '';
            Vue.set(this,'operationInput',newInput);
        },
        //Toggles media selector
        toggleMediaSelector: function(){
            if(this.mediaSelector.keepOpen)
                this.mediaSelector.hidden = !this.mediaSelector.hidden;
            else
                this.mediaSelector.open = !this.mediaSelector.open;
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
                        alertLog(this.commonText['response-unknown'],'error',this.$el);
                        console.log(response);
                        return;
                    }

                    let outcomes = {
                        '-2': {
                            identifiers:[],
                            error:true,
                            message:this.commonText['response-create-dependency']
                        },
                        '-1': {
                            identifiers:[],
                            error:true,
                            message:this.commonText['response-db-connection-error']
                        },
                        '0': {
                            identifiers:[],
                            error:false,
                            message:this.commonText['response-create-success']
                        },
                        '2': {
                            identifiers:[],
                            error:true,
                            message:this.commonText['response-create-exists']
                        },
                        '3': {
                            identifiers:[],
                            error:true,
                            message:this.commonText['response-create-missing']
                        },
                    };
                    let successes = [];
                    for(let i in this.newTags){
                        let hasCategories = this.tagModes[this.tagMode].categories;
                        let respIdentifier = this.tagMode+'/'+
                            (hasCategories? this.tagCategory+'/':'')+
                            this.newTags[i].identifier;
                        let itemResponse = response[respIdentifier] + '';
                        if(itemResponse === '1')
                            itemResponse = '-1';
                        if(itemResponse === '0')
                            successes.push(i);
                        outcomes[itemResponse+''].identifiers.push(this.newTags[i].identifier);
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
                        this.newTags.splice(successes[i],1);

                    if(this.newTags.length === 0)
                        this.cancelOperation();

                    if(successes.length > 0)
                        this.searchAgain(false);

                    break;
                case 'delete':
                    switch (response) {
                        case -1:
                            alertLog(this.commonText['response-db-connection-error'],'error',this.$el);
                            break;
                        case 0:
                            alertLog(this.commonText['response-delete-success'],'success',this.$el,{autoDismiss:2000});
                            this.cancelOperation();
                            this.searchAgain(false);
                            break;
                        default:this.test?
                            alertLog(response,'warning',this.$el):
                            alertLog(this.commonText['response-unknown'],'error',this.$el);
                            console.log(response);
                            break;
                    }
                    break;
                default:
                    alertLog(this.commonText['response-unknown'],'error',this.$el);
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
            this.tags = [];

            for(let k in response.content){
                let temp = k.split('/');
                response.content[k].identifier = temp[temp.length-1];
                this.tags.push(response.content[k]);
            }
        },
        searchAgain: function(newPage = true){
            if(this.verbose)
                console.log('Searching again!');
            this.tags = [];
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
            return !( index==='edit' && this.currentMode !== index && this.selected === -1 );
        },
        parseCategory(response){
            if(this.verbose)
                console.log('Received response',response);

           let target = this.categories[this.tagModes[this.tagMode].categories.identifier];

            //In this case the response was an error code, or the page no longer exists
            if(response['@'] === undefined){
                target.error = true;
                return;
            }

            delete response['@'];

            Vue.set(target,'items',response);

            target.initiated = true;
            this.searchAgain();
        },
        getCategories(type){
            if(this.verbose)
                console.log('Getting categories of type ', type);

            let data = new FormData();
            let details = this.categories[type].details;

            data.append('action',details.action);

            this.apiRequest(data, 'api/'+details.api, 'categoryAPIResponse', {
                verbose: this.verbose,
                parseJSON: true
            })
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
                alertLog(this.commonText['switch-mode-no-item'],'warning',this.$el);
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
            let hasCategories = this.tagModes[this.tagMode].categories;
            let currentOperation = this.currentOperation;
            if(this.test)
                data.append('req','test');
            data.append('type',this.tagMode);

            switch (currentOperation){
                case 'delete':
                    let identifiers = [];
                    for(let i in this.selectedMultiple)
                        identifiers.push(this.tags[this.selectedMultiple[i]].identifier);
                    data.append('action',hasCategories?'deleteCategoryTags':'deleteBaseTags');
                    if(this.tagCategory)
                        data.append('category',this.tagCategory);
                    data.append('identifiers',JSON.stringify(identifiers));
                    break;
                case 'create':
                    if(this.newTags.length === 0)
                        if(!this.addToNewTags())
                            return;
                    let toSend = JSON.parse(JSON.stringify(this.newTags));
                    if(this.verbose)
                        console.log('Creating tags using:', toSend,hasCategories);
                    for(let i in toSend)
                        if(toSend[i].thumbnail && Object.keys(toSend[i].thumbnail)){
                            toSend[i].img = toSend[i].thumbnail.address;
                            delete toSend[i].thumbnail;
                        }
                    if(hasCategories)
                        data.append('category',this.tagCategory);
                    data.append('tags',JSON.stringify(toSend));
                    data.append('overwrite','0');
                    data.append('action',hasCategories?'setCategoryTags':'setBaseTags');
                    break;
                default:
                    break;
            }

            this.apiRequest(data, 'api/tags', 'tagAPIResponse', {
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
                else if ( ((index === 'create') || (index === 'delete')) && this.tagMode && this.tagModes[this.tagMode].categories && !this.tagCategory)
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
            Vue.set(this,'newTags',[]);
            this.resetOperationInput();
            this.resetMediaSelector();
            this.currentOperation = '';

        },
        //Handles thumbnail selection
        thumbnailSelection: function(item){
            if(this.verbose)
                console.log('Setting thumbnail to ',item);

            if(item.local)
                item.address = item.relativeAddress;
            else
                item.address = item.identifier;
            item.meta = {};
            if(item.alt)
                item.meta.alt = item.alt;
            if(item.caption)
                item.meta.caption = item.caption;
            if(item.name)
                item.meta.name = item.name;

            item.updated = item.lastChanged;

            this.toggleMediaSelector();
            Vue.set(this.operationInput,'thumbnail',item);
        },
        //Handles thumbnail selection
        thumbnailReset: function(){
            Vue.set(this.operationInput,'thumbnail', {});
        },
        //Adds thumbnail to list of tags to create
        addToNewTags: function(){
            if(!this.checkInputs())
                return false;
            let validInputs = ['identifier'];
            if(this.tagModes[this.tagMode].img)
                validInputs.push('thumbnail');
            if(this.tagModes[this.tagMode].extraMetaParameters)
                for (let i in this.tagModes[this.tagMode].extraMetaParameters)
                    validInputs.push(i);
            let attr = {};
            for(let i in validInputs){
                attr[validInputs[i]] = this.operationInput[validInputs[i]];
            }
            for(let i in this.newTags)
                if(this.newTags[i].identifier === attr.identifier){
                    alertLog(this.commonText['create-duplicate-identifiers'],'warning',this.$el);
                    return false;
                }
            this.newTags.push(attr);
            return true;
        },
        //Checks inputs for validity
        checkInputs: function(){
            if(!this.operationInput.identifier){
                alertLog(this.commonText['create-missing-input-identifier'],'warning',this.$el);
                return false;
            }
            if(this.tagModes[this.tagMode].categories && !this.tagCategory){
                alertLog(this.commonText['create-missing-input-category'],'warning',this.$el);
                return false;
            }
            if(this.operationInput.identifier.match(/^\w[\w\d-_]{0,63}$/)  === null){
                alertLog(this.commonText['create-invalid-input-identifier'],'warning',this.$el);
                return false;
            }
            if(this.tagModes[this.tagMode].extraMetaParameters)
                for (let i in this.tagModes[this.tagMode].extraMetaParameters){
                    let extraParamInfo = this.tagModes[this.tagMode].extraMetaParameters[i];
                    if(extraParamInfo.required && !this.operationInput[i]){
                        alertLog(this.commonText['create-missing-input-required']+' '+i,'warning',this.$el);
                        return false;
                    }
                    if(this.operationInput[i] && extraParamInfo.valid && !this.operationInput[i].match(new RegExp(extraParamInfo.valid))){
                        alertLog(this.commonText['create-invalid-input']+' - '+i+', '+this.commonText['create-invalid-input-requires-regex']+' '+extraParamInfo.valid,'warning',this.$el);
                        return false;
                    }
                    else if( this.operationInput[i] && extraParamInfo.color && !this.operationInput[i].toLowerCase().match(/^[0-9a-f]{6}$/) ){
                        alertLog(this.commonText['create-invalid-input']+' - '+i+': '+this.commonText['create-invalid-input-color'],'warning',this.$el);
                        return false;
                    }
                }
            return true;
        },
        //Prepends pathToRoot to url
        prependPathToRoot: function(path){
            return document.ioframe.pathToRoot + 'api/' + path;
        },

        //What to do when a tag is updated in the editor
        tagUpdated: function(){
            if(this.verbose)
                console.log('Tag updated');

            if(this.selected===-1)
                return;

            this.searchAgain(false);
        },
    },
    template: `
    <div id="tags" class="main-app">
        <div class="loading" v-if="isLoading">
        </div>
    
        <h1 v-if="title!==''" v-text="title"></h1>
    
        <div class="type-mode">
            <span v-text="commonText['title-type']"></span>
            <select class="currentMode" v-model:value="tagMode" :disabled="currentMode !== 'search'">
                <option v-for="(item, index) in tagModes"
                        v-if="index !== '@'"
                        :value="index"
                        v-text="item.title"
                    ></option>
            </select>
            <span v-if="currentCategoryDetails">
                <span
                    v-if="currentCategoryDetails.error"
                    :name="currentOperation+'category'"
                    v-text="commonText['categories-error']"
                    disabled=""
                ></span>
                <span
                    v-else-if="!currentCategoryDetails.initiated"
                    :name="currentOperation+'category'"
                    v-text="commonText['categories-loading']"
                    disabled=""
                ></span>
                <select v-else="" v-model="tagCategory" :disabled="currentMode !== 'search'">
                    <option value="" v-text="commonText['categories-all']"></option>
                    <option v-for="(item,key) in currentCategoryDetails.items" 
                    :value="key"
                    v-text="item[language]??item.eng"></option>
                </select>
            </span>
        </div>
    
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
    
        <div class="operations" v-if="currentModeHasOperations && currentOperation !==''">
            <div class="input-container" v-if="currentOperation === 'create'" >
                <label :for="currentOperation+'identifier'" 
                v-text="commonText['operation-create-identifier']" ></label>
                <input :name="currentOperation+'identifier'"
                    v-model:value="operationInput.identifier"
                    type="text"
                >
            </div>
            <div class="input-container" v-if="(currentOperation === 'create') && tagModes[tagMode].categories">
                <label
                :for="currentOperation+'category'"
                 v-text="commonText['operation-create-category']"></label>
                <span
                    v-if="currentCategoryDetails.error"
                    :name="currentOperation+'category'"
                    v-text="commonText['categories-error']"
                    disabled=""
                ></span>
                <span
                    v-else-if="!currentCategoryDetails.initiated"
                    :name="currentOperation+'category'"
                    v-text="commonText['categories-loading']"
                    disabled=""
                ></span>
                <select v-else="" v-model="tagCategory" disabled>
                    <option disabled value="" v-text="commonText['operation-create-category']"></option>
                    <option v-for="(item,key) in currentCategoryDetails.items" 
                    :value="key"
                    v-text="item[language]??item.eng"></option>
                </select>
            </div>
            <div :class="['input-container',{required:item.required},{color:item.color}]" v-if="(currentOperation === 'create') && tagModes[tagMode].extraMetaParameters" v-for="item,index in tagModes[tagMode].extraMetaParameters">
                <label
                 :for="currentOperation+index"
                  v-text="item.title"></label>
                <input
                    :name="currentOperation+index"
                    v-model:value="operationInput[index]"
                    type="text"
                >
                <span
                    v-if="item.color"
                    class="color"
                    :style="{background:'#'+operationInput[index]??'ffffff'}"
                ></span>
            </div>
            <div class="input-container thumbnail-preview" v-if="(currentOperation === 'create') && tagModes[tagMode].img"
                @click.prevent="toggleMediaSelector">
                
                <img v-if="extractImageAddress(operationInput.thumbnail)"  
                :src="extractImageAddress(operationInput.thumbnail)" 
                :alt="operationInput.thumbnail.meta.alt? operationInput.thumbnail.meta.alt : false">
                
                <img v-else="" :src="getAbsoluteFrontUrl(tagModes[tagMode].img_empty_url??'ioframe/img/icons/upload.svg')">
                
            </div>
            <button
                v-if="(currentOperation === 'create') && !thumbnailEmpty"
                class="cancel-1"
                @click="thumbnailReset()">
                <span v-text="commonText['operation-thumbnail-reset']"></span>
            </button>
            <button
                v-if="(currentOperation === 'create')"
                class="positive-1"
                @click="addToNewTags">
                <span v-text="commonText['operation-add-to-list']"></span>
            </button>
            <button
                :class="{'negative-1':(currentOperation === 'delete'), 'positive-1':(currentOperation === 'create')}"
                @click="confirmOperation">
                <div v-text="commonText['operation-confirm']"></div>
            </button>
            <button class="cancel-1" @click="cancelOperation">
                <div v-text="commonText['operation-cancel']"></div>
            </button>
            <div class="tags-list"  v-if="newTags.length">
                <div v-for="item,index in newTags">
                    <span v-if="tagModes[tagMode].categories" class="category">
                        <h5 v-text="commonText['operation-create-category']"></h5>
                        <span v-text="item.category"></span>
                    </span>
                    <span class="identifier">
                        <h5 v-text="commonText['operation-create-identifier']"></h5>
                        <span v-text="item.identifier"></span>
                    </span>
                    <span  v-if="tagModes[tagMode].extraMetaParameters" v-for="paramItem,paramIndex in tagModes[tagMode].extraMetaParameters" :class="paramIndex">
                        <h5 v-text="paramItem.title"></h5>
                        <span v-if="!paramItem.color" v-text="item[paramIndex]"></span>
                        <span v-else="" class="color" :style="{backgroundColor:'#'+item[paramIndex]}"></span>
                    </span>
                    <img v-if="tagModes[tagMode].img && extractImageAddress(item.thumbnail)"  
                    :src="extractImageAddress(item.thumbnail)">
                    <img v-else="" class="no-image" :src="getMediaUrl('ioframe','general/missing.svg')">
                    <button class="negative-1" @click="newTags.splice(index,1)">
                        <div v-text="commonText['operation-remove-from-list']"></div>
                    </button>
                </div>
            </div>
            <div class="media-selector-container" :class="{hidden:mediaSelector.hidden}" dir="ltr"  v-if="mediaSelector.open">
                <div class="control-buttons">
                    <button 
                    @click.prevent="mediaSelector.keepOpen = !mediaSelector.keepOpen"
                    v-text="mediaSelector.keepOpen?commonText['media-selector-reset']:commonText['media-selector-keep-open']"
                    class="cancel"
                    >
                    </button>
                    <img 
                    :src="getMediaUrl('ioframe','icons/cancel-icon.svg')" 
                    @click.prevent="toggleMediaSelector">
                </div>

                <div
                     is="media-selector"
                     :identifier="'media-selector'"
                     :test="test"
                     :verbose="verbose"
                ></div>
            </div>
        </div>
    
        <button v-if="(currentMode==='search') && tagModes['@'].initiated" class="filter-toggle" :class="(filtersOpen?'negative-1':'positive-1')"
         v-text="commonText['title-filters-toggle-'+(filtersOpen?'hide':'show')]" @click="filtersOpen = !filtersOpen">
            </button>
        <div is="search-list"
             :class="{'no-filters':!filtersOpen}"
             v-if="(currentMode==='search') && tagModes['@'].initiated"
             :api-action="tagModes[tagMode].categories? 'getCategoryTags' : 'getBaseTags'"
             :api-url="prependPathToRoot('tags')"
             :extra-params="extraParams"
             :current-filters="searchListFilters['search']"
             :page="page"
             :limit="limit"
             :total="total"
             :items="tags"
             :initiate="!initiated"
             :columns="columns"
             :filters="filters"
             :selected="selectedMultiple.length > 1 ? selectedMultiple : selected"
             :text="searchListText"
             :test="test"
             :verbose="verbose"
             identifier="search"
        ></div>
    
        <div is="tags-editor"
             v-if="currentMode==='edit'"
             identifier="tag-editor"
             :tag-name="tagMode"
             :tag-details="tagModes[tagMode]"
             :category-details="tagCategory?categories[tagModes[tagMode].categories.identifier].items[tagCategory]:undefined"
             :tag="selectedItem"
             :common-text="commonTextEditor"
             :test="test"
             :verbose="verbose"
        ></div>
    </div>
    `
});