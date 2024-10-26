/** Common functions for control panel editor components
 * **/
const objectEditor = {
    data:function(){
        return {
            //Editable part of the object
            mainItem:{
            },
            //Defines the editable portion of the object - might be computed
            paramMap:{
                /*
                    <key, can be a string of comma seperated values, maps to each key in the object we get from the API>: {
                        title: string, defaults to key - human readable title of the key
                        edit: bool, default true - whether the parameter is editable
                        ignore: bool, default false - whether to completely ignore this param
                        required: bool, default false - whether this param is required
                        placeholder: string, default '' - placeholder for input
                        type: string, default 'text' - type of value, possible types: 'boolean', 'text', 'date', 'number', 'email', 'textArea', 'select'
                        list: object, default null - If type 'select', of the form value:title
                        display: bool, default true - whether to display the param at all
                        considerChanged: bool, default false - whether to consider the param changed when getting it
                        parseOnGet: function, default function(value){ return value; } - parse item when getting it from the API
                        parseOnDisplay: function, default function(value){ return value; } - parse item, output text/HTML to display
                        parseOnChange: function, default function(value){ return value; } - parse item when it's changed
                        displayHTML: bool, default false - whether to treat parseOnDisplay result as HTML or not
                        // Stuff related to updating the object
                        onUpdate: {
                            ignore: bool, default false - whether to ignore this param on update
                            parse: function, default function(value){ return value; } - parse value on update
                            validate: function, default function(value){ return true; } - additional validation
                            validateFailureMessage: string, default 'Parameter '+<key>+' failed validation!' - message to display when validation failed
                            requiredFailureMessage: string, default <title / key>+' must be set!' - message to append to required param title when it's not set on update
                            setName: string, default i - name of this param on setting an item (what to send to the API),
                            replaceEmpty: string, default null - if set, replaces empty value with this string
                        }
                        min: int, default null - smallest value for "number" or "date" fields
                        max: int, default null - largest value for "number" or "date" fields
                        pattern:string, default null - potential pattern "text" fields need to match
                        // Stuff related to boolean type params
                        button: {
                            positive: string, default 'Yes' - true value
                            negative: string, default 'No' - false value
                        }
                    }
                */
            },
            'recompute': {  //created dynamically if doesnt exist
                changed:false,
                paramMap: false
            }
        }
    },
    created:function(){
        //Shouldn't be needed, but just in case - it doesn't affect performance anyway
        if(!this.recompute)
            Vue.set(this,'recompute',{});
        this.recompute.changed = this.recompute.changed ?? false;
        this.recompute.paramMap = this.recompute.paramMap ?? false;
    },
    computed: {
        //This is a boilerplate. Implement separately in each component that needs to track more changes this way.
        changed: function(){
            if(this.recompute.changed)
                ;//Do nothing
            for(let i in this.mainItem){
                if(
                    this.mainItem[i] && this.mainItem[i].original !== undefined &&
                    (this.mainItem[i].original !== this.mainItem[i].current || this.paramMap[i].considerChanged)
                )
                    return true;
            }

            return false;
        },
    },
    methods: {

        //Sets main item, based on API response
        setMainItem(item){
            if(!this.paramMap)
                return;

            for(let i in item){

                if(typeof item[i] === 'object')
                    continue;

                this.setSingleParam(i);

                if(!this.paramMap[i].ignore)
                    this.mainItem[i] =
                        this.paramMap[i].edit ?
                            {
                                original:this.paramMap[i].parseOnGet(item[i]),
                                current:this.paramMap[i].parseOnGet(item[i])
                            }
                            :
                            this.paramMap[i].parseOnGet(item[i]);
            }

            for(let i in this.paramMap){
                if((item[i] === undefined || typeof item[i] === 'object') && !this.paramMap[i].ignore){
                    this.setSingleParam(i);
                    let prefixes = i.split('.');
                    let target = JSON.parse(JSON.stringify(item));
                    let j = 0;
                    while(target !== undefined && typeof target === 'object' && prefixes[j] && target[prefixes[j]]!== undefined){
                        target = target[prefixes[j++]];
                    }
                    let newItem = (target !== undefined && typeof target !== 'object')? target : null;
                    this.setSingleMainItem(i,newItem);
                }
            }


            this.initiated = true;
        },
        //Helper function for setMainItem
        setSingleParam: function(i){
            if(!this.paramMap)
                return;
            if(!this.paramMap[i])
                this.paramMap[i] ={};
            this.paramMap[i].ignore = this.paramMap[i].ignore !== undefined ? this.paramMap[i].ignore : false;
            this.paramMap[i].title = this.paramMap[i].title !== undefined ? this.paramMap[i].title : i;
            this.paramMap[i].edit = this.paramMap[i].edit !== undefined ? this.paramMap[i].edit: true;
            this.paramMap[i].type = this.paramMap[i].type !== undefined ? this.paramMap[i].type : "text";
            this.paramMap[i].display = this.paramMap[i].display !== undefined ?  this.paramMap[i].display: true;
            this.paramMap[i].considerChanged = this.paramMap[i].considerChanged !== undefined ?  this.paramMap[i].considerChanged: false;
            this.paramMap[i].required = this.paramMap[i].required !== undefined ?  this.paramMap[i].required: false;

            if(!this.paramMap[i].onUpdate)
                this.paramMap[i].onUpdate = {};
            this.paramMap[i].onUpdate.ignore = this.paramMap[i].onUpdate.ignore !== undefined ? this.paramMap[i].onUpdate.ignore : false;
            this.paramMap[i].onUpdate.parse = this.paramMap[i].onUpdate.parse !== undefined ? this.paramMap[i].onUpdate.parse : function(value){
                return value;
            };
            this.paramMap[i].onUpdate.validate = this.paramMap[i].onUpdate.validate !== undefined ? this.paramMap[i].onUpdate.validate : function(value){
                return true;
            };
            this.paramMap[i].onUpdate.validateFailureMessage = this.paramMap[i].onUpdate.validateFailureMessage !== undefined ? this.paramMap[i].onUpdate.validateFailureMessage : 'Parameter '+i+' failed validation!';
            this.paramMap[i].onUpdate.requiredFailureMessage = this.paramMap[i].onUpdate.requiredFailureMessage !== undefined ? this.paramMap[i].onUpdate.requiredFailureMessage : this.paramMap[i].title+' must be set!';
            this.paramMap[i].onUpdate.setName = this.paramMap[i].onUpdate.setName !== undefined ? this.paramMap[i].onUpdate.setName : i;

            this.paramMap[i].parseOnGet = this.paramMap[i].parseOnGet !== undefined ? this.paramMap[i].parseOnGet : function(value){
                return value;
            };
            this.paramMap[i].parseOnDisplay = this.paramMap[i].parseOnDisplay !== undefined ? this.paramMap[i].parseOnDisplay : function(value){
                return value;
            };
            this.paramMap[i].parseOnChange = this.paramMap[i].parseOnChange !== undefined ? this.paramMap[i].parseOnChange : function(value){
                return value;
            };
            this.paramMap[i].displayHTML = this.paramMap[i].displayHTML !== undefined ? this.paramMap[i].displayHTML : false;

            if(!this.paramMap[i].button)
                this.paramMap[i].button = {};
            this.paramMap[i].button.positive = this.paramMap[i].button.positive !== undefined ? this.paramMap[i].button.positive : 'Yes';
            this.paramMap[i].button.negative = this.paramMap[i].button.negative !== undefined ? this.paramMap[i].button.negative : 'No';
        },
        //Helper functin for setMainItem
        setSingleMainItem: function(i, item){
            if(!this.paramMap)
                return;
            this.mainItem[i] =
                this.paramMap[i].edit ?
                    {
                        original:this.paramMap[i].parseOnGet(item),
                        current:this.paramMap[i].parseOnGet(item)
                    }
                    :
                    this.paramMap[i].parseOnGet(item);
        },
        //Helper function for parsing items on set
        _setItemHelper: function(){
            if(!this.paramMap)
                return;
            let sendParams = {};
            let errors = {};
            for(let paramName in this.paramMap){

                let param = this.paramMap[paramName];
                let item = this.mainItem[paramName];

                if(
                    param.ignore ||
                    param.onUpdate.ignore ||
                    (item.current !== undefined && item.current === item.original && !param.considerChanged && !param.required)
                )
                    continue;
                else if(item.current === undefined){
                    sendParams[param.onUpdate.setName] = item;
                    continue;
                }

                if(param.required && item.current === null){
                    errors[paramName] = {
                        type:'missing',
                        message:param.onUpdate.requiredFailureMessage
                    };
                    alertLog(param.onUpdate.requiredFailureMessage,'warning',this.$el);
                    continue;
                }

                let paramValue = param.onUpdate.parse(item.current);

                if(!param.onUpdate.validate(paramValue)){
                    errors[paramName] = {
                        type:'validation',
                        message:param.onUpdate.validateFailureMessage
                    };
                    alertLog(param.onUpdate.validateFailureMessage,'warning',this.$el);
                    continue;
                }

                if( param.onUpdate.replaceEmpty && (paramValue === '') )
                    paramValue = param.onUpdate.replaceEmpty;

                sendParams[param.onUpdate.setName] = paramValue;
            }

            return {
                toSend:sendParams,
                errors: errors
            };
        },
        //Reset a single item
        resetSingleMainItem: function (i, recomputeChanged = true){
            if(this.mainItem[i].original === undefined)
                return;
            else
                this.mainItem[i].current = this.mainItem[i].original;
            if(recomputeChanged)
                this.recompute.changed = !this.recompute.changed;
        },
        //Resets all main items
        resetAllMainItems: function (){
            for(let i in this.mainItem){
                this.resetSingleMainItem(i,false);
            }
            this.recompute.changed = !this.recompute.changed;
        },
        /** Updates main item with new info.
         *  Either sets all original values to their current ones, but may also override them via argument
         *  @param specificStuff Object where each key corresponds to mainItem keys, each value is an override value
         * */
        updateMainItem:function (specificStuff = {}){
            for(let i in this.mainItem){
                if(typeof this.mainItem[i] !== 'object'){
                    if(specificStuff[i])
                        this.mainItem[i] = specificStuff[i];
                }
                else{
                    this.mainItem[i].original = specificStuff[i] ?? this.mainItem[i].current;
                }
            }
            this.recompute.changed = !this.recompute.changed;
        },

        /** Parses main item before set, constructs an object based on mainItem and paramMap, returns object of things to set and/or errors
         * @param params object of the form:
         *              'ignoreIDs': string[], default []. Ignore specific items in the map
         *              'alertOnError': bool, default true - whether to alert message on error
         * @return object of the form:
         *          {
         *              result:<object, each key is a valid param to set, each value is its value>,
         *              errors:<object, each key is param with an error, each value is error value, one of 'unset', 'invalid'>
         *          }
         * */
        prepareMainItemSet:function (
            params = {
                ignoreIDs: [],
                alertOnError: true,
            }
        ){
            let sendParams = {
                result:{},
                errors:{}
            };

            for(let paramName in this.paramMap){

                if(params.ignoreIDs.indexOf(paramName) !== -1)
                    continue;

                let param = this.paramMap[paramName];
                let item = this.mainItem[paramName];

                if(
                    param.ignore ||
                    param.onUpdate.ignore ||
                    (item.current !== undefined && item.current === item.original && !param.considerChanged && !param.required)
                )
                    continue;
                else if(item.current === undefined){
                    sendParams.result[param.onUpdate.setName] = item;
                    continue;
                }

                if(param.required && (item.current === null)){
                    let title = param.title? param.title : paramName;
                    sendParams.errors.title = 'unset';
                    if(params.alertOnError)
                        alertLog(title+' must be set!','warning',this.$el);
                    return sendParams;
                }

                let paramValue = param.onUpdate.parse(item.current);

                if(!param.onUpdate.validate(paramValue)){
                    sendParams.errors.title = 'invalid';
                    if(params.alertOnError)
                        alertLog(param.onUpdate.validateFailureMessage,'warning',this.$el);
                    return sendParams;
                }

                if(param.onUpdate.encodeURI)
                    paramValue = encodeURIComponent(paramValue);

                sendParams.result[param.onUpdate.setName] = paramValue;
            }

            return sendParams;
        }

    },

    /* At the moment, this mixin is not implemented in the form of components, which would communicate with the parent via events.
     * Thus, below is the boilerplate that should be pasted into the template.
     * At some point, this might be implemented as a separate list of components, but at least in this form, it allows for a bit more flexibility at the expanse of this long code block.
    *
        <form class="main-items">
            <div
                v-for="(item, key) in mainItem"
                v-if="!paramMap[key].edit && !paramMap[key].ignore"
                :class="['main-item','static',key.replace('.','-')]"
                >

                    <span class="title" v-text="paramMap[key].title?? key"></span>

                    <div
                     v-if="paramMap[key].type !== 'boolean'"
                     class="item-param"
                     :type="paramMap[key].type"
                     v-text="item"
                    ></div>

                    <button
                    v-else-if="paramMap[key].type === 'boolean'"
                    class="item-param"
                    v-text="item?(paramMap[key].button.positive?? 'Yes'):(paramMap[key].button.negative?? 'No')"
                     ></button>

            </div>

            <div
            v-for="(item, key) in mainItem"
            v-if="paramMap[key].edit && !paramMap[key].ignore"
            :class="['main-item',{changed:(item.current !== item.original || paramMap[key].considerChanged)},key.replace('.','-'),{required:paramMap[key].required},paramMap[key].type]"
            >

                <span class="title" v-text="paramMap[key].title?? key"></span>

                <input
                 v-if="!paramMap[key].type || ['text','date','number','email'].indexOf(paramMap[key].type) !== -1"
                 class="item-param"
                 :type="paramMap[key].type"
                 :min="(['date','number'].indexOf(paramMap[key].type) !== -1) && (paramMap[key].min ?? false)"
                 :max="(['date','number'].indexOf(paramMap[key].type) !== -1) && (paramMap[key].max ?? false)"
                 :pattern="(['text'].indexOf(paramMap[key].type) !== -1) && (paramMap[key].pattern ?? false)"
                 :placeholder="paramMap[key].placeholder ?? false"
                 v-model:value="item.current"
                 @change="item.current = paramMap[key].parseOnChange($event.target.value);recompute.changed = ! recompute.changed"
                >
                <button
                v-else-if="paramMap[key].type === 'boolean'"
                class="item-param"
                v-text="item.current?(paramMap[key].button.positive ?? 'Yes'):(paramMap[key].button.negative ?? 'No')"
                 @click.prevent="item.current = paramMap[key].parseOnChange(!item.current);recompute.changed = ! recompute.changed"
                 ></button>

                <textarea
                v-else-if="paramMap[key].type === 'textArea'"
                class="item-param"
                 :placeholder="paramMap[key].placeholder ?? false"
                 v-model:value="item.current"
                 @change="item.current = paramMap[key].parseOnChange($event.target.value);recompute.changed = ! recompute.changed"
                 ></textarea>

                <select
                v-else-if="paramMap[key].type === 'select'"
                class="item-param"
                 v-model:value="item.current"
                 @change="item.current = paramMap[key].parseOnChange($event.target.value);recompute.changed = ! recompute.changed"
                 >
                    <option v-for="(title,value) in paramMap[key].list" :value="value" v-text="title"></option>
                 </select>

                <button
                v-if="item.current !== item.original"
                class="reset-changes"
                 @click.prevent="resetSingleMainItem(key)"
                 >
                 <img :src="getMediaUrl('ioframe','icons/refresh-icon.svg')">
                </button>
            </div>
        </form>
    * */
};
