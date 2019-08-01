
//***************************
//******USER LOGIN APP*******
//***************************//
//The plugin list component, which is responsible for everything
var apiTest = new Vue({
    el: '#apiTest',
    data: {
        target:'',
        content:'',
        req: 'test',
        inputs: '',
        resp: ''
    },
    methods:{
        send: function(){
            //output user inputs for testing
            this.resp = "Waiting...";
            //Data to be sent
            var data = new FormData();
            let contentArray = this.content.split('&');
            contentArray.forEach(function(postPair, index) {
                postPair = postPair.split('=');
                if(postPair.length == 1)
                    postPair[1] = '';
                data.append(postPair[0], postPair[1]);
            });
            data.append('req', this.req);
            let image = document.querySelector('#uploaded1');
            if(image.files.length>0){
                data.append('image', image.files[0]);
            }
            let file = document.querySelector('#uploaded2');
            if(file.files.length>0){
                data.append('file', file.files[0]);
            }
            this.inputs="Target:"+this.target+", Content:"+JSON.stringify(data);
            //Api url
            let url=document.pathToRoot+"api/"+this.target;
            //Request itself
            updateCSRFToken().then(
                function(token){
                    data.append('CSRF_token', token);
                    console.log(data);
                    fetch(url, {
                        method: 'post',
                        body: data,
                        mode: 'cors'
                    })
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

        }
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