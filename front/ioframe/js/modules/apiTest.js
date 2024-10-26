if(eventHub === undefined)
    var eventHub = new Vue();

//***************************
//******USER LOGIN APP*******
//***************************//
//The plugin list component, which is responsible for everything
var apiTest = new Vue({
    el: '#apiTest',
    data: {
        target:'',
        content:'',
        imgName:'',
        req: 'test',
        inputs: '',
        resp: '',
        separateVariables: false,
        forceParamsToQuery: '',
        requestContentType: '',
        newVariableName: '',
        apiTarget:'',
        apiMethod:'post',
        variables:{}
    },
    methods:{
        send: function(){
            //output user inputs for testing
            this.resp = "Waiting...";
            let context = this;
            let methodHasBody = !this.forceParamsToQuery && (['put','post','patch'].indexOf(context.apiMethod)!== -1);

            //Data to be sent
            let data = new FormData();
            let queryParams = [];
            if(this.separateVariables)
                for(let name in this.variables){
                    if(methodHasBody)
                        data.append(name, this.variables[name].value);
                    else
                        queryParams.push(name+' = '+this.variables[name].value);
                }
            else{
                let contentIsJSON = IsJsonString(this.content);
                let contentArray = contentIsJSON? Object.entries(JSON.parse(this.content)) : this.content.split('&');
                contentArray.forEach(function(entry) {
                    let postPair = contentIsJSON ? entry: entry.split('=');
                    console.log(postPair);

                    if(postPair[1] === undefined)
                        postPair[1] = '';

                    if(methodHasBody){
                        data.append(postPair[0], postPair[1]);
                    }
                    else{
                        queryParams.push(postPair[0]+'='+postPair[1]);
                    }

                });
            }
            if(methodHasBody)
                data.append('req', this.req);
            else
                queryParams.push('req='+this.req);
            let image = document.querySelector('#uploaded1');
            let imageName = document.querySelector('#imgName');
            imageName = imageName.value? imageName.value : 'image';
            if(image.files.length>0){
                data.append(imageName, image.files[0]);
            }
            let file = document.querySelector('#uploaded2');
            if(file.files.length>0){
                data.append('file', file.files[0]);
            }
            this.inputs="Target:"+this.target+", Content:"+JSON.stringify(data);
            //Api url
            let url=document.ioframe.pathToRoot+"api/"+(this.apiTarget?this.apiTarget+'/':'')+this.target;
            //Request itself
            updateCSRFToken().then(
                function(token){
                    if(methodHasBody)
                        data.append('CSRF_token', token);
                    else
                        queryParams.push('CSRF_token='+token);
                    console.log(data);
                    let init = {
                        method: context.apiMethod,
                        mode: 'cors'
                    };
                    if(context.requestContentType)
                        init.headers = {
                            'Content-Type': context.requestContentType
                        };
                    if(methodHasBody)
                        init.body = data;
                    else
                        url += '?'+queryParams.join('&');
                    fetch(url,init )
                        .then(function (json) {
                            if(json.status >= 400){
                                return JSON.stringify({
                                    error:'invalid-return-status',
                                    errorStatus: json.status
                                });
                            }
                            else
                                return json.text();
                        })
                        .then(function (data) {
                            console.log('Request succeeded with JSON response!');
                            apiTest.resp = data;
                            alertLog(apiTest.resp);
                            if(IsJsonString(data))
                                data = JSON.parse(data);
                            console.log(data);
                        })
                        .catch(function (error) {
                            console.log('Request failed', error);
                            apiTest.resp = error;
                        });
                },
                function(reject){
                    alertLog('CSRF token expired. Please refresh the page to submit the form.','danger');
                }
            )

        },
        //Adds a new variable
        addVariable: function(newName){
            this.variables[newName] = {value:''};
            this.$forceUpdate();
        },
        //Removes a variable
        removeVariable: function(name){
            delete this.variables[name];
            this.$forceUpdate();
        },
    },
    mounted: function(){
        bindImagePreview(document.querySelector('#uploaded1'),document.querySelector('#preview1'),{
            'callback':function(){
                console.log('img2 changed!');
            },
            'bindClick':true
        });
    },
    template: `
    <span id="apiTest">
        <button @click.prevent="separateVariables = !separateVariables"> Toggle Separate Variables Mode</button>
        <form novalidate>
            <input type="text" id="target" name="target" placeholder="target API" v-model="target" required>
            <span v-if="separateVariables" id="variables">
                <div v-for="(content, name) in variables">
                    <button @click.prevent="removeVariable(name)"> X </button>
                    <input type="text" v-model:value="name">
                    <textarea class="content" name="content" placeholder="content" v-model="content.value"></textarea>
                </div>
                <div>
                    <input type="text" v-model:value="newVariableName">
                    <button @click.prevent="addVariable(newVariableName)"> Add Variable </button>
                </div>
            </span>
            <textarea v-else="" class="content" name="content" placeholder="content" v-model="content"></textarea>
            <span class="form-group">
                <input type="text" id="imgName" name="imgName" placeholder="Upload POST name" v-model="imgName">
                <img id="preview1" src="" style="height: 100px;width: 100px;cursor: pointer;">
                <input id="uploaded1" name="uploaded1" type="file" style="display:none;">
            </span>
            <span class="form-group">
                <input id="uploaded2" name="uploaded2" type="file" style="display: inline">
            </span>
            <select id="req_log" name="req" v-model="req" value="test" required>
                <option value="real" selected>Real</option>
                <option value="test">Test</option>
            </select>
            <select id="api_target" name="target" v-model="apiTarget" required>
                <option value="" selected>Default API</option>
                <option value="v1">API Version 1</option>
                <option value="v2">API Version 2</option>
            </select>
            <select id="force-params-to-query" name="force-to-query" v-model="forceParamsToQuery" required>
                <option value="" selected>Default</option>
                <option value="1">Force Params To Query</option>
            </select>
            <select id="force-params-to-query" name="force-to-query" v-model="requestContentType" required>
                <option value="" selected>Default</option>
                <option value="multipart/form-data" selected>multipart/form-data</option>
                <option value="application/x-www-form-urlencoded">application/x-www-form-urlencoded</option>
                <option value="text/plain">text/plain</option>
                <option value="application/json">application/json</option>
            </select>
            <select id="api_method" name="method" v-model="apiMethod" required>
                <option value="post" selected>POST</option>
                <option value="get">GET</option>
                <option value="put">PUT</option>
                <option value="delete">DELETE</option>
                <option value="patch">PATCH</option>
                <option value="head">HEAD</option>
            </select>
    
    
            <button @click.prevent="send">Send</button>
    
        </form>
        <div>inputs = {{  }}</div>
    
        <div v-html="resp"></div>
    </span>
    `
});