if(eventHub === undefined)
    var eventHub = new Vue();

var contacts = new Vue({
    el: '#contacts',
    name: 'Contacts',
    mixins:[sourceURL,eventHubManager,IOFrameCommons,searchListFilterSaver],
    data(){
        return {
            configObject: JSON.parse(JSON.stringify(document.siteConfig)),
            //Contact Types
            contactTypes: [
                null
            ],
            //Current type
            currentType: null,
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
                    title:'View Contacts'
                },
                edit:{
                    operations:{},
                    title:'Edit Contact'
                },
                create:{
                    operations:{},
                    title:'Create Contact'
                }
            },
            //Filters to display for the search list
            filters:[
                {
                    type:'Group',
                    group: [
                        {
                            name:'emailLike',
                            title:'Email',
                            type:'String',
                            min:0,
                            max: 64,
                            validator: function(value){
                                return value.match(/^[\w\-\. ]{1,128}$/) !== null;
                            }
                        },
                        {
                            name:'firstNameLike',
                            title:'First Name',
                            type:'String',
                            min:0,
                            max: 64,
                            validator: function(value){
                                return value.match(/^[\w\-\. ]{1,128}$/) !== null;
                            }
                        },
                        {
                            name:'companyNameLike',
                            title:'Company Name',
                            type:'String',
                            min:0,
                            max: 64,
                            validator: function(value){
                                return value.match(/^[\w\-\. ]{1,128}$/) !== null;
                            }
                        }
                    ]
                },
                {
                    type:'Group',
                    group: [
                        {
                            name:'cityLike',
                            title:'City Name',
                            type:'String',
                            min:0,
                            max: 64,
                            validator: function(value){
                                return value.match(/^[\w\-\. ]{1,128}$/) !== null;
                            }
                        },
                        {
                            name:'countryLike',
                            title:'Country Name',
                            type:'String',
                            min:0,
                            max: 64,
                            validator: function(value){
                                return value.match(/^[\w\-\. ]{1,128}$/) !== null;
                            }
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
                },
                {
                    type:'Group',
                    group: [
                        {
                            name:'includeRegex',
                            title:'Include',
                            placeholder:'Text the name includes',
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
                            placeholder:'Text the name excludes',
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
            //SearchList API (and probably the only relevant API) URL
            url: document.ioframe.pathToRoot + 'api/v1/contacts',
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
                contactType:''
            },
            extraClasses: 'has-type',
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
        this.registerEvent('deleteContacts', this.deleteContactsParse);
        this.registerEvent('getContactTypes', this.parseContactTypes);

        this.getContactTypes();
    },
    computed:{
        //Search columns
        columns:function(){
            let columns = [];
            if(this.currentType == null)
                columns.push({
                    id:'contactType',
                    title:'Type'
                });
            columns.push(
                {
                    id:'identifier',
                    title:'Identifier'
                },
                {
                    id:'isCompany',
                    custom:true,
                    title:'Company?',
                    parser:function(item){
                        return (item['companyName'] || item['companyID']) ? 'Yes' : 'No' ;
                    }
                },
                {
                    id:'firstName',
                    title:'First Name',
                    parser:function(value){
                        return value? value : '-';
                    }
                },
                {
                    id:'lastName',
                    title:'Last Name',
                    parser:function(value){
                        return value? value : '-';
                    }
                },
                {
                    id:'companyName',
                    title:'Company Name',
                    parser:function(value){
                        return value? value : '-';
                    }
                },
                {
                    id:'email',
                    title:'Email',
                    parser:function(value){
                        return value? value : '-';
                    }
                },
                {
                    id:'phone',
                    title:'Phone',
                    parser:function(value){
                        return value? value : '-';
                    }
                },
                {
                    id:'fax',
                    title:'Fax',
                    parser:function(value){
                        return value? value : '-';
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
            );

            return columns;
        },
        //Main title TODO
        title:function(){
            switch(this.currentMode){
                case 'search':
                    return 'Contacts';
                case 'edit':
                    return 'Editing Contact';
                case 'create':
                    return 'Creating Contact';
                default:
            }
        },
        //Text for current operation
        currentOperationPlaceholder:function(){
            if(this.currentOperationHasInput){
                switch(this.currentOperation){
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
    watch:{
        'currentType':function(newVal){
            this.extraParams.contactType = newVal ? newVal : '';
            this.extraClasses = newVal? '' : 'has-type';
            this.searchAgain();
        }
    },
    methods:{
        //Searches again (meant to be invoked after relevant changes)
        searchAgain: function(){
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
                let trimmedIdentifier;
                if(this.currentType !== null)
                    trimmedIdentifier = k.split('/').pop();
                else
                    trimmedIdentifier = k;
                response.content[k].identifier = trimmedIdentifier;
                this.items.push(response.content[k]);
            }
        },
        //Parses deleteContacts response
        deleteContactsParse: function(response){
            if(this.verbose)
                console.log('Received delete response',response);

            switch (response) {
                case -1:
                    alertLog('Server error!','error',this.$el);
                    break;
                case 0:
                    alertLog('Item deleted!','success',this.$el,{autoDismiss:2000});
                    break;
                default:
                    alertLog('Unknown deletion response '+response,'error',this.$el);
                    break;
            }
        },
        //Parses getContactTypes response
        parseContactTypes: function(response){
            if(this.verbose)
                console.log('Received contacts response',response);
            //Either way, the items should be considered initiated
            if(response.length)
                this.contactTypes = [...[null], ...response];
            else
                this.contactTypes = [null];
        },
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
        shouldDisplayMode: function(index){
            if(index==='edit' && (this.selected === -1) )
                return false;
            if(index==='create' && (this.selected !== -1))
                return false;

            return true;
        },
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
            if(this.verbose)
                console.log('Current Operation ', this.currentOperation ,'Current input ',this.operationInput, 'Current type ',this.currentType);

            let data = new FormData();
            let test = this.test;
            let verbose = this.verbose;
            let currentOperation = this.currentOperation;
            let selectedContact = this.items[this.selected];
            let thisElement = this.$el;

            if(this.currentMode === 'search'){
                switch (currentOperation){
                    case 'delete':
                        data.append('action','deleteContacts');
                        data.append('contactType',selectedContact.contactType);
                        data.append('identifiers',JSON.stringify([selectedContact.identifier]));
                        console.log('Deleted contact type ', selectedContact.contactType);
                        break;
                    default:
                        break;
                }

                if(this.test)
                    data.append('req','test');

                 this.apiRequest(
                     data,
                      'api/v1/contacts',
                      'deleteContacts',
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

        },
        //Gets contact types
        getContactTypes: function(){
            if(this.verbose)
                console.log('Getting contact types!');

            let data = new FormData();

            data.append('action','getContactTypes');

            this.apiRequest(
                data,
                'api/v1/contacts',
                'getContactTypes',
                {
                    verbose: this.verbose,
                    parseJSON: true,
                    ignoreCSRF:true
                }
            );
        }
    },
});