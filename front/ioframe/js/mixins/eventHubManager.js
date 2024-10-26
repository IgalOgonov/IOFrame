/** Manages event hubs
 * This class helps register, and clean up events for components.
 * Without using this mixin, event registration will happen at each and every component creation, and the
 * events will be executed as many times as a component was created.
 * Using this class helps clean up that mess.
 * The only restriction - this mixin assumes components are created and destroyed in a form of a stack - first in last out.
 * If similar components are created one after the other, but not destroyed in a similar order, this mixin will behave
 * incorrectly.
 * **/
const eventHubManager = {
    data: function(){
        return{
            events:[],
            eventHub:null,
            /* A simpler way to register events with less of a boilerplate, as well as checking certain conditions, such as who the event was addressed to.
            *  Most of the functionality here can also be used for events with dynamic identifiers.
            * Format:
            * <string => event name> : {
            *   fn: function - function to be bound to this event.
            *   register: bool, default true - whether to register this at creation (obviously, events with dynamic names should be registered manually at their definition)
            *   from: string|string[], default null - Regex expression*, will only accept this event *from* sources with a specific identifier(s) matching the expression.
            *   to: string|string[], default null - Regex expression*, will only accept this event when addressed *to* a this identifier(s), same as from.
            *   fromExclude: string|string[], default null - same as from, but excludes
            *   toExclude: string|string[], default null - same as to, but excludes
            * }
            * *) All regex strings are automatically converted to RegExp, prefixed with ^ and affixed with $
            *
            * Usage:
            * this.registerEvents(string[] specificEvents = null) - register all defined events (only if eventsProperties.$eventNAme.register is true), or just specific ones.
            * this.checkIfRelevantEvent(string eventPropertiesIdentifier, object eventObject): bool - checks if the event is relevant to this specific module/component.
            *
            * Examples:
            *
            * eventsProperties: {
            *   'requestSelection': {
            *       fn: this.selectSearchElement,
            *       register: true,
            *       from: this.identifier+'-search',
            *       to: this.identifier
            *   },
            *   'updateSearchListElement':{
            *       fn: this.updateSearchListElement
            *   },
            *   'dynamicallyCreatedEvents':{
            *       from: this.identifier+'-some-component-\d+',
            *       register: false
            *   }
            * }
            *
            * // Register all events except 'dynamicallyCreatedEvents'
            * this.registerEvents();
            * // Same as above
            * this.registerEvents(['updateSearchListElement','requestSelection']);
            * // Call in event function to ensure event is relevant
            * if(!this.checkIfRelevantEvent('updateSearchListElement',event))
            *   return;
            * */
            eventsProperties:{
            }
        }
    },
    created: function(){
        if(this.verbose)
            console.log('Event hub manager mixin added!');
        for(let eventName in this.eventsProperties){
            const properties = this.eventsProperties[eventName];
            if(!properties.fn || (typeof properties.fn !== 'function')){
                delete this.eventsProperties[eventName];
                continue;
            }
            if(properties.register === undefined)
                properties.register = true;
        }
    },
    methods: {
        //Registers an event hub to use
        registerHub: function(eventHub){
            this.eventHub = eventHub;
        },
        //Registers an event at the hub
        registerEvent: function(eventName, eventFunction){
            this.eventHub.$on(eventName,eventFunction);
            this.events.push(eventName);
            const identifier = this.identifier ? this.identifier+' - ' : '';
            if(this.verbose)
                console.log(identifier + eventName + ' registered to hub.');
        },
        //Explained in eventsProperties
        registerEvents: function(specificEvents = null){
            for(let eventName in this.eventsProperties){
                const properties = this.eventsProperties[eventName];
                if( specificEvents && specificEvents.length && ( specificEvents.find((name) => name === eventName) === undefined ) ){
                    if(this.verbose)
                        console.log( eventName + ' not in specified events');
                    continue;
                }
                if(!properties.register){
                    if(this.verbose)
                        console.log( eventName + ' not to be registered by default');
                    continue;
                }
                this.registerEvent(eventName, properties.fn);
            }
        },
        //Checks if event is relevant to this function, returns boolean
        checkIfRelevantEvent: function(eventPropertiesIdentifier, eventObject){
            const properties = this.eventsProperties[eventPropertiesIdentifier];
            if(!properties || (typeof properties !== 'object')){
                if(this.verbose)
                    console.log( eventPropertiesIdentifier + ' properties not found');
                return false;
            }
            if(!eventObject || (typeof eventObject !== 'object')){
                if(this.verbose)
                    console.log('Invalid event object');
                return false;
            }
            //Checks for from/to share too much similar code to write this twice (and then twice again)
            let targets = ['from','to'];
            for(let i in targets){

                //This helps handle both original case, and fromExclude and toExclude
                let target = targets[i];
                let targetExclude = target+'Exclude';

                //Case where even is missing
                if(!eventObject[target] || (typeof eventObject[target] !== 'string')){
                    //Only matters if we need require from/to identifiers, not when we exclude
                    if(properties[target]){
                        if(this.verbose)
                            console.log(eventPropertiesIdentifier + ' - missing event '+target);
                        return !properties[target];
                    }
                }
                //Done this way for the sake of self-documentation, could really do this with [0,1]
                let targetTypes = ['include','exclude'];
                for(let j in targetTypes){
                    let include = targetTypes[j] === 'include';
                    let currentTarget = include ? target : targetExclude;
                    if(properties[currentTarget]){
                        //This will only happen once as the properties object is passed by reference
                        if(typeof properties[currentTarget] === 'string')
                            properties[currentTarget] = [properties[currentTarget]];
                        //Match at least one regex expression
                        let matchingRegexes = properties[currentTarget].filter((e)=> new RegExp('^'+e+'$').test(eventObject[target]));
                        //If include - needs to match at least one regex, if exclude - must not match anything
                        if((include && !matchingRegexes.length) || (!include && matchingRegexes.length)){
                            if(this.verbose)
                                console.log(eventPropertiesIdentifier + ' - event ' + target + (include ? ' not relevant to receiver' : ' excluded by receiver') );
                            return false;
                        }
                    }
                }
            }

            if(this.verbose)
                console.log(eventPropertiesIdentifier+ ' received - ', eventObject);
            return true;
        }
    },
    //Cleans up the hub
    destroyed: function(){
        for(let i = 0; i<this.events.length; i++){
            this.eventHub._events[this.events[i]].pop();
            if(this.eventHub._events[this.events[i]].length === 0)
                delete this.eventHub._events[this.events[i]];
        }
        const identifier = this.identifier ? this.identifier+' - ' : '';
        if(this.verbose)
            console.log(identifier+'events cleaned from hub.');
    }
};
