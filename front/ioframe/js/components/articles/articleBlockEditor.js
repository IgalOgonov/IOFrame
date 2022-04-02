if(eventHub === undefined)
    var eventHub = new Vue();

Vue.component('article-block-editor', {
    mixins: [componentSize,sourceURL,eventHubManager,IOFrameCommons],
    props: {
        //Block Original block
        block:{
            type: Object,
            default: function(){
                return {};
            }
        },
        //Starting mode - create or update
        mode: {
            type: String,
            default: 'view' //'create' / 'update' / 'view'
        },
        //Whether to allow block modification
        allowModifying: {
            type: Boolean,
            default: false
        },
        //Whether to allow changing the block type
        allowBlockTypeModifying: {
            type: Boolean,
            default: false
        },
        //An object of options, unique to each supported block type
        options: {
            type: Object,
            default: function(){
                return {
                    /** For example:
                     *
                     * */
                }
            }
        },
        //Article identifier - used for creation
        articleId: {
            type: Number,
            default: -1
        },
        //Index - used to identify the block to the parent
        index: {
            type: Number,
            default: -1
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
            currentBlock: JSON.parse(JSON.stringify(this.block)),
            //Editable block part
            mainItem:{
            },
            //Editable portion of the block
            paramMap:{
                exists:{
                    ignore: true
                },
                orphan:{
                    ignore: true
                },
                articleId:{
                    title:'Article ID',
                    edit: false,
                    type: "number",
                    required:true,
                    onUpdate: {
                    }
                },
                blockId:{
                    title:'Block ID',
                    ignore: true,
                    edit: false,
                    type: "number",
                    required:true,
                    onUpdate: {
                        //bool, default false - do no send this key on update
                        ignore: true
                    }
                },
                type:{
                    title:'Block Type',
                    type: 'select',
                    list:[
                        {
                            title:'Markdown',
                            value:'markdown',
                        },
                        {
                            title:'Image',
                            value:'image',
                        },
                        {
                            title:'Cover',
                            value:'cover',
                        },
                        {
                            title:'Video',
                            value:'video',
                        },
                        {
                            title:'Youtube',
                            value:'youtube',
                        },
                        {
                            title:'Gallery',
                            value:'gallery'
                        },
                        {
                            title:'Article',
                            value:'article'
                        },
                    ],
                    edit: true,
                    required:true
                },
                orderIndex:{
                    ignore: true,
                    title:'Index in Article',
                    type: 'number',
                    edit: false,
                    required:true
                },
                text:{
                    ignore: true,
                    title: '',
                    type: '',
                    required: true,
                    pattern:'',
                    onUpdate: {
                        encodeURI:true,
                        validate:
                                function(){
                                    return true
                                }
                    },
                    parseOnChange:function(newVal,currentBlock){
                        currentBlock.text = newVal;
                        return newVal;
                    },
                    validateFailureMessage: `Invalid youtube identifeir!`,
                },
                'resource.address':{
                    ignore: true,
                    title: '',
                    type: 'text',
                    edit: false,
                    onUpdate: {
                        ignore: true,
                    }
                },
                'collection.name':{
                    ignore: true,
                    title: 'Gallery Name',
                    type: 'text',
                    edit: false,
                    onUpdate: {
                        ignore: true,
                    }
                },
                'otherArticle.id':{
                    ignore: true,
                    title: 'Other Article ID',
                    type: 'text',
                    edit: false,
                    onUpdate: {
                        ignore: true,
                    }
                },
                'meta.alt':{
                    ignore: true,
                    title: 'Image Alt',
                    type: 'text',
                    onUpdate: {
                        setName: 'alt'
                    },
                    parseOnChange:function(newVal,currentBlock){
                        if(currentBlock.meta === undefined)
                            currentBlock.meta = {};
                        currentBlock.meta.alt = newVal;
                        return newVal;
                    }
                },
                'meta.name':{
                    ignore: true,
                    title: 'Name',
                    type: 'text',
                    onUpdate: {
                        setName: 'name'
                    },
                    parseOnChange:function(newVal,currentBlock){
                        if(currentBlock.meta === undefined)
                            currentBlock.meta = {};
                        currentBlock.meta.name = newVal;
                        return newVal;
                    }
                },
                'meta.caption':{
                    ignore: true,
                    title: 'Caption',
                    type: 'text',
                    onUpdate: {
                        setName: 'caption'
                    },
                    parseOnChange:function(newVal,currentBlock){
                        if(currentBlock.meta === undefined)
                            currentBlock.meta = {};
                        currentBlock.meta.caption = newVal;
                        return newVal;
                    }
                },
                'meta.embed':{
                    ignore: true,
                    title: 'Embed?',
                    type: 'boolean',
                    onUpdate: {
                        setName: 'embed'
                    },
                    parseOnGet: function(item){
                        return item === undefined || item === null ? false : item;
                    },
                    parseOnChange:function(newVal,currentBlock){
                        if(currentBlock.meta === undefined)
                            currentBlock.meta = {};
                        currentBlock.meta.embed = newVal;
                        return newVal;
                    }
                },
                'meta.height':{
                    ignore: true,
                    title: 'Video Height',
                    type: 'number',
                    onUpdate: {
                        setName: 'height'
                    },
                    parseOnChange:function(newVal,currentBlock){
                        if(currentBlock.meta === undefined)
                            currentBlock.meta = {};
                        currentBlock.meta.height = newVal;
                        return newVal;
                    }
                },
                'meta.width':{
                    ignore: true,
                    title: 'Video Width',
                    type: 'number',
                    onUpdate: {
                        setName: 'width'
                    },
                    parseOnChange:function(newVal,currentBlock){
                        if(currentBlock.meta === undefined)
                            currentBlock.meta = {};
                        currentBlock.meta.width = newVal;
                        return newVal;
                    }
                },
                'meta.mute':{
                    ignore: true,
                    title: 'Mute?',
                    type: 'boolean',
                    onUpdate: {
                        setName: 'mute'
                    },
                    parseOnGet: function(item){
                        return item === undefined || item === null ? false : item;
                    },
                    parseOnChange:function(newVal,currentBlock){
                        if(currentBlock.meta === undefined)
                            currentBlock.meta = {};
                        currentBlock.meta.mute = newVal;
                        return newVal;
                    }
                },
                'meta.autoplay':{
                    ignore: true,
                    title: 'Autoplay?',
                    type: 'boolean',
                    onUpdate: {
                        setName: 'autoplay'
                    },
                    parseOnGet: function(item){
                        return item === undefined || item === null ? false : item;
                    },
                    parseOnChange:function(newVal,currentBlock){
                        if(currentBlock.meta === undefined)
                            currentBlock.meta = {};
                        currentBlock.meta.autoplay = newVal;
                        return newVal;
                    }
                },
                'meta.controls':{
                    ignore: true,
                    title: 'Show Controls?',
                    type: 'boolean',
                    onUpdate: {
                        setName: 'controls'
                    },
                    parseOnGet: function(item){
                        return item === undefined || item === null ? false : item;
                    },
                    parseOnChange:function(newVal,currentBlock){
                        if(currentBlock.meta === undefined)
                            currentBlock.meta = {};
                        currentBlock.meta.autoplay = newVal;
                        return newVal;
                    }
                },
                'meta.loop':{
                    ignore: true,
                    title: 'Loop?',
                    type: 'boolean',
                    onUpdate: {
                        setName: 'loop'
                    },
                    parseOnGet: function(item){return item},
                    parseOnChange:function(newVal,currentBlock){
                        if(currentBlock.meta === undefined)
                            currentBlock.meta = {};
                        currentBlock.meta.loop = newVal;
                        return newVal;
                    }
                },
                'meta.center':{
                    ignore: true,
                    title: 'Keep Gallery Centered?',
                    type: 'boolean',
                    onUpdate: {
                        setName: 'center'
                    },
                    parseOnGet: function(item){
                        return item === undefined || item === null ? false : item;
                    },
                    parseOnChange:function(newVal,currentBlock){
                        if(currentBlock.meta === undefined)
                            currentBlock.meta = {};
                        currentBlock.meta.center = newVal;
                        return newVal;
                    }
                },
                'meta.preview':{
                    ignore: true,
                    title: 'Gallery Preview?',
                    type: 'boolean',
                    onUpdate: {
                        setName: 'preview'
                    },
                    parseOnGet: function(item){
                        return item === undefined || item === null ? true : item;
                    },
                    parseOnChange:function(newVal,currentBlock){
                        if(currentBlock.meta === undefined)
                            currentBlock.meta = {};
                        currentBlock.meta.preview = newVal;
                        return newVal;
                    }
                },
                'meta.slider':{
                    ignore: true,
                    title: 'Bottom Slider?',
                    type: 'boolean',
                    onUpdate: {
                        setName: 'slider'
                    },
                    parseOnGet: function(item){
                        return item === undefined || item === null ? true : item;
                    },
                    parseOnChange:function(newVal,currentBlock){
                        if(currentBlock.meta === undefined)
                            currentBlock.meta = {};
                        currentBlock.meta.slider = newVal;
                        return newVal;
                    }
                },
                'meta.fullScreenOnClick':{
                    ignore: true,
                    title: 'Full Screen Images On Click?',
                    type: 'boolean',
                    onUpdate: {
                        setName: 'fullScreenOnClick'
                    },
                    parseOnGet: function(item){
                        return item === undefined || item === null ? false : item;
                    },
                    parseOnChange:function(newVal,currentBlock){
                        if(currentBlock.meta === undefined)
                            currentBlock.meta = {};
                        currentBlock.meta.fullScreenOnClick = newVal;
                        return newVal;
                    }
                },
                created:{
                    ignore: true,
                    title:'Created At',
                    type: 'text',
                    edit: false,
                    onUpdate: {
                        ignore: true
                    },
                    parseOnGet: timeStampToReadableFullDate
                },
                updated:{
                    ignore: true,
                    title:'Updated At',
                    type: 'text',
                    edit: false,
                    onUpdate: {
                        ignore: true
                    },
                    parseOnGet: timeStampToReadableFullDate
                },
            },
            currentOptions:{
                'markdown':{
                    checkedChanges: false,
                    render: function(context = this){
                        // Set options
                        marked.setOptions({
                            renderer: new marked.Renderer(),
                            highlight: function(code) {
                                return code;
                            },
                            smartLists: true
                        });
                        return  marked(context.currentBlock.text);
                    }
                },
                'image':{
                    checkedChanges: false,
                    render: function(context = this){
                        let img = '<img src="'+context.extractMediaAddress(context.currentBlock.resource)+'"';
                        let meta = context.currentBlock.meta;
                        let resourceMeta = context.currentBlock.resource.meta;
                        if(meta.alt)
                            img += 'alt ="'+meta.alt+'"';
                        else if(resourceMeta.alt)
                            img += 'alt ="'+resourceMeta.alt+'"';
                        img += '>';
                        let caption = meta.caption? '<figcaption>'+meta.caption+'</figcaption>' : '';
                        return '<div class="image-container">'+img+'</div>'+caption;
                    }
                },
                'cover':{
                    checkedChanges: false,
                    render: function(context = this){
                        let meta = context.currentBlock.meta;
                        let div = '<div style="background-image:url(\''+context.extractMediaAddress(context.currentBlock.resource)+'\');';

                        //TODO - all of the following are still not implemented at the backend
                        if(meta.fixed === undefined || meta.fixed)
                            div += 'background-attachment:fixed;';

                        if(meta.size !==undefined)
                            div += 'background-size:'+meta.size+';';
                        else
                            div += 'background-size:cover;';

                        if(meta.repeat ===undefined || meta.repeat)
                            div += 'background-repeat:no-repeat;';

                        if(meta.position !==undefined)
                            div += 'background-position:'+meta.position+';';
                        else
                            div += 'background-position:initial;';

                        if(meta.clip !==undefined)
                            div += 'background-clip:'+meta.clip+';';

                        div += '">';
                        div += meta.caption? '<figcaption>'+meta.caption+'</figcaption>' : '';
                        div += '</div>';
                        return div;
                    }
                },
                'gallery':{
                    checkedChanges: false,
                    render: function(context = this){
                        return '';
                    },
                    displayNumberArray: [
                        {
                            width:900,
                            number:4
                        },
                        {
                            width:700,
                            number:3
                        },
                        {
                            width:500,
                            number: 2
                        },
                        {
                            width:0,
                            number:1
                        },
                    ]
                },
                'video':{
                    checkedChanges: false,
                    render: function(context = this){
                        let vid = '<video src="'+context.extractMediaAddress(context.currentBlock.resource)+'"';
                        let meta = context.currentBlock.meta;
                        let resourceMeta = context.currentBlock.resource.meta;

                        if(typeof meta.loop !== 'undefined')
                            vid += 'loop ="'+(meta.loop? '1' : '0')+'"';
                        else if(typeof resourceMeta.loop !== 'undefined')
                            vid += 'loop ="'+(resourceMeta.loop? '1' : '0')+'"';

                        if(typeof meta.mute !== 'undefined')
                            vid += 'muted ="'+(meta.mute? '1' : '0')+'"';
                        else if(typeof resourceMeta.mute !== 'undefined')
                            vid += 'muted ="'+(resourceMeta.mute? '1' : '0')+'"';

                        if(typeof meta.controls !== 'undefined')
                            vid += (meta.controls? 'controls ="1"' : '');
                        else if(typeof resourceMeta.controls !== 'undefined')
                            vid += (resourceMeta.controls? 'controls ="1"' : '');

                        if(typeof meta.autoplay !== 'undefined')
                            vid += 'autoplay ="'+meta.autoplay+'"';
                        else if(typeof resourceMeta.autoplay !== 'undefined')
                            vid += 'autoplay ="'+resourceMeta.autoplay+'"';

                        if(typeof meta.width !== 'undefined')
                            vid += 'width ="'+meta.width+'"';
                        if(typeof meta.height !== 'undefined')
                            vid += 'height ="'+meta.height+'"';

                        vid += '></video>';
                        let caption = meta.caption? '<figcaption>'+meta.caption+'</figcaption>' : '';
                        return '<div class="image-container">'+vid+'</div>'+caption;
                    }
                },
                'youtube':{
                    checkedChanges: false,
                    render: function(context = this){
                        let identifier = context.currentBlock.text;
                        let meta = context.currentBlock.meta;
                        let src = "https://www.youtube.com/embed/"+identifier;
                        if(typeof meta.autoplay !== 'undefined')
                            src += (src.indexOf('?') !== -1 ? "&" : "?") + 'autoplay=' + (meta.autoplay? '1' : '0');
                        if(typeof meta.loop !== 'undefined')
                            src += (src.indexOf('?') !== -1 ? "&" : "?") + 'loop=' + (meta.loop? '1' : '0');
                        if(typeof meta.mute !== 'undefined')
                            src += (src.indexOf('?') !== -1 ? "&" : "?") + 'mute=' + (meta.mute? '1' : '0');
                        if(typeof meta.controls !== 'undefined')
                            src += (src.indexOf('?') !== -1 ? "&" : "?") + 'controls=' + (meta.controls? '1' : '0');

                        let iframe = '<iframe src="'+src+'"';
                        if(meta.width)
                            iframe += 'width ="'+meta.width+'"';
                        if(meta.height)
                            iframe += 'height ="'+meta.height+'"';
                        iframe += '></iframe>';
                        let caption = meta.caption? '<figcaption>'+meta.caption+'</figcaption>' : '';
                        return iframe+caption;
                    }
                },
                'article':{
                    checkedChanges: false,
                    linkFunction: function(url){
                        return document.pathToRoot + 'articles/'+url
                    },
                    render: function(context = this){
                        let otherArticle = context.currentBlock.otherArticle;
                        let meta = context.currentBlock.meta;

                        let creator = otherArticle.creator.firstName;
                        if(creator){
                            creator = otherArticle.creator.lastName?
                            '<span class="first-name">'+creator+'</span><span class="last-name">'+otherArticle.creator.lastName+'</span>' :
                            '<span class="first-name">'+creator+'</span>';
                            creator = '<div class="author">'+creator+'</div>';
                        }
                        else
                            creator = '<!---->';

                        let article = `
                        <a href="`+context.currentOptions.article.linkFunction(otherArticle.address)+`">
                            <h3 class="title">`+otherArticle.title+`</h3>
                            <img class="thumbnail" src="`+(otherArticle.thumbnail.address? context.extractMediaAddress(otherArticle.thumbnail) : context.sourceURL()+'img/icons/image-generic.svg')+`">
                            `+creator+`

                        </a>`;
                        let caption = meta.caption? '<figcaption>'+meta.caption+'</figcaption>' : '';
                        return article+caption;
                    }
                },
            },
            //Sometimes, you need to manially recompute Vue computed properties
            recompute:{
                changed:false,
                galleryOptions:false,
                render:false,
                paramMap: false
            },
            //Modes, and array of available operations in each mode
            modes: ['view','create','update'],
            currentMode: this.mode.toString(),
            componentSize: {
                disable: this.block.type !== 'gallery'
            },
            //Needed during thumbnail selection
            mediaSelector: {
                changed:false,
                open:false,
                newItem:null,
                selectMultiple:false,
                quickSelect:true,
                mode:null,
                selection:-1
            },
            //Needed during article selection
            gallerySelector: {
                changed:false,
                open:false,
                newItem:null,
                searchList:{
                    url:document.rootURI + 'api/v1/media',
                    action:'getGalleries',
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
                                    placeholder:'Text gallery name includes',
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
                                    placeholder:'Text gallery name excludes',
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
                    columns:[
                        {
                            id:'identifier',
                            title:'Gallery Name',
                            custom:true,
                            parser:function(item){
                                if(document.selectedLanguage && (item[document.selectedLanguage+'_name'] !== undefined) )
                                    return item[document.selectedLanguage+'_name'];
                                return item.identifier;
                            }
                        },
                        {
                            id:'isNamed',
                            title:'Other Names?',
                            custom:true,
                            parser:function(item){
                                let possibleNames = JSON.parse(JSON.stringify(document.languages));
                                possibleNames =possibleNames.map(function(x) {
                                    return x+'_name';
                                });
                                possibleNames.push('name');
                                for(let i in possibleNames){
                                    if(item[possibleNames[i]] !== undefined)
                                        return 'Yes';
                                }
                                return 'No';
                            }
                        },
                        {
                            id:'created',
                            title:'Date Created',
                            parser:timeStampToReadableFullDate
                        },
                        {
                            id:'lastChanged',
                            title:'Last Changed',
                            parser:timeStampToReadableFullDate
                        }
                    ],
                    page: 0,
                    limit: 25,
                    total: 0,
                    items: [],
                    initiated: false,
                    selected: -1,
                    extraParams: {
                    },
                    extraClasses: function (item) {
                        if (item.vertical)
                            return 'vertical';
                        else if (item.small)
                            return 'small';
                        else
                            return false;
                    },
                    functions: {
                        'mounted': function () {
                            //This means we re-mounted the component without searching again
                            if (!this.initiate) {
                                eventHub.$emit('resizeImages',{
                                    from:this.identifier,
                                    timeout:5
                                });
                            }
                        }
                    }
                }
            },
            //Needed during article selection
            articleSelector: {
                changed:false,
                open:false,
                newItem:null,
                searchList:{
                    action:'getArticles',
                    filters:[
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
                                    default: ''
                                }
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
                    ],
                    //Result columns to display, and how to parse them //TODO Expend with more
                    columns:[
                        {
                            id:'identifier',
                            title:'ID'
                        },
                        {
                            id:'title',
                            title:'Article Title'
                        },
                        {
                            id:'image',
                            custom:true,
                            title:'Thumbnail',
                            parser:function(item){
                                if(!item.thumbnail.address)
                                    return 'None';
                                let src;
                                if(item.thumbnail.local){
                                    src = document.rootURI+document.imagePathLocal+item.thumbnail.address;
                                }
                                else
                                    src = item.thumbnail.dataType?
                                        (document.rootURI+'api/v1/media?action=getDBMedia&address='+item.thumbnail.address+'&lastChanged='+item.thumbnail.updated)
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
                    //SearchList API (and probably the only relevant API) URL
                    url: document.pathToRoot+ 'api/v1/articles',
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
                        authAtMost:9999
                    },
                    selected:-1,
                    extraClasses: function (item) {
                        let classes = [];

                        if(item.articleAuth > 2)
                            classes.push('hidden');

                        if (item.vertical)
                            classes.push('vertical');
                        else if (item.small)
                            classes.push('small');

                        return classes;
                    },
                    functions: {
                        'mounted': function () {
                            //This means we re-mounted the component without searching again
                            if (!this.initiate) {
                                eventHub.$emit('resizeImages',{
                                    from:this.identifier,
                                    timeout:5
                                });
                            }
                        }
                    }
                }
            },
            //Whether we are currently updating the item
            updating: false
        }
    },
    created:function(){
        this.registerHub(eventHub);
        this.registerEvent('resizeImages',this.resizeImages);
        this.registerDynamicEvents();

        //Create all defaults
        for(let i in JSON.parse(JSON.stringify(this.options))){
            if(this.currentOptions[i] === undefined || typeof this.currentOptions[i] !== 'object')
                Vue.set(this.currentOptions,i,this.options[i]);
            else{
                for(let j in this.currentOptions[i]){
                    if(this.options[i][j] !== undefined)
                        Vue.set(this.currentRenderOptions[i],j,this.options[i][j]);
                }
            }
        }
        this.updateParamMap();
        this.setMainItem(this.currentBlock);
        this.$forceUpdate();
    },
    mounted:function(){
        if(this.currentMode === 'view' && this.currentBlock.type === 'markdown' && (window.hljs !==undefined))
            this.$el.querySelectorAll('pre code').forEach((block) => {
                hljs.highlightBlock(block);
            });
    },
    updated: function(){
        if(this.currentMode === 'view' && !this.currentOptions[this.currentBlock.type].checkedChanges){
            let checkMap = {
                /* 'mainItem key':target */
            };
            switch (this.currentBlock.type){
                case 'markdown':
                    let context = this;
                    //TODO fix dirty hack later
                    if(window.hljs !==undefined)
                        setTimeout(function(){
                            context.$el.querySelectorAll('pre code').forEach((block) => {
                                hljs.highlightBlock(block);
                            })
                        },500);
                    break;
                case 'cover':
                case 'image':
                    checkMap = {
                        'meta.alt':this.currentBlock.meta.alt,
                        'meta.caption':this.currentBlock.meta.caption,
                        'meta.name':this.currentBlock.meta.name,
                    };
                    break;
                case 'gallery':
                    checkMap = {
                        'meta.caption':this.currentBlock.meta.caption,
                        'meta.name':this.currentBlock.meta.name,
                        'meta.autoplay':this.currentBlock.meta.autoplay,
                        'meta.loop':this.currentBlock.meta.loop,
                        'meta.center':this.currentBlock.meta.center,
                        'meta.preview':this.currentBlock.meta.preview,
                        'meta.fullScreenOnClick':this.currentBlock.meta.fullScreenOnClick,
                        'meta.slider':this.currentBlock.meta.slider,
                    };
                    break;
                case 'video':
                case 'youtube':
                    checkMap = {
                        'meta.name':this.currentBlock.meta.name,
                        'meta.caption':this.currentBlock.meta.caption,
                        'meta.autoplay':this.currentBlock.meta.autoplay,
                        'meta.loop':this.currentBlock.meta.loop,
                        'meta.height':this.currentBlock.meta.height,
                        'meta.width':this.currentBlock.meta.width,
                        'meta.mute':this.currentBlock.meta.mute,
                        'meta.controls':this.currentBlock.meta.controls
                    };
                    if(this.currentBlock.type === 'youtube'){
                        checkMap['meta.embed'] = this.currentBlock.meta.embed;
                        checkMap['text'] = this.currentBlock.text;
                    }
                    break;
                case 'article':
                    checkMap = {
                        'meta.caption':this.currentBlock.meta.caption,
                    };
                    break;
            }
            for(let param in checkMap){
                if(this.mainItem[param].current !== undefined && this.mainItem[param].current !== this.mainItem[param].original)
                    checkMap[param] = this.mainItem[param].current;
            }
            if(this.currentBlock.type === 'gallery')
                this.recompute.galleryOptions = !this.recompute.galleryOptions;
            this.recompute.render = !this.recompute.render;
            this.currentOptions[this.currentBlock.type].checkedChanges = true;
        }
    },
    computed:{
        changed: function(){
            if(this.recompute.changed)
                ;//Do nothing
            for(let i in this.mainItem){
                if(
                    this.mainItem[i] && this.mainItem[i].original !== undefined &&
                    (this.mainItem[i].original !== this.mainItem[i].current || this.paramMap[i].considerChanged)
                ){
                    return true;
                }
            }
            //If we changed anything outside of main item, return true
            return (this.articleSelector.changed || this.gallerySelector.changed || this.mediaSelector.changed);
        },
        renderFromBlock: function(){
            if(!this.recompute.render)
                ;//DO NOTHING
            if(this.currentOptions[this.currentBlock.type] === undefined)
                return '<!---->';
            return this.currentOptions[this.currentBlock.type].render(this);
        },
        //Default gallery options, overwritten by provided ones
        galleryOptions: function(){
            if(this.currentBlock.type !== 'gallery')
                return {};

            if(!this.recompute.galleryOptions)
                ;//DO NOTHING

            let validOptions = {
                caption:null,
                name:null,
                loop:true,
                center:false,
                preview:true,
                slider:true,
                fullScreenOnClick:false,
                autoplay:false,
                delay:5,
                loadingImageUrl: this.sourceURL()+'img/icons/image-generic.svg',
                missingImageUrl: this.sourceURL()+'img/icons/image-missing.svg'
            };

            for(let i in validOptions){
                if(this.currentBlock.meta[i] !== undefined)
                    validOptions[i] = this.currentBlock.meta[i];
            }

            return validOptions;
        },
        galleryDisplayNumber: function(){
            for(let i in this.currentOptions.gallery.displayNumberArray){
                let option =  this.currentOptions.gallery.displayNumberArray[i];
                if(this.componentSize.width > option.width)
                    return option.number;
            }
            return 1;
        },
        galleryImages: function(){
            let images = [];
            if(this.currentBlock.type !== 'gallery')
                return images;
            for(let i in this.currentBlock.collection.members){
                let image = this.currentBlock.collection.members[i];
                let obj = {
                    url:this.extractMediaAddress(image)
                };
                if(image.alt)
                    obj.alt = image.alt;
                images.push(obj);
            }
            return images;
        },
    },
    methods:{
        //Allows controlling the video
        videoControls: function(action){
            const video = this.$el.querySelector('.image-selector .image-preview video');
            switch (action){
                case 'play':
                    if(video.paused)
                        video.play();
                    else
                        video.pause();
                    break;
                case 'mute':
                    video.muted = !video.muted;
                    break;
                case 'rewind':
                    video.currentTime = 0;
                    video.pause();
                    break;
            }
        },
        //Wrapper for item deletion
        handleItemPermDeletion: function(response){
            this.handleItemDeletion(response,true);
        },
        //Wrapper for item hiding
        handleItemHiding: function(response){
            this.handleItemDeletion(response,false);
        },
        //Handles response to deleting an item
        handleItemDeletion: function(response, permanent){
            if(this.verbose)
                console.log('Recieved handleItemSet',response);

            this.updating = false;

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

            if(typeof response !== 'object'){
                alertLog('Unknown response '+response,'error',this.$el);
                return;
            }

            let blockRes = response.block;
            let orderRes = response.order;
            let blockResCorrect = !permanent;
            let orderResCorrect = false;

            if(permanent)
                switch (blockRes) {
                    case -1:
                        alertLog('Server error, block not deleted!','error',this.$el);
                        break;
                    case 0:
                        blockResCorrect = true;
                        break;
                    default:
                        alertLog('Unknown block response '+blockRes,'error',this.$el);
                }

            if(!blockResCorrect)
                return;

            switch (orderRes) {
                case -1:
                    alertLog('Block deleted, but not removed from article!','error',this.$el);
                    orderResCorrect = true;
                    break;
                case 0:
                    orderResCorrect = true;
                    break;
                case 1:
                    alertLog('Unknown server error!','error',this.$el);
                    break;
                default:
                    alertLog('Unknown order response '+orderRes,'error',this.$el);
            }

            if(!orderResCorrect)
                return;

            alertLog('Block '+(permanent?this.block.blockId+' deleted!': this.index+' hidden!'),'success',this.$el);
            eventHub.$emit('deleteBlock',{
                from:this.identifier,
                content:{
                    permanent:permanent,
                    key: (permanent?this.block.blockId:this.index)
                }
            });
        },
        //Handles response to setting an item
        handleItemSet: function(response){

            if(this.verbose)
                console.log('Recieved handleItemSet',response);

            this.updating = false;

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

            if(typeof response !== 'object'){
                alertLog('Unknown response '+response,'error',this.$el);
                return;
            }

            let blockRes = response.block;
            let orderRes = response.order;
            let blockResCorrect = false;
            let orderResCorrect = false;

            if(this.currentMode === 'create')
                switch (blockRes) {
                    case -3:
                        alertLog('Missing inputs when creating block!','error',this.$el);
                        break;
                    case -2:
                        alertLog('One of the dependencies (image, article, etc) no longer exists!','error',this.$el);
                        break;
                    case -1:
                        alertLog('Server error!','error',this.$el);
                        break;
                    default:
                        if(typeof blockRes === 'number' || (typeof  blockRes === 'string' && blockRes.match(/^\d+$/))){
                            blockResCorrect = true;
                        }
                        else
                            alertLog('Unknown block response '+blockRes,'error',this.$el);
                }
            else if(this.currentMode === 'update' || this.currentMode === 'view')
                switch (blockRes) {
                    case -2:
                        alertLog('One of the dependencies (likely thumbnail) no longer exists!','error',this.$el);
                        break;
                    case -1:
                    case 2:
                    case 3:
                        alertLog('Server error!','error',this.$el);
                        break;
                    case 0:
                        blockResCorrect = true;
                        break;
                    case 1:
                        alertLog('Article no longer exists!','error',this.$el);
                        break;
                    default:
                        alertLog('Unknown block response '+blockRes,'error',this.$el);
                        break;
                }

            if(!blockResCorrect)
                return;

            if(this.currentMode === 'update' || this.currentMode === 'view')
                orderResCorrect = true;
            else if(orderRes === 0)
                orderResCorrect = true;
            else
                alertLog('Block '+(this.currentMode === 'create'? 'created' : 'updated')+', but failed to be set in article order.','warning',this.$el);

            if(!orderResCorrect)
                return;

            if(this.currentBlock.type === 'gallery'){
                this.currentBlock.collection['@out-of-date'] = true;
            }
            let newBlock = JSON.parse(JSON.stringify(this.currentBlock));
            if(this.currentMode === 'create'){
                newBlock.updated = Math.ceil(Date.now()/1000);
                newBlock.created = Math.ceil(Date.now()/1000);
                newBlock.orphan = false;
                newBlock.exists = true;
                newBlock.blockId = blockRes;
                if(!newBlock.meta)
                    newBlock.meta = {};
                if(newBlock.resource && !newBlock.resource.meta)
                    newBlock.resource.meta = {};
            }
            eventHub.$emit('updateBlock',{
                from:this.identifier,
                content:{
                    newBlock:newBlock,
                    key: (this.currentMode === 'create'? blockRes : this.blockId)
                }
            });
            if(this.currentMode === 'create')
                this.resetCreation();
        },
        //Tries do delete the block
        deleteItem: function(permanent = true){
            if(this.updating){
                if(this.verbose)
                    console.log('Still updating item!');
                return;
            }

            //Data to be sent
            let data = new FormData();
            let sendParams = {
                action: 'deleteArticleBlocks',
                articleId: this.articleId,
                permanentDeletion: permanent,
                deletionTargets: JSON.stringify([(permanent ? this.block.blockId : this.index)])
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
                this.identifier+(permanent?'-delete-response':'-hide-response'),
                {
                    'verbose': this.verbose,
                    'parseJSON':true,
                    'identifier':this.identifier
                }
            );
        },
        //Tries to update the block
        setItem:function(){
            if(this.updating){
                if(this.verbose)
                    console.log('Still updating item!');
                return;
            }

            //Data to be sent
            let data = new FormData();
            data.append('action', 'setArticleBlock');
            data.append('create', this.currentMode === 'create');
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

                if(param.onUpdate.encodeURI)
                    paramValue = encodeURIComponent(paramValue);

                data.append(param.onUpdate.setName, paramValue);
                sendParams[param.onUpdate.setName] = paramValue;
            }

            let target = null;
            let paramName = '';
            let newValue = null;

            switch (this.currentBlock.type){
                case 'gallery':
                    target = this.gallerySelector;
                    newValue = target.newItem;
                    paramName = 'blockCollectionName';
                    break;
                case 'article':
                    target = this.articleSelector;
                    newValue = target.newItem;
                    paramName = 'otherArticleId';
                    break;
                case 'image':
                case 'cover':
                case 'video':
                    target = this.mediaSelector;
                    newValue = target.newItem;
                    paramName = 'blockResourceAddress';
                    break;
            }

            if(paramName !== '' && newValue !== null){
                data.append(paramName, newValue);
                sendParams[paramName] = newValue;
            }
            else if(this.currentMode === 'create' && paramName){
                alertLog(this.currentBlock.type+' must be selected!','warning',this.$el);
                return;
            }

            if(this.verbose)
                console.log('Setting item with parameters ',sendParams);

            this.updating = true;

            this.apiRequest(
                data,
                'api/v1/articles',
                this.identifier+'-set-response',
                {
                    'verbose': this.verbose,
                    'parseJSON':true,
                    'identifier':this.identifier
                }
            );
        },
        //Resets the param map
        updateParamMap: function(){
            Vue.set(this.paramMap.articleId,'ignore',this.currentMode === 'create' && !this.currentBlock.type);
            Vue.set(this.paramMap.blockId,'ignore',this.currentMode === 'create');
            Vue.set(this.paramMap.blockId.onUpdate,'ignore',this.currentMode === 'create');
            Vue.set(this.paramMap.type,'edit',this.currentMode === 'create' && !this.currentBlock.type);
            Vue.set(this.paramMap.orderIndex,'ignore',this.currentMode !== 'create' || !this.currentBlock.type);
            Vue.set(this.paramMap.orderIndex,'required',this.currentMode === 'create');
            Vue.set(this.paramMap.text,'ignore',(['youtube','markdown'].indexOf(this.currentBlock.type) === -1));
            Vue.set(this.paramMap.text,'type',(this.currentBlock.type === 'youtube' ? 'text' : 'textArea'));
            Vue.set(this.paramMap.text,'title',(this.currentBlock.type === 'youtube' ? 'Youtube Identifier' : 'Markdown Text'));
            Vue.set(this.paramMap.text,'required',this.currentMode === 'create');
            Vue.set(this.paramMap.text,'pattern',(this.currentBlock.type === 'youtube' ? '(?:\\w|-|_){11}' : undefined));
            Vue.set(this.paramMap.text.onUpdate,'validate',this.currentBlock.type === 'youtube' ?
                function(item){
                    return item.match(/(?:\w|-|_){11}/);
                }
                :
                function(){
                    return true
                }
            );
            Vue.set(this.paramMap['resource.address'],'ignore',(['image','cover','video'].indexOf(this.currentBlock.type) === -1));
            Vue.set(this.paramMap['resource.address'],'required',this.currentMode === 'create');
            switch(this.currentBlock.type){
                case 'image':
                case 'cover':
                    Vue.set(this.paramMap['resource.address'],'title','Image Address');
                    break;
                case 'video':
                    Vue.set(this.paramMap['resource.address'],'title','Video Address');
                    break;
            }
            Vue.set(this.paramMap['collection.name'],'ignore',(['gallery'].indexOf(this.currentBlock.type) === -1));
            Vue.set(this.paramMap['collection.name'],'required',this.currentMode === 'create');
            Vue.set(this.paramMap['otherArticle.id'],'ignore',(['article'].indexOf(this.currentBlock.type) === -1));
            Vue.set(this.paramMap['otherArticle.id'],'required',this.currentMode === 'create');
            Vue.set(this.paramMap['meta.alt'],'ignore',(['image','cover'].indexOf(this.currentBlock.type) === -1));
            Vue.set(this.paramMap['meta.name'],'ignore',(['image','cover','gallery','youtube','video'].indexOf(this.currentBlock.type) === -1));
            Vue.set(this.paramMap['meta.caption'],'ignore',(['image','cover','gallery','youtube','video','article'].indexOf(this.currentBlock.type) === -1));
            Vue.set(this.paramMap['meta.embed'],'ignore',(['youtube'].indexOf(this.currentBlock.type) === -1));
            Vue.set(this.paramMap['meta.height'],'ignore',(['youtube','video'].indexOf(this.currentBlock.type) === -1));
            Vue.set(this.paramMap['meta.width'],'ignore',(['youtube','video'].indexOf(this.currentBlock.type) === -1));
            Vue.set(this.paramMap['meta.mute'],'ignore',(['youtube','video'].indexOf(this.currentBlock.type) === -1));
            Vue.set(this.paramMap['meta.controls'],'ignore',(['youtube','video'].indexOf(this.currentBlock.type) === -1));
            Vue.set(this.paramMap['meta.autoplay'],'ignore',(['youtube','video','gallery'].indexOf(this.currentBlock.type) === -1));
            Vue.set(this.paramMap['meta.loop'],'ignore',(['youtube','video','gallery'].indexOf(this.currentBlock.type) === -1));
            Vue.set(this.paramMap['meta.loop'],'parseOnGet',this.currentBlock.type === 'gallery'?
                function(item){
                    return item === undefined ? true : item;
                }
                :
                function(item){return item}
            );
            Vue.set(this.paramMap['meta.center'],'ignore',(['gallery'].indexOf(this.currentBlock.type) === -1));
            Vue.set(this.paramMap['meta.preview'],'ignore',(['gallery'].indexOf(this.currentBlock.type) === -1));
            Vue.set(this.paramMap['meta.fullScreenOnClick'],'ignore',(['gallery'].indexOf(this.currentBlock.type) === -1));
            Vue.set(this.paramMap['meta.slider'],'ignore',(['gallery'].indexOf(this.currentBlock.type) === -1));
            Vue.set(this.paramMap['created'],'ignore',this.currentMode === 'create');
            Vue.set(this.paramMap['updated'],'ignore',this.currentMode === 'create');
            this.$forceUpdate();
        },
        //Extracts the image address from an image object
        extractMediaAddress: function(item){
            if(this.verbose)
                console.log('Extracting address from ',item);
            if(item === undefined || item.address === undefined)
                return this.sourceURL()+'img/icons/image-generic.svg';
            let trueAddress = item.address;
            if(item.local)
                trueAddress = document.rootURI + document[(this.currentBlock.type !== 'video'?'imagePathLocal':'videoPathLocal')]+trueAddress;
            else if(item.dataType)
                trueAddress = document.rootURI+'api/v1/media?action=getDBMedia&address='+trueAddress+'&lastChanged='+item.updated+'&resourceType='+(this.currentBlock.type !== 'video' ? 'img' : 'vid');
            return trueAddress;
        },
        //Changes creation block back to initial state
        resetCreation: function(){
            if(this.verbose)
                console.log('Resetting creation!');
            this.mainItem.articleId = undefined;
            this.block.articleId = undefined;
            this.block.type = undefined;
            this.block.orderIndex = undefined;
            this.resetInputs();
        },
        //Resets changes to edited block
        resetInputs:function(){
            if(this.verbose)
                console.log('Resetting inputs!');
            this.currentBlock = JSON.parse(JSON.stringify(this.block));
            this.mediaSelector.changed = false;
            this.mediaSelector.newItem = null;
            this.gallerySelector.changed = false;
            this.gallerySelector.newItem = null;
            this.articleSelector.changed = false;
            this.articleSelector.newItem = null;
            this.updateParamMap();
            this.setMainItem(this.currentBlock);
            this.recompute.changed = ! this.recompute.changed;
            this.$forceUpdate();
            //TODO Find a better solution
            if(this.currentMode === 'view' && this.currentBlock.type === 'markdown'){
                let context = this;
                if(window.hljs !==undefined)
                    setTimeout(function(){
                        context.$el.querySelectorAll('pre code').forEach((block) => {
                            hljs.highlightBlock(block);
                        })
                    },500);
            }
        },
        //Sets main item
        setMainItem(item){
            this.mainItem = {};

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
        //Resizes searchlist images
        resizeImages: function (request) {

            if(!request || !request.from || request.identifier !== this.identifier)
                return;
            let timeout = request.timeout;

            let context = this;
            let target = this.currentBlock.type === 'gallery'? this.gallerySelector.searchList : this.articleSelector.searchList;

            if(!target.initiated && timeout > 0){
                if(this.verbose)
                    console.log('resizing images again, timeout '+timeout);
                setTimeout(function(){context.resizeImages({from:context.identifier,timeout:timeout-1})},1000);
                return;
            }
            else if(!target.initiated && timeout === 0){
                if(this.verbose)
                    console.log('Cannot resize images, timeout reached!');
                return;
            }

            if(this.verbose)
                console.log('resizing images!');

            let searchItems = this.$el.querySelectorAll('.search-list .search-item');
            let verbose = this.verbose;
            for( let index in target.items ){
                let element = searchItems[index];
                let image = element.querySelector('img');
                image.onload = function () {
                    let naturalWidth = image.naturalWidth;
                    let naturalHeight = image.naturalHeight;
                    if(naturalWidth < 320){
                        Vue.set(context.searchList.items[index],'small',true);
                        if(verbose)
                            console.log('setting image '+index+' as small');
                    }
                    else if(naturalHeight > naturalWidth){
                        Vue.set(context.searchList.items[index],'vertical',true);
                        if(verbose)
                            console.log('cropping image '+index+' vertically', naturalWidth, naturalHeight);
                    }
                };
                if(image.complete)
                    image.onload();
            };
        },

        //Parses search results returned from a search list
        parseSearchResults: function(response){
            if(this.verbose)
                console.log('Received response',response);

            if(!response.from || response.from !== this.identifier+'-article-search' && response.from !== this.identifier+'-gallery-search')
                return;

            let target = (response.from === this.identifier+'-gallery-search') ? this.gallerySelector.searchList : this.articleSelector.searchList;

            //Either way, the galleries should be considered initiated
            target.items = [];
            target.initiated =  true;

            //In this case the response was an error code, or the page no longer exists
            if(response.content['@'] === undefined)
                return;

            target.total = (response.content['@']['#'] - 0) ;
            delete response.content['@'];

            for(let k in response.content){
                response.content[k].identifier = k;
                target.items.push(response.content[k]);
            }

            target.functions.updated = function(){
                eventHub.$emit('resizeImages');
            };
        },
        //Wrapper for selectSearchElement with articles
        selectArticlesFromSearchList: function(request){
            this.selectSearchElement(request,'articles');
        },
        //Wrapper for selectSearchElement with galleries
        selectGalleriesFromSearchList: function(request){
            this.selectSearchElement(request,'galleries');
        },
        //Selects an element in the search list
        selectSearchElement: function(request,type){

            if(this.verbose)
                console.log('requestSelection ',type,request);

            let target = type === 'galleries' ? this.gallerySelector : this.articleSelector;
            let index = request.content;
            let item = JSON.parse(JSON.stringify(target.searchList.items[index]));
            if(type === 'articles')
                item.identifier = item.identifier - 0;

            if(this.verbose)
                console.log('Got item',item,'from searchlist!');

            target.newItem = item.identifier;
            target.open = false;

            if(type === 'galleries'){
                if(!this.currentBlock.collection)
                    this.currentBlock.collection = {};
                if(this.currentMode === 'create' || this.block.collection && (this.block.collection.name !== item.identifier))
                    target.changed = true;
                else
                    target.changed = false;
                this.currentBlock.collection.name = item.identifier;
            }
            else{
                if(!this.currentBlock.otherArticle)
                    this.currentBlock.otherArticle = {};
                if(this.currentMode === 'create' || this.block.otherArticle && (this.block.otherArticle.id !== item.identifier))
                    target.changed = true;
                else
                    target.changed = false;
                this.currentBlock.otherArticle.id = item.identifier;
                this.currentBlock.otherArticle.address = item.articleAddress;
                this.currentBlock.otherArticle.title = item.title;
                this.currentBlock.otherArticle.creator = {
                    id:item.creatorId
                };
                if(item.firstName)
                    this.currentBlock.otherArticle.creator.firstName = item.firstName;
                if(item.lastName)
                    this.currentBlock.otherArticle.creator.lastName = item.lastName;
                Vue.set(this.currentBlock.otherArticle,'thumbnail', item.thumbnail);
            }

        },
        //Handles image selection from media selector
        handleImageSelection: function(item){
            this.mediaSelector.open = false;
            this.mediaSelector.changed = true;
            this.mediaSelector.newItem = item.relativeAddress? item.relativeAddress : item.identifier;
            if(!this.currentBlock.resource)
                this.currentBlock.resource = {};
            this.currentBlock.resource.address = item.relativeAddress? item.relativeAddress : item.identifier;
            this.currentBlock.resource.local = item.local;
            this.currentBlock.resource.dataType = item.dataType ? item.dataType : null;
            this.currentBlock.resource.updated = item.lastChanged;
        },
        //Registers dynamic events
        registerDynamicEvents: function(){
            this.registerEvent(this.identifier+'SearchResults',this.parseSearchResults);
            this.registerEvent(this.identifier+'-gallery-selection', this.selectGalleriesFromSearchList);
            this.registerEvent(this.identifier+'-article-selection', this.selectArticlesFromSearchList);
            this.registerEvent(this.identifier+'-media-selector-selection-event', this.handleImageSelection);
            this.registerEvent(this.identifier+'-set-response' ,this.handleItemSet);
            this.registerEvent(this.identifier+'-delete-response' ,this.handleItemPermDeletion);
            this.registerEvent(this.identifier+'-hide-response' ,this.handleItemHiding);
        },
    },
    watch: {
        mode: function(newValue){
            this.currentMode = newValue;
        },
        'currentMode': function(newVal){
            if(newVal === 'view'){
                this.currentOptions[this.currentBlock.type].checkedChanges = false;
            }
            this.updateParamMap();
        },
        block: {
            handler: function(){
                this.resetInputs();
            },
            deep:true
        },
        changed: function(){
            if(this.currentMode === 'create' && this.mainItem.articleId === undefined){
                this.block.type = this.mainItem.type.current;
                this.block.articleId = this.articleId;
                this.block.orderIndex = this.index;
                this.resetInputs();
            }
        },
        identifier: function(){
            this.registerDynamicEvents();
        }
    },
    template: `
    <div class="article-block edit" :class="{changed:changed,create:currentMode === 'create'}" v-if="currentMode === 'update' || currentMode === 'create'">

        <div v-if="allowModifying || currentMode === 'create'" class="controls">
            <button
                v-for="index in modes"
                v-if="index !== 'create' && currentMode !== 'create'"
                @click.prevent="currentMode = index"
                :class="[{selected:(currentMode===index)},index]"
                class="positive-3"
                ></button>
            <button
                v-if="currentMode !== 'create'"
                @click.prevent="deleteItem(false)"
                class="dangerous-1 hide"
                ></button>
            <button
                v-if="currentMode !== 'create'"
                @click.prevent="deleteItem(true)"
                class="negative-1 delete"
                ></button>
            <button
                v-if="changed"
                @click.prevent="setItem()"
                class="positive-1 set"
                ></button>
            <button
                v-if="changed"
                @click.prevent="resetInputs()"
                class="cancel-1 reset"
                ></button>
            <button
                v-if="!changed  && currentMode === 'create' && this.mainItem.articleId"
                @click.prevent="resetCreation()"
                class="cancel-1 reset"
                ></button>
            </div>
            

        <form class="block-edit">
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
                     @change="item.current = paramMap[key].parseOnChange($event.target.value,currentBlock);recompute.changed = ! recompute.changed;$forceUpdate()"
                    >
                    <button
                    v-else-if="paramMap[key].type === 'boolean'"
                    class="item-param"
                    :class="item.current? 'positive-1' : 'negative-1'"
                    v-text="item.current?(paramMap[key].button.positive? paramMap[key].button.positive : 'Yes'):(paramMap[key].button.negative? paramMap[key].button.negative : 'No')"
                     @click.prevent="item.current = paramMap[key].parseOnChange(!item.current,currentBlock);recompute.changed = ! recompute.changed;$forceUpdate()"
                     ></button>

                    <textarea
                    v-else-if="paramMap[key].type === 'textArea'"
                    class="item-param"
                     :placeholder="paramMap[key].placeholder !== undefined  ? paramMap[key].placeholder : false"
                     v-model:value="item.current"
                     @change="item.current = paramMap[key].parseOnChange($event.target.value,currentBlock);recompute.changed = ! recompute.changed;$forceUpdate()"
                     ></textarea>

                    <select
                    v-else-if="paramMap[key].type === 'select'"
                    class="item-param"
                     v-model:value="item.current"
                     @change="item.current = paramMap[key].parseOnChange($event.target.value,currentBlock);recompute.changed = ! recompute.changed;$forceUpdate()"
                     >
                        <option v-for="listItem in paramMap[key].list" :value="listItem.value" v-text="listItem.title? listItem.title: listItem.value"></option>
                     </select>

                </div>
                

                <div class="selector-container article-selector" v-if="currentBlock.type === 'article'">
                    <button class="article-name positive-3" :class="{changed:articleSelector.changed}" @click.prevent="articleSelector.open = true">
                        <span class="title" v-text="paramMap['otherArticle.id'].title"></span> <span v-text="currentBlock.otherArticle ? currentBlock.otherArticle.id : ' - '"></span>
                    </button>
                    <div class="article selector-sub-container">
                        <div class="control-buttons" v-if="articleSelector.open">
                            <img :src="sourceURL()+'img/icons/close-red.svg'"  @click.prevent="articleSelector.open = false">
                        </div>
                        <div
                        v-if="articleSelector.open"
                        is="search-list"
                        :_functions="articleSelector.searchList.functions"
                        :api-url="articleSelector.searchList.url"
                        :extra-params="articleSelector.searchList.extraParams"
                        :extra-classes="articleSelector.searchList.extraClasses"
                        :api-action="articleSelector.searchList.action"
                        :event-name="identifier+'SearchResults'"
                        :page="articleSelector.searchList.page"
                        :limit="articleSelector.searchList.limit"
                        :total="articleSelector.searchList.total"
                        :items="articleSelector.searchList.items"
                        :initiate="!articleSelector.searchList.initiated"
                        :columns="articleSelector.searchList.columns"
                        :filters="articleSelector.searchList.filters"
                        :selected="articleSelector.searchList.selected"
                        :test="test"
                        :verbose="verbose"
                        :selection-event-name="identifier+'-article-selection'"
                        :identifier="identifier+'-article-search'"
                        ></div>
                    </div>
                </div>

                
                <div class="selector-container gallery-selector" v-else-if="currentBlock.type === 'gallery'">
                    <button class="gallery-name positive-3" :class="{changed:gallerySelector.changed}" @click.prevent="gallerySelector.open = true">
                        <span class="title" v-text="paramMap['collection.name'].title"></span> <span v-text="currentBlock.collection ? currentBlock.collection.name : ' - '"></span>
                    </button>
                    <div class="gallery selector-sub-container">
                        <div class="control-buttons" v-if="gallerySelector.open">
                            <img :src="sourceURL()+'img/icons/close-red.svg'"  @click.prevent="gallerySelector.open = false">
                        </div>
                        <div
                        v-if="gallerySelector.open"
                        is="search-list"
                        :_functions="gallerySelector.searchList.functions"
                        :api-url="gallerySelector.searchList.url"
                        :extra-params="gallerySelector.searchList.extraParams"
                        :extra-classes="gallerySelector.searchList.extraClasses"
                        :api-action="gallerySelector.searchList.action"
                        :event-name="identifier+'SearchResults'"
                        :page="gallerySelector.searchList.page"
                        :limit="gallerySelector.searchList.limit"
                        :total="gallerySelector.searchList.total"
                        :items="gallerySelector.searchList.items"
                        :initiate="!gallerySelector.searchList.initiated"
                        :columns="gallerySelector.searchList.columns"
                        :filters="gallerySelector.searchList.filters"
                        :selected="gallerySelector.searchList.selected"
                        :test="test"
                        :verbose="verbose"
                        :selection-event-name="identifier+'-gallery-selection'"
                        :identifier="identifier+'-gallery-search'"
                        ></div>
                    </div>
                </div>
                
                <div class="selector-container image-selector" v-else-if="currentBlock.type === 'cover' || currentBlock.type === 'image' || currentBlock.type === 'video'">
                    <div class="image-preview" :class="{changed:mediaSelector.changed}" @click.prevent="mediaSelector.open = true">
                        <img v-if="currentBlock.type !== 'video'" :src="extractMediaAddress(currentBlock.resource)">
                        <video v-else="" :src="currentBlock.resource ? extractMediaAddress(currentBlock.resource) : ''" :poster="currentBlock.resource ? '' : extractMediaAddress(currentBlock.resource)"></video>
                    </div>
                    <div v-if="currentBlock.type === 'video' && currentBlock.resource" class="video-controls">
                        <button v-text="'Play / Pause'" class="positive-3" @click.prevent="videoControls('play')"></button>
                        <button v-text="'Mute / Unmute'" class="positive-3" @click.prevent="videoControls('mute')"></button>
                        <button v-text="'Rewind To Start'" class="cancel-1" @click.prevent="videoControls('rewind')"></button>
                    </div>
                    <div class="media selector-sub-container">
                        <div class="control-buttons" v-if="mediaSelector.open">
                            <img :src="sourceURL()+'img/icons/close-red.svg'"  @click.prevent="mediaSelector.open = false" v-if="mediaSelector.open">
                        </div>
                        <div
                             v-if="mediaSelector.open"
                             is="media-selector"
                             :identifier="identifier+'-media-selector'"
                             :media-type="currentBlock.type === 'video' ? 'vid' : 'img'"
                             :test="test"
                             :verbose="verbose"
                        ></div>
                    </div>
                </div>
        </form>
                
    </div>
    <div class="article-block view" :class="{changed:changed}" v-else="">

        <div v-if="allowModifying" class="controls">
            <button
                v-for="index in modes"
                v-if="index !== 'create'"
                @click.prevent="currentMode = index"
                :class="[{selected:(currentMode===index)},index]"
                class="positive-3"
                ></button>
            <button
                @click.prevent="deleteItem(false)"
                class="dangerous-1 hide"
                ></button>
            <button
                @click.prevent="deleteItem(true)"
                class="negative-1 delete"
                ></button>
            <button
                v-if="changed"
                @click.prevent="setItem()"
                class="positive-1 set"
                ></button>
            <button
                v-if="changed"
                @click.prevent="resetInputs()"
                class="cancel-1 reset"
                ></button>
        </div>

        <p v-if="['cover','image','video','youtube','article','markdown'].indexOf(currentBlock.type) !== -1" :class="currentBlock.type" v-html="renderFromBlock"></p>
        <div v-else-if="['gallery'].indexOf(currentBlock.type) !== -1" :class="currentBlock.type">
            <div
                v-if="block.collection && block.collection.name === currentBlock.collection.name && !block.collection['@out-of-date']"
                is="ioframe-gallery"
                :images="galleryImages"
                :caption="currentBlock.meta.caption? currentBlock.meta.caption : null"
                :display-number="galleryDisplayNumber"
                :center-gallery="galleryOptions.center"
                :has-preview="galleryOptions.preview"
                :has-slider="galleryOptions.slider"
                :full-screen-on-click="galleryOptions.fullScreenOnClick"
                :loading-image-url="galleryOptions.loadingImageUrl"
                :missing-image-url="galleryOptions.missingImageUrl"
            ></div>
            <div v-else="" class="no-gallery-preview"></div>
        </div>
        <p v-else="" class="unsupported" v-text="'Unsupported Block Type!'"></p>
    </div>
    `
});