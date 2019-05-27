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
                fetch(url, {
                    method: 'post',
                    headers: {
                        "Content-type": "application/x-www-form-urlencoded; charset=UTF-8"
                    },
                    body: data,
                    mode: 'cors'
                })
                    .then(function (json) {
                            return json.text();
                    })
                    .then(function (data) {
                        console.log('Request succeeded with JSON response!');
                        apiTest.resp = data;
                        if(IsJsonString(data))
                            data = JSON.parse(data);
                        console.log(data);
                    })
                    .catch(function (error) {
                        console.log('Request failed', error);
                        apiTest.resp = error;
                    });
            }
        }
    });
</script>