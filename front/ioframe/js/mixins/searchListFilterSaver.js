/** A reusable mixin for saving searchlist filters
 * **/
const searchListFilterSaver = {
    mixins:[eventHubManager],
    data:function(){
        return {
            //Saves search list filters - supports multiple search lists
            searchListFilters:{
                /*
                    <key, identifier of the searchlist>: {
                       filters: <object, corresponds to the filters object>,

                    }
                */
            },
            //Events to monitor (defaults to the default searchList filters event)
            searchListFiltersEventNames:[]
        }
    },
    created:function(){
        if(this.verbose)
            console.log('searchList filters added');
    },
    methods: {

        //Updates filters
        _updateFiltersFromRequest(response){
            if(!response.from || !this.searchListFilters[response.from])
                return;

            Vue.set(this.searchListFilters, response.from,response.content)
        },

        //Registers a new searchlist to listen to.
        //Optionally can initiate default events
        _registerSearchListFilters(id, defaultFilters = {}, params = {}){

            params.startListening = params.startListening ?? false;
            params.registerDefaultEvents = params.registerDefaultEvents ?? false;

            Vue.set(this.searchListFilters,id,defaultFilters);

            if(params.registerDefaultEvents)
                this._registerSearchListFilterEvent('searchingFilters');

            if(params.startListening)
                this._startListeningToSearchListFilters();
        },

        //Starts listening to filter events
        _registerSearchListFilterEvent: function(eventName){
            this.searchListFiltersEventNames.push(eventName);
        },

        //Starts listening to filter events
        _startListeningToSearchListFilters: function(){
            for (let i in this.searchListFiltersEventNames){
                this.registerEvent(this.searchListFiltersEventNames[i], this._updateFiltersFromRequest);
            }
        }

    }

};
