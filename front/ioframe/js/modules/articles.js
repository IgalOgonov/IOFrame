if(eventHub === undefined)
    var eventHub = new Vue();

var articles = new Vue({
    el: '#articles',
    name: 'articles',
    mixins:[eventHubManager,IOFrameCommons],
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
            //Filters to display for the search list //TODO Expend common filters
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
                                    },
                                    {
                                        value:'@',
                                        title:'All'
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
                    id:'articleAddress',
                    title:'Address'
                },
                {
                    id:'subtitle',
                    custom:true,
                    title:'Subtitle',
                    parser:function(item){
                        return item.meta.subtitle? (item.meta.subtitle.substr(0,40)+'...') : ' - ';
                    }
                },
                {
                    id:'caption',
                    custom:true,
                    title:'Caption',
                    parser:function(item){
                        return item.meta.caption? (item.meta.caption.substr(0,40)+'...') : ' - ';
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
                    parser:function(timestamp){
                        timestamp *= 1000;
                        let date = timestampToDate(timestamp).split('-').reverse().join('-');
                        let hours = Math.floor(timestamp%(1000 * 60 * 60 * 24)/(1000 * 60 * 60));
                        let minutes = Math.floor(timestamp%(1000 * 60 * 60)/(1000 * 60));
                        let seconds = Math.floor(timestamp%(1000 * 60)/(1000));
                        if(hours < 10)
                            hours = '0'+hours;
                        if(minutes < 10)
                            minutes = '0'+minutes;
                        if(seconds < 10)
                            seconds = '0'+seconds;
                        return date + ', ' + hours+ ':'+ minutes+ ':'+seconds;
                    }
                },
                {
                    id:'updated',
                    title:'Last Changed',
                    parser:function(timestamp){
                        timestamp *= 1000;
                        let date = timestampToDate(timestamp).split('-').reverse().join('-');
                        let hours = Math.floor(timestamp%(1000 * 60 * 60 * 24)/(1000 * 60 * 60));
                        let minutes = Math.floor(timestamp%(1000 * 60 * 60)/(1000 * 60));
                        let seconds = Math.floor(timestamp%(1000 * 60)/(1000));
                        if(hours < 10)
                            hours = '0'+hours;
                        if(minutes < 10)
                            minutes = '0'+minutes;
                        if(seconds < 10)
                            seconds = '0'+seconds;
                        return date + ', ' + hours+ ':'+ minutes+ ':'+seconds;
                    }
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
        this.registerEvent('operationRequest', this.handleOperationResponse);
        this.registerEvent('goToPage', this.goToPage);
        this.registerEvent('searchAgain', this.searchAgain);
        this.registerEvent('returnToMainApp', this.returnToMainApp);
    },
    computed:{
        //Main title TODO
        title:function(){
            switch(this.currentMode){
                case 'search':
                    return 'Browsing Articles';
                    break;
                case 'edit':
                    return 'Editing Article';
                    break;
                case 'create':
                    return 'Creating Article';
                    break;
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
                    break;
                case 'permanentDeletion':
                    return 'Delete permanently?';
                    break;
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
        //Searches again (meant to be invoked after relevant changes
        searchAgain: function(){
            if(this.verbose)
                console.log('Searching again!');
            this.items = [];
            this.total = 0;
            this.selected = -1;
            this.initiated = false;
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
            if(response.content['@'] === undefined)
                return;

            this.total = (response.content['@']['#'] - 0) ;
            delete response.content['@'];

            for(let k in response.content){
                response.content[k].identifier = k;
                this.items.push(response.content[k]);
            }
        },
        //Handles operation response
        handleOperationResponse: function(request){
            if(this.verbose)
                console.log('Received response',request);

            if(!request.from)
                return;

            let content = request.content;

            //Common contents
            if(content === 'INPUT_VALIDATION_FAILURE'){
                alertLog('Operation input validation failed!','error',this.$el);
            }
            else if(content === 'AUTHENTICATION_FAILURE'){
                alertLog('Operation not authorized! Check if you are logged in.','error',this.$el);
            }
            else if(content === 'OBJECT_AUTHENTICATION_FAILURE'){
                alertLog('Not authorized to view/modify object!','error',this.$el);
            }
            else if(content === 'WRONG_CSRF_TOKEN'){
                alertLog('CSRF token wrong. Try refreshing the page if this continues.','warning',this.$el);
            }
            else if(content === 'SECURITY_FAILURE'){
                alertLog('Security related operation failure.','warning',this.$el);
            }


            switch (request.from){
                case 'delete':
                case 'permanentDeletion':
                    let actualResponse = content[this.items[this.selected].identifier];
                    if(actualResponse === undefined){
                        alertLog('Unknown '+request.from+' response, '+actualResponse,'error',this.$el);
                        return;
                    }
                    switch (actualResponse){
                        case -1:
                            alertLog(request.from+' failed due to server error!','error',this.$el);
                            break;
                        case 0:
                            alertLog(request.from+' successful!','success',this.$el);
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
                            console.log(actualResponse);
                            alertLog('Unknown response to '+request.from+', '+actualResponse,'error',this.$el);
                    }
                    break;
                default:
                    alertLog('Unknown operation '+request.from+' response, '+content,'error',this.$el);
            }
        },
        //Goes to relevant page  TODO Remove if no searchlist
        goToPage: function(page){
            if(!this.initiating && page.from == 'search'){
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
        //Element selection from search list  TODO Remove if no searchlist
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
            let operation = '';

            if(this.currentMode === 'search'){
                switch (currentOperation){
                    case 'delete':
                    case 'permanentDeletion':
                        operation = currentOperation;
                        data.append('action','deleteArticles');
                        data.append('articles',JSON.stringify([this.items[this.selected].identifier]));
                        if(currentOperation === 'permanentDeletion')
                            data.append('permanentDeletion',true);
                        break;
                    default:
                        break;
                };

                if(!operation){
                    if(this.verbose)
                        console.log('Returning, no operation set!');
                    return;
                }

                if(this.test)
                    data.append('req','test');

                 this.apiRequest(
                     data,
                      'api/v1/articles',
                      'operationRequest',
                      {
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
            };
            this.operationInput= '';
            this.currentOperation = '';

        }
    },
});