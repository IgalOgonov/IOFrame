Vue.component('search-list', {
    mixins:[
        eventHubManager,
        sourceURL,
        IOFrameCommons,
        componentHookFunctions
    ],
    props: {
        //TODO Add infinite scroll support
        //Identifier
        identifier: {
            type: String
        },
        //The API url, e.g. document.ioframe.pathToRoot+"api/media"
        apiUrl: {
            type: String
        },
        //The base action required to request the data from the api, e.g. "getGalleries".
        //On v2 APIs, this might not be required
        apiAction: {
            type: String,
            default: null
        },
        //Elements we are displaying
        items: {
            type: Array
        },
        //The name of the event to emit on getting results
        eventName: {
            type: String,
            default: 'searchResults'
        },
        //The name of the event to emit when starting a search and sending current filters
        filtersEventName: {
            type: String,
            default: 'searchingFilters'
        },
        //The name of the event to emit on selection
        selectionEventName: {
            type: String,
            default: 'requestSelection'
        },
        //The name of the event to emit on selection
        goToPageEventName: {
            type: String,
            default: 'goToPage'
        },
        //Allows to control the default text in multilingual systems
        text:{
            type: Object,
            default: function(){
                return {
                    search:'Search',
                    pagination:{
                        total:'Total Pages:',
                        goTo:'Go To Page',
                        go:'Go',
                        resultNum:'#'
                    }
                };
            }
        },
        //Request parameters
        requestParams: {
            type: Object,
            default: function(){
                return {
                    'method':'get',
                    'mode':'cors',
                };
            }
        },
        //Constant extra parameters to include in the search
        extraParams: {
            type: Object,
            default: function(){
                return {
                    //Object of the form {'param'  : value}
                };
            }
        },
        //Amount of galleries per page
        limit: {
            type: Number,
            default: 50
        },
        //page
        page: {
            type: Number,
            default: 0
        },
        //Total number of matching results
        total: {
            type: Number,
            default: 0
        },
        /* Array of objects OR strings, columns of the items that need to be displayed, if objects then of the form:
         * {
         *   'id': String, <name of the identifier inside the item - remember, you can place the item key inside the item
         *                  during parsing.
         *                  Also, the separator '.' (dot) allows to dig deeper into an object. This assumes no valid key
         *                  has a dot as part of its name.>
         *   'title': String, <Title of the column you want to be displayed>,
         *   'parser': Function, parses the values to be displayed.
         *                       the output is HTML! Beware of XSS.
         *   'custom': Boolean, default false - if true, then the id is just the identifier of the column - the column itself
         *                      renders something custom based of the item. Parser then refers to the item as a whole
         *                      (rather than item[id] - what's referred to as column "value" earlier), and must be set.
         *   'idSuffix': String, sometimes, you to display different properties of the same object from the API response.
         *               In that case, the main ID will be similar - however, you need to differentiate beteen them somehow.
         *               This is what the ID suffix is for - it appends to the end of the ID in the HTML, but does not affect
         *               the way you get stuff from the object.
         * }.
         *
         * This assumes you recieve the results in an
         *
         * For example:
         *   [
         *   {
         *        id:'identifier',
         *        title:'Gallery Title',
         *        parser:function(name){
         *            return '<div>'+name.title+'</div>';
         *        }
         *    },
         *   {
         *        id:'identifier',
         *        title:'Gallery Subtitle',
         *        'idSuffix':'subtitle',
         *        parser:function(name){
         *            return '<div>'+name.subtitle+'</div>';
         *        }
         *    },
         *    {
         *        id:'created',
         *        title:'Date Created',
         *        parser:function(timestamp){
         *          timestamp *= 1000;
         *          let date = timestampToDate(timestamp).split('-').reverse().join('-');
         *          return '<div>'+date+'</div>';
         *        }
         *    },
         *    {
         *        id:'spec.model',
         *        title:'Car Model'
         *    },
         *   ]
         * */
        columns: {
            type: Array,
            default: function () {
                return []
            }
        },
        /* Array of filters - those are the additional POST parameters to send with the API request. They're of the form:
         * [
         *   name: String, <name of the POST parameter>. In case of a group, only serves as element name.
         *   title: String, <title to be displayed for the user>. In case of a group, may be ommitted.
         *   type: String, "Number"/"String"/"Date"/"Datetime"/"Select"/"List" or "Group". In the last case, it can contain an
         *                  array of filters. Only 1 level of grouping is currently possible!
         *                  IMPORTANT Date is automatically converted to timestamp, and is a Number for min/max/validator/parser purposes.
         *   group: Array, if type is "group" then this is an array of filter objects like this one.
         *   list: Array, If the type was "Select"/"List", you need to pass an array of objects of the form:
         *           {
         *               value: <value of choice>,
         *               [title: <title of choice>] - Only if "Select"
         *           },
         *    min: in case of number/date, this is the minimal value, in case of string this is the minimal length.
         *    max:  in case of number/date, this is the maximal value, in case of string this is the maximal length.
         *    default: default value. For "Date"/"Datetime", may be a number for timestamp, or "now" for current date.
         *    required: bool, default false - whether the filter has to have a value
         *    placeholder: placeholder text, if it's a string.
         *    tooltip: If defined, will create a tooltip. TODO
         *    validator: function that takes the current value as its first input and returns true/false on whether it
         *                 is valid. Defaults to function(){ return true; }
         *    parser: function that takes the current value as its first input, and parses it before sending the request.
         *              Defaults to function(value){ return value; },
         * ]
         *
         * For example, if you want to allow a user to filter by date creationDate, but only between 2018.01.01 and 2018.12.31,
         * and allow to filter by catsOwned number, but send the date in seconds rather than milliseconds:
         *
         * [
         *   {
         *       name:'creationDate',
         *       title:'User Creation Date',
         *       type:'Date',
         *       min:1514757600000,
         *       max:1546293600000,
         *       parser: function(value){ return Math.round(value/1000); }
         *   }
         * ]
         * */
        filters: {
            type: Array,
            default: function () {
                return []
            }
        },
        /* Function that takes ALL of the filters - an array of objects of the form {paramName:paramValue}
         *  and potentially parses it.
         * */
        parseFilters: {
            type: Function,
            default: function(filters){
                return filters;
            },
        },
        /* Allows "remembering" the filters (by the parent).
         * Is an object of the form:
         * {
         *   <filter "name", corresponding to the filters array "name" parameter> : <current value to set (directly via setting the element "value" attribute)>
         * }
         * */
        currentFilters: {
            type: Object,
            default: function(){
                return {};
            },
        },
        /* Automatically initiates a search when the key "Enter" is pressed inside the filters.
         * */
        filtersSearchOnEnter: {
            type: Boolean,
            default: true
        },
        /* Extra classes for an item. May be a string (a specific class for each item), a array of strings (similar), or
           a function (which parses the item, and returns a AN ARRAY OF STRINGS - even if it's one string) */
        extraClasses: {
            default: null
        },
        //Whether to render invisible titles before each item (so they can be displayed via CSS later if need be)
        invisibleTitles: {
            type: Boolean,
            default: true
        },
        //Show Number Of Results
        showResultNum: {
            type: Boolean,
            default: true
        },
        //Currently selected item/items
        selected: {
            default: -1
        },
        //Whether to check CSRF token on search (shouldn't be needed in 99% of the cases)
        checkCSRFToken: {
            type: Boolean,
            default: false
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
        //Whether to initiate the view on creation
        initiate: {
            type: Boolean,
            default: true
        },
        //Whether to initiate before update
        earlyInitiation: {
            type: Boolean,
            default: false
        },
    },
    data: function(){
        return {
            //Used to signify that we are currently waiting for an API
            initiating: false,
            //Default value for the go to page
            pageToGoTo:1
        }
    },
    methods: {

        //Requests item selection
        requestSelection: function(index){
            if(this.verbose)
                console.log('Requesting selection for '+index);

            let request = this.identifier ?
                {
                    from: this.identifier,
                    content: index
                }
                :
                index;

            eventHub.$emit(this.selectionEventName,request);
        },

        //Goes to a page. index -1 returns you to the previous page.
        goToPage: function(pageNum){

            let newPage;

            if(pageNum === 'goto')
                pageNum = this.pageToGoTo-1;

            if(pageNum < 0)
                newPage = Math.max(this.page - 1, 0);
            else
                newPage = Math.min(pageNum,this.pagesArray.length-1);

            if(this.page === newPage)
                return;

            let request =  this.identifier ?
                {
                from: this.identifier,
                content: newPage
                }
                :
                newPage;

            if(this.verbose)
                console.log('Emitting '+this.goToPageEventName,request);

            eventHub.$emit(this.goToPageEventName,request)
        },

        //Validates all filter variables.
        validateFilterVars(filters = []){

            //This makes it so that if a group of filters was defined an empty array, this function will cause an
            //infinite recursion. Don't do that!
            if(filters.length === 0)
                filters = this.filters;

            for(let k in filters){
                const filter = filters[k];
                //Recursively validate a group of filters
                if(filter.type === 'Group'){
                    let res = this.validateFilterVars(filter.group);
                    if(!res)
                        return false;
                }
                else if(filter.type !== 'List' && filter.type !== 'Select'){

                    const localFilter = this.$el.querySelector('.filters *[name="'+filter.name+'"]');

                    let value = localFilter? localFilter.value : null;
                    //Basic check
                    if(value === ''){
                        if(filter.required){
                            alertLog(
                                'Missing required input: '+(filter.title!== undefined? filter.title : filter.name),
                                'warning',
                                this.$el
                            );
                        }
                        else
                            continue;
                    }
                    switch(filter.type){
                        case 'Datetime':
                        case 'Date':
                            if(!value)
                                continue;
                            value = value.split('-').reverse();
                            [value[0], value[1]] = [value[1], value[0]];
                            value = dateToTimestamp(value.join('-'));
                            //In case of Datetime, add the minutes
                            if(filter.type === 'Datetime'){
                                let value2 = this.$el.querySelector('.filters *[name="@'+filter.name+'"]').value;
                                if(value2 !== ''){
                                    value2 = value2.split(':');
                                    value += value2[0]*3600*1000 + value2[1]*60*1000 ;
                                }
                            }
                            //Fallthrough - as date is basically a number
                        case 'Number':
                            //Min validator
                            if(filter.min !== undefined && value < filter.min){
                                alertLog(
                                    'Input '+(filter.title!== undefined? filter.title : filter.name)+' too small!',
                                    'warning',
                                    this.$el
                                );
                                return false;
                            }
                            //Max validator
                            if(filter.max !== undefined && value > filter.max){
                                alertLog(
                                    'Input '+(filter.title!== undefined? filter.title : filter.name)+' too big!',
                                    'warning',
                                    this.$el
                                );
                                return false;
                            }
                            //Provided validator
                            if(filter.validator !== undefined && !filter.validator(value)){
                                alertLog(
                                    'Input '+(filter.title!== undefined? filter.title : filter.name)+' failed validation!',
                                    'warning',
                                    this.$el
                                );
                                return false;
                            }
                            break;
                        case 'String':
                            //Min validator
                            if(filter.min !== undefined && value.length < filter.min){
                                alertLog(
                                    'Input '+(filter.title!== undefined? filter.title : filter.name)+' too short!',
                                    'warning',
                                    this.$el
                                );
                                return false;
                            }
                            //Max validator
                            if(filter.max !== undefined && value.length > filter.max){
                                alertLog(
                                    'Input '+(filter.title!== undefined? filter.title : filter.name)+' too long!',
                                    'warning',
                                    this.$el
                                );
                                return false;
                            }
                            //Provided validator
                            if(filter.validator !== undefined && !filter.validator(value)){
                                alertLog(
                                    'Input '+(filter.title!== undefined? filter.title : filter.name)+' failed validation!',
                                    'warning',
                                    this.$el
                                );
                                return false;
                            }
                            break;
                    }
                }
                else{
                    //TODO List/Select support - although they shouldn't really be filtered
                }
            }

            return true;
        },

        //Gets all filter variables (recursively works on groups). Will also parse the value.
        // rawValueStore, if passed, will store raw values.
        getFilterVars(filters = [], rawValueStore = null){

            let variables = {};

            //This makes it so that if a group of filters was defined an empty array, this function will cause an
            //infinite recursion. Don't do that!
            if(filters.length === 0)
                filters = this.filters;
            for(let k in filters){
                const filter = filters[k];
                //Recursively validate a group of filters
                if(filter.type === 'Group'){
                    Object.assign(variables, this.getFilterVars(filter.group, rawValueStore));
                }
                else if(filter.type !== 'List'){
                    let localFilter = this.$el.querySelector('.filters *[name="'+filter.name+'"]');
                    //Basic check
                    if(localFilter === null)
                        continue;

                    let value = localFilter.value;

                    if(typeof rawValueStore === 'object')
                        rawValueStore[filter.name] = value;

                    //Basic check
                    if(value === '')
                        continue;

                    //If the value is a date, parse it
                    if(filter.type === 'Date' || filter.type === 'Datetime'){
                        value = value.split('-').reverse();
                        [value[0], value[1]] = [value[1], value[0]];
                        value = dateToTimestamp(value.join('-'));
                    }

                    //In case of Datetime, add the minutes
                    if(filter.type === 'Datetime'){
                        let value2 = this.$el.querySelector('.filters *[name="@'+filter.name+'"]').value;
                        if(typeof rawValueStore === 'object')
                            rawValueStore['@'+filter.name] = value;
                        if(value2 !== ''){
                            value2 = value2.split(':');
                            value += value2[0]*3600*1000 + value2[1]*60*1000 ;
                        }
                    }

                    //Fix "true" and "false"
                    if(value === 'true')
                        value = true;
                    if(value === 'false')
                        value = false;
                    variables[filter.name] = value;
                }
                else{
                    //TODO LIST SUPPORT
                }
                //Parse the value
                if(filter.parser !== undefined)
                    variables[filter.name] = filter.parser(variables[filter.name]);
            }
            return variables;
        },

        search: function(params = {}){

            params.ignoreOffset = params.ignoreOffset ?? false;

            if(this.initiating){
                if(this.verbose)
                    console.log('Already initiating a search!');
                return;
            }

            //Validation
            if(this.verbose)
                console.log('Validating filter variables!');
            if(!this.validateFilterVars()){
                if(this.verbose)
                    console.log('Validation failed!');
                return;
            }

            this.initiating = true;

            //Parsing
            let rawFilers = {};
            let filterArray = this.getFilterVars([],rawFilers);
            filterArray = this.parseFilters(filterArray);

            if(this.verbose)
                console.log('Querying API at '+this.apiUrl+' with parameters ', filterArray,'extra parameters ',
                    this.extraParams,' limit '+this.limit+', offset '+this.limit*(this.page));

            let dontSendBody = this.requestParams.dontSendBody ?? false;
            let method = this.requestParams.method ?? 'get';
            let methodHasBody = !dontSendBody && (['put','post','patch'].indexOf(method)!== -1);

            //Data to be sent
            const appendToFormOrObject = function(data,key,value,methodHasBody){
                if(methodHasBody)
                    data.append(key, value);
                else
                    data[key] = value;
            }

            let data = methodHasBody? new FormData() : {};
            if(this.apiAction){
                appendToFormOrObject(data,'action',this.apiAction,methodHasBody);
            }

            //Add pagination
            appendToFormOrObject(data,'limit',this.limit,methodHasBody);
            if((this.page > 0) && !params.ignoreOffset)
                appendToFormOrObject(data,'offset',this.limit*(this.page),methodHasBody);

            //Add filters
            for(let key in filterArray){
                appendToFormOrObject(data,key,filterArray[key],methodHasBody);
            }

            //Add extra params - do not override filters!
            for(let key in this.extraParams){
                if(filterArray[key] === undefined)
                    appendToFormOrObject(data,key,encodeURIComponent(this.extraParams[key]),methodHasBody);
            }

            //Emit filters event
            let request =  this.identifier ?
                {
                    from: this.identifier,
                    content: rawFilers
                }
                :
                rawFilers;
            eventHub.$emit(this.filtersEventName,request);

            let requestParams = JSON.parse(JSON.stringify(this.requestParams));
            requestParams.verbose = this.verbose;
            requestParams.parseJSON = true;
            requestParams.identifier = this.identifier;
            requestParams.urlPrefix = '';
            requestParams.ignoreCSRF = !this.checkCSRFToken;
            if(!methodHasBody){
                requestParams.queryParams = requestParams.queryParams ?? {};
                for (let i in data)
                    requestParams.queryParams[i] = data[i];
            }
            if(params.ignoreOffset)
                requestParams.extraEvents = {
                    'resetSearchPage':true
                };

            this.apiRequest(
                methodHasBody?data:null,
                this.apiUrl,
                this.eventName,
                requestParams
            );
        },

        //Returns the absolute media URL
        absoluteMediaURL:function(relativeURL){
            return document.ioframe.rootURI + 'front/ioframe/img/'+relativeURL;
        },

        //Returns byts in readable size
        readableSize: function(bytes){
            return getReadableSize(bytes);
        },

        //Returns the HTML code that should be rendered, given a filter object.
        renderFilter(filterProperties){
            let result = '';
            if(filterProperties.type === 'Group'){

                if(filterProperties.name)
                    result +='<div name="'+filterProperties.name+'" class="filter-group">';
                else
                    result +='<div class="filter-group">';

                if(filterProperties.title)
                    result +='<h2 name="'+filterProperties.title+'"></h2>';

                for(let filter in filterProperties.group){
                    result += this.renderFilter(filterProperties.group[filter]);
                }
                result +='</div>';
            }
            else if(filterProperties.type !== 'List' && filterProperties.type !== 'Select'){

                if(filterProperties.title)
                    result +='<label for="'+filterProperties.name+'"> <h2>'+filterProperties.title+'</h2>';

                result +='<input ';
                result +='name="'+filterProperties.name+'" ';
                switch(filterProperties.type){
                    case 'Number':
                        result +='type="number" ';
                        if(filterProperties.default)
                            result +='value="'+filterProperties.default+'" ';
                        if(filterProperties.min)
                            result +='min="'+filterProperties.min+'" ';
                        if(filterProperties.max)
                            result +='max="'+filterProperties.max+'" ';
                        break;
                    case 'String':
                        result +='type="text" ';
                        if(filterProperties.default)
                            result +='value="'+filterProperties.default+'" ';
                        if(filterProperties.placeholder)
                            result +='placeholder="'+filterProperties.placeholder+'" ';
                        break;
                    case 'Datetime':
                    case 'Date':

                        result +='type="date" ';

                        let defaultTimestamp;

                        if(filterProperties.default){
                            if(typeof filterProperties.default === 'number')
                                defaultTimestamp = filterProperties.default;
                            else if(filterProperties.default === 'now'){
                                defaultTimestamp = getCurrentDate().getTime();
                            }
                            else
                                defaultTimestamp = '';
                        }

                        let date = '';

                        if(defaultTimestamp !== ''){
                            if(filterProperties.min)
                                defaultTimestamp = Math.max(defaultDate,filterProperties.min);

                            if(filterProperties.max)
                                defaultTimestamp = Math.min(defaultDate,filterProperties.max);

                            //Default date handling in JS is horrible
                            date = timestampToDate(defaultTimestamp);
                        }
                        if(date !== '')
                            result +='value="'+date+'" ';

                        break;
                }
                result +='>';

                //For Datetime, we need a special additional element
                if(filterProperties.type === 'Datetime')
                    result += '<input type="time" name="@'+filterProperties.name+'">';

                if(filterProperties.title)
                    result +='</label>';
            }
            else if(filterProperties.type === 'Select'){

                if(filterProperties.title)
                    result +='<label for="'+filterProperties.name+'"> <h2>'+filterProperties.title+'</h2>';

                result +='<select name="'+filterProperties.name+'"> ';
                    for(let k in filterProperties.list){
                        result +='<option value="'+filterProperties.list[k].value+'">';
                        result +=filterProperties.list[k].title??filterProperties.list[k].value;
                        result +='</option>';
                    }
                result +='</select >';

                if(filterProperties.title)
                    result +='</label>';
            }
            else{
                //TODO List support
            }

            return result;
        },

        //Calculates any classes an item might have
        calculateItemClasses: function(index){
            let item = this.items[index];
            let classes = ['search-item'];

            if(
                (typeof this.selected !== 'object' && this.selected===index) ||
                (typeof this.selected === 'object' && this.selected !== null && this.selected.indexOf(index) !== -1)
            )
                classes.push('selected');
            //Calculate extra classes if needed
            if(this.extraClasses){
                switch(typeof this.extraClasses){
                    case 'function':
                        let res = this.extraClasses(item);
                        if(typeof res === 'string')
                            classes.push(res);
                        else if(res)
                            classes = [...classes,...res];
                        break;
                    case 'object':
                        classes = [...classes,...this.extraClasses];
                        break;
                    //Default is 'string'
                    default:
                        classes.push(this.extraClasses);
                }
            }
            return classes;
        },

        //Renders the results of a search
        renderItem: function(index){

            let items = this.items;
            let item = items[index];
            let columns = this.columns;
            let result = '';



            //Render item
            for(let k in columns){
                let identifier;

                //Handler complex identifier
                if(columns[k].id.indexOf('.') !== -1)
                    identifier = columns[k].id.replace('.','-');
                else
                    identifier = columns[k].id;
                if(columns[k]['idSuffix'])
                    identifier+=columns[k]['idSuffix'];

                result += '<span class="search-item-property '+identifier+'">';

                let value;

                if((columns[k].id.indexOf('.') !== -1) && !columns[k].custom){
                    let identifiers = columns[k].id.split('.');
                    value = item;
                    for(let tempIdentifier in identifiers){
                        value = value[identifiers[tempIdentifier]];
                    }
                }
                else{
                    value = columns[k].custom? item : item[columns[k].id];
                }

                if(columns[k].parser !== undefined)
                    value = columns[k].parser(value);
                else if(columns[k].custom){
                    value = 'Custom value must have a parsing function';
                }

                //Enclose value
                value = '<span>'+value+'</span>';

                //Renders title
                if(this.invisibleTitles){
                    let title = columns[k].title? columns[k].title : columns[k].id;
                    value ='<h3 style="display:none" class="search-item-title">'+title+'</h3>' + value;
                }

                result += value;
                
                result += '</span>';
            }

            return result;
        },
        //What to do when we get search results
        gotSearchResults: function(response){
            if(!response.from || response.from !== this.identifier)
                return;
            this.initiating = false;
        }
    },
    computed:{
        //Returns an array of page numbers, based on limit and total
        pagesArray: function(){
            let pagesArray = [];
            let pages = Math.floor(this.total/this.limit) + ( ((this.total/this.limit)%1!==0) ? 1 : 0 );
            for(let i=0; i<pages; i++){
                pagesArray.push(i+1)
            }
            return pagesArray;
        },
        //Returns an array of up to 10 of the pages that should be currently displayed
        currentPages: function(){
            let result = this.pagesArray.slice(0);
            const startIndex = Math.max(0, this.page - 5 );
            const endIndex = Math.min( this.pagesArray.length, this.page + 5);
            return result.slice(startIndex,endIndex);
        },
        //Renders the results of a search
        renderTitles: function(){
            let columns = this.columns;
            //Render column titles
            let result = '';
            for(let k in columns){
                let identifier = (columns[k].id.indexOf('.') !== -1)?  columns[k].id.replace('.','-') : columns[k].id;
                if(columns[k]['idSuffix'])
                    identifier+=columns[k]['idSuffix'];
                result += '<span class="search-title '+columns[k].id+'">';
                result += columns[k].title;
                result += '</span>';
            }
            return result;
        },
        //Renders the filter list
        filterList: function(){
            const filters = this.filters;
            let result = '';
            for(let filter in filters){
                const filterProperties = filters[filter];
                result += this.renderFilter(filterProperties);
            }
            return result;
        }
    },
    watch:{
        //If the items changed, it means we are 100% done initiating
        items:{
            handler:function(){
                this.initiating = false;
            },
            deep:true
        }
    },
    created:function(){
        if(this.verbose)
            console.log('Search list ',this.identifier,' created');
        this.registerHub(eventHub);
        this.registerEvent('refreshSearchResults', this.search);
        this.registerEvent(this.eventName, this.gotSearchResults);

    },
    beforeMount:function(){
        if(this.verbose)
            console.log('Search list ',this.identifier,' beforeMount');
    },
    mounted:function(){
        if(this.verbose)
            console.log('Search list ',this.identifier,' mounted');
        if(this.initiate){
            if(this.verbose)
                console.log('Initiating at mounted');
            this.search();
        }
        if(Object.keys(this.currentFilters).length > 0){
            if(this.verbose)
                console.log('Setting filters at mounted');
            for(let identifier in this.currentFilters){
                if(!this.currentFilters.hasOwnProperty(identifier))
                    continue;
                let filter = this.$el.querySelector('.filters *[name="'+identifier+'"]');
                if(filter)
                    filter.value = this.currentFilters[identifier];
            }
        }
    },
    beforeUpdate: function(){
        if(this.verbose)
            console.log('Search list ',this.identifier,' beforeUpdate');
        if(this.initiate && this.earlyInitiation){
            if(this.verbose)
                console.log('Initiating before update');
            this.search();
        }
    },
    updated: function(){
        if(this.verbose)
            console.log('Search list ',this.identifier,' updated');
        if(this.initiate){
            if(this.verbose)
                console.log('Initiating at update');
            this.search();
        }
    },
    template: `
         <div class="search-list">
            <div class="filter-container" v-if="filters.length>0">
                <div v-html="filterList" v-on:keydown.enter="filtersSearchOnEnter ? search({ignoreOffset:true}) : ()=>{}" class="filters"></div>
                <button @click.prevent.prevent="search({ignoreOffset:true})"><img :src="sourceURL()+'img/icons/search-icon.svg'"><div v-text="text.search" ></div></button>
            </div>
            
            <div class="pagination" v-if="(showResultNum && items.length) || (pagesArray.length > 1)">
                
                <span v-if="showResultNum && total" class="result-num"> 
                    <span v-text="text.pagination.resultNum"></span> 
                    <span v-text="total"></span>
                </span>
                
                <span class="buttons-container">
                    <button v-if="pagesArray.length > 6 && (page+1) > 6" v-text="'<<'" @click.prevent="goToPage(0)"></button>
                    <button v-if="page > 0" @click.prevent="goToPage(-1)" v-text="'<'">  </button>
                    <span v-if="pagesArray.length > 6 && (page+1) > 6" v-text="'...'"></span>
                    <button v-for="(item,index) in currentPages"
                        v-text="item"
                        :class="{selected:(page===item-1)}"
                        @click.prevent="goToPage(item-1)"
                       ></button>
                    <span v-if="pagesArray.length - (page+1) > 5" v-text="'...'"></span>
                    <button  v-if="pagesArray.length - (page+1) > 0" @click.prevent="goToPage(page+1)"  v-text="'>'"></button>
                    <button  v-if="pagesArray.length - (page+1) > 5" @click.prevent="goToPage(pagesArray.length-1)"  v-text="'>>'"> </button>
                </span>
                <span class="total-pages"> 
                    <span v-text="text.pagination.total"></span> 
                    <span v-text="pagesArray.length"></span>
                </span>
                
                <span class="go-to-page"> 
                    <span v-text="text.pagination.goTo"></span>
                    <input type="number" v-model:value="pageToGoTo" :min="1" :max="pagesArray.length"> 
                    <button @click.prevent="goToPage('goto')" class="go-to" v-text="text.pagination.go"> </button>
                </span>
            </div>
            
            <div class="search-results">
                <div class="search-titles" v-html="renderTitles"></div>
                <div v-for="(item,index) in items"
                v-html="renderItem(index)"
                @click="requestSelection(index)"
                :class="calculateItemClasses(index)"></div>
            </div>
            
            <div class="pagination" v-if="(pagesArray.length > 1) && items.length > 9">
                <span class="buttons-container">
                    <button v-if="pagesArray.length > 6 && (page+1) > 6" v-text="'<<'" @click.prevent="goToPage(0)"></button>
                    <button v-if="page > 0" @click.prevent="goToPage(-1)" v-text="'<'">  </button>
                    <span v-if="pagesArray.length > 6 && (page+1) > 6" v-text="'...'"></span>
                    <button v-for="(item,index) in currentPages"
                        v-text="item"
                        :class="{selected:(page===item-1)}"
                        @click.prevent="goToPage(item-1)"
                       ></button>
                    <span v-if="pagesArray.length - (page+1) > 5" v-text="'...'"></span>
                    <button  v-if="pagesArray.length - (page+1) > 0" @click.prevent="goToPage(page+1)"  v-text="'>'"></button>
                    <button  v-if="pagesArray.length - (page+1) > 5" @click.prevent="goToPage(pagesArray.length-1)"  v-text="'>>'"> </button>
                </span>
                <span class="total-pages"> 
                    <span v-text="text.pagination.total"></span> 
                    <span v-text="pagesArray.length"></span>
                </span>
                
                <span class="go-to-page"> 
                    <span v-text="text.pagination.goTo"></span>
                    <input type="number" v-model:value="pageToGoTo" :min="1" :max="pagesArray.length"> 
                    <button @click.prevent="goToPage('goto')" class="go-to" v-text="text.pagination.go"> </button>
                </span>
            </div>
         </div>
        `
});