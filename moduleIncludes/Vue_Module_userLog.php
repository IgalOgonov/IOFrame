<span id="userLog">
    <form novalidate>


        <input :style="{backgroundColor: mStyle}" type="email" id="m_log" name="m" placeholder="email" v-model="m" required>
        <input :style="{backgroundColor: pStyle}" type="password" id="p_log" name="p" placeholder="password" v-model="p" required>
        <input type="checkbox" name="rMe" v-model="rMe" checked>Remember Me!
        <select id="req_log" name="req" v-model="req" value="real" required hidden>
            <option value="real" selected>Real</option>
            <option value="test">Test</option>
        </select>
        <button @click.prevent="log">Login</button>

    </form>
    <div hidden>test = {{ test1 }} </div>

    <div hidden>inputs = {{ inputs }}</div>

    <div hidden>{{ resp }}</div>
</span>


<script>
    //***************************
    //******USER LOGIN APP*******
    //***************************//
    //The plugin list component, which is responsible for everything
    var userLog = new Vue({
        el: '#userLog',
        data: {
            m:'',
            p:'',
            rMe: true,
            req: 'real',
            mStyle: '',
            pStyle: '',
            test1: '',
            inputs: '',
            resp: ''
        },
        methods:{
            log: function(){
                let errors = 0;
                //output user inputs for testing
                this.inputs="Email:"+this.m+", password:"+this.p;
                //validate email
                if(this.m==undefined || this.m==""){
                    this.mStyle = "red";
                    errors++;
                }
                else
                    this.mStyle = "";
                //validate password
                if( (this.p.length>64) ||(this.p.length<8) || (this.p.match(/(\s|<|>)/g)!=null)
                    || (this.p.match(/[0-9]/g) == null) || (this.p.match(/[a-z]|[A-Z]/g) == null) ){
                    this.pStyle = "red";
                    errors++;
                }
                else
                    this.pStyle = "";
                //If no errors, log in
                if(errors<1){
                    this.resp = "Logging in...";
                    //Data to be sent
                    let data = 'action=logUser&m='+this.m+'&p='+this.p+'&req='+this.req+'&log=in';
                    if(this.rMe)
                        data +='&userID='+localStorage.getItem('deviceID');
                    //Api url
                    let url=document.pathToRoot+"_siteAPI/userAPI.php";
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
                                let respType;
                                let respColor;
                                let response = xhr.responseText;
                                // success
                                userLog.test1 = "Posted: "+data+"to"+document.pathToRoot+"_siteAPI/userAPI.php"+" ,Succeeded in getting response to post.";
                                userLog.resp = response;
                                /*This will display messages that the API could return
                                 **/
                                switch(response){
                                    case '-2':
                                        userLog.resp = 'User login failed - current login type/API was not allowed!';
                                        respType='warning';
                                        break;
                                    case '-1':
                                        userLog.resp = 'User login failed - incorrect input';
                                        respType='warning';
                                        respColor='rgb(252, 248, 227)';
                                        break;
                                    case '0':
                                        userLog.resp = 'User logged in successfully!';
                                        respType='success';
                                        respColor='rgb(210, 210, 256)';
                                        //If we specifically don't want to be remembered - we wont be.
                                        if(userLog.rMe){
                                            localStorage.removeItem("sesID");
                                            localStorage.removeItem("sesIV");
                                        }
                                        //Remember to udate session info!
                                        updateSesInfo(document.pathToRoot,true);
                                        break;
                                    case '1':
                                        userLog.resp = 'User login failed - username and password combination is wrong!';
                                        respType='danger';
                                        respColor = 'red';
                                        break;
                                    case '2':
                                        userLog.resp = 'User login failed - expired auto-login';
                                        respType='warning';
                                        respColor='rgb(252, 248, 227)';
                                        break;
                                    case '3':
                                        userLog.resp = 'User login failed - login type not allowed  (why you using this API?!)';
                                        respType='warning';
                                        respColor='rgb(252, 248, 227)';
                                        break;
                                    //TODO Drop auto-login cached data if it exists
                                    default:
                                        let loginSuccess = false;
                                        if(response.length >= 32){
                                            //This means we got both sesID and IV
                                            if(IsJsonString(response) ){
                                                let resp = JSON.parse(response);
                                                if(resp['sesID'].length == 32){
                                                    localStorage.setItem("sesID",resp['sesID']);
                                                    loginSuccess = true;
                                                }
                                                if(resp['iv'].length == 32){
                                                    localStorage.setItem("sesIV",resp['iv']);
                                                    loginSuccess = true;
                                                }
                                            }
                                            //This means we just got a new ID
                                            else{
                                                if(response.length == 32){
                                                    localStorage.setItem("sesID",response);
                                                    loginSuccess = true;
                                                }
                                            }
                                            if(loginSuccess){
                                                userLog.resp = 'User logged in successfully!';
                                                respType='success';
                                                respColor = 'rgb(210, 210, 256)';
                                                //Remember to udate session info!
                                                updateSesInfo(document.pathToRoot,true);
                                            }
                                        }
                                        //Means this is something else...
                                        if(!loginSuccess){
                                            userLog.resp = 'Illegal response: '+response;
                                            respType='danger';
                                            respColor = 'red';
                                        }
                                }

                                //Use AlertLog to tell the user what the resault was
                                //Can be implemented differently in different apps
                                alertLog(userLog.resp,respType);
                                userLog.mStyle = respColor;
                                userLog.pStyle = respColor;
                            }
                        } else {
                            if(xhr.status < 200 || xhr.status > 299 ){
                                // error
                                userLog.test1 = "Posted: "+data+"to"+document.pathToRoot+"_siteAPI/userAPI.php"+" ,Failed in getting response to post.";
                                userLog.resp = xhr.responseText;
                                console.log('Error: ' + xhr.status); // An error occurred during the request.
                            }
                        }
                    };
                };
            }
        }
    });
</script>