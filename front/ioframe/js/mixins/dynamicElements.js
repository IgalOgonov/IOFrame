/* Allows monitoring the specific app/component for window size and scrolling changes, and modifying specific dynamic
* elements that are dependent on it.
* */
const dynamicElements = {
    data: function(){
        return {
            //The state of the elements that change when you scroll near them
            dynamicElementState:{
                //Meta information
                '@':{
                    //Common query to select all relevant elements inside this module/component. The more precise, the better the performance.
                    query:'',
                    //Is usually appended with ".dynamic-element"
                    querySuffix:'.dynamic-element',
                    //Throttle time in milliseconds
                    throttle: 250,
                    //Throttle timer name - supports multiple modules without problems
                    timerName: ''
                },
                /* Objects of the form:
                 * identifier - element identifier - needs to be unique! {
                 *   //element specific state, depends on activation/deactivation
                 *   state:{
                 *      //Array of extra classes the element should have at this point in time - modified by activate/deactivate
                 *      classes: []
                 *   },
                 *   //The condition for the element activation/deactivation. -1 - "unchanged", 0 - "deactivate", 1 - "activate"
                 *   condition: function(element,context,identifier){
                 *      return -1;
                 *   }
                 *   //what to do once the element is activated - important to use Vue.set for reactivity!
                 *   activate: function(element,context,identifier){
                 *   }
                 *   //what to do once the element is deactivated - important to use Vue.set for reactivity!
                 *   deactivate: function(element,context,identifier){
                 *   }
                 * }
                 * */
            }
        }
    },
    created: function(){
        this.initiateDynamicElements();
    },
    mounted:function(){
        //Add scroll listener
        window.addEventListener('scroll', this.throttledCheckDynamicElements);
        window.addEventListener('resize', this.throttledCheckDynamicElements);
    },
    destroyed: function(){
        window.removeEventListener('scroll', this.throttledCheckDynamicElements);
        window.removeEventListener('resize', this.throttledCheckDynamicElements);
    },
    computed:{
        /* Calculates dynamic element classes so that they can be easily appended in the template.
         * Returns an object of the form "identifier" => ['dynamic-element',<identifier class>, ...]
        * */
        dynamicElementsClasses: function(){
            let classes = {};
            for(let i in this.dynamicElementState){
                if(i === '@')
                    continue;
                classes[i] = ["dynamic-element","element-identifier-"+i];
                if(this.identifier !== undefined)
                    classes[i].push("app-identifier-"+this.identifier);
                if(this.dynamicElementState[i].state.classes.length > 0)
                    classes[i] = [...classes[i],...this.dynamicElementState[i].state.classes];
            }
            return classes;
        }
    },
    methods:{
        initiateDynamicElements: function (){
            for(let i in this.dynamicElementState){
                if(i === '@'){
                    this.dynamicElementState[i].timerName = makeid(20,'')+(this.identifier? this.identifier+'-':'')+'throttle-timer';
                    continue;
                }
                if(this.dynamicElementState[i].state === undefined)
                    Vue.set(this.dynamicElementState[i],'state',{});
                if(this.dynamicElementState[i].state.classes === undefined)
                    Vue.set(this.dynamicElementState[i].state,'classes',[]);
                if(this.dynamicElementState[i].condition === undefined)
                    Vue.set(this.dynamicElementState[i],'condition',function(){
                        return -1;
                    });
                if(this.dynamicElementState[i].activate === undefined)
                    Vue.set(this.dynamicElementState[i],'activate',function(){
                    });
                if(this.dynamicElementState[i].deactivate === undefined)
                    Vue.set(this.dynamicElementState[i],'deactivate',function(){
                    });
            }
        },
        //Checks dynamic elements for changes, and activates their functions if need be
        throttledCheckDynamicElements: function(){
            throttle(this.checkDynamicElements,this.dynamicElementState['@'].throttle,this.dynamicElementState['@'].timerName)();
        },
        //Checks dynamic elements for changes, and activates their functions if need be
        checkDynamicElements: function(){
            let state = this.dynamicElementState;
            let meta = state['@'];
            let possibleElements = this.$el.parentElement.querySelectorAll(meta.query+meta.querySuffix+(this.identifier !== undefined ? ".app-identifier-"+this.identifier : ''));
            for(let i in possibleElements){
                if(!possibleElements.hasOwnProperty(i))
                    continue;
                let element = possibleElements[i];
                if(this.identifier && !element.classList.contains("app-identifier-"+this.identifier))
                    continue;
                let elementParams = null;
                let elementIdentifier = null;
                for(let j in element.classList){
                    if(typeof element.classList[j] === 'string' && element.classList[j].startsWith('element-identifier-')){
                        let internalIdentifier = element.classList[j].substring('element-identifier-'.length);
                        if(state[internalIdentifier]){
                            elementParams = state[internalIdentifier];
                            elementIdentifier = internalIdentifier;
                        }
                    }
                }
                if(elementParams === null)
                    continue;
                let condition = elementParams.condition(element,this,elementIdentifier);
                switch (condition){
                    case 0:
                        elementParams.deactivate(element,this,elementIdentifier);
                        break;
                    case 1:
                        elementParams.activate(element,this,elementIdentifier);
                        break;
                    default :
                }
            }
        }
    },
};
