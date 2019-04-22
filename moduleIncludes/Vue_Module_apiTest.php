<span id="apiTest">
    <form novalidate>


        <input type="text" id="target" name="target" placeholder="target API" v-model="target" required>
        <textarea  id="content" name="content" placeholder="API request content" v-model="content" required></textarea>
        <select id="req_log" name="req" v-model="req" value="test" required>
            <option value="real" selected>Real</option>
            <option value="test">Test</option>
        </select>
        <input type="radio" id="json" name="json" v-model="json" value="1" required> Json
        <input type="radio" id="json" name="json" v-model="json" value="0" required checked> Not Json
        <button @click.prevent="send">Send</button>

    </form>
    <div>inputs = {{ inputs }}</div>

    <div>{{ resp }}</div>
</span>


<script>
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
            resp: '',
            json: 0
        },
        methods:{
            send: function(){
                //output user inputs for testing
                this.inputs="Target:"+this.target+", Content:"+this.content;
                this.resp = "Waiting...";
                //Data to be sent
                let data = this.content+'&req='+this.req;
                //Api url
                let url=document.pathToRoot+"_siteAPI/"+this.target+".php";
                //Request itself
                var xhr = new XMLHttpRequest();
                xhr.open('POST', url+'?'+data);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=utf-8;');
                xhr.send(null);
                xhr.onreadystatechange = function () {
                    var DONE = 4; // readyState 4 means the request is done.
                    var OK = 200; // status 200 is a successful return.
                    if (xhr.readyState === DONE) {
                        if (xhr.status === OK){
                            let response = xhr.responseText;
                            apiTest.resp = response;
                            alertLog(apiTest.resp);
                            if(apiTest.json == '1')
                                console.log(JSON.parse(apiTest.resp));
                        }
                    } else {
                        if(xhr.status < 200 || xhr.status > 299 ){
                            // error
                            apiTest.test1 = "Posted: "+data+"to "+url+" ,Failed in getting response to post.";
                            apiTest.resp = xhr.responseText;
                            console.log('Error: ' + xhr.status); // An error occurred during the request.
                        }
                    }
                };
            }
        }
    });
</script>