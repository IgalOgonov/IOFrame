if(eventHub === undefined)
    var eventHub = new Vue();

var articles = new Vue({
    el: '#articles',
    name: 'articles',
    mixins:[eventHubManager,IOFrameCommons,cacheableObjectGetter,searchListFilterSaver],
    data(){
        return {
            configObject: JSON.parse(JSON.stringify(document.siteConfig)),
            language:document.ioframe.selectedLanguage ?? 'eng',
            cacheableObjects:{
                'ArticleTags':{
                    apiName:'api/tags',
                    actionName:'getBaseTags',
                    cacheName:'_Default_Article_Tags_Cache',
                    eventName:'getBaseTagsResponse',
                    extraParams:{
                        'type':'default-article-tags'
                    }
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
                        'permanentDeletion':{
                            title:'Delete Permanently',
                            button:'negative-2'
                        },
                        'cancel':{
                            title:'Cancel',
                            button:'cancel-1'
                        }
                    },
                    title:'View Articles'
                },
                edit:{
                    operations:{},
                    title:'Edit Article'
                },
                create:{
                    operations:{},
                    title:'Create Article'
                }
            },
            //Result columns to display, and how to parse them //TODO Expend with more
            columns:[
                {
                    id:'identifier',
                    title:'ID'
                },
                {
                    id:'title',
                    title:'Title'
                },
                {
                    id:'tags',
                    title:'Tags',
                    parser:function(tags){
                    }
                },
                {
                    id:'image',
                    custom:true,
                    title:'Thumbnail',
                    parser:function(item){
                        if(!item.thumbnail || !item.thumbnail.address)
                            return 'None';
                        let src;
                        if(item.thumbnail.local){
                            src = document.ioframe.rootURI+document.ioframe.imagePathLocal+item.thumbnail.address;
                        }
                        else
                            src = item.thumbnail.dataType?
                                (document.ioframe.rootURI+'api/v1/media?action=getDBMedia&address='+item.thumbnail.address+'&lastChanged='+item.thumbnail.updated)
                                :
                                item.thumbnail.address;
                        let alt = item.meta.alt? item.meta.alt : (item.thumbnail.meta.alt? item.thumbnail.meta.alt : '');
                        return '<img src="'+src+'" alt="'+alt+'">';
                    }
                },
                {
                    id:'articleAuth',
                    title:'View Auth',
                    parser:function(level){
                        switch (level){
                            case 0:
                                return 'Public';
                            case 1:
                                return 'Restricted';
                            case 2:
                                return 'Private';
                            default:
                                return 'Admin';
                        }
                    }
                },
                {
                    id:'articleAddress',
                    title:'Address'
                },
                {
                    id:'subtitle',
                    custom:true,
                    title:'Subtitle',
                    parser:function(item){
                        if(!item.meta)
                            return '';
                        return item.meta.subtitle? (item.meta.subtitle.substring(0,40)+'...') : ' - ';
                    }
                },
                {
                    id:'caption',
                    custom:true,
                    title:'Caption',
                    parser:function(item){
                        if(!item.caption)
                            return '';
                        return item.meta.caption? (item.meta.caption.substring(0,40)+'...') : ' - ';
                    }
                },
                {
                    id:'weight',
                    title:'Article Weight',
                },
                {
                    id:'creator',
                    custom:true,
                    title:'Creator',
                    parser:function(item){
                        let result = item.creatorId;
                        if(item.firstName){
                            result = item.firstName;
                            if(item.lastName)
                                result += ' '+item.lastName;
                        }
                        return result;
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
            //Calculated Dynamically
            filters:{

            },
            //SearchList API (and probably the only relevant API) URL
            url: document.ioframe.pathToRoot+ 'api/v2/articles',
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
            extraParams:{
                authAtMost: 9999
            },
            extraClasses: function(item){
                if(item.articleAuth > 2)
                    return ['hidden'];
                else
                    return [];
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
        this.registerEvent('resetSearchPage',this.resetSearchPage);
        this.registerEvent('operationRequest', this.handleOperationResponse);
        this.registerEvent('goToPage', this.goToPage);
        this.registerEvent('searchAgain', this.searchAgain);
        this.registerEvent('returnToMainApp', this.returnToMainApp);
        this.registerEvent('getBaseTagsResponse', this.getBaseTagsResponse);
        this.getCacheableObjects();
        this.updateFilters();
        this._registerSearchListFilters('search',{authAtMost:'9999',languageIs:'@'}, {startListening:true,registerDefaultEvents:true});
    },
    beforeMount: function (){
    },
    computed:{
        //Main title
        title:function(){
            switch(this.currentMode){
                case 'search':
                    return 'Browsing Articles';
                case 'edit':
                    return 'Editing Article';
                case 'create':
                    return 'Creating Article';
                default:
            }
        },
        //Text for current operation
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
        //Text for current operation
        currentOperationText:function(){
            switch(this.currentOperation){
                case 'delete':
                    return 'Delete selected?';
                case 'permanentDeletion':
                    return 'Delete permanently?';
                default:
                    return '';
            }
        },
        //Whether current operation has input
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
        //Filters depend on the tags
        updateFilters:function(tagFilters = {}){
            let filters = [];

            filters.push(
                {
                    type:'Group',
                    group: [
                        {
                            name:'titleLike',
                            title:'Title Includes',
                            placeholder:'Text the name includes',
                            type:'String',
                            min:0,
                            max: 64,
                            validator: function(value){
                                return value.match(/^[\w\.\-\_ ]{1,64}$/) !== null;
                            }
                        },
                        {
                            name:'addressLike',
                            title:'Address Includes',
                            placeholder:'Text the name includes',
                            type:'String',
                            min:0,
                            max: 64,
                            validator: function(value){
                                return value.match(/^[\w\.\-\_ ]{1,64}$/) !== null;
                            }
                        },
                    ]
                },
                {
                    type:'Group',
                    group: [
                        {
                            name:'authAtMost',
                            title:'Minimal View Auth',
                            type:'Select',
                            list:[
                                {
                                    value:9999,
                                    title:'Admin'
                                },
                                {
                                    value:2,
                                    title:'Private'
                                },
                                {
                                    value:1,
                                    title:'Restricted'
                                },
                                {
                                    value:0,
                                    title:'Public'
                                }
                            ],
                            default: 9999
                        },
                        {
                            name:'weightIn',
                            title:'Article Weights (comma separated)',
                            placeholder: '0,1,2 ... up to 100',
                            type:'String',
                            list:[
                                {
                                    value:9999,
                                    title:'Admin'
                                },
                                {
                                    value:2,
                                    title:'Private'
                                },
                                {
                                    value:1,
                                    title:'Restricted'
                                },
                                {
                                    value:0,
                                    title:'Public'
                                }
                            ],
                            validator: function(value){
                                return value.match(/^0|(([1-9][0-9]{0,5}\,){0,99}[1-9][0-9]{0,5})$/);
                            },
                            parser:  function(value){
                                return JSON.stringify(value.split(',',100).map(x => x - 0));
                            }
                        },
                    ]
                },
                {
                    type:'Group',
                    group: [
                        {
                            name:'languageIs',
                            title:'Language',
                            type:'Select',
                            list:function(){
                                let list = [
                                    {
                                        value:'',
                                        title:'Default'
                                    },
                                    {
                                        value:'@',
                                        title:'All'
                                    }
                                ];
                                for(let i in document.ioframe.languages){
                                    list.push({
                                        value:document.ioframe.languages[i],
                                        title:document.ioframe.languages[i]
                                    });
                                }
                                return list;
                            }(),
                            default: ''
                        },
                        tagFilters
                    ]
                },
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
                }
            );
            Vue.set(this,'filters',filters) ;
        },
        getBaseTagsResponse:function(){
            let context = this;
            let tagFilters = {
                name:'tagsIn',
                title:'Article Tag',
                type:'Select',
                list:function(){
                    let list = [
                        {
                            value:'',
                            title:'All'
                        }
                    ];
                    const articleTags = context.cacheableObjects.ArticleTags.contents;
                    if(articleTags && (typeof articleTags === 'object') )
                        for(let i in articleTags){
                            if(i !== '@'){
                                let tagId = i.split('/');
                                let tagInfo = articleTags[i] ?? {};
                                list.push({
                                    value:i,
                                    title:tagInfo[context.language] ?? tagInfo['eng'] ?? tagId[1]
                                });
                            }
                        }
                    return list;
                }(),
                parser:function(value){
                    if(!value)
                        return value;
                    else{
                        let tagId = value.split('/');
                        return tagId[1];
                    }
                },
                default: ''
            };
            this.updateFilters(tagFilters);

            Vue.set(this.columns[2],'parser',function(tags){
                let tagsHTML = '';
                let tagsFromCache = context.cacheableObjects.ArticleTags.contents;

                if(!tagsFromCache || !tags || !tags.length)
                    return tagsHTML;
                for (let i in tags){
                    if(!tagsFromCache[tags[i]])
                        continue;
                    let tagId = tags[i].split('/');
                    let tagInfo = tagsFromCache[tags[i]];
                    tagsHTML += `<span class="tag `+tagId[0]+`">`+( tagInfo[context.language] ?? tagInfo['eng'] ?? tagId[1] )+`</span>`;
                }
                return tagsHTML;
            });
        },
        //Returns to main app
        returnToMainApp: function(){
            if(this.verbose)
                console.log('Returning to main app!');
            this.switchModeTo('search');
        },
        //Searches again (meant to be invoked after relevant changes
        searchAgain: function(){
            if(this.verbose)
                console.log('Searching again!');
            this.items = [];
            this.total = 0;
            this.selected = -1;
            this.initiated = false;
        },
        //Resets search result page
        resetSearchPage: function (response){
            if(this.verbose)
                console.log('Received response',response);

            if(!response.from || response.from !== 'search')
                return;

            this.page = 0;
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
            if(!response.content['meta'] || !response.content.articles)
                return;

            this.total = (response.content['meta']['#'] - 0) ;
            const articles = response.content.articles;
            for(let k in articles){
                articles[k].identifier = k;
                this.items.push(articles[k]);
            }
            this.selected = -1;
        },
        //Handles operation response
        handleOperationResponse: function(request){
            if(this.verbose)
                console.log('Received response',request);

            if(!request.from)
                return;

            let content = request.content;

            if(content.error){
                //Common Errors
                if(content.error === 'INPUT_VALIDATION_FAILURE'){
                    alertLog('Operation input validation failed!','error',this.$el);
                }
                else if(content.error === 'AUTHENTICATION_FAILURE'){
                    alertLog('Operation not authorized! Check if you are logged in.','error',this.$el);
                }
                else if(content.error === 'OBJECT_AUTHENTICATION_FAILURE'){
                    alertLog('Not authorized to view/modify object!','error',this.$el);
                }
                else if(content.error === 'WRONG_CSRF_TOKEN'){
                    alertLog('CSRF token wrong. Try refreshing the page if this continues.','warning',this.$el);
                }
                else if(content.error === 'SECURITY_FAILURE'){
                    alertLog('Security related operation failure.','warning',this.$el);
                }
            }


            switch (request.from){
                case 'delete':
                case 'permanentDeletion':
                    if(this.test){
                        alertLog(content,'info',this.$el);
                        return;
                    }
                    let actualResponse = content.response[this.items[this.selected].identifier];
                    if(actualResponse === undefined){
                        alertLog('Unknown '+request.from+' response, '+actualResponse,'error',this.$el);
                        return;
                    }
                    switch (actualResponse){
                        case -1:
                            alertLog(request.from+' failed due to server error!','error',this.$el);
                            break;
                        case 0:
                            alertLog(request.from+' successful!','success',this.$el,{autoDismiss:2000});
                            if(request.from === 'permanentDeletion')
                                this.items.splice(this.selected,1);
                            else
                                this.items[this.selected].articleAuth = 9999;
                            this.cancelOperation();
                            break;
                        case 'AUTHENTICATION_FAILURE':
                            alertLog(request.from+' not authorized! Check if you are logged in.','error',this.$el);
                            break;
                        default:
                            console.warn(actualResponse);
                            alertLog('Unknown response to '+request.from+', '+actualResponse,'error',this.$el);
                    }
                    break;
                default:
                    alertLog('Unknown operation '+request.from+' response, '+content,'error',this.$el);
            }
        },
        //Goes to relevant page
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
        //Element selection from search list
        selectElement: function(request){

            if(!request.from || (request.from !== 'search'))
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

            let queryParams = {};
            let test = this.test;
            let verbose = this.verbose;
            let currentOperation = this.currentOperation;
            let thisElement = this.$el;
            let operation = '';

            if(this.currentMode === 'search'){
                switch (currentOperation){
                    case 'delete':
                    case 'permanentDeletion':
                        operation = currentOperation;
                        queryParams.permanent = currentOperation === 'permanentDeletion';
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
                    queryParams.req = 'test';

                 this.apiRequest(
                     null,
                      'api/v2/articles/'+this.items[this.selected].identifier,
                      'operationRequest',
                      {
                          method:'delete',
                          queryParams:queryParams,
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
                if(this.selected === -1)
                    return false;
                else if(this.items[this.selected].articleAuth > 2 && index === 'delete')
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
    template:`
    <div id="articles" class="main-app">
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
                :class="(['delete','permanentDeletion'].indexOf(currentOperation) !== -1 ? 'negative-1' : 'positive-1')"
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
             api-action="getArticles"
             :extra-params="extraParams"
             :extra-classes="extraClasses"
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
    
        <div is="articles-editor"
             v-else=""
             :mode="currentMode==='edit'? 'update' : 'create'"
             :default-auth="9999"
             :allow-modifying="true"
             :existing-tag-info="cacheableObjects.ArticleTags"
             :is-admin="true"
             :item-identifier="currentMode==='edit'? items[selected].identifier - 0 : null"
             identifier="editor"
             :test="test"
             :verbose="verbose"
            ></div>
    </div>
    `
});