if(eventHub === undefined)
    var eventHub = new Vue();

//***************************
//******GALLERIES APP*******
//***************************//
var galleries = new Vue({
    el: '#galleries',
    name: 'Galleries',
    mixins:[sourceURL],
    data: {
        configObject: JSON.parse(JSON.stringify(document.siteConfig)),
        //Modes, and array of available operations in each mode
        modes: {
            search:{
                operations:{
                    create:{
                        title:'Create New Gallery',
                        button:'positive-1'
                    },
                    'delete':{
                        title:'Delete',
                        button:'negative-1'
                    },
                    'cancel':{
                        title:'Cancel',
                        button:'cancel-1'
                    }
                },
                title:'View Galleries'
            },
            edit:{
                operations:{
                    'remove':{
                        title:'Remove From Gallery',
                        button:'negative-1'
                    },
                    'cancel':{
                        title:'Cancel',
                        button:'cancel-1'
                    },
                    'add':{
                        title:'Add Media To Gallery',
                        button:'positive-1'
                    },
                },
                title:'View/Edit Gallery'
            }
        },
        //Types of media - Images or Videos
        currentType:'img',
        mediaTypes:{
            img:'Images',
            vid:'Videos'
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
        //Result comunts to display, and how to parse them
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
                id:'lastChanged',
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
        //Current page
        page:0,
        //Limit
        limit:50,
        //Total available results
        total: 0,
        //Galleries (on this page)
        galleries: [],
        //Whether the search list is initiated
        galleriesInitiated: false,
        //Selected gallery - search list
        selected:-1,
        //Currently selected gallery object
        gallery: {},
        //The editor's searchlist - filled with remote results
        editorSearchList:{
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
                            placeholder:'Text identifier includes',
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
                            placeholder:'Text identifier excludes',
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
            //Result comunts to display, and how to parse them
            columns:[
                {
                    id:'image',
                    custom:true,
                    title:'Image',
                    parser:function(item){
                        let src = item.dataType? (document.rootURI+'api/media?action=getDBMedia&address='+item.identifier+'&lastChanged='+item.lastChanged) : item.identifier;
                        return '<img src="'+src+'">';
                    }
                },
                {
                    id:'name',
                    custom:true,
                    title:'Name',
                    parser:function(item){
                        return item.name? item.name : (item.dataType ? item.identifier : 'Unnamed link');
                    }
                },
                {
                    id:'lastChanged',
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
                },
                {
                    id:'identifier',
                    title:'Media Identifier',
                    parser:function(identifier){
                        return '<textarea disabled>'+identifier+'</textarea>';
                    }
                }
            ],
            page:0,
            limit:25,
            total: 0,
            items: [],
            initiated: false,
            selected:[],
            extraParams:{
                getDB:1
            },
            extraClasses: function(item){
                if(item.vertical)
                    return 'vertical';
                else if(item.small)
                    return 'small';
                else
                    return false;
            },
            functions:{
                'mounted': function(){
                    //This means we re-mounted the component without searching again
                    if(!this.initiate){
                        eventHub.$emit('resizeImages');
                    }
                }
            },
            url: document.rootURI + 'api/media'
        },
        //Members of currently selected gallery
        galleryMembers: [],
        //Whether currently selected gallery is initiated
        galleryInitiated: false,
        //Selected gallery - current gallery in editor
        selectedGalleryMembers:[],
        editorView:{
            //View 2 elements - editor
            elements: {},
            //View 2 selected - editor
            selected: [],
            //Whether view2 is up-to-date  - editor
            upToDate:false,
            //View 2 target - editor
            target:'',
            //View 2 url - editor
            url:'',
        },
        //Current operation mode
        currentMode: 'search',
        //Current operation
        currentOperation: '',
        //Current operation input
        operationInput:'',
        //Targets to move
        moveTargets:[],
        //Whether we are currently loading
        isLoading:false,
        verbose:false,
        test:false
    },
    created:function(){
        eventHub.$on('searchResults',this.parseSearchResults);
        eventHub.$on('parseGallery',this.parseGalleryResponse);
        eventHub.$on('requestSelection',this.selectElement);
        eventHub.$on('requestSelectionGallery',this.selectGalleryElement);
        eventHub.$on('goToPage',this.goToPage);
        eventHub.$on('viewElementsUpdated', this.updateSecondView);
        eventHub.$on('changeURLRequest', this.changeViewURL);
        eventHub.$on('resetEditorView', this.resetEditorView);
        eventHub.$on('select', this.selectElement);
        eventHub.$on('drop', this.moveGalleryElement);
        eventHub.$on('resizeImages',this.resizeImages);
        eventHub.$on('searchAgain', this.searchAgain);
    },
    computed:{
        //Gets the selected gallery
        getSelectedGallery: function(){
            if(this.selected !== -1)
                return this.galleries[this.selected];
            else
                return {};
        },
        //Main title
        title:function(){
            switch(this.currentMode){
                case 'search':
                    return 'Galleries';
                    break;
                case 'edit':
                    return 'Viewing Gallery';
                    break;
                case 'create':
                    return 'Creating Gallery';
                    break;
                default:
            }
        },
        //Secondary title
        secondTitle:function(){
            switch(this.currentOperation){
                case 'add':
                    return 'Choose an image to add to the gallery';
                    break;
                default:
                    return '';
            }
        },
        //Text for current operation
        currentOperationText:function(){
            switch(this.currentOperation){
                case 'delete':
                    return 'Delete selected?';
                    break;
                case 'remove':
                    return 'Remove selected images from gallery?';
                    break;
                case 'create':
                    return 'New gallery name:';
                    break;
                case 'add':
                    return 'Add selected images to gallery?:';
                    break;
                default:
                    return '';
            }
        },
        //Text for current operation
        currentOperationHasInput:function(){
            switch(this.currentOperation){
                case 'create':
                    return true;
                    break;
                default:
                    return false;
            }
        },
        //Whether the current mode has operations
        currentModeHasOperations:function(){
            return Object.keys(this.modes[this.currentMode].operations).length>0;
        },
        targetIsFolder:function(){
            return this.editorView.target!== '' && (this.editorView.target.indexOf('.') === -1);
        },
        mediaURL: function(){
            return document.pathToRoot + 'api/media';
        },
        mediaAction: function(){
            return this.currentType === 'img' ? 'getGalleries' : 'getVideoGalleries'
        }
    },
    methods:{
        //Searches again (meant to be invoked after relevant changes)
        searchAgain: function(){
            if(this.verbose)
                console.log('Searching again!');
            this.galleriesInitiated = false;
        },
        //Parses search results returned from a search list
        parseSearchResults: function(response){
            if(this.verbose)
                console.log('Recieved response',response);

            if(!response.from)
                return;

            if( response.from === 'search' ){
                //Either way, the galleries should be considered initiated
                this.galleries = [];
                this.galleriesInitiated = true;

                //In this case the response was an error code, or the page no longer exists
                if(response.content['@'] === undefined)
                    return;

                this.total = (response.content['@']['#'] - 0) ;
                delete response.content['@'];

                for(let k in response.content){
                    response.content[k].identifier = k;
                    this.galleries.push(response.content[k]);
                }
            }
            else if(response.from === 'editor-search' ){
                //Either way, the galleries should be considered initiated
                this.editorSearchList.items = [];
                this.editorSearchList.initiated = true;

                //In this case the response was an error code, or the page no longer exists
                if(response.content['@'] === undefined)
                    return;

                this.editorSearchList.total = (response.content['@']['#'] - 0) ;
                delete response.content['@'];

                for(let k in response.content){
                    response.content[k].identifier = k;
                    this.editorSearchList.items.push(response.content[k]);
                }
            }
        },
        //Parses API response for gallery initiation
        parseGalleryResponse: function(request){

            if(!request.from || request.from !== 'editor')
                return;

            this.galleryInitiated = true;

            let response = request.content;

            if(typeof response !== 'object'){
                alertLog('Gallery initiation failed with response '+response,'error',this.$el);
                return;
            }

            //From here on out, we assume a valid gallery
            this.galleryMembers = [];
            for(let key in response){
                if(key === '@')
                    continue;
                response[key].identifier = key;
                this.galleryMembers.push(response[key]);
            }
        },
        //Moves gallery element to a new position
        moveGalleryElement: function(request){
            if(!request.from || request.from !== 'editor-viewer1')
                return;
            this.currentOperation = 'move';
            this.moveTargets = request.content;
            this.confirmOperation();
        },
        //Goes to a different page
        goToPage: function(response){
            if(this.verbose)
                console.log('Recieved response',response);

            if(!response.from)
                return;


            if( response.from === 'search' ){
                this.page = response.content;

                this.galleriesInitiated = false;

                this.selected = -1;
            }
            else if(response.from === 'editor-search' ){
                this.editorSearchList.page = response.content;

                this.editorSearchList.initiated = false;

                this.editorSearchList.selected = -1;
            }
        },
        //Resets editor
        resetEditor: function(newMode){
            this.selectedGalleryMembers = [];
            this.galleryMembers = [];
            this.galleryInitiated = false;
            this.editorView.elements = {};
            this.editorView.selected = [];
            this.editorView.upToDate = false;
            this.editorSearchList.selected = [];
            this.editorSearchList.items = [];
            this.editorSearchList.total = 0;
            this.editorSearchList.initiated = false;
            this.moveTargets = [];
        },
        //Switches to requested mode
        switchModeTo: function(newMode){
            if(this.currentMode === newMode)
                return;
            if(newMode === 'edit' && this.selected===-1){
                alertLog('Please select an image before you view/edit it!','warning',document.querySelector('#galleries'));
                return;
            };
            this.currentMode = newMode;
            this.currentOperation = '';
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
                    if(this.currentMode === 'search'){
                        this.editorView.target = '';
                    }
                    else if(this.currentMode === 'edit'){
                        this.selectedGalleryMember = [];
                    }
                    this.cancelOperation();
                    break;
                default:
                    this.currentOperation = operation;
            }
        },
        //Executes the operation
        confirmOperation: function(){
            if(this.test)
                console.log('Current Operation ', this.currentOperation ,'Current input ',this.operationInput);
            var data = new FormData();
            var apiURL = document.pathToRoot+"api/v1/media";
            var test = this.test;
            var verbose = this.verbose;
            var currentOperation = this.currentOperation;
            var thisElement = this.$el;
            if(this.currentMode === 'search'){
                switch (currentOperation){
                    case 'delete':
                        data.append('action',this.currentType === 'img' ? 'deleteGallery' : 'deleteVideoGallery');
                        data.append('gallery',this.galleries[this.selected].identifier);
                        break;
                    case 'create':
                        if(this.operationInput === ''){
                            alertLog('Gallery to be created must have a name!','warning',this.$el);
                            return;
                        }
                        if(this.operationInput.match(/^[\w ]{1,128}$/)  === null){
                            alertLog('Gallery name may contain characters, latters, and space!','warning',this.$el);
                            return;
                        }
                        data.append('action',this.currentType === 'img' ? 'setGallery' : 'setVideoGallery');
                        data.append('gallery',this.operationInput);
                        break;
                    default:
                };
            }
            else if(this.currentMode === 'edit'){
                switch (currentOperation){
                    case 'remove':
                        let stuffToRemove = [];
                        let index = 0;
                        for(let k in this.selectedGalleryMembers){
                            index = this.selectedGalleryMembers[k];
                            stuffToRemove.push(this.galleryMembers[index].identifier);
                        }

                        if(stuffToRemove.length > 0){
                            data.append('action',this.currentType === 'img' ? 'removeFromGallery' : 'removeFromVideoGallery');
                            data.append('gallery',this.galleries[this.selected].identifier);
                            data.append('addresses' , JSON.stringify(stuffToRemove) );
                        }
                        break;
                    case 'move':
                        data.append('action',this.currentType === 'img' ? 'moveImageInGallery' : 'moveVideoInVideoGallery');
                        data.append('gallery',this.galleries[this.selected].identifier);
                        data.append('from' , this.moveTargets[0]);
                        data.append('to' , this.moveTargets[1] );
                        break;
                    case 'add':
                        let stuffToAdd = [];
                        if(this.editorView.selected.length > 0){
                            for(let k in this.editorView.selected){
                                stuffToAdd.push( (this.editorView.url==='') ? this.editorView.selected[k] : this.editorView.url+'/'+this.editorView.selected[k] );
                            }
                        }
                        else if(this.editorSearchList.selected.length > 0){
                            for(let k in this.editorSearchList.selected){
                                stuffToAdd.push(this.editorSearchList.items[this.editorSearchList.selected[k]].identifier);
                            }
                            data.append('remote',true);
                        }
                        if(stuffToAdd.length > 0){
                            data.append('action',this.currentType === 'img' ? 'addToGallery' : 'addToVideoGallery' );
                            data.append('gallery',this.galleries[this.selected].identifier);
                            data.append('addresses' , JSON.stringify(stuffToAdd) );
                        }
                        break;
                    default:
                };
            }
            //Handle the rest of the request if it should be sent
            if(data.get('action')){
                this.isLoading = true;
                if(this.test){
                    data.append('req','test');
                };
                updateCSRFToken().then(
                    function(token){
                        data.append('CSRF_token', token);
                        fetch(
                            apiURL,
                            {
                                method: 'post',
                                body: data,
                                mode: 'cors'
                            }
                        )
                            .then(function (json) {
                                return json.text();
                            })
                            .then(function (data) {
                                let response = data;
                                if(verbose)
                                    console.log('Response data',response);
                                galleries.handleResponse(response, currentOperation);
                            })
                            .catch(function (error) {
                                alertLog('View initiation failed! '+error,'error',thisElement);
                            });
                    },
                    function(reject){
                        alertLog('CSRF token expired. Please refresh the page to submit the form.','error',thisElement);
                    }
                );
            }
            this.cancelOperation();
        },
        //Cancels the operation
        cancelOperation: function(){
            if(this.test)
                console.log('Canceling operation');
            if(this.currentMode === 'search'){
                this.selected = -1;
            }
            else if(this.currentMode === 'edit'){
                this.selectedGalleryMembers = [];
                this.resetEditorView();
            };
            this.operationInput = '';
            this.currentOperation = '';
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
                //Always display 'cancel' when there are selected members
                if(this.selectedGalleryMembers.length !== 0 &&  index === 'cancel')
                    return true;

                if(this.selectedGalleryMembers.length === 0 && index !== 'add')
                    return false;
                else if(this.selectedGalleryMembers.length > 0 && index !== 'remove')
                    return false;
            }

            return true;
        },
        shouldDisplayMode: function(index){
            if(index==='edit' && (this.selected === -1) )
                return false;
            if(index==='create' && (this.selected !== -1))
                return false;

            return true;
        },
        //Selects an element, if the mode is right
        selectElement: function(request){
            if(this.verbose)
                console.log('Selecting item ',request);

            if(!request.from)
                return;

            if(this.currentMode === 'search'){

                request = request.content;

                this.resetEditor();
                if(this.selected === request){
                    this.switchModeTo('edit');
                }
                else{
                    this.selected = request;
                }
            }
            else if(this.currentMode === 'edit'){

                if(request.from === 'editor-viewer2'){

                    let newTarget = request.key;
                    let targetName = newTarget.split('/').pop();
                    let element = this.editorView.elements[newTarget];
                    // Select/Unselect an image
                    if(!element.folder){
                        if(this.editorView.selected.indexOf(targetName) !== -1){
                            this.editorView.selected.splice(this.editorView.selected.indexOf(targetName),1);
                        }
                        else{
                            this.editorView.selected.push(targetName);
                        }
                    }
                    //Open a folder
                    else{
                        this.changeViewURL({from:'editor',content:newTarget});
                    }
                }
                else if(request.from === 'editor-search'){

                    request = request.content;

                    if(this.editorSearchList.selected.indexOf(request) !== -1){
                        this.editorSearchList.selected.splice(this.editorView.editorSearchList.indexOf(request),1);
                    }
                    else{
                        this.editorSearchList.selected.push(request);
                    }
                }

            }
        },
        //Selects a gallery element
        selectGalleryElement: function(request){
            if(this.verbose)
                console.log('Selecting gallery item ',request);
            if(this.selectedGalleryMembers.indexOf(request) !== -1){
                this.selectedGalleryMembers.splice(this.selectedGalleryMembers.indexOf(request),1);
            }
            else{
                this.selectedGalleryMembers.push(request);
            }
        },
        //Updates the galleries list
        updateGalleries: function(request){
        },
        //Handles responses of the API based on the operation
        handleResponse: function(response, currentOperation){
            this.isLoading = false;
            if(typeof response === 'string' && response.match(/^\d+$/))
                response = response - 0;
            if(this.currentMode === 'search')
                switch (currentOperation){
                    case 'delete':
                        switch (response){
                            case -1:
                                alertLog('Database connection failed!','warning',this.$el);
                                break;
                            case 0:
                                alertLog('Gallery deleted!','success',this.$el);
                                this.galleriesInitiated = false;
                                eventHub.$emit('refreshSearchResults');
                                break;
                            default :
                                alertLog('Operation failed with response: '+response,'error',this.$el);
                                break;
                        }
                        break;
                    case 'create':
                        switch (response){
                            case -1:
                                alertLog('Database connection failed!','warning',this.$el);
                                break;
                            case 1:
                                alertLog('Gallery name already exists!','info',this.$el);
                                break;
                            case 0:
                                alertLog('Gallery created!','success',this.$el);
                                this.galleriesInitiated = false;
                                eventHub.$emit('refreshSearchResults');
                                break;
                            default :
                                alertLog('Operation failed with response: '+response,'error',this.$el);
                                break;
                        }
                        break;
                }
            else if(this.currentMode === 'edit'){
                switch (currentOperation){
                    case 'remove':
                        switch (response){
                            case -1:
                                alertLog('Database connection failed!','warning',this.$el);
                                break;
                            case 0:
                                alertLog('Media removed from gallery!','success',this.$el);
                                break;
                            default :
                                alertLog('Operation failed with response: '+response,'error',this.$el);
                                break;
                        }
                        this.resetEditor();
                        break;

                    case 'add':

                        //First, handle invalid responses
                        if(!IsJsonString(response)){
                            alertLog('Operation failed with response: '+response,'error',this.$el);
                            return;
                        }
                        else
                            response = JSON.parse(response);

                        let responseBody = '';
                        let responseType = 'success';
                        let allAdded = true;
                        let serverError = false;
                        let failedItems = {};
                        for(let index in response){
                            let code = response[index];
                            switch (code){
                                case -1:
                                    serverError = true;
                                    allAdded = false;
                                    break;
                                case 0:
                                    break;
                                case 1:
                                    allAdded = false;
                                    failedItems[index] = 'Resource no longer exists';
                                    break;
                                case 2:
                                    serverError = true;
                                    allAdded = false;
                                    break;
                                case 3:
                                    allAdded = false;
                                    failedItems[index] = 'Resource already in collection';
                                    break;
                                default :
                                    allAdded = false;
                                    failedItems[index] = 'Failed with code '+code;
                                    break;
                            }
                        }
                        if(allAdded){
                            responseBody += 'All images added to gallery!';
                        }
                        else if(serverError){
                            responseBody += 'A server error occurred! Items were not added.';
                            responseType = 'error';
                        }
                        else{
                            responseType = 'info';
                            responseBody += '<div>Some resources were not added, for the following reasons:</div>';
                            for(let resourceName in failedItems){
                                responseBody += '<div>';
                                responseBody += '<span>' + resourceName + '</span>' +' : ' + '<span>' + failedItems[resourceName] + '</span>';
                                responseBody += '</div>';
                            }
                        }

                        alertLog(responseBody,responseType,this.$el);
                        this.resetEditor();
                        break;

                    case 'move':
                        switch (response){
                            case -1:
                                alertLog('Database connection failed!','warning',this.$el);
                                break;
                            case 0:
                                alertLog('Media moved!','success',this.$el);
                                //In this specific case, we only re-render the front end, not refresh the gallery.
                                let from = this.moveTargets[0];
                                let to = this.moveTargets[1];
                                let elementToMove = this.galleryMembers.splice(from,1)[0];
                                this.galleryMembers.splice(to,0,elementToMove);
                                break;
                            case 1:
                                alertLog('One of the media items no longer exists in the gallery!','success',this.$el);
                                break;
                            case2:
                                alertLog('Gallery no longer exists!','success',this.$el);
                                break;
                            default :
                                alertLog('Operation failed with response: '+response,'error',this.$el);
                                break;
                        }
                        //In this specific case, we assume all worked well on the success code and dont refresh the gallery.
                        if(response !== 0)
                            this.resetEditor();
                        break;
                }
            }
        },
        //Initiates the 2nd view
        updateSecondView: function(request){
            if(!request.from || request.from !== 'editor-viewer2')
                return;

            if(this.verbose)
                console.log('Recieved', request);

            const response = request.content;

            //If we got a valid view, update the app
            if(typeof response === 'object'){
                this.editorView.elements = response;
                this.editorView.upToDate = true;
            }
            //Handle errors
            else{
                if(this.test)
                    console.log('Error code: '+response);
            }
        },
        //Changes the view URL
        changeViewURL: function(request){

            if(!request.from || !(request.from === 'editor-viewer2' || request.from === 'editor' ) )
                return;

            if(this.verbose)
                console.log('Recieved', request);

            this.editorView.url = request.content;
            this.editorView.selected = [];
            this.editorView.upToDate = false;
        },
        //Resizes searchlist images
        resizeImages: function (timeout = 5) {

            let context = this;

            if(!this.editorSearchList.initiated && timeout > 0){
                if(this.verbose)
                    console.log('resizing images again, timeout '+timeout);
                setTimeout(function(){context.resizeImages(timeout-1)},1000);
                return;
            }
            else if(!this.editorSearchList.initiated && timeout === 0){
                if(this.verbose)
                    console.log('Cannot resize images, timeout reached!');
                return;
            }

            if(this.verbose)
                console.log('resizing images!');

            let searchItems = this.$el.querySelectorAll('#galleries .search-list .search-item');
            let verbose = this.verbose;
            for( let index in this.editorSearchList.items ){
                let element = searchItems[index];
                let image = element.querySelector('img');
                image.onload = function () {
                    let naturalWidth = image.naturalWidth;
                    let naturalHeight = image.naturalHeight;
                    if(naturalWidth < 320){
                        Vue.set(context.editorSearchList.items[index],'small',true);
                        if(verbose)
                            console.log('setting image '+index+' ass small');
                    }
                    else if(naturalHeight > naturalWidth){
                        Vue.set(context.editorSearchList.items[index],'vertical',true);
                        if(verbose)
                            console.log('cropping image '+index+' vertically', naturalWidth, naturalHeight);
                    }
                };
                if(image.complete)
                    image.onload();
            };
        },
        //Resets selected images in editor
        resetEditorView: function(){
            this.editorView.selected = [];
            this.editorSearchList.selected = [];
        }
    },
    watch:{
        'currentType':function(newType){
            if(this.verbose)
                console.log('Current type changed to ',newType);
            this.galleries = [];
            this.searchAgain();
        }
    },
    mounted: function(){
    }
});