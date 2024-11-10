Vue.component('articles-editor', {
    mixins: [sourceURL,eventHubManager,IOFrameCommons,cacheableObjectGetter,objectEditor],
    props: {
        //Default auth - whether you are an admin (0), owner (2), permitted to see (1), or public user (10000)
        defaultAuth:{
            type: Number,
            default: 10000
        },
        //Whether to allow modification (disabling switches mode to view and locks it there)
        allowModifying:{
            type: Boolean,
            default: false
        },
        //Whether to assume the user is an admin
        isAdmin:{
            type: Boolean,
            default: false
        },
        //Item identifier
        itemIdentifier: {
            type: [Number, String],
            default: null
        },
        //Allows pre-loading an existing item without the API
        existingItem:{
            type: Object,
            default: function(){
                return {};
            }
        },
        //Allows pre-loading tags without getting them from cache TODO Get from cache if empty
        existingTagInfo:{
            type: Object,
            default: function(){
                return {};
            }
        },
        //Whether to view default headline
        viewHeadline:{
            type: Boolean,
            default: true
        },
        //Object of defaultHeadlineRenderer and articleBlockEditor parameters IN VIEW MODE
        viewParams:{
            type: Object,
            default: function(){
                return {
                    defaultHeadlineRenderer:{},
                    articleBlockEditor:{}
                };
            }
        },
        //Language, mainly used for tags - otherwise uses article language
        forceLanguage:{
            type: String,
            default: ''
        },
        //Starting mode - create or update
        mode: {
            type: String,
            default: 'view' //'create' / 'update' / 'view'
        },
        //App Identifier
        identifier: {
            type: String,
            default: ''
        },
        //Test Mode
        test: {
            type: Boolean,
            default: false
        },
        //Verbose Mode
        verbose: {
            type: Boolean,
            default: false
        },
    },
    data: function(){
        return {
            //Modes, and array of available operations in each mode
            modes: {
                'view': {
                    title: 'View Article'
                },
                'create': {
                    title: 'Create New Article'
                },
                'update': {
                    title: 'Edit Article'
                }
            },
            currentMode:this.mode,
            //article id
            articleId:typeof this.itemIdentifier === 'number' ? this.itemIdentifier : -1,
            //article address
            articleAddress:typeof this.itemIdentifier === 'number' ? '' : this.itemIdentifier,
            //article
            article:{
            },
            //language
            language:this.forceLanguage,
            //New article thumbnail
            articleThumbnail: {
                original:{},
                current:{},
                changed:false,
                address:'',
            },
            //Event Parsing Map
            eventParsingMap: {
                //Fully filled as an example
                'handleItemCreate':{
                    identifierFrom:this.identifier,
                    apiResponses:true,
                    alertErrors:true,
                    valid:{
                        type: 'number', /*No value, as any number above 0 would be valid*/
                        condition: function(value){return value > 0;}
                    },
                    objectOfResults: false, /*Doesn't really change anything, as the response isn't an object*/
                    errorMap:{
                        '-3':{
                            text:'Missing inputs when creating article!',
                            warningType: 'error',
                        },
                        '-2':{
                            text:'One of the dependencies (likely thumbnail) no longer exists!',
                            warningType: 'error',
                        },
                        '-1':{
                            text:'Server error!',
                            warningType: 'error',
                        }
                    }
                },
                'handleItemUpdate':{
                    apiResponses:true,
                    alertErrors:true,
                    valid:{
                        type: 'number',
                        value: 0
                    },
                    errorMap:{
                        '-2':{
                            text:'One of the dependencies (likely thumbnail) no longer exists!',
                            warningType: 'error',
                        },
                        '-1':{
                            text:'Server error!',
                        },
                        '1':{
                            text:'Article no longer exists!',
                        },
                        '2':{
                            text:'Server error!',
                        },
                        '3':{
                            text:'Server error!',
                        }
                    }
                }
            },
            //Editable article part
            mainItem:{
            },
            //Editable portion of the article
            paramMap:{
                articleId:{
                    ignore: this.mode === 'create',
                    title:'Article ID',
                    edit: false,
                    type: "number",
                    required:this.mode !== 'create',
                    onUpdate: {
                        ignore: true
                    }
                },
                title:{
                    title:'Article Title',
                    placeholder: "Creative Title Goes Here",
                    required: this.mode === 'create',
                    //What to do on item update
                    onUpdate: {
                        validate: function(item){
                            return item.length > 0 && item.length < 512;
                        },
                    },
                    pattern:'^.{1,512}$',
                    validateFailureMessage: 'Article title must be between 1 and 512 characters long!'
                },
                firstName:{
                    ignore: this.mode === 'create',
                    title:'First Name',
                    type: 'string',
                    edit: false,
                    onUpdate: {
                        ignore: true
                    },
                    parseOnGet: function(name){
                        if(name === null)
                            return ' - ';
                        else
                            return name;
                    }
                },
                lastName:{
                    ignore: this.mode === 'create',
                    title:'Last Name',
                    type: 'string',
                    edit: false,
                    onUpdate: {
                        ignore: true
                    },
                    parseOnGet: function(name){
                        if(name === null)
                            return ' - ';
                        else
                            return name;
                    }
                },
                articleAddress:{
                    title:'Article Address',
                    placeholder: "smallcase-letters-separated-like-this",
                    onUpdate: {
                        validate: function(item){
                            if(item === '')
                                return true;
                            let itemArr = item.split('-');
                            for(let i in itemArr){
                                if(!itemArr[i].match(/^[a-z0-9]{1,24}$/))
                                    return false;
                            }
                            return item.length > 0 && item.length < 128;
                        },
                        setName: 'address'
                    },
                    validateFailureMessage: `Address must be a sequence of low-case characters and numbers separated
                     by "-", each sequence no longer than 24 characters long`
                },
                'meta.subtitle':{
                    title:'Subtitle',
                    type:'textArea',
                    placeholder: "Will appear when people view the thumbnail",
                    onUpdate: {
                        validate: function(item){
                            return item.length < 512;
                        },
                        setName: 'subtitle'
                    },
                    replaceEmpty: '@',
                    pattern:'^.{0,512}$',
                    validateFailureMessage: `Thumbnail subtitle must be no longer than 128 characters`,
                },
                'meta.caption':{
                    title:'Caption',
                    type:'textArea',
                    placeholder: "May appear below the subtitle in some implementations",
                    pattern:'^.{0,1024}$',
                    onUpdate: {
                        validate: function(item){
                            return item.length < 1024;
                        },
                        replaceEmpty: '@',
                        setName: 'caption'
                    },
                    validateFailureMessage: `Caption must be no longer than 1024 characters`,
                },
                'meta.alt':{
                    title:'Thumbnail Alt',
                    placeholder: "ALT tag for - for SEO purposes",
                    onUpdate: {
                        validate: function(item){
                            return item.length < 128;
                        },
                        replaceEmpty: '@',
                        setName: 'alt'
                    },
                    pattern:'^.{0,128}$',
                    validateFailureMessage: `Thumbnail alt must be no longer than 128 characters`,
                },
                'meta.name':{
                    title:'Thumbnail Name',
                    placeholder: "Specific to some implementations",
                    onUpdate: {
                        validate: function(item){
                            return item.length < 128;
                        },
                        replaceEmpty: '@',
                        setName: 'name'
                    },
                    pattern:'^.{0,128}$',
                    validateFailureMessage: `Thumbnail name must be no longer than 128 characters`,
                },
                thumbnail:{
                    ignore:true,
                },
                blockOrder:{
                    ignore:true,
                },
                blocks:{
                    ignore:true,
                },
                articleAuth: {
                    title: 'Auth Level',
                    type: 'select',
                    list:{
                        0:'Public',
                        1:'Restricted',
                        2:'Private',
                        3:'Admin',
                    },
                    onUpdate: {
                        validate: function(item){
                            return item>=0 && item<4;
                        },
                        parse: function(item){
                            return item-0;
                        }
                    },
                    //Generally here to set a default for creation
                    parseOnGet: function(item){
                        if(item === null)
                            return 2;
                        else
                            return item-0;
                    },
                    validateFailureMessage: `Valid auth levels are 0 to 3`
                },
                language:{
                    title:'Language',
                    type: 'select',
                    list:function(){
                        let list = {
                            '':'Default'
                        };
                        for(let i in document.ioframe.languages){
                            list[document.ioframe.languages[i]] = document.ioframe.languages[i];
                        }
                        return list;
                    }(),
                    //Generally here to set a default for creation
                    parseOnGet: function(item){
                        if(item === null)
                            return '';
                        else
                            return item;
                    },
                    onUpdate:{
                        replaceEmpty: '@'
                    },
                    validateFailureMessage: `Valid auth levels are 0 to 3`
                },
                weight:{
                    title:'Weight',
                    ignore:!this.isAdmin,
                    placeholder: 'promotes "heavier" articles',
                    type: "number",
                    min:0,
                    max:999999,
                    onUpdate: {
                        validate: function(item){
                            return item>=0 && item<1000000;
                        },
                    },
                    //Generally here to set a default for creation
                    parseOnGet: function(item){
                        if(item === null)
                            return 0;
                        else
                            return item;
                    },
                    validateFailureMessage: `Valid weights are 0 to 999999`
                },
                creatorId:{
                    ignore: true,
                },
                created:{
                    ignore: this.mode === 'create',
                    title:'Created At',
                    type: 'string',
                    edit: false,
                    onUpdate: {
                        ignore: true
                    },
                    parseOnGet: timeStampToReadableFullDate
                },
                updated:{
                    ignore: this.mode === 'create',
                    title:'Updated At',
                    type: 'string',
                    edit: false,
                    onUpdate: {
                        ignore: true
                    },
                    parseOnGet: timeStampToReadableFullDate
                },
            },
            //Sometimes, you need to manially recompute Vue computed properties
            recompute:{
                changed:false,
                hasGhosts: false,
                paramMap: false,
                newTags:false
            },
            //Block opeions
            blockOptions:{
                allowModifying: this.allowModifying,
                allowAdding: this.allowModifying,
                allowMoving: this.allowModifying,
            },
            //tags
            tags: {
                obj:{},
                newTag:null
            },
            //blocks
            blocks:[],
            //Block creation index
            blockCreationIndex: 10000,
            //New block order - as opposed to article.blockOrder
            newOrder: '',
            //Needed during thumbnail selection
            mediaSelector: {
                open:false,
                selectMultiple:false,
                quickSelect:true,
                mode:null,
                selection:{}
            },
            //Whether we passed the control operations panel
            passedControls:false,
            //Whether we passed the headline start
            passedHeadline:false,
            //Whether the item is up to date
            initiated: false,
            //Whether we are currently initiating the item
            initiating: false,
            //Whether we are currently updating the item
            updating: false,
            //Whether we are currently updating the tags
            updatingTags: {add:false,remove:false}
        }
    },
    created:function(){
        //Register eventhub
        this.registerHub(eventHub);
        //Register events
        this.registerEvent('articleInfo' ,this.initiateArticle);
        this.registerEvent('setResponse' ,this.handleItemSet);
        this.registerEvent('newOrderResponse' ,this.handleNewOrder);
        this.registerEvent('cleanGhostsResponse' ,this.handleCleanGhosts);
        this.registerEvent('editor-media-selector-selection-event' ,this.thumbnailSelection);
        this.registerEvent('updateBlock' ,this.updateBlock);
        this.registerEvent('deleteBlock' ,this.deleteBlock);
        this.registerEvent('addedTagsResponse' ,this.handleTagsAdded);
        this.registerEvent('removedTagsResponse' ,this.handleTagsRemoved);

        if(!this.allowModifying)
            this.currentMode = 'view';

        if(Object.keys(this.existingItem).length)
            this.setArticleInfo(this.item);
        else if(this.currentMode !== 'create' && (this.articleId > 0 || this.articleAddress !== ''))
            this.getArticleInfo();
        else{
            this.initiating = true;
            this.setMainItem({});
            this.initiating = false;
            this.initiated = false;
        }
    },
    mounted:function(){
        //Add scroll listener
        window.addEventListener('scroll', this.checkOperationsMenu);
        window.addEventListener('resize', this.checkOperationsMenu);
    },
    updated: function(){
        if(this.currentMode !== 'create' && !this.initiated)
            this.getArticleInfo();
    },
    destroyed: function(){
        window.removeEventListener('scroll', this.checkOperationsMenu);
        window.removeEventListener('resize', this.checkOperationsMenu);
    },
    computed:{
        hasGhosts: function(){
            if(this.recompute.hasGhosts)
                ;//DO NOTHING
            for(let i in this.blocks){
                if(!this.blocks[i].exists)
                    return true;
            }
            return false;
        },
        orderChanged: function(){
            return this.article && (this.newOrder !== this.article.blockOrder);
        },
        changed: function(){
            if(this.recompute.changed)
                ;//Do nothing
            for(let i in this.mainItem){
                if(
                    this.mainItem[i] && this.mainItem[i].original !== undefined &&
                    ( (this.mainItem[i].original != this.mainItem[i].current) || this.paramMap[i].considerChanged)
                )
                    return true;
            }
            //If we changed the thumbnail, return true
            return this.articleThumbnail.changed;
        },
        tagsChanged: function(){
            if(this.recompute.newTags)
                ;//Do nothing
            for(let i in this.tags.obj)
                if(this.tags.obj[i].added || this.tags.obj[i].removed)
                    return true;
            return false;
        },
        anyNewTags: function(){
            if(this.recompute.newTags)
                ;//DO NOTHING
            let tags = JSON.parse(JSON.stringify(this.existingTagInfo.contents));
            delete tags['@'];
            for(let i in this.tags.obj){
                delete tags[i];
            }
            return Object.keys(tags).length;
        },
        allTags: function(){
            let tags = {};
            for(let i in this.existingTagInfo.contents){
                if(i === '@')
                    continue;
                tags[i] = this.existingTagInfo.contents[i];
            }
            return tags;
        },
    },
    methods:{
        //Scrolls to somewhere in the article. Default is -1 ('header'), otherwise - index of block. Above the maximum index defaults to last block.
        scrollTo(target = -1){
            let targetEl;
            if(target < 0)
                targetEl = this.currentMode === 'view' ?
                    this.$el.querySelector('.articles-editor > .wrapper > .main-article > .default-headline-renderer') :
                    this.$el.querySelector('.articles-editor > .wrapper > .article-info-editor');
            else {
                targetEl = this.$el.querySelectorAll('.articles-editor > .wrapper > .main-article > .article-block-container');
                targetEl = (target < this.blocks.length - 1)? targetEl[target] : targetEl[this.blocks.length - 1];
            }
            window.scrollTo({
                left: 0,
                top: targetEl.offsetTop,
                behavior: 'smooth'
            });
        },
        //Handles making operations menu sticky and non sticky
        checkOperationsMenu: function(){
            let headerStart= this.currentMode === 'view' ?
                this.$el.querySelector('.articles-editor > .wrapper > .main-article > .default-headline-renderer') :
                this.$el.querySelector('.articles-editor > .wrapper > .article-info-editor');
            if(!headerStart)
                return;
            let controlsDelta = window.scrollY - (this.currentMode === 'view' ? headerStart.offsetTop : (headerStart.clientHeight + headerStart.offsetTop));
            let headlineDelta =  window.scrollY -  (headerStart.clientHeight + headerStart.offsetTop) ;
            this.passedControls = (controlsDelta > 0);
            this.passedHeadline = (headlineDelta > 0);
        },
        //Cleans non-existing blocks
        cleanGhosts: function(){

            if(this.initiating){
                if(this.verbose)
                    console.log('Still getting item info!');
                return;
            }

            if(this.updating){
                if(this.verbose)
                    console.log('Still updating item!');
                return;
            }

            //Data to be sent
            let queryParams = {};
            if(this.test)
                queryParams.req = 'test';

            if(this.verbose)
                console.log('Cleaning article ghost blocks');

            this.updating = true;

            this.apiRequest(
                null,
                'api/v2/articles/'+this.articleId+'/blocks/clean',
                'cleanGhostsResponse',
                {
                    'method':'delete',
                    'queryParams':queryParams,
                    'verbose': this.verbose,
                    'parseJSON':true,
                    'identifier':this.identifier
                }
            );
        },
        //Sets new order as article order
        setOrder: function(){

            if(this.initiating){
                if(this.verbose)
                    console.log('Still getting item info!');
                return;
            }

            if(this.updating){
                if(this.verbose)
                    console.log('Still updating item!');
                return;
            }

            //Data to be sent
            let data = new FormData();
            let queryParams = {
                blockOrder: this.newOrder
            };
            if(this.test)
                data.append('req','test');

            if(this.verbose)
                console.log('Setting order ',this.newOrder);

            this.updating = true;

            this.apiRequest(
                data,
                'api/v2/articles/'+this.articleId,
                'newOrderResponse',
                {
                    'method':'put',
                    'queryParams':queryParams,
                    'verbose': this.verbose,
                    'parseJSON':true,
                    'identifier':this.identifier
                }
            );
        },
        //Resets order to what it was.
        resetOrder: function(){
            this.newOrder = this.article.blockOrder;
            let order = this.newOrder.split(',');
            let blocksMap = {};
            for(let i in this.blocks){
                if(blocksMap[this.blocks[i].blockId] === undefined)
                    blocksMap[this.blocks[i].blockId] = JSON.parse(JSON.stringify(this.blocks[i]));
            }
            this.blocks = [];
            for(let i in order){
                let block = blocksMap[order[i]];
                if(!block.meta || block.meta.length !== undefined)
                    block.meta = {};
                this.blocks.push(block);
            }
        },
        //Moves a block LOCALLY IN THE ORDER
        moveBlock: function(index, up){
            if(this.verbose)
              console.log('Moving block '+index+(up?' up':' down'));
            let newOrder = this.newOrder.split(',');
            let temp = newOrder[index];
            newOrder[index] = up? newOrder[index+1] : newOrder[index-1];
            if(up)
                newOrder[index+1] = temp;
            else
                newOrder[index-1] = temp;
            newOrder = newOrder.filter(x => x);
            Vue.set(this,'newOrder',newOrder.join(','));

            if(up){
                this.blocks.splice(index,2,this.blocks[index+1],this.blocks[index]);
            }
            else{
                this.blocks.splice(index-1,2,this.blocks[index],this.blocks[index-1]);
            }
        },
        //Deletes a specific block (or removes it from order)
        deleteBlock: function(request){
            if(this.verbose)
                console.log('Received deleteBlock',request);

            let existingBlockPrefix = this.identifier+'-block-';

            if((typeof request.from !== 'string') || request.from.indexOf(existingBlockPrefix) !== 0)
                return;

            let permanent = request.content.permanent;
            let key = permanent ? request.content.key : request.from.split('-').pop();
            let order = this.article.blockOrder?this.article.blockOrder.split(','):[];
            if(!permanent){
                if(this.verbose)
                    console.log('Removing block '+key+' from order.');
                delete order[key];
                order = order.filter(x => x);
                this.blocks.splice(key,1);
                Vue.set(this.article,'blockOrder',order.join(','));
            }
            else{
                if(this.verbose)
                    console.log('Deleting all blocks with id '+key);

                for(let i in this.blocks){
                    if(this.blocks[i].blockId === key){
                        this.blocks.splice(i-0,1);
                    }
                }
                for(let i in order){
                    if(order[i] === key)
                        delete order[i];
                }
                order = order.filter(x => x);
                Vue.set(this.article,'blockOrder',order.filter(x => x).join(','));
            }
        },
        //Updates a specific block with new info
        updateBlock: function(request){
            if(this.verbose)
                console.log('Received updateBlock',request);

            let existingBlockPrefix = this.identifier+'-block-';
            let newBlockPrefix = this.identifier+'-new-block-';

            if((typeof request.from !== 'string') || (request.from.indexOf(existingBlockPrefix) !== 0 && request.from.indexOf(newBlockPrefix) !== 0 ))
                return;

            let newPosition = (request.from.indexOf(newBlockPrefix) === 0) ? (request.from.split('-').pop() - 0) : -1;
            request = request.content;
            if(newPosition >= 0){
                if(this.verbose)
                    console.log('Pushing ',request.newBlock,' to '+newPosition);
                let order = this.article.blockOrder?this.article.blockOrder.split(','):[];
                order.splice(newPosition,0,request.newBlock.blockId);
                this.blocks.splice(newPosition,0,request.newBlock);
                Vue.set(this.article,'blockOrder',order.join(','));
                Vue.set(this,'newOrder',order.join(','));
                this.blockCreationIndex = this.blocks.length;
            }
            else{
                if(this.verbose)
                    console.log('Updating all blocks with id '+request.newBlock.blockId);
                for(let i in this.blocks){
                    if(this.blocks[i].blockId === request.newBlock.blockId){
                        Vue.set(this.blocks,i,request.newBlock);
                    }
                }
            }
        },

        //Initiates article
        initiateArticle: function(response){

            if(this.verbose)
                console.log('Received initiateArticle',response);

            if(this.identifier && (response.from !== this.identifier))
                return;

            this.initiating = false;
            this.initiated = true;
            this.setArticleInfo(response.content);
        },

        //Sets main item
        setArticleInfo(response){
            if(this.verbose)
                console.log('Setting article info  with ',response);
            if(typeof response !== 'object'){
                alertLog('Unknown response '+response,'error',this.$el);
                return;
            }
            switch(response.error){
                case 1:
                    alertLog('Article no longer exists!','error',this.$el);
                    break;
                case 'INPUT_VALIDATION_FAILURE':
                    alertLog('Unexpected error occurred!','error',this.$el);
                    break;
                case 'OBJECT_AUTHENTICATION_FAILURE':
                case 'AUTHENTICATION_FAILURE':
                    alertLog('Article view not authorized! Check if you are logged in.','error',this.$el);
                    break;
                case 'WRONG_CSRF_TOKEN':
                    alertLog('CSRF token wrong. Try refreshing the page if this continues.','warning',this.$el);
                    break;
                case 'SECURITY_FAILURE':
                    alertLog('Security related operation failure.','warning',this.$el);
                    break;
                default :
            }
            if(response.error)
                return;

            let article = response.article;
            //tags
            if(article.tags && article.tags.length){
                for (let i in article.tags)
                    Vue.set(this.tags.obj,article.tags[i],{original:true,removed:false,added:false});
            }

            //blocks
            let blocks = JSON.parse(JSON.stringify(article.blocks));
            this.blocks.splice(0,this.blocks.length);
            for(let i in blocks){
                if(!blocks[i].meta || blocks[i].meta.length !== undefined)
                    blocks[i].meta = {};
                this.blocks.push(blocks[i]);
            }
            this.blockCreationIndex = blocks.length;
            for(let i in article){
                Vue.set(this.article,i,JSON.parse(JSON.stringify(article[i])));
            }
            Vue.set(this,'articleId',JSON.parse(JSON.stringify(article['articleId'])));
            Vue.set(this,'articleAddress',JSON.parse(JSON.stringify(article['articleAddress'])));

            Vue.set(this.articleThumbnail,'original',article['thumbnail']);
            Vue.set(this.articleThumbnail,'current',article['thumbnail']);
            Vue.set(this.articleThumbnail,'changed',false);

            if(article['thumbnail'].address)
                Vue.set(this.articleThumbnail,'address',this.extractImageAddress(article['thumbnail']));

            if(!this.language && article.language)
                this.language = article.language;

            this.newOrder = this.article.blockOrder;
            this.setMainItem(JSON.parse(JSON.stringify(article)));
            this.resetInputs();
        },

        //Gets article by id
        getArticleInfo(){

            if(this.initiating){
                if(this.verbose)
                    console.log('Still getting item info!');
                return;
            }

            this.initiating = true;
            let queryParams = {};

            if(this.defaultAuth < 10000)
                queryParams.authAtMost = this.defaultAuth;

            this.apiRequest(
                null,
                'api/v2/articles/'+(this.articleId>0 ? this.articleId : this.articleAddress),
                'articleInfo',
                {
                    'method':'get',
                    'queryParams':queryParams,
                    'verbose': this.verbose,
                    'parseJSON':true,
                    'identifier':this.identifier,
                    ignoreCSRF:true
                }
            );
        },
        
        //Tries to update the item
        setItem: function(){

            if(this.initiating){
                if(this.verbose)
                    console.log('Still getting item info!');
                return;
            }

            if(this.updating){
                if(this.verbose)
                    console.log('Still updating item!');
                return;
            }

            //Data to be sent
            let data = new FormData();
            if(this.test)
                data.append('req','test');

            let sendParams = this['_setItemHelper']();
            if(this.articleThumbnail.changed){
                if(this.articleThumbnail.current.local)
                    sendParams.toSend.resourceAddressLocal = this.articleThumbnail.current.address;
                else if(!this.articleThumbnail.current.dataType)
                    sendParams.toSend.resourceAddressURI = this.articleThumbnail.current.address;
                else
                    sendParams.toSend.resourceAddressDB = this.articleThumbnail.current.address
            }

            if(Object.keys(sendParams.errors).length){
                for (let i in sendParams.errors)
                    alertLog(sendParams.errors[i].message,'warning',this.$el);
            }
            if(Object.keys(sendParams.toSend).length){
                for (let i in sendParams.toSend){
                    data.append(i, sendParams.toSend[i]);
                }
            }

            if(this.verbose)
                console.log('Setting item with parameters ',sendParams);

            this.updating = true;

            this.apiRequest(
                data,
                'api/v2/articles' + (this.currentMode === 'create'? '' : ('/'+this.articleId)),
                'setResponse',
                {
                    'method': this.currentMode === 'create' ? 'post': 'put',
                    'verbose': this.verbose,
                    'parseJSON':true,
                    'identifier':this.identifier
                }
            );
        },
        //Handles item  update
        handleItemSet: function(response){

            let eventResult = this.eventResponseParser(response,(this.currentMode === 'create')?'handleItemCreate':'handleItemUpdate',2);

            if(!eventResult)
                return;

            this.updating = false;

            if(eventResult.valid){
                if(this.currentMode === 'create'){
                    this.articleId = response.content.response;
                    this.paramMap.articleId.required = true;
                    this.paramMap.articleId.ignore = false;
                    this.paramMap.articleId.onUpdate.ignore = false;
                    this.initiated = false;
                    this.initiating = false;
                    this.currentMode = 'update';
                    eventHub.$emit('searchAgain');
                }
                else if(this.currentMode === 'update'){
                    alertLog('Article updated!','success',this.$el,{autoDismiss:2000});
                    this.initiating = false;
                    this.getArticleInfo();
                    eventHub.$emit('searchAgain');
                }
            }
        },
        handleTagsAdded:function(response){
            this['_handleTags'](response,'add');
        },
        handleTagsRemoved:function(response){
            this['_handleTags'](response,'remove');
        },

        _handleTags:function(response,type){

            if(this.verbose)
                console.log('Received tags response of type '+type,response);

            if(this.identifier && (response.from !== this.identifier))
                return;

            this.updatingTags[type] = false;

            response = response.content;
            if(typeof response !== 'object'){
                if(this.test)
                    alertLog(response,'info',this.$el);
                else
                    alertLog('Unknown Response','error',this.$el);
                return;
            }

            switch(response.error){
                case 'INPUT_VALIDATION_FAILURE':
                    alertLog('Input validation error!','error',this.$el);
                    return;
                case 'OBJECT_AUTHENTICATION_FAILURE':
                case 'AUTHENTICATION_FAILURE':
                    alertLog('Article view not authorized! Check if you are logged in.','error',this.$el);
                    return;
                case 'WRONG_CSRF_TOKEN':
                    alertLog('CSRF token wrong. Try refreshing the page if this continues.','warning',this.$el);
                    return;
                case 'SECURITY_FAILURE':
                    alertLog('Security related operation failure.','warning',this.$el);
                    return;
            }
            response = response.response;
            if(type === 'add'){
                if(typeof response !== 'object'){
                    if(this.test)
                        alertLog(response,'info',this.$el);
                    else{
                        alertLog('Invalid response: <br>'+response,'error',this.$el);
                    }
                    return;
                }

                for (let tagId in response){
                    let realId = tagId.split('/')[2];
                    let type = tagId.split('/')[1];

                    switch (response[tagId]-0) {
                        case -2:
                            alertLog('Tag (or article) no longer exists!','error',this.$el);
                            break;
                        case -1:
                            alertLog('Server error!','error',this.$el);
                            break;
                        case 0:
                            for(let i in this.tags.obj){
                                console.log(tagId,i);
                                if(i === type+'/'+realId){
                                    Vue.set(this.tags.obj[i],'added',false);
                                    Vue.set(this.tags.obj[i],'original',true);
                                }
                            }
                            eventHub.$emit('searchAgain');
                            this.recompute.newTags = !this.recompute.newTags;
                            break;
                        default:
                            alertLog('Unknown tag response '+response,'error',this.$el);
                            break;
                    }
                }

            }
            else {

                switch (response-0) {
                    case -1:
                        alertLog('Server error!','error',this.$el);
                        break;
                    case 0:
                        for(let i in this.tags.obj){
                            if(this.tags.obj[i].removed)
                                delete this.tags.obj[i];
                        }
                        eventHub.$emit('searchAgain');
                        this.recompute.newTags = !this.recompute.newTags;
                        break;
                    default:
                        alertLog('Unknown tags response '+response,this.test?'info':'error',this.$el);
                        break;
                }
            }
        },

        //Handles cleaning unexisting blocks
        handleCleanGhosts: function(response){

            if(this.verbose)
                console.log('Received handleCleanGhosts',response);

            if(this.identifier && (response.from !== this.identifier))
                return;

            this.updating = false;

            if(response.from)
                response = response.content;

            switch(response.error??null){
                case 'INPUT_VALIDATION_FAILURE':
                    alertLog('Input validation error!','error',this.$el);
                    return;
                case 'OBJECT_AUTHENTICATION_FAILURE':
                case 'AUTHENTICATION_FAILURE':
                    alertLog('Article view not authorized! Check if you are logged in.','error',this.$el);
                    return;
                case 'WRONG_CSRF_TOKEN':
                    alertLog('CSRF token wrong. Try refreshing the page if this continues.','warning',this.$el);
                    return;
                case 'SECURITY_FAILURE':
                    alertLog('Security related operation failure.','warning',this.$el);
                    return;
                default:
            }
            switch (response.response -0) {
                case -1:
                    alertLog('Server error!','error',this.$el);
                    break;
                case 0:
                    alertLog('Non-existant blocks removed!','success',this.$el,{autoDismiss:2000});
                    let removeIDs = [];
                    for(let i in this.blocks){
                        if(!this.blocks[i].exists){
                            removeIDs.push(this.blocks[i].blockId);
                            this.blocks.splice(i,1);
                        }
                    }
                    let order = this.article.blockOrder?this.article.blockOrder.split(','):[];
                    for(let i in order){
                        if(removeIDs.indexOf(order[i]) !== -1)
                            delete order[i];
                    }
                    order = order.filter(x => x);
                    Vue.set(this.article,'blockOrder',order.filter(x => x).join(','));
                    this.newOrder = this.article.blockOrder;
                    this.recompute.hasGhosts = !this.recompute.hasGhosts;
                    eventHub.$emit('searchAgain');
                    break;
                case 1:
                    alertLog('Article no longer exists!','error',this.$el);
                    break;
                default:
                    alertLog('Unknown response '+response,'error',this.$el);
                    break;
            }
        },

        //Handles new order set
        handleNewOrder: function(response){

            if(this.verbose)
                console.log('Received handleNewOrder',response);

            if(this.identifier && (response.from !== this.identifier))
                return;

            this.updating = false;

            if(response.from)
                response = response.content;

            switch(response.error??null){
                case 'INPUT_VALIDATION_FAILURE':
                    alertLog('Input validation error!','error',this.$el);
                    return;
                case 'OBJECT_AUTHENTICATION_FAILURE':
                case 'AUTHENTICATION_FAILURE':
                    alertLog('Article view not authorized! Check if you are logged in.','error',this.$el);
                    return;
                case 'WRONG_CSRF_TOKEN':
                    alertLog('CSRF token wrong. Try refreshing the page if this continues.','warning',this.$el);
                    return;
                case 'SECURITY_FAILURE':
                    alertLog('Security related operation failure.','warning',this.$el);
                    return;
                default:
            }
            switch (response.response - 0) {
                case -1:
                case 2:
                case 3:
                case -2:
                    alertLog('Server error!','error',this.$el);
                    break;
                case 0:
                    alertLog('Order updated!','success',this.$el,{autoDismiss:2000});
                    this.article.blockOrder = this.newOrder;
                    eventHub.$emit('searchAgain');
                    break;
                case 1:
                    alertLog('Article no longer exists!','error',this.$el);
                    break;
                default:
                    alertLog('Unknown response '+response,'error',this.$el);
                    break;
            }
        },
        
        //Resets inputs
        resetInputs: function(){
            for(let key in this.mainItem){
                if(this.mainItem[key] && this.mainItem[key]['original'] !== undefined){
                    this.mainItem[key]['current'] = this.mainItem[key]['original'];
                }
            }

            Vue.set(this.articleThumbnail,'current',this.articleThumbnail.original);
            this.articleThumbnail.address = this.extractImageAddress(this.articleThumbnail.original);
            this.articleThumbnail.changed = false;
            this.mediaSelector.open = false;

            this.recompute.changed = ! this.recompute.changed;
            this.$forceUpdate();
        },
        //Saves inputs as the actual data (in case of a successful update or whatnot)
        setInputsAsCurrent: function(){
            for(let key in this.mainItem){
                if(this.mainItem[key] && this.mainItem[key]['original'] !== undefined){
                    this.mainItem[key]['original'] = this.mainItem[key]['current'];
                }
            }
            this.recompute.changed = ! this.recompute.changed;
            this.$forceUpdate();
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

            Vue.set(this.mediaSelector,'open',false);
            Vue.set(this.articleThumbnail,'current',item);
            Vue.set(this.articleThumbnail,'changed',true);
            Vue.set(this.articleThumbnail,'address',this.extractImageAddress(item));
        },
        //Translates tag
        translateTag: function(tag){
            let tagInfo = this.existingTagInfo.contents[tag];
            if(!tagInfo)
                return tag;
            return tagInfo[this.language]??tagInfo['eng']??tag;
        },
        modifyTag: function(tag){
            if(this.tags.obj[tag].added)
                delete this.tags.obj[tag];
            else
                this.tags.obj[tag].removed = !this.tags.obj[tag].removed;
            this.recompute.newTags = !this.recompute.newTags;
        },
        addTag: function(){
            if(this.tags.newTag && !this.tags.obj[this.tags.newTag])
                Vue.set(this.tags.obj,this.tags.newTag,{original:false,removed:false,added:true});
            this.recompute.newTags = !this.recompute.newTags;
        },
        resetTags:function(){
            for(let i in this.tags.obj){
                let tag = this.tags.obj[i];
                if(tag.original)
                    Vue.set(this.tags.obj[i],'removed',false);
                else if(tag.added)
                    delete this.tags.obj[i];
            }
            this.recompute.newTags = !this.recompute.newTags;
        },
        saveTags:function(){

            if(this.updatingTags.add || this.updatingTags.remove){
                if(this.verbose)
                    console.log('Still updating tags!');
                return;
            }

            let tagsToAdd = [];
            let tagsToRemove = [];
            for(let tag in this.tags.obj){
                let tagInfo = {
                    type:tag.split('/')[0],
                    name:tag.split('/')[1],
                };
                if(tagInfo.type !== 'default-article-tags')
                    continue;
                if(this.tags.obj[tag].added)
                    tagsToAdd.push(tagInfo.name);
                else if(this.tags.obj[tag].removed)
                    tagsToRemove.push(tagInfo.name);
            }

            if(!tagsToAdd.length && !tagsToRemove.length)
                return;
            let toSend = {
                add:tagsToAdd,
                remove:tagsToRemove,
            };
            for(let i in toSend){
                if(!toSend[i].length)
                    continue;
                //Data to be sent
                let queryParams = {};
                let data = new FormData();
                queryParams.tags = toSend[i].join(',');
                if(this.test)
                    data.append('req','test');

                if(this.verbose)
                    console.log(i==='add'?'Adding tags ':'Removing Tags',toSend[i]);

                this.updatingTags[i] = true;

                this.apiRequest(
                    data,
                    'api/v2/articles/'+this.articleId+'/tags/default-article-tags',
                    i==='add'?'addedTagsResponse':'removedTagsResponse',
                    {
                        'queryParams':queryParams,
                        'method' : i==='add'? 'post' :'delete',
                        'verbose': this.verbose,
                        'parseJSON':true,
                        'identifier':this.identifier
                    }
                );
            }
        }
    },
    watch: {
        currentMode:function(newVal, oldVal){
            let relevantIdentifiers = ['articleId','created','updated','firstName','lastName'];
            if(oldVal === 'create'){
                for(let i in relevantIdentifiers){
                    this.paramMap[relevantIdentifiers[i]]['ignore'] = false;
                }
            }
            else if(newVal === 'create'){
                for(let i in relevantIdentifiers){
                    this.paramMap[relevantIdentifiers[i]]['ignore'] = true;
                }
            }
            this.recompute.changed = !this.recompute.changed;
        },
        itemIdentifier: function(newVal){
            this.articleId = newVal;
            this.article = {};
            this.blocks = [];
            if(this.currentMode !== 'create' && newVal > 0){
                this.initiated = false;
                this.initiating = false;
                this.getArticleInfo();
            }
        }
    },
    template: `
    <div class="articles-editor" :class="{initiating:initiating,initiated:initiated,updating:updating}">
        <div class="wrapper">

            <div class="types" v-if="currentMode !== 'create' && allowModifying">
                <button
                    v-if="index !== 'create'"
                    v-for="(item,index) in modes"
                    @click.prevent="currentMode = index"
                    v-text="item.title"
                    :class="{selected:(currentMode===index)}"
                    class="positive-3"
                    >
                </button>
            </div>

            
            <form class="article-info-editor" v-if="currentMode !== 'view' && (currentMode === 'create' || initiated)">

                <div class="thumbnail-preview" :class="{changed:articleThumbnail.changed}" @click.prevent="mediaSelector.open = true">
                    <img v-if="articleThumbnail.current.address"  :src="articleThumbnail.address" :alt="articleThumbnail.current.meta.alt? articleThumbnail.current.meta.alt : false">
                    <img v-else="" class="image-generic" :src="sourceURL()+'img/icons/image-generic.svg'">
                </div>

                <div v-if="initiated" class="tags" :class="{changed:tagsChanged}">
                    <span v-for="item,index in tags.obj" :class="['tag', {removed:item.removed, added:item.added}]">
                        <span class="title" v-text="translateTag(index)"></span>
                        <button 
                        v-text="item.removed?'+':'X'"
                        @click.prevent="modifyTag(index)"
                        ></button>
                    </span>
                    <span class="tag new" v-if="anyNewTags">
                        <button 
                        class="add" 
                        v-text="'+'"
                        @click.prevent="addTag()"
                        ></button>
                        <select v-model:value="tags.newTag">
                            <option v-for="item, index in allTags" v-if="!tags.obj[index]" :value="index" v-text="translateTag(index)"></option>
                        </select>
                    </span>
                    <div class="buttons" v-if="tagsChanged"> 
                        <button 
                        class="reset cancel-1" 
                        v-text="'Reset'"
                        @click.prevent="resetTags()"
                        ></button>
                        <button 
                        class="add positive-1" 
                        v-text="'Save'"
                        @click.prevent="saveTags()"
                        ></button>
                    </div>
                </div>

                <div
                v-for="(item, key) in mainItem"
                v-if="!paramMap[key].edit && !paramMap[key].ignore"
                :class="['static',key.replace('.','-')]"
                >

                    <span class="title" v-text="paramMap[key].title?? key"></span>

                    <div
                     v-if="paramMap[key].type !== 'boolean'"
                     class="item-param"
                     :type="paramMap[key].type"
                     v-text="item"
                    ></div>

                    <button
                    v-else-if="paramMap[key].type === 'boolean'"
                    class="item-param"
                    v-text="item?(paramMap[key].button.positive?? 'Yes'):(paramMap[key].button.negative?? 'No')"
                     ></button>

                </div>

                <div
                v-for="(item, key) in mainItem"
                v-if="paramMap[key].edit && !paramMap[key].ignore"
                :class="[{changed:(item.current !== item.original || paramMap[key].considerChanged)},key.replace('.','-'),{required:paramMap[key].required},paramMap[key].type]"
                >

                    <span class="title" v-text="paramMap[key].title?? key"></span>

                    <input
                     v-if="!paramMap[key].type || ['text','date','number','email'].indexOf(paramMap[key].type) !== -1"
                     class="item-param"
                     :type="paramMap[key].type"
                     :min="(['date','number'].indexOf(paramMap[key].type) !== -1) && (paramMap[key].min ?? false)"
                     :max="(['date','number'].indexOf(paramMap[key].type) !== -1) && (paramMap[key].max ?? false)"
                     :pattern="(['text'].indexOf(paramMap[key].type) !== -1) && (paramMap[key].pattern ?? false)"
                     :placeholder="paramMap[key].placeholder ?? false"
                     v-model:value="item.current"
                     @change="item.current = paramMap[key].parseOnChange($event.target.value);recompute.changed = ! recompute.changed"
                    >
                    <button
                    v-else-if="paramMap[key].type === 'boolean'"
                    class="item-param"
                    v-text="item.current?(paramMap[key].button.positive ?? 'Yes'):(paramMap[key].button.negative ?? 'No')"
                     @click.prevent="item.current = paramMap[key].parseOnChange(!item.current);recompute.changed = ! recompute.changed"
                     ></button>

                    <textarea
                    v-else-if="paramMap[key].type === 'textArea'"
                    class="item-param"
                     :placeholder="paramMap[key].placeholder ?? false"
                     v-model:value="item.current"
                     @change="item.current = paramMap[key].parseOnChange($event.target.value);recompute.changed = ! recompute.changed"
                     ></textarea>

                    <select
                    v-else-if="paramMap[key].type === 'select'"
                    class="item-param"
                     v-model:value="item.current"
                     @change="item.current = paramMap[key].parseOnChange($event.target.value);recompute.changed = ! recompute.changed"
                     >
                        <option v-for="(title,value) in paramMap[key].list" :value="value" v-text="title"></option>
                     </select>

                </div>

            </form>


            <div class="control-buttons" v-if="changed">
                <button  v-text="currentMode === 'create' ? 'Create Article' :'Update Article Info'" @click.prevent="setItem()" class="positive-1"></button>
                <button v-text="'Reset'" @click.prevent="resetInputs()" class="cancel-1"></button>
            </div>

            <div class="media-selector-container"  v-if="mediaSelector.open">
                <div class="control-buttons">
                    <img :src="sourceURL()+'img/icons/cancel-icon.svg'"  @click.prevent="mediaSelector.open = false">
                </div>

                <div
                     is="media-selector"
                     :identifier="identifier+'-media-selector'"
                     :test="test"
                     :verbose="verbose"
                ></div>
            </div>
            

            <article
            v-if="currentMode !== 'create'  && initiated"
             class="main-article"
             :class="['article-id-'+this.itemIdentifier,{'order-changed':orderChanged,'passed-controls':passedControls,'passed-headline':passedHeadline,'allow-modifying':allowModifying}]"
             >

                <div class="block-controls detach"">
                    <button class="positive-3" v-text="'Block Controls: '+(blockOptions.allowModifying? 'ON' : 'OFF')" @click.prevent="blockOptions.allowModifying = !blockOptions.allowModifying"></button>
                    <button class="positive-3" v-text="'Block Creation: '+(blockOptions.allowAdding? 'ON' : 'OFF')" @click.prevent="blockOptions.allowAdding = !blockOptions.allowAdding"></button>
                    <button class="positive-3" v-text="'Block Moving: '+(blockOptions.allowMoving? 'ON' : 'OFF')" @click.prevent="blockOptions.allowMoving = !blockOptions.allowMoving"></button>
                    <button v-if="hasGhosts" class="positive-1" v-text="'Clean non-existing blocks'" @click.prevent="cleanGhosts()"></button>
                    <button v-if="orderChanged" class="positive-1" v-text="'Save New Order'" @click.prevent="setOrder()"></button>
                    <button v-if="orderChanged" class="cancel-1" v-text="'Reset Order'" @click.prevent="resetOrder()"></button>
                </div>
                <div class="block-controls placeholder">
                </div>

                <header  v-if="(currentMode === 'view') && viewHeadline"
                is="default-headline-renderer"
                :article="article"
                :share-options="viewParams.defaultHeadlineRenderer.shareOptions !== undefined ? viewParams.defaultHeadlineRenderer.shareOptions : {}"
                :render-options="viewParams.defaultHeadlineRenderer.renderOptions !== undefined ? viewParams.defaultHeadlineRenderer.renderOptions : {}"
                :existing-tag-info="existingTagInfo"
                :language:="language"
                :identifier="identifier+'-headline-renderer'"
                :test="test"
                :verbose="verbose"
                ></header>

                <button class="positive-3 back-to-top" @click.prevent="scrollTo(-1)">
                </button>


                <div class="article-block-container" v-for="(item,index) in blocks" v-if="item.exists">

                    <p v-if="blockCreationIndex === index && blockOptions.allowAdding"
                       is="article-block-editor"
                       :article-id="articleId"
                       mode="create"
                       :index="index"
                       :identifier="identifier+'-new-block-'+index"
                       :test="test"
                       :verbose="verbose"
                    ></p>
                    <button v-else-if="blockOptions.allowAdding" class="add-block-here positive-1" @click.prevent="blockCreationIndex = index"></button>

                    <button
                    v-if="blockOptions.allowMoving && index > 0"
                    class="move up"
                    @click="moveBlock(index,false)"
                    ></button>

                    <p is="article-block-editor"
                       :article-id="articleId"
                       :key="index"
                       :index="index"
                       :block="item"
                       :allow-modifying="blockOptions.allowModifying"
                       mode="view"
                       :identifier="identifier+'-block-'+index"
                       :test="test"
                       :verbose="verbose"
                    ></p>

                    <button
                    v-if="blockOptions.allowMoving && index < (blocks.length - 1)"
                    class="move down"
                    @click="moveBlock(index,true)"
                    ></button>

                </div>
            

                <p v-if="blockCreationIndex >= blocks.length && blockOptions.allowAdding"
                   is="article-block-editor"
                   :article-id="articleId"
                   mode="create"
                   :index="blockCreationIndex"
                   :identifier="identifier+'-new-block-'+blockCreationIndex"
                   :test="test"
                   :verbose="verbose"
                ></p>
                <button v-else-if="blockOptions.allowAdding" class="add-block-here positive-1" @click.prevent="blockCreationIndex = blocks.length"></button>

                <p is="article-block-editor"
                   v-for="(item,index) in blocks"
                   v-if="!item.exists && (currentMode !== 'view' || isAdmin)"
                   class="non-existent"
                   v-text="'Block with id '+item.blockId+' no longer exists, but still belongs to the article!'"
                ></p>

            </article>

            <!-- Here will be lots of blocks -->

        </div>
    </div>
    `
});