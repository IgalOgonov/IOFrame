if(eventHub === undefined)
    var eventHub = new Vue();

//***************************
//******USER LOGIN APP*******
//***************************//
//The plugin list component, which is responsible for everything
var userLog = new Vue({
    el: '#userLog',
    name: 'User Login',
    data: {
        test: false,
        verbose: false
    },
    created:function(){
      eventHub.$on('loginResponse',this.parseLoginResponse)
    },
    methods:{
        parseLoginResponse: function(response){
            let responseBody = response.body;
            let responseType = response.type;
            let alertParams = {};
            /*This will display messages that the API could return
             **/
            switch(responseBody){
                case 'INPUT_VALIDATION_FAILURE':
                    responseBody = 'User login failed - incorrect input';
                    responseType='warning';
                    break;
                case 'AUTHENTICATION_FAILURE':
                    responseBody = 'User login failed - authentication failure';
                    responseType='warning';
                    break;
                case 'WRONG_CSRF_TOKEN':
                    responseBody = 'User login failed - CSRF token wrong';
                    responseType='warning';
                    break;
                case '0':
                    responseBody = 'User logged in successfully!';
                    responseType='success';
                    alertParams = {autoDismiss:2000};
                    break;
                case '1':
                    responseBody = 'User login failed - username and password combination is wrong!';
                    responseType='danger';
                    break;
                case '2':
                    responseBody = 'User login failed - expired auto-login';
                    responseType='warning';
                    break;
                case '3':
                    responseBody = 'User login failed - login type not allowed  (why you using this API?!)';
                    responseType='warning';
                    break;
                default:
            }
            alertLog(responseBody, responseType,alertParams);
        }
    }
});