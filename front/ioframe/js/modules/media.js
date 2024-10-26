if(eventHub === undefined)
    var eventHub = new Vue();

//***************************
//****** MEDIA APP*******
//***************************//
var media = new Vue({
    el: '#media',
    name:'Media',
    mixins:[sourceURL,eventHubManager,searchListFilterSaver],
    data: function(){
        return {
            identifier: 'media',
                configObject: JSON.parse(JSON.stringify(document.siteConfig)),
            eventsProperties:{
            'select':{
                fn:this.selectElement,
                from:['viewer1','viewer2']
            },
            'requestSelection':{
                fn:this.selectSearchElement,
                from:'search'
            },
            'changeURLRequest':{
                fn:this.changeURLRequest,
                from:['viewer1','viewer2'] /* Could also be expressed as viewer\d */
            },
            'viewElementsUpdated':{
                fn:this.updateViewElements,
                from:['viewer1','viewer2']
            },
            'updateViewElement':{
                fn:this.updateViewElement,
                from:'editor',
            },
            'updateSearchListElement':{
                fn:this.updateSearchListElement,
                from:'editor',
            },
            'imageUploadedToServer':{
                fn:this.updateView,
                from:'uploader'
            },
            'searchResults':{
                fn:this.parseSearchResults,
                from:'search'
            },
            'goToPage':{
                fn:this.goToPage,
                from:'search'
            },
            'resizeImages':{
                fn:this.resizeImages,
                from:'media',
                to:'media'
            },
        },
            //Modes, and array of available operations in each mode
            modes: {
                'view':{
                    operations:{
                        'move':{
                            title:'Move'
                        },
                        'copy':{
                            title:'Copy'
                        },
                        'rename':{
                            title:'Rename (Filename / Identifier)'
                        },
                        'delete':{
                            title:'Delete',
                                button:'negative-1'
                        },
                        'deleteMultiple':{
                            title:'Delete Multiple',
                                button:'negative-1'
                        },
                        'create':{
                            title:'Create Folder'
                        },
                        'cancel':{
                            title:'Cancel',
                                button:'cancel-1'
                        }
                    },
                    title:'View Media'
                },
                'view-db':{
                    operations:{
                        'copy':{
                            title:'Copy'
                        },
                        'rename':{
                            title:'Rename (filename)'
                        },
                        'delete':{
                            title:'Delete',
                                button:'negative-1'
                        },
                        'deleteMultiple':{
                            title:'Delete Multiple',
                                button:'negative-1'
                        },
                        'cancel':{
                            title:'Cancel',
                                button:'cancel-1'
                        }
                    },
                    title:'View Remote Media'
                },
                'edit':{
                    operations:{},
                    title:'View/Edit Media'
                },
                'upload':{
                    operations:{},
                    title:'Upload Media'
                }
            },
            currentMode: 'view',
                //Types of media - Images or Videos
                currentType:'img',
            mediaTypes:{
            img:'Images',
                vid:'Videos'
        },
            currentOperation: '',
                operationInput:'',
            mediaURL: document.ioframe.rootURI + 'api/media',
            view1:{
            url: '',
                target:'',
                deleteTargets:[],
                elements: {},
            upToDate:false,
        },
            view2:{
                url: '',
                    target:'',
                    elements: {},
                upToDate:false,
            },
            searchList:{
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
                        id:'media',
                        custom:true,
                        title:'Media',
                        parser:function(item){
                            let src = item.dataType? (document.ioframe.rootURI+'api/media?action=getDBMedia&address='+item.identifier+'&lastChanged='+item.lastChanged) : item.identifier;
                            return '<img src="'+src+'">';
                        }
                    },
                    {
                        id:'name',
                        custom:true,
                        title:'Name',
                        parser:function(item){
                            const lang = document.ioframe.selectedLanguage;
                            if(lang && (item[lang+'_name'] !== undefined) )
                                return item[lang+'_name'] ?? item.identifier;
                            else
                                return item.name?? item.identifier;
                        }
                    },
                    {
                        id:'lastChanged',
                        title:'Last Changed',
                        parser:timeStampToReadableFullDate
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
                }
            },
            lastMode:'view', //This can only be 'view' or 'view-db' -
                isLoading:false,
            verbose:false,
            test:false
        }
    },
    created:function(){
        this.registerHub(eventHub);
        //Tells viewer to load initial target
        this.registerEvents();

        //Defaults
        if(this.configObject.media === undefined)
            this.configObject.media = [];

        if(this.configObject.media.local === undefined)
            this.currentMode = 'view';
        else
            this.currentMode = this.configObject.media.local ? 'view' : 'view-db';

    },
    computed:{
        //We don't need the main viewer when editing or uploading
        needViewer:function(){
            return this.currentMode === 'view';
        },
        //We need the second viewer only when creating a folder, moving or copying
        needSecondViewer:function(){
            const operations = ['move'];
            return operations.indexOf(this.currentOperation) !== -1;
        },
        //Main title
        title:function(){
            switch(this.currentMode){
                case 'view':
                case 'view-db':
                    return 'Media';
                case 'edit':
                    return 'Editing '+(this.lastMode === 'view' ? 'local' : 'remote')+' media';
                case 'upload':
                    return 'Uploading '+(this.lastMode === 'view' ? 'local': 'remote')+' media ' ;
                default:
            }
        },
        //Secondary title
        secondTitle:function(){
            switch(this.currentOperation){
                case 'move':
                    return 'Choose the folder to move the item into';
                default:
                    return '';
            }
        },
        //Text for current operation
        currentOperationText:function(){
            switch(this.currentOperation){
                case 'move':
                    return 'Move to selected item?';
                case 'copy':
                    return (this.currentMode === 'view' ? 'Choose the new filename:' : 'Choose the new identifier');
                case 'delete':
                    return 'Delete selected?';
                case 'rename':
                    return  (this.currentMode === 'view' ? 'Choose a new filename:' : 'Choose a new identifier:');
                case 'deleteMultiple':
                    return 'Delete selected?';
                case 'create':
                    return 'Choose a new the folder name:';
                default:
                    return '';
            }
        },
        //Text for current operation
        currentOperationHasInput:function(){
            switch(this.currentOperation){
                case 'copy':
                    return true;
                case 'create':
                    return true;
                case 'rename':
                    return true;
                default:
                    return false;
            }
        },
        //Whether the current mode has operations
        currentModeHasOperations:function(){
            return Object.keys(this.modes[this.currentMode].operations).length>0;
        },
        targetIsFolder:function(){
            return this.view1.target!== '' && (this.view1.target.indexOf('.') === -1);
        }
    },
    methods:{
        //Switches to requested mode
        updateView: function(request){
            if(!this.checkIfRelevantEvent('imageUploadedToServer',request))
                return;
            this.view1.upToDate = false;
        },
        //Switches to requested mode
        switchModeTo: function(newMode){
            if(this.currentMode === newMode)
                return;

            if(
                newMode === 'edit' &&
                (
                    (this.currentMode === 'view' && this.view1.target==='') ||
                    (this.currentMode === 'view-db' && this.searchList.selected.length !== 1)
                )
            ){
                alertLog('Please select the media before you view/edit it!','info',document.querySelector('#media'));
                return;
            }

            if(['view','view-db'].indexOf(newMode) !== -1 && this.lastMode !== newMode)
                this.lastMode = newMode;

            this.currentMode = newMode;

            this.cancelOperation();
        },
        //Initiates an operation
        operation: function(operation){
            if(this.verbose)
                console.log('Operation',operation);
            switch (operation){
                case 'copy':
                    let newName = (this.currentMode === 'view' ? this.view1.target : this.searchList.items[this.searchList.selected[0]].identifier);
                    if(newName.indexOf('.') !== -1){
                        newName = newName.split('.');
                        let extension = newName.pop();
                        newName[0] += (' copy');
                        newName.push(extension);
                        newName = newName.join('.');
                    }
                    else
                        newName += ' copy';
                    this.operationInput = newName;
                    this.currentOperation = operation;
                    break;
                case 'rename':
                    this.operationInput = (this.currentMode === 'view' ? this.view1.target : this.searchList.items[this.searchList.selected[0]].identifier);
                    this.currentOperation = operation;
                    break;
                case 'delete':
                    this.view1.deleteTargets.push((this.currentMode === 'view' ? this.view1.target : this.searchList.items[this.searchList.selected[0]].identifier));
                    this.view1.target = '';
                    this.currentOperation = 'deleteMultiple';
                    break;
                case 'cancel':
                    this.cancelOperation();
                    break;
                default:
                    this.currentOperation = operation;
            }
        },
        //Executes the operation
        confirmOperation: function(){
            if(this.verbose)
                console.log('Current Operation ', this.currentOperation ,'Current input ',this.operationInput);
            let data = new FormData();
            let apiURL = this.mediaURL;
            let test = this.test;
            let verbose = this.verbose;
            let currentOperation = this.currentOperation;
            let thisElement = this.$el;

            let source;
            let destination;

            switch (currentOperation){
                //Can only be done locally
                case 'move':
                    let oldURL = this.view1.url;
                    if(oldURL[oldURL.length-1]!=='/' && oldURL!=='')
                        oldURL += '/';
                    let newURL = this.view2.url;
                    if(newURL[newURL.length-1]!=='/' && newURL!=='')
                        newURL += '/';
                    source = oldURL+this.view1.target;
                    destination =  newURL+this.view1.target;
                    if(source === destination)
                        alertLog('Cannot move to the same folder!','warning',document.querySelector('#media'));
                    else{
                        data.append('action', this.currentType === 'img' ? 'moveImage' : 'moveVideo');
                        data.append('oldAddress', source);
                        data.append('newAddress', destination);
                        data.append('copy', false);
                        if(this.verbose)
                            console.log('Moving ',source,' to ',destination);
                    }

                    break;
                case 'copy':
                case 'rename':
                    if(this.currentMode === 'view' && this.view1.elements[this.operationInput] !== undefined){
                        alertLog(this.operationInput,' already exists, cannot '+currentOperation+'!','warning',document.querySelector('#media'));
                        this.cancelOperation();
                        return;
                    }
                    data.append('action', this.currentType === 'img' ? 'moveImage' : 'moveVideo');

                    if(this.currentMode === 'view'){
                        let oldURL = this.view1.url;
                        if(oldURL[oldURL.length-1]!=='/' && oldURL!=='')
                            oldURL += '/';
                        let newURL = oldURL;
                        source = oldURL+this.view1.target;
                        destination =  newURL+this.operationInput;
                    }
                    else{
                        source = this.searchList.items[this.searchList.selected[0]].identifier;
                        destination =  this.operationInput;
                    }

                    data.append('oldAddress', source);
                    data.append('newAddress', destination);
                    data.append('copy', currentOperation === 'copy');
                    data.append('remote', this.currentMode === 'view-db');
                    if(this.verbose)
                        console.log(currentOperation+' ',source,' to ',destination);
                    break;
                case 'deleteMultiple':
                    let deletionTargets = [];

                    if(this.currentMode === 'view'){
                        let url = this.view1.url;
                        if(url!== '')
                            url +='/';
                        this.view1.deleteTargets.forEach(function(item,index){
                            deletionTargets.push(url+item);
                        });
                    }
                    else{
                        let context = this;
                        this.searchList.selected.forEach(function(index){
                            deletionTargets.push(context.searchList.items[index].identifier);
                        });
                    }

                    if(deletionTargets.length>0){
                        data.append('action', this.currentType === 'img' ? 'deleteImages' : 'deleteVideos');
                        data.append('addresses', JSON.stringify(deletionTargets));
                    }
                    data.append('remote', this.currentMode === 'view-db');
                    if(this.verbose)
                        console.log('Deleting ',deletionTargets);
                    break;
                case 'create':
                    if(this.view1.elements[this.operationInput] !== undefined)
                        console.log(this.operationInput,' already exists, cannot create folder!');
                    else{
                        data.append('action', 'createFolder');
                        data.append('category', this.currentType);
                        let url = this.view1.url;
                        if(url!== '')
                            data.append('relativeAddress', this.view1.url);
                        data.append('name', this.operationInput);
                        if(this.verbose)
                            console.log('Creating ', this.operationInput);
                    }
                    break;
                default:
            }

            //Handle the rest of the request if it should be sent
            if(data.get('action')){
                this.isLoading = true;
                if(this.test){
                    data.append('req','test');
                }
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
                                media.handleResponse(response, currentOperation);
                            })
                            .catch(function (error) {
                                alertLog('View initiation failed! '+error,'error',thisElement);
                                eventHub.$emit('viewInitiated', error);
                            });
                    },
                    function(reject){
                        alertLog('CSRF token expired. Please refresh the page to submit the form.','error',thisElement);
                        eventHub.$emit('viewInitiated', request);
                    }
                );
            }
            this.cancelOperation();
        },
        //Cancels the operation
        cancelOperation: function(){
            if(this.verbose)
                console.log('Canceling operation');
            this.currentOperation = '';
            this.operationInput = '';
            switch(this.currentMode){
                case 'view':
                    this.view1.target = '';
                    Vue.set(this.view1,'deleteTargets',[]);
                    break;
                case 'view-db':
                    this.searchList.selected = [];
                    break;
            }
        },
        shouldDisplayOperation: function(index){
            if(this.currentMode === 'view'){
                if(
                    this.view1.target === '' &&
                    index !== 'deleteMultiple' &&
                    index !== 'create'
                )
                    return false;
                else if(
                    this.view1.target !== '' &&
                    (index === 'deleteMultiple' || index === 'create')
                )
                    return false;
                else if(
                    this.view1.target === '' &&
                    (index === 'cancel')
                )
                    return false;
            }
            else{
                if(index === 'create' || index === 'move')
                    return false;
                else if(
                    this.searchList.selected.length === 0
                )
                    return false;
                else if(
                    this.searchList.selected.length === 1 &&
                    index === 'deleteMultiple'
                )
                    return false;
                else if(
                    (this.searchList.selected.length > 1 || this.searchList.items[this.searchList.selected[0]] && !this.searchList.items[this.searchList.selected[0]].dataType) &&
                    ( index === 'copy' )
                )
                    return false;
                else if(
                    this.searchList.selected.length > 1 &&
                    (index !== 'deleteMultiple' && index !== 'cancel')
                )
                    return false;
            }

            return true;
        },
        shouldDisplayMode: function(index){

            switch (index){
                case 'edit':
                    if(
                        this.currentMode === 'upload' ||
                        ( this.currentMode === 'view' && (this.view1.target==='' || this.view1.targetIsFolder) ) ||
                        (this.currentMode === 'view-db' && this.searchList.selected.length !== 1)
                    )
                        return false;
                    break;
                case 'upload':
                    if(
                        this.currentMode === 'edit' ||
                        ( this.currentMode === 'view' && (this.view1.target!=='') ) ||
                        (this.currentMode === 'view-db' && this.searchList.selected.length < 0)
                    )
                        return false;
                    break;
            }

            return true;
        },
        //Selects an element in the search list
        selectSearchElement: function(request){
            if(!this.checkIfRelevantEvent('requestSelection',request))
                return;

            let index = request.content;

            //Something new selected
            if(this.searchList.selected.indexOf(index) === -1){
                this.searchList.selected.push(index);
            }
            //only one item selected and we are clicking it again
            else if(this.searchList.selected.length === 1){
                this.switchModeTo('edit');
            }
            //Any other case
            else{
                this.searchList.selected.splice(this.searchList.selected.indexOf(index),1);
            }

            if(this.currentOperation && !this.shouldDisplayOperation(this.currentOperation))
                this.cancelOperation();
        },
        //Selects an element, if the mode is right
        selectElement: function(request){

            if(!this.checkIfRelevantEvent('select',request))
                return;

            let isFolder = request.content.folder;
            let newTarget = request.key.split('/').pop();
            if(this.currentOperation === 'deleteMultiple'){
                let oldIndex = this.view1.deleteTargets.indexOf(newTarget);
                if(oldIndex === -1)
                    this.view1.deleteTargets.push(newTarget);
                else
                    this.view1.deleteTargets.slice(oldIndex);
            }
            else{
                if(request.from === 'viewer1'){

                    let oldTarget = this.view1.target;

                    if(isFolder){
                        if(oldTarget !== newTarget)
                            this.view1.target = newTarget;
                        else{
                            const targetFolder = newTarget;
                            let newURL = this.view1.url;
                            if(newURL != '')
                                newURL += '/';
                            this.changeURL('viewer1',newURL+targetFolder);
                        }
                    }
                    else{
                        if(this.view1.target !== newTarget)
                            this.view1.target = newTarget;
                        else
                            this.switchModeTo('edit')
                    }
                }
                else if(request.from === 'viewer2'){

                    let oldTarget = this.view2.target;

                    if(isFolder){
                        if(oldTarget !== newTarget)
                            this.view2.target = newTarget;
                        else{
                            const targetFolder = newTarget;
                            let newURL = this.view2.url;
                            if(newURL != '')
                                newURL += '/';
                            this.changeURL('viewer2',newURL+targetFolder);
                        }
                    }
                    else{
                        if(this.view2.target !== request.key)
                            this.view2.target = request.key;
                        else
                            this.view2.target = '';
                    }
                }
            }
        },
        changeURLRequest: function(request){

            if(!this.checkIfRelevantEvent('changeURLRequest',request))
                return;

            this.changeURL(request.from,request.content)
        },
        changeURL: function(viewer, newURL){
            if(this.verbose)
                console.log('Changing '+viewer+' url to '+newURL);
            //For now, handle only single selection, not deletion
            if(viewer === 'viewer1'){
                this.cancelOperation();
                this.view1.url = newURL;
                this.view1.target = '';
                this.view1.upToDate = false;
            }
            else if(viewer === 'viewer2'){
                this.view2.url = newURL;
                this.view2.target = '';
                this.view2.upToDate = false;
            }
        },
        //Updates the current view with what we got from a viewer
        updateViewElements: function(request){

            if(!this.checkIfRelevantEvent('viewElementsUpdated',request))
                return;

            //If we got a valid view, update the app
            if(request.from === 'viewer1'){
                this.view1.elements = request.content;
                this.view1.upToDate = true;
            }
            else if(request.from === 'viewer2'){
                this.view2.elements = request.content;
                this.view2.upToDate = true;
            }
        },
        //Updates a single element currently selected in the searchlist (yes I know it's the same as updateViewElement, might combine them later)
        updateSearchListElement: function(request){

            if(!this.checkIfRelevantEvent('updateSearchListElement',request))
                return;

            //If we got a valid view, update the app
            let element = request.content;
            let target = this.searchList.items[this.searchList.selected];
            for(let key in element){
                Vue.set(target,key,element[key]);
            }
        },
        //Updates a single element of the current view
        updateViewElement: function(request){

            if(!this.checkIfRelevantEvent('updateViewElement',request))
                return;

            //If we got a valid view, update the app
            let element = request.content;
            let targetKey = (this.view1.url==='')? this.view1.target : this.view1.url+'/'+this.view1.target;
            for(let key in element){
                this.view1.elements[targetKey][key] = element[key];
            }
            this.view1.upToDate = false;
        },
        //Handles responses of the API based on the operation
        handleResponse: function(response, currentOperation){
            if(this.verbose)
                console.log(response,currentOperation);
            this.isLoading = false;
            if(this.currentMode === 'view')
                this.changeURL('viewer1',this.view1.url);
            else{
                this.cancelOperation();
                this.searchList.initiated = false;
            }
        },
        //Parses search results returned from a search list
        parseSearchResults: function(response){
            if(!this.checkIfRelevantEvent('searchResults',response))
                return;

            //Either way, the galleries should be considered initiated
            this.searchList.items = [];
            this.searchList.initiated =  true;

            //In this case the response was an error code, or the page no longer exists
            if(response.content['@'] === undefined)
                return;

            this.searchList.total = (response.content['@']['#'] - 0) ;
            delete response.content['@'];

            for(let k in response.content){
                response.content[k].identifier = k;
                this.searchList.items.push(response.content[k]);
            }

            this.searchList.functions.updated = function(){
                eventHub.$emit('resizeImages',{from:'media',to:'media'});
            };
        },
        //Goes to a different page
        goToPage: function(response){
            if(!this.checkIfRelevantEvent('goToPage',response))
                return;

            this.searchList.page = response.content;

            this.searchList.initiated = false;

            this.searchList.selected = -1;
        },
        //Resizes searchlist images
        resizeImages: function (request, timeout = 5) {

            if((timeout === 5) && !this.checkIfRelevantEvent('resizeImages',request))
                return;

            let context = this;

            if(!this.searchList.initiated && timeout > 0){
                if(this.verbose)
                    console.log('resizing images again, timeout '+timeout);
                /*After all those years, this is still the least annoying way to deal with async*/
                setTimeout(function(){context.resizeImages(request, timeout-1)},1000);
                return;
            }
            else if(!this.searchList.initiated && timeout === 0){
                if(this.verbose)
                    console.log('Cannot resize images, timeout reached!');
                return;
            }

            if(this.verbose)
                console.log('resizing images!');

            let searchItems = this.$el.querySelectorAll('#media .search-list .search-item');
            let verbose = this.verbose;
            for( let index in this.searchList.items ){
                let element = searchItems[index];
                let image = element.querySelector('img');
                if(image){
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
                }
            }
        }
    },
    mounted: function(){
    },

    watch:{
        'currentType':function(newType){
            if(this.verbose)
                console.log('Current type changed to ',newType);
            this.cancelOperation();
            Vue.set(this.view1,'elements', {});
            Vue.set(this.view1,'url', '');
            Vue.set(this.view1,'target', '');
            Vue.set(this.view1,'upToDate', false);
            Vue.set(this.view2,'elements', {});
            Vue.set(this.view2,'url', '');
            Vue.set(this.view2,'target', '');
            Vue.set(this.view2,'upToDate', false);
            Vue.set(this.searchList,'items', []);
            Vue.set(this.searchList,'initiated', false);
            Vue.set(this.searchList.columns[0],'parser',
                (
                newType === 'img' ?
                    function(item){
                        let src = item.dataType? (document.ioframe.rootURI+'api/media?action=getDBMedia&address='+item.identifier+'&resourceType=img&lastChanged='+item.lastChanged) : item.identifier;
                        return '<img src="'+src+'">';
                    }
                    :
                    function(item){
                        let src = item.dataType? (document.ioframe.rootURI+'api/media?action=getDBMedia&address='+item.identifier+'&resourceType=vid&lastChanged='+item.lastChanged) : item.identifier;
                        return '<video src="'+src+'" preload="metadata">';
                    }
                )
            );
        }
    },
    template: `
    <div id="media" class="main-app">
        <div class="loading-cover" v-if="isLoading">
        </div>
    
        <h1 v-if="title!==''" v-text="title"></h1>
    
        <div class="modes">
            <button
                v-for="(item,index) in modes"
                v-if="shouldDisplayMode(index)"
                @click="switchModeTo(index)"
                v-text="item.title"
                :class="{selected:(currentMode===index)}"
                class="positive-3"
                >
            </button>
        </div>
        <div class="operations-container"  v-if="currentModeHasOperations">
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
    
        <div class="operations" v-if="currentModeHasOperations && currentOperation !== ''">
            <div class="input-container">
                <label :for="currentOperation" v-text="currentOperationText"></label>
                <input
                    v-if="currentOperationHasInput"
                    :name="currentOperation"
                    v-model:value="operationInput"
                    type="text"
                    >
            </div>
            <button :class="(currentOperation === 'deleteMultiple' || currentOperation === 'delete')? 'negative-1':'positive-1'" @click="confirmOperation" >
                <div v-text="'Confirm'"></div>
                <img :src="sourceURL() + '/img/icons/confirm-icon.svg'">
            </button>
            <button class="cancel-1" @click="cancelOperation" >
                <div v-text="'Cancel'"></div>
                <img :src="sourceURL() + '/img/icons/cancel-icon.svg'">
            </button>
        </div>
    
        <div class="types">
            <span class="title">Media Type: </span>
            <select class="types" v-model:value="currentType" :disabled="currentMode!=='view-db' && currentMode!=='view'">
                <option
                    v-for="(item,index) in mediaTypes"
                    :value="index"
                    :class="{selected:currentType === index}"
                    v-text="item? item : ' - '"
                    >
                </option>
            </select>
        </div>
    
        <div v-if="needViewer">
            <div is="media-viewer"
                 :media-type="currentType"
                 :url="view1.url"
                 :target="view1.target"
                 :multiple-targets="view1.deleteTargets"
                 :select-multiple="currentOperation==='deleteMultiple'"
                 :display-elements="view1.elements"
                 :initiate="!view1.upToDate"
                 :verbose="verbose"
                 :test="test"
                 identifier="viewer1"
                ></div>
    
            <h2 v-if="secondTitle!==''" v-text="secondTitle"></h2>
            <div is="media-viewer"
                 v-if="needSecondViewer"
                 :media-type="currentType"
                 :url="view2.url"
                 :target="view2.target"
                 :display-elements="view2.elements"
                 :initiate="!view2.upToDate"
                 :test="test"
                 :verbose="verbose"
                 :only-folders="true"
                 identifier="viewer2"
                >
            </div>
        </div>
    
        <div v-if="currentMode==='view-db'">
            <div  is="search-list"
                  :_functions="searchList.functions"
                  :api-url="mediaURL"
                  :extra-params="searchList.extraParams"
                  :extra-classes="searchList.extraClasses"
                  :api-action="(currentType === 'img' ? 'getImages' : 'getVideos')"
                  :page="searchList.page"
                  :limit="searchList.limit"
                  :total="searchList.total"
                  :items="searchList.items"
                  :initiate="!searchList.initiated"
                  :columns="searchList.columns"
                  :filters="searchList.filters"
                  :selected="searchList.selected"
                  :test="test"
                  :verbose="verbose"
                  identifier="search"
                >
            </div>
        </div>
    
        <div  v-if="currentMode==='upload'"
              is="media-uploader"
              :media-type="currentType"
              :type="lastMode === 'view'? 'local' : 'remote'"
              :url="view1.url"
              :test="test"
              :verbose="verbose"
              identifier="uploader"
            >
        </div>
    
        <div v-if="currentMode==='edit'"
             is="media-editor"
             :media-type="currentType"
             :type="lastMode === 'view'? 'local' : 'remote'"
             :url="view1.url"
             :target="lastMode==='view' ? view1.target : searchList.items[searchList.selected[0]].identifier"
             :image="lastMode==='view' ? view1.elements[(view1.url==='')? view1.target : view1.url+'/'+view1.target] : searchList.items[searchList.selected[0]]"
             :verbose="verbose"
             :test="test"
             identifier="editor">
            </div>
        </div>
    </div>
    `
});