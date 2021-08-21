
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
            let methodHasBody = ['put','post','patch'].indexOf(context.apiMethod)!== -1;

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
            let url=document.pathToRoot+"api/"+(this.apiTarget?this.apiTarget+'/':'')+this.target;
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
                    if(methodHasBody)
                        init.body = data;
                    else
                        url += '?'+queryParams.join('&');
                    fetch(url,init )
                        .then(function (json) {
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
    }
});