
//***************************
//******USER LOGIN APP*******
//***************************//
//The plugin list component, which is responsible for everything
var userReg = new Vue({
    el: '#userReg',
    data: {
        u:'',
        p:'',
        p2:'',
        m:'',
        req: 'real',
        uStyle: '',
        pStyle: '',
        p2Style: '',
        mStyle: '',
        test1: '',
        inputs: '',
        resp: ''
    },
    methods:{
        reg: function(){
            let errors = 0;
            //output user inputs for testing
            this.inputs="Username:"+this.u+", password:"+
                this.p+", email:"+this.m+", req:"+this.req;

            //validate username
            if( (this.u.length>16) ||(this.u.length<6) || (this.u.match(/\W/g)!=null) ){
                this.uStyle = "red";
                errors++;
            }
            else
                this.uStyle = "rgb(142, 255, 188)";

            //validate password
            if( (this.p.length>64) ||(this.p.length<8) || (this.p.match(/(\s|<|>)/g)!=null)
                || (this.p.match(/[0-9]/g) == null) || (this.p.match(/[a-z]|[A-Z]/g) == null) ){
                this.pStyle = "red";
                errors++;
            }
            else
                this.pStyle = "rgb(142, 255, 188)";

            //validate 2nd pass
            if(this.p!=this.p2 || this.p2==undefined){
                this.p2Style = "red";
                errors++;
            }
            else
                this.p2Style = "rgb(142, 255, 188)";

            //validate email
            if(this.m==undefined){
                this.mStyle = "red";
                errors++;
            }
            else
                this.mStyle = "rgb(142, 255, 188)";

            //if no errors - send data
            if(errors<1){
                this.resp = "Posting...";
                //Data to be sent
                let data = 'action=addUser'+'&u='+this.u+'&p='+this.p+'&m='+this.m+'&req='+this.req;
                //Api url
                let url=document.pathToRoot+"api\/users";
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
                            let response = xhr.responseText;
                            // success
                            userReg.test1 = "Posted: "+data+" to user API"+" ,Succeeded in getting response to post.";
                            userReg.resp = response;
                            /*This will display messages that the addUser page could return
                             *0 - success
                             *1 - failed - incorrect input - wrong or missing
                             *2 - failed - username already in use
                             *3 - failed - email already in use
                             *4 - failed - server error
                             *5 - failed - registration not allowed!
                             **/
                            switch(response){
                                case 'INPUT_VALIDATION_FAILURE':
                                    userReg.resp = 'User auto-login failed - incorrect input';
                                    respType='warning';
                                    break;
                                case 'AUTHENTICATION_FAILURE':
                                    userReg.resp = 'User auto-login failed - authentication failure';
                                    respType='warning';
                                    break;
                                case 'WRONG_CSRF_TOKEN':
                                    userReg.resp = 'User auto-login failed - CSRF token wrong';
                                    respType='warning';
                                    break;
                                case '0':
                                    userReg.resp = 'User created successfully!';
                                    respType='success';
                                    break;
                                case '1':
                                    userReg.resp = 'User creation failed - username already in use!';
                                    respType='warning';
                                    userReg.uStyle = "#fcf8e3";
                                    break;
                                case '2':
                                    userReg.resp = 'User creation failed - email already in use!';
                                    respType='warning';
                                    userReg.mStyle = "#fcf8e3";
                                    break;
                                case '3':
                                    userReg.resp = 'User creation failed - server error!';
                                    respType='warning';
                                    break;
                                default:
                                    userReg.resp = response;
                                    respType='warning';
                            }

                            //Use AlertLog to tell the user what the result was
                            //Can be implemented differently in different apps
                            alertLog(userReg.resp,respType);
                        }
                    } else {
                        if(xhr.status < 200 || xhr.status > 299 ){
                            // error
                            userReg.test1 = "Posted: "+data+"to user API"+" ,Failed in getting response to post.";
                            userReg.resp = xhr.responseText;
                            console.log('Error: ' + xhr.status); // An error occurred during the request.
                        }
                    }
                };
            };
        }
    },
    mounted: function(){
        document.popupHandler = new ezPopup("pop-up-tooltip");
        document.popupHandler.initPopup('u_reg-tooltip','Must be 6-16 characters long, Must contain numbers and latters','');
        document.popupHandler.initPopup('p_reg-tooltip',"Must be 8-64 characters long<br> Must include latters and numbers<br>Can include special characters except '>' and '<'",'');
    }
});