Vue.component('media-selector', {
    name:'MediaSelector',
    mixins:[sourceURL,eventHubManager,searchListFilterSaver],
    props: {
        //Function to execute when we select an item. The function is this.selectFunction(<selected key>/<selected searchlist item>,this)
        //Will trigger each time we add an item in selectMultiple
        selectionFunction: {
            type: Function,
            default: function(item,context = this){
                if(context.verbose)
                    console.log('Selected',item);
                eventHub.$emit(context.identifier+'-selection-event',item);
            }
        },
        //If set to true, will automatically enter folders - no need to double click.
        //Will also consider items selected on first click instead of the second.
        //Only affects folders if selectMultiple is true
        quickSelect: {
            type: Boolean,
            default: true
        },
        //Whether we are selecting single items, or multiple
        selectMultiple: {
            type: Boolean,
            default: false
        },
        //Whether to force starting mode
        forceMode:{
            type: Boolean,
            default: false
        },
        //Whether we are dealing with images or videos - possible values are 'img' and 'vid'
        mediaType: {
            type: String,
            default: 'img'
        },
        //Starting mode - view or view-db
        mode: {
            type: String,
            default: 'view'
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
                    operations: {
                        'cancel': {
                            title: 'Cancel',
                            button: 'cancel-1'
                        }
                    },
                    title: 'Local Media'
                },
                'view-db': {
                    operations: {
                        'cancel': {
                            title: 'Cancel',
                            button: 'cancel-1'
                        }
                    },
                    title: 'Remote Media'
                }
            },
            currentMode: 'view',
            mediaURL: document.ioframe.rootURI + 'api/v1/media',
            view: {
                url: '',
                target: '',
                multipleTargets: [],
                elements: {},
                upToDate: false,
            },
            searchList: {
                //Filters to display for the search list
                filters: [
                    {
                        type: 'Group',
                        group: [
                            {
                                name: 'createdAfter',
                                title: 'Created After',
                                type: 'Datetime',
                                parser: function (value) {
                                    return Math.round(value / 1000);
                                }
                            },
                            {
                                name: 'createdBefore',
                                title: 'Created Before',
                                type: 'Datetime',
                                parser: function (value) {
                                    return Math.round(value / 1000);
                                }
                            }
                        ]
                    },
                    {
                        type: 'Group',
                        group: [
                            {
                                name: 'changedAfter',
                                title: 'Changed After',
                                type: 'Datetime',
                                parser: function (value) {
                                    return Math.round(value / 1000);
                                }
                            },
                            {
                                name: 'changedBefore',
                                title: 'Changed Before',
                                type: 'Datetime',
                                parser: function (value) {
                                    return Math.round(value / 1000);
                                }
                            }
                        ]
                    },
                    {
                        type: 'Group',
                        group: [
                            {
                                name: 'includeRegex',
                                title: 'Include',
                                placeholder: 'Text identifier includes',
                                type: 'String',
                                min: 0,
                                max: 64,
                                validator: function (value) {
                                    return value.match(/^[\w\.\-\_ ]{1,64}$/) !== null;
                                }
                            },
                            {
                                name: 'excludeRegex',
                                title: 'Exclude',
                                placeholder: 'Text identifier excludes',
                                type: 'String',
                                min: 0,
                                max: 64,
                                validator: function (value) {
                                    return value.match(/^[\w\.\-\_ ]{1,64}$/) !== null;
                                }
                            },
                        ]
                    }
                ],
                //Result comunts to display, and how to parse them
                columns: [
                    {
                        id: 'image',
                        custom: true,
                        title: 'Image',
                        parser: (
                            this.mediaType === 'img'?
                                function (item) {
                                    let src = item.dataType ?
                                        (document.ioframe.rootURI + 'api/v1/media?action=getDBMedia&address=' + item.identifier + '&lastChanged=' + item.lastChanged+'&resourceType=img')
                                        :
                                        item.identifier;
                                    return '<img src="' + src + '">';
                                }
                                :
                                function (item) {
                                    let src = item.dataType ?
                                        (document.ioframe.rootURI + 'api/v1/media?action=getDBMedia&address=' + item.identifier + '&lastChanged=' + item.lastChanged+'&resourceType=vid')
                                        :
                                        item.identifier;
                                    return '<video src="'+src+'" preload="metadata"></video>';
                                }
                        )
                    },
                    {
                        id: 'name',
                        custom: true,
                        title: 'Name',
                        parser: function (item) {
                            if (document.ioframe.selectedLanguage && (item[document.ioframe.selectedLanguage + '_name'] !== undefined))
                                return item[document.ioframe.selectedLanguage + '_name'];
                            else
                                return item.name ? item.name : (item.dataType ? item.identifier : 'Unnamed link');
                        }
                    },
                    {
                        id: 'lastChanged',
                        title: 'Last Changed',
                        parser: function (timestamp) {
                            timestamp *= 1000;
                            let date = timestampToDate(timestamp).split('-').reverse().join('-');
                            let hours = Math.floor(timestamp % (1000 * 60 * 60 * 24) / (1000 * 60 * 60));
                            let minutes = Math.floor(timestamp % (1000 * 60 * 60) / (1000 * 60));
                            let seconds = Math.floor(timestamp % (1000 * 60) / (1000));
                            if (hours < 10)
                                hours = '0' + hours;
                            if (minutes < 10)
                                minutes = '0' + minutes;
                            if (seconds < 10)
                                seconds = '0' + seconds;
                            return date + ', ' + hours + ':' + minutes + ':' + seconds;
                        }
                    },
                    {
                        id: 'identifier',
                        title: 'Media Identifier',
                        parser: function (identifier) {
                            return '<textarea disabled>' + identifier + '</textarea>';
                        }
                    }
                ],
                page: 0,
                limit: 25,
                total: 0,
                items: [],
                initiated: false,
                selected: this.selectMultiple? [] : -1,
                extraParams: {
                    getDB: 1
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
                            eventHub.$emit('resizeImages');
                        }
                    }
                }
            },
            lastMode: 'view', //This can only be 'view' or 'view-db' -
            isLoading: false
        }
    },
    created:function(){
        this.registerHub(eventHub);
        //Tells viewer to load initial target
        this.registerEvent('select', this.selectElement);
        this.registerEvent('requestSelection', this.selectSearchElement);
        this.registerEvent('changeURLRequest', this.changeURLRequest);
        this.registerEvent('viewElementsUpdated', this.updateViewElements);
        this.registerEvent('updateViewElement', this.updateViewElement);
        this.registerEvent('updateSearchListElement', this.updateSearchListElement);
        this.registerEvent(this.identifier+'SearchResults',this.parseSearchResults);
        this.registerEvent('goToPage',this.goToPage);
        this.registerEvent('resizeImages',this.resizeImages);
        this.registerEvent('resetSelection',this.cancelOperation);
        this._registerSearchListFilters(this.identifier+'-search',{}, {startListening:true,registerDefaultEvents:true});

        //Defaults
        this.currentMode = this.mode;
    },
    computed:{
    },
    methods:{
        //Switches to requested mode
        switchModeTo: function(newMode){
            if(this.currentMode === newMode)
                return;

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
                case 'cancel':
                    this.cancelOperation();
                    break;
            }
        },
        //Cancels the operation
        cancelOperation: function(request = null){

            if(this.verbose)
                console.log('Canceling operation!');

            if(request !== null && (!request.to || request.to !== this.identifier))
                return;

            this.currentOperation = '';
            this.operationInput = '';
            switch(this.currentMode){
                case 'view':
                    this.view.target = '';
                    Vue.set(this.view,'multipleTargets',[]);
                    break;
                case 'view-db':
                    this.searchList.selected = this.selectMultiple ? [] : -1;
                    break;
            }
        },
        shouldDisplayMode: function(index){

            switch (index){
                case 'view':
                    if(
                        this.currentMode === 'view-db' && this.forceMode
                    )
                        return false;
                    break;
                case 'view-db':
                    if(
                        this.currentMode === 'view' && this.forceMode
                    )
                        return false;
                    break;
            }

            return true;
        },
        //Selects an element in the search list
        selectSearchElement: function(request){
            if(this.verbose)
                console.log('requestSelection ',request);

            if(!request.from || request.from !== this.identifier+'-search')
                return;

            let index = request.content;
            let item = JSON.parse(JSON.stringify(this.searchList.items[index]));

            //Something new selected
            if(this.selectMultiple){
                this.selectionFunction(item,this);
                if(this.searchList.selected.indexOf(index) === -1){
                    this.searchList.selected.push(index);
                }
                //Any other case
                else{
                    this.searchList.selected.splice(this.searchList.selected.indexOf(index),1);
                }
            }
            else{
                if(this.quickSelect){
                    this.selectionFunction(item,this);
                }
                else{
                    if(this.searchList.selected !== index)
                        this.searchList.selected = index;
                    else{
                        this.selectionFunction(item,this);
                        this.searchList.selected = -1;
                    }
                }
            }
        },
        //Selects an element, if the mode is right
        selectElement: function(request){

            if(!request.from || request.from === this.identifier)
                return;

            if(this.verbose)
                console.log('Recieved', request);

            let item= JSON.parse(JSON.stringify(request.content));
            let isFolder = item.folder;
            let newTarget = request.key.split('/').pop();

            if(this.selectMultiple){
                let oldIndex = this.view.multipleTargets.indexOf(newTarget);
                if(oldIndex === -1)
                    this.view.multipleTargets.push(newTarget);
                else
                    this.view.multipleTargets.slice(oldIndex);
            }
            else{
                if(request.from === this.identifier+'-viewer'){

                    let oldTarget = this.view.target;

                    if(this.quickSelect){
                        if(isFolder){
                            const targetFolder = newTarget;
                            let newURL = this.view.url;
                            if(newURL != '')
                                newURL += '/';
                            this.changeURL(newURL+targetFolder);
                        }
                        else
                            this.selectionFunction(item,this);
                    }
                    else{
                        if(isFolder){
                            if(oldTarget !== newTarget)
                                this.view.target = newTarget;
                            else{
                                const targetFolder = newTarget;
                                let newURL = this.view.url;
                                if(newURL != '')
                                    newURL += '/';
                                this.changeURL(newURL+targetFolder);
                            }
                        }
                        else{
                            if(this.view.target !== newTarget)
                                this.view.target = newTarget;
                            else
                                this.selectionFunction(item,this);
                        }
                    }
                }
            }
        },
        changeURLRequest: function(request){
            if(this.verbose)
                console.log('Recieved changeURLRequest', request);

            if(!request.from || request.from !== this.identifier+'-viewer')
                return;

            this.changeURL(request.content)
        },
        changeURL: function(newURL){
            if(this.verbose)
                console.log('Changing url to '+newURL);
            //For now, handle only single selection, not deletion
            this.cancelOperation();
            this.view.url = newURL;
            this.view.target = '';
            this.view.upToDate = false;
        },
        //Updates the current view with what we got from a viewer
        updateViewElements: function(request){

            if(this.verbose)
                console.log('Recieved updateViewElements', request);

            if(!request.from || request.from === this.identifier)
                return;

            //If we got a valid view, update the app
            if(typeof request.content === 'object'){
                if(request.from === this.identifier+'-viewer'){
                    this.view.elements = request.content;
                    this.view.upToDate = true;
                }
            }
            //Handle errors
            else{
                if(this.verbose)
                    console.log('Error code: '+request.content);
            }
        },
        //Updates a single element currently selected in the searchlist (yes I know it's the same as updateViewElement, might combine them later)
        updateSearchListElement: function(request){

            if(this.verbose)
                console.log('Received', request);

            if(!request.from || request.from === this.identifier)
                return;

            //If we got a valid view, update the app
            let element = request.content;
            let target = this.searchList.items[this.searchList.selected];
            for(let key in element){
                target[key] = element[key];
            }
        },
        //Updates a single element of the current view
        updateViewElement: function(request){

            if(!request.from || request.from === this.identifier)
                return;

            if(this.verbose)
                console.log('Recieved', request);

            //If we got a valid view, update the app
            let element = request.content;
            let targetKey = (this.view.url==='')? this.view.target : this.view.url+'/'+this.view.target;
            for(let key in element){
                this.view.elements[targetKey][key] = element[key];
            }
            this.view.upToDate = false;
        },
        //Parses search results returned from a search list
        parseSearchResults: function(response){
            if(this.verbose)
                console.log('Received response',response);

            if(!response.from || response.from !== this.identifier+'-search')
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
                eventHub.$emit('resizeImages');
            };
        },
        //Goes to a different page
        goToPage: function(response){
            if(this.verbose)
                console.log('Recieved response',response);

            if(!response.from || response.from !== this.identifier+'-search')
                return;

            this.searchList.page = response.content;

            this.searchList.initiated = false;

            this.searchList.selected = -1;
        },
        //Resizes searchlist images
        resizeImages: function (timeout = 5) {

            let context = this;

            if(!this.searchList.initiated && timeout > 0){
                if(this.verbose)
                    console.log('resizing images again, timeout '+timeout);
                setTimeout(function(){context.resizeImages(timeout-1)},1000);
                return;
            }
            else if(!this.searchList.initiated && timeout === 0){
                if(this.verbose)
                    console.log('Cannot resize images, timeout reached!');
                return;
            }

            if(this.verbose)
                console.log('resizing images!');

            let searchItems = this.$el.querySelectorAll('.search-list .search-item');
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
    },
    template: `
    <div class="media-selector">
        <div class="loading-cover" v-if="isLoading">
        </div>

        <div class="types">
            <button
                v-for="(item,index) in modes"
                v-if="shouldDisplayMode(index)"
                @click.prevent="switchModeTo(index)"
                v-text="item.title"
                :class="{selected:(currentMode===index)}"
                class="positive-3"
                >
            </button>
        </div>

        <div v-if="currentMode==='view'"
            is="media-viewer"
             :media-type="mediaType"
             :url="view.url"
             :target="view.target"
             :multiple-targets="view.multipleTargets"
             :select-multiple="selectMultiple"
             :display-elements="view.elements"
             :initiate="!view.upToDate"
             :verbose="verbose"
             :test="test"
             :identifier="identifier+'-viewer'"
            ></div>

        <div  v-if="currentMode==='view-db'"
              is="search-list"
              :_functions="searchList.functions"
              :api-url="mediaURL"
              :extra-params="searchList.extraParams"
              :extra-classes="searchList.extraClasses"
              :api-action="mediaType === 'img' ? 'getImages' : 'getVideos'"
              :event-name="identifier+'SearchResults'"
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
              :identifier="identifier+'-search'"
            ></div>

    </div>
    `
});