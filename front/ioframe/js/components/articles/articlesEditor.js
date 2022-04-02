if(eventHub === undefined)
    var eventHub = new Vue();

Vue.component('articles-editor', {
    mixins: [sourceURL,eventHubManager,IOFrameCommons],
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
            //New article thumbnail
            articleThumbnail: {
                original:{},
                current:{},
                changed:false,
                address:'',
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
                        //bool, default false - do no send this key on update
                        ignore: this.mode === 'create'
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
                            return item.length < 128;
                        },
                        setName: 'subtitle'
                    },
                    pattern:'^.{0,128}$',
                    validateFailureMessage: `Thumbnail subtitle must be no longer than 128 characters`,
                },
                'meta.caption':{
                    title:'Caption',
                    type:'textArea',
                    placeholder: "May appear bellow the subtitle in some implementations",
                    pattern:'^.{0,1024}$',
                    onUpdate: {
                        validate: function(item){
                            return item.length < 1024;
                        },
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
                articleAuth:{
                    title:'Auth Level',
                    type: 'select',
                    list:[
                        {
                            title:'Public',
                            value:0,
                        },
                        {
                            title:'Restricted',
                            value:1,
                        },
                        {
                            title:'Private',
                            value:2,
                        },
                        {
                            title:'Admin',
                            value:3
                        },
                    ],
                    onUpdate: {
                        validate: function(item){
                            return item>=0 && item<4;
                        },
                    },
                    //Generally here to set a default for creation
                    parseOnGet: function(item){
                        if(item === null)
                            return 2;
                        else
                            return item;
                    },
                    validateFailureMessage: `Valid auth levels are 0 to 3`
                },
                language:{
                    title:'Language',
                    type: 'select',
                    list:function(){
                        let list = [
                            {
                                value:'',
                                title:'Default'
                            }
                        ];
                        for(let i in document.languages){
                            list.push({
                                value:document.languages[i],
                                title:document.languages[i]
                            });
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
                paramMap: false
            },
            //Block opeions
            blockOptions:{
                allowModifying: this.allowModifying,
                allowAdding: this.allowModifying,
                allowMoving: this.allowModifying,
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
           updating: false
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
            return this.article && this.newOrder != this.article.blockOrder;
        },
        changed: function(){
            if(this.recompute.changed)
                ;//Do nothing
            for(let i in this.mainItem){
                if(
                    this.mainItem[i] && this.mainItem[i].original !== undefined &&
                    (this.mainItem[i].original != this.mainItem[i].current || this.paramMap[i].considerChanged)
                )
                    return true;
            }
            //If we changed the thumbnail, return true
            if(this.articleThumbnail.changed)
                return true;

            return false;
        },
        itemHasInfo: function(){
            if(this.paramMap)
                ;//Do nothing

            for(let i in this.paramMap){
                if(!this.paramMap[i].edit && this.paramMap[i].display)
                    return true;
            }
            return false;
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
            let controlsDelta = window.pageYOffset - (this.currentMode === 'view' ? headerStart.offsetTop : (headerStart.clientHeight + headerStart.offsetTop));
            let headlineDelta =  window.pageYOffset -  (headerStart.clientHeight + headerStart.offsetTop) ;
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
            let data = new FormData();
            let sendParams = {
                action: 'cleanArticleBlocks',
                articleId: this.articleId
            };
            for(let i in sendParams){
                data.append(i,sendParams[i]);
            }
            if(this.test)
                data.append('req','test');

            if(this.verbose)
                console.log('Cleaning article ghost blocks');

            this.updating = true;

            this.apiRequest(
                data,
                'api/v1/articles',
                'cleanGhostsResponse',
                {
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
            let sendParams = {
                action: 'setArticle',
                articleId: this.articleId,
                create: false,
                blockOrder: this.newOrder
            };
            for(let i in sendParams){
                data.append(i,sendParams[i]);
            }
            if(this.test)
                data.append('req','test');

            if(this.verbose)
                console.log('Deleting item with parameters ',sendParams);

            this.updating = true;

            this.apiRequest(
                data,
                'api/v1/articles',
                'newOrderResponse',
                {
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
                    if(this.blocks[i].blockId == key){
                        this.blocks.splice(i,1);
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
                    if(this.blocks[i].blockId == request.newBlock.blockId){
                        Vue.set(this.blocks,i,request.newBlock);
                    }
                }
            }
        },
        //Extracts the image address from an image object
        extractImageAddress: function(item){
            if(this.verbose)
                console.log('Extracting address from ',item);
            let trueAddress = item.address;
            if(item.local)
                trueAddress = document.rootURI + document.imagePathLocal+trueAddress;
            else if(item.dataType)
                trueAddress = document.rootURI+'api/media?action=getDBMedia&address='+trueAddress+'&lastChanged='+item.updated;
            return trueAddress;
        },
        //Sets main item
        setMainItem(item){

            for(let i in item){
                if(typeof item[i] === 'object')
                    continue;

                this.setSingleParam(i);

                if(!this.paramMap[i].ignore)
                    this.mainItem[i] =
                        this.paramMap[i].edit ?
                        {
                            original:this.paramMap[i].parseOnGet(item[i]),
                            current:this.paramMap[i].parseOnGet(item[i])
                        }
                            :
                            this.paramMap[i].parseOnGet(item[i]);
            }

            for(let i in this.paramMap){
                if((item[i] === undefined || typeof item[i] === 'object') && !this.paramMap[i].ignore){
                    this.setSingleParam(i);
                    let prefixes = i.split('.');
                    let target = JSON.parse(JSON.stringify(item));
                    let j = 0;
                    while(target !== undefined && typeof target === 'object' && prefixes[j] && target[prefixes[j]]!== undefined){
                        target = target[prefixes[j++]];
                    }
                    let newItem = (target !== undefined && typeof target !== 'object')? target : null;
                    this.setSingleMainItem(i,newItem);
                }
            }

            this.initiated = true;
        },
        //Helper function for setMainItem
        setSingleParam: function(i){
            if(!this.paramMap[i])
                this.paramMap[i] ={};
            this.paramMap[i].ignore = this.paramMap[i].ignore !== undefined ? this.paramMap[i].ignore : false;
            this.paramMap[i].title = this.paramMap[i].title !== undefined ? this.paramMap[i].title : i;
            this.paramMap[i].edit = this.paramMap[i].edit !== undefined ? this.paramMap[i].edit: true;
            this.paramMap[i].type = this.paramMap[i].type !== undefined ? this.paramMap[i].type : "text";
            this.paramMap[i].display = this.paramMap[i].display !== undefined ?  this.paramMap[i].display: true;
            this.paramMap[i].considerChanged = this.paramMap[i].considerChanged !== undefined ?  this.paramMap[i].considerChanged: false;
            this.paramMap[i].required = this.paramMap[i].required !== undefined ?  this.paramMap[i].required: false;

            if(!this.paramMap[i].onUpdate)
                this.paramMap[i].onUpdate = {};
            this.paramMap[i].onUpdate.ignore = this.paramMap[i].onUpdate.ignore !== undefined ? this.paramMap[i].onUpdate.ignore : false;
            this.paramMap[i].onUpdate.parse = this.paramMap[i].onUpdate.parse !== undefined ? this.paramMap[i].onUpdate.parse : function(value){
                return value;
            };
            this.paramMap[i].onUpdate.validate = this.paramMap[i].onUpdate.validate !== undefined ? this.paramMap[i].onUpdate.validate : function(value){
                return true;
            };
            this.paramMap[i].onUpdate.validateFailureMessage = this.paramMap[i].onUpdate.validateFailureMessage !== undefined ? this.paramMap[i].onUpdate.validateFailureMessage : 'Parameter '+i+' failed validation!';
            this.paramMap[i].onUpdate.setName = this.paramMap[i].onUpdate.setName !== undefined ? this.paramMap[i].onUpdate.setName : i;

            this.paramMap[i].parseOnGet = this.paramMap[i].parseOnGet !== undefined ? this.paramMap[i].parseOnGet : function(value){
                return value;
            };
            this.paramMap[i].parseOnDisplay = this.paramMap[i].parseOnDisplay !== undefined ? this.paramMap[i].parseOnDisplay : function(value){
                return value;
            };
            this.paramMap[i].parseOnChange = this.paramMap[i].parseOnChange !== undefined ? this.paramMap[i].parseOnChange : function(value){
                return value;
            };
            this.paramMap[i].displayHTML = this.paramMap[i].displayHTML !== undefined ? this.paramMap[i].displayHTML : false;

            if(!this.paramMap[i].button)
                this.paramMap[i].button = {};
            this.paramMap[i].button.positive = this.paramMap[i].button.positive !== undefined ? this.paramMap[i].button.positive : 'Yes';
            this.paramMap[i].button.negative = this.paramMap[i].button.negative !== undefined ? this.paramMap[i].button.negative : 'No';
        },
        //Helper functin for setMainItem
        setSingleMainItem: function(i, item){
            this.mainItem[i] =
                this.paramMap[i].edit ?
                {
                    original:this.paramMap[i].parseOnGet(item),
                    current:this.paramMap[i].parseOnGet(item)
                }
                    :
                    this.paramMap[i].parseOnGet(item);
        },

        //Initiates article
        initiateArticle: function(response){

            if(this.verbose)
                console.log('Received initiateArticle',response);

            if(this.identifier && (response.from !== this.identifier))
                return;

            this.setArticleInfo(response.content);
        },

        //Sets main item
        setArticleInfo(article){
            if(this.verbose)
                console.log('Setting article info  with ',article);
            if(typeof article !== 'object'){
                switch(article){
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
                        alertLog('Error initiating article, unknown response '+article,'error',this.$el);
                }
                return;
            }
            this.initiating = false;
            let blocks = JSON.parse(JSON.stringify(article.blocks));
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
            let data = new FormData();
            data.append('action', 'getArticle');
            if(this.articleId>0)
                data.append('id', this.articleId);
            else
                data.append('articleAddress', this.articleAddress);
            if(this.defaultAuth < 10000)
            data.append('authAtMost', this.defaultAuth);

            this.apiRequest(
                data,
                'api/v1/articles',
                'articleInfo',
                {
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
            data.append('action', 'setArticle');
            data.append('create', this.currentMode === 'create' ? true : false);
            if(this.test)
                data.append('req','test');

            let sendParams = {};

            for(let paramName in this.paramMap){

                let param = this.paramMap[paramName];
                let item = this.mainItem[paramName];

                if(
                    param.ignore ||
                    param.onUpdate.ignore ||
                    (item.current !== undefined && item.current === item.original && !param.considerChanged && !param.required)
                )
                    continue;
                else if(item.current === undefined){
                    data.append(param.onUpdate.setName, item);
                    sendParams[param.onUpdate.setName] = item;
                    continue;
                }

                if(param.required && item.current === null){
                    let title = param.title? param.title : paramName;
                    alertLog(title+' must be set!','warning',this.$el);
                    return;
                }

                let paramValue = param.onUpdate.parse(item.current);

                if(!param.onUpdate.validate(paramValue)){
                    alertLog(param.onUpdate.validateFailureMessage,'warning',this.$el);
                    return;
                }

                //Meta params are sent as '@' instead of "null", to signify their deletion
                if( ( (paramName.substr(0,4) === 'meta') || (paramName === 'language') ) && (paramValue === '') )
                    paramValue = '@';

                data.append(param.onUpdate.setName, paramValue);
                sendParams[param.onUpdate.setName] = paramValue;
            }

            if(this.articleThumbnail.changed){
                data.append('thumbnailAddress',  this.articleThumbnail.current.address);
                sendParams['thumbnailAddress'] = this.articleThumbnail.current.address;
            }

            if(this.verbose)
                console.log('Setting item with parameters ',sendParams);

            this.updating = true;

            this.apiRequest(
                data,
                'api/v1/articles',
                'setResponse',
                {
                    'verbose': this.verbose,
                    'parseJSON':true,
                    'identifier':this.identifier
                }
            );
        },
        //Handles item  update
        handleItemSet: function(response){

            if(this.verbose)
                console.log('Received handleItemSet',response);

            if(this.identifier && (response.from !== this.identifier))
                return;

            this.updating = false;

            if(response.from)
                response = response.content;

            switch(response){
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

            if(this.currentMode === 'create')
                switch (response) {
                    case -3:
                        alertLog('Missing inputs when creating article!','error',this.$el);
                        break;
                    case -2:
                        alertLog('One of the dependencies (likely thumbnail) no longer exists!','error',this.$el);
                        break;
                    case -1:
                        alertLog('Server error!','error',this.$el);
                        break;
                    default:
                        if(typeof response === 'number' || (typeof  response === 'string' && response.match(/^\d+$/))){
                            this.articleId = response - 0;
                            this.initiated = false;
                            this.initiating = false;
                            this.currentMode = 'update';
                            eventHub.$emit('searchAgain');
                        }
                        else
                            alertLog('Unknown response '+response,'error',this.$el);
                }
            else if(this.currentMode === 'update')
                switch (response) {
                    case -2:
                        alertLog('One of the dependencies (likely thumbnail) no longer exists!','error',this.$el);
                        break;
                    case -1:
                    case 2:
                    case 3:
                        alertLog('Server error!','error',this.$el);
                        break;
                    case 0:
                        alertLog('Article updated!','success',this.$el);
                        this.initiating = false;
                        this.getArticleInfo();
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

        //Handles cleaning unexisting blocks
        handleCleanGhosts: function(response){

            if(this.verbose)
                console.log('Received handleCleanGhosts',response);

            if(this.identifier && (response.from !== this.identifier))
                return;

            this.updating = false;

            if(response.from)
                response = response.content;

            switch(response){
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
            switch (response) {
                case -1:
                    alertLog('Server error!','error',this.$el);
                    break;
                case 0:
                    alertLog('Non-existant blocks removed!','success',this.$el);
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

            switch(response){
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
            switch (response) {
                case -1:
                case 2:
                case 3:
                case -2:
                    alertLog('Server error!','error',this.$el);
                    break;
                case 0:
                    alertLog('Order updated!','success',this.$el);
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
                    <img v-else="" :src="sourceURL()+'img/icons/image-generic.svg'">
                </div>

                <div
                v-for="(item, key) in mainItem"
                v-if="!paramMap[key].edit && !paramMap[key].ignore"
                :class="['static',key.replace('.','-')]"
                >

                    <span class="title" v-text="paramMap[key].title? paramMap[key].title : key"></span>

                    <div
                     v-if="paramMap[key].type !== 'boolean'"
                     class="item-param"
                     :type="paramMap[key].type"
                     v-text="item"
                    ></div>

                    <button
                    v-else-if="paramMap[key].type === 'boolean'"
                    class="item-param"
                    v-text="item?(paramMap[key].button.positive? paramMap[key].button.positive : 'Yes'):(paramMap[key].button.negative? paramMap[key].button.negative : 'No')"
                     ></button>

                </div>
            
                <div
                v-for="(item, key) in mainItem"
                v-if="paramMap[key].edit && !paramMap[key].ignore"
                :class="[{changed:(item.current !== item.original || paramMap[key].considerChanged)},key.replace('.','-'),{required:paramMap[key].required},paramMap[key].type]"
                >

                    <span class="title" v-text="paramMap[key].title? paramMap[key].title : key"></span>

                    <input
                     v-if="!paramMap[key].type || ['text','date','number','email'].indexOf(paramMap[key].type) !== -1"
                     class="item-param"
                     :type="paramMap[key].type"
                     :min="(['date','number'].indexOf(paramMap[key].type) !== -1) && (paramMap[key].min !== undefined) ? paramMap[key].min : false"
                     :max="(['date','number'].indexOf(paramMap[key].type) !== -1) && (paramMap[key].max !== undefined)  ? paramMap[key].max : false"
                     :pattern="(['text'].indexOf(paramMap[key].type) !== -1) && (paramMap[key].pattern !== undefined)  ? paramMap[key].pattern : false"
                     :placeholder="paramMap[key].placeholder !== undefined  ? paramMap[key].placeholder : false"
                     v-model:value="item.current"
                     @change="item.current = paramMap[key].parseOnChange($event.target.value);recompute.changed = ! recompute.changed"
                    >
                    <button
                    v-else-if="paramMap[key].type === 'boolean'"
                    class="item-param"
                    v-text="item.current?(paramMap[key].button.positive? paramMap[key].button.positive : 'Yes'):(paramMap[key].button.negative? paramMap[key].button.negative : 'No')"
                     @click.prevent="item.current = paramMap[key].parseOnChange(!item.current);recompute.changed = ! recompute.changed"
                     ></button>

                    <textarea
                    v-else-if="paramMap[key].type === 'textArea'"
                    class="item-param"
                     :placeholder="paramMap[key].placeholder !== undefined  ? paramMap[key].placeholder : false"
                     v-model:value="item.current"
                     @change="item.current = paramMap[key].parseOnChange($event.target.value);recompute.changed = ! recompute.changed"
                     ></textarea>

                    <select
                    v-else-if="paramMap[key].type === 'select'"
                    class="item-param"
                     v-model:value="item.current"
                     @change="item.current = paramMap[key].parseOnChange($event.target.value);recompute.changed = ! recompute.changed"
                     >
                        <option v-for="listItem in paramMap[key].list" :value="listItem.value" v-text="listItem.title? listItem.title: listItem.value"></option>
                     </select>

                </div>

            </form>


            <div class="control-buttons" v-if="changed">
                <button  v-text="currentMode === 'create' ? 'Create Article' :'Update Article Info'" @click.prevent="setItem()" class="positive-1"></button>
                <button v-text="'Reset'" @click.prevent="resetInputs()" class="cancel-1"></button>
            </div>

            <div class="media-selector-container"  v-if="mediaSelector.open">
                <div class="control-buttons">
                    <img :src="sourceURL()+'img/icons/close-red.svg'"  @click.prevent="mediaSelector.open = false">
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

                <header  v-if="currentMode === 'view' && viewHeadline"
                is="default-headline-renderer"
                :article="article"
                :share-options="viewParams.defaultHeadlineRenderer.shareOptions !== undefined ? viewParams.defaultHeadlineRenderer.shareOptions : {}"
                :render-options="viewParams.defaultHeadlineRenderer.renderOptions !== undefined ? viewParams.defaultHeadlineRenderer.renderOptions : {}"
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