Vue.component('default-headline-renderer', {
    mixins: [sourceURL,eventHubManager,IOFrameCommons],
    props: {
        //Article item as returned from the articles API.
        article:{
            type: Object,
            default: function(){
                return {};
            }
        },
        //An object of rendering options. Overrides defaults at currentRenderOptions
        renderOptions: {
            type: Object,
            default: function(){

                return {
                    /** For example:
                     *
                     *  title: {
                     *      display: false,
                     *  },
                     *  author: {
                     *      parser: function(article){
                     *          return article.firstName? '<span class="first-name">'+article.firstName+'</span>' : '<!---->';
                     *      },
                     *  },
                     *
                     * */
                }
            }
        },
        //Array of supported share options. Currently available: 'mail','twitter','facebook'. Overrides defaults at currentShareOptions
        shareOptions: {
            type: Object,
            default: function(){
                return {
                    /** For example:
                     * {
                     *  mail: {
                     *      text: 'Share Via Mail',
                     *      icon: '',
                     *  },
                     * }
                     * */
                }
            }
        },
        //Info about tags, same as articlesEditor
        existingTagInfo:{
            type: Object,
            default: function(){
                return {};
            }
        },
        //Language, mainly used for tags
        language:{
            type: String,
            default: ''
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
            currentRenderOptions:{
                title:{
                    display:true,
                    parser: function(context = this){
                        let article = context.article;
                        return '<h1 class="title">'+article.title+'</h1>';
                    }
                },
                author:{
                    display:true,
                    parser: function(context = this){
                        let article = context.article;
                        let creator = article.firstName;
                        if(creator){
                            creator = article.lastName?
                            '<span class="first-name">'+creator+'</span><span class="last-name">'+article.lastName+'</span>' :
                            '<span class="first-name">'+creator+'</span>';
                            return '<div class="author">'+creator+'</div>';
                        }
                        else
                            return '<!---->';
                    }
                },
                updated:{
                    display:true,
                    parser: function(context = this){
                        let article = context.article;
                        let timestamp = article.updated;
                        return '<span class="updated">'+timeStampToReadableFullDate(timestamp)+'</span>';
                    }
                },
                created:{
                    display:true,
                    parser: function(context = this){
                        let article = context.article;
                        let timestamp = article.created;
                        return '<span class="created">'+timeStampToReadableFullDate(timestamp)+'</span>';
                    }
                },
                subtitle:{
                    display:true,
                    parser: function(context = this){
                        let article = context.article;
                        return article.meta.subtitle ?
                        '<h2 class="subtitle">'+article.meta.subtitle+'</h2>' :
                            '<!---->';
                    }
                },
                caption:{
                    display:true,
                    parser: function(context = this){
                        let article = context.article;
                        return article.meta.caption ?
                        '<h3 class="caption">'+article.meta.caption+'</h3>' :
                            '<!---->';
                    }
                },
                thumbnail:{
                    display:true,
                    parser: function(context = this){
                        let article = context.article;
                        if(!article.thumbnail.address)
                            return '<!---->';
                        let imageAddress = context.extractImageAddress(article.thumbnail);
                        let element = '<span class="thumbnail"><img src="'+imageAddress+'" ';
                        if(article.meta.alt)
                            element += 'alt="'+article.meta.alt+'"';
                        else if(article.thumbnail.meta.alt)
                            element += 'alt="'+article.meta.alt+'"';
                        element += '></span>';
                        return element;
                    }
                },
                share:{
                    display:true,
                    types: ['mail','twitter','facebook'],
                    parser: function(context = this){

                        let allButtons = '';

                        for(let i in context.currentRenderOptions.share.types){

                            let type = context.currentRenderOptions.share.types[i];

                            if(context.currentShareOptions[type] === undefined)
                                continue;

                            let options = context.currentShareOptions[type];

                            let href = options.href(context,options);

                            let button = '<button class="share-button '+type+'"><a href="'+href+'" target="_blank">';
                            if(options.text)
                                button += '<span class="title">'+options.text+'</span>';
                            if(options.icon)
                                button += '<img src="'+options.icon+'">';
                            button += '</a></button>';

                            allButtons += button;

                        }

                        return allButtons ? '<div class="share">'+allButtons+'</div>' : '<!---->';
                    }
                },
                tags:{
                    display:true,
                    parser: function(context = this){
                        let article = context.article;
                        let language = context.language ?? article.language ?? '';
                        let tagsHTML = ``;
                        if(!article.tags || !article.tags.length || !context.existingTagInfo || !context.existingTagInfo.contents || !context.existingTagInfo.contents['@'])
                            return '<!---->';

                        for(let i in article.tags){
                            let tagId = article.tags[i];
                            let tagName = tagId.split('/')[1];
                            let tagInfo = context.existingTagInfo.contents[tagId];
                            if(!tagInfo || (typeof tagInfo !== 'object'))
                                continue;
                            let title = tagInfo[(language?language:'eng')]??tagName; //Set language -> Article language -> 'eng' -> id

                            tagsHTML += `<span class="tag `+tagName+`">`

                            let img = false;
                            if(tagInfo.img.address)
                                img = context.extractImageAddress(tagInfo.img);
                            if(img)
                                tagsHTML += `<img src="`+img+`">`;

                            tagsHTML += `<span class="title">`+title+`</span>`

                            tagsHTML += `</span>`
                        }
                        if(!tagsHTML)
                            return '<!---->';

                        return '<span class="tags">'+tagsHTML+'</span>';
                    }
                },
            },
            currentShareOptions:{
                mail:{
                    class:'mail',
                    text:'',
                    icon:this.sourceURL()+'img/icons/article/mail.svg',
                    subject: this.article.title,
                    url: function(article){
                        return window.location.origin + window.location.pathname;
                    },
                    href: function(context,options){
                        let subject = context.article.title;
                        let message = options.url()+'?ref=share-mail-'+Date.now();
                        return "mailto:?subject="+encodeURIComponent(subject)+"&body="+encodeURIComponent(message);
                    }
                },
                twitter:{
                    class:'twitter',
                    text:'',
                    icon:this.sourceURL()+'img/icons/article/twitter.svg',
                    url: function(article){
                        return window.location.origin + window.location.pathname;
                    },
                    content:  function(article){
                        return article.title;
                    },
                    //The following can be passed through the main module siteConfigs, all the way down here,
                    via: null,
                    originalReferrer: null,
                    href: function(context,options){
                        let url= options.url()+'?ref=share-twitter-'+Date.now();
                        let href = "https://twitter.com/intent/tweet?url="+encodeURIComponent(url);
                        if(options.content(context.article))
                            href += "&text="+encodeURIComponent(options.content(context.article));
                        if(options.via)
                            href += "&via="+encodeURIComponent(options.via);
                        if(options.originalReferrer)
                            href += "&original_referrer="+encodeURIComponent(options.originalReferrer);
                        return href;
                    }
                },
                facebook:{
                    class:'facebook',
                    text:'',
                    icon:this.sourceURL()+'img/icons/article/facebook.svg',
                    url: function(article){
                        return window.location.origin + window.location.pathname;
                    },
                    href: function(context,options){
                        let url= options.url()+'?ref=share-facebook-'+Date.now();
                        return "https://www.facebook.com/sharer.php?u="+encodeURIComponent(url);
                    }
                }
            },
        }
    },
    created:function(){
        //Create all defaults
        for(let i in this.renderOptions){
            if(this.currentRenderOptions[i] === undefined)
                Vue.set(this.currentRenderOptions,i,this.renderOptions[i]);
            else{
                let validParams = ['display','parser'];
                for(let j in validParams){
                    let param = validParams[j];
                    if(this.renderOptions[i][param] !== undefined)
                        Vue.set(this.currentRenderOptions[i],'param',this.renderOptions[i][param]);
                    if(this.renderOptions[i][param] !== undefined)
                        Vue.set(this.currentRenderOptions[i],param,this.renderOptions[i][param]);
                }
            }
        }
        for(let i in this.shareOptions){
            if(this.currentShareOptions[i] === undefined)
                Vue.set(this.currentShareOptions,i,this.shareOptions[i]);
            else{
                for(let j in this.shareOptions[i]){
                    Vue.set(this.currentShareOptions[i],j,this.shareOptions[i][j]);
                }
            }
        }
    },
    mounted:function(){
    },
    updated: function(){
    },
    computed:{
        renderFromItems: function(){
            let html = '';
            for(let i in this.currentRenderOptions)
                if(this.currentRenderOptions[i].display)
                    html += this.currentRenderOptions[i].parser(this);
            return html;
        }
    },
    methods:{
    },
    watch: {
    },
    template: `
    <header class="default-headline-renderer" v-html="renderFromItems">
    </header>
    `
});