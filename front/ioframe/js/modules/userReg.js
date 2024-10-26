if(eventHub === undefined)
    var eventHub = new Vue();

//***************************
//******USER LOGIN APP*******
//***************************//
//The plugin list component, which is responsible for everything
var userReg = new Vue({
    el: '#userReg',
    name: 'User Registration',
    data: {
        test:false,
        verbose:false
    },
    created:function(){
        eventHub.$on('registrationResponse',this.parseRegistrationResponse)
    },
    methods:{
        parseRegistrationResponse: function(response){
            let responseBody = response.body;
            let responseType = response.type;
            let alertOptions = {};
            /*This will display messages that the API could return
             **/
            switch(responseBody){
                case 'INPUT_VALIDATION_FAILURE':
                    responseBody = 'User creation failed - incorrect input';
                    break;
                case 'AUTHENTICATION_FAILURE':
                    responseBody = 'User creation failed - authentication failure';
                    break;
                case 'WRONG_CSRF_TOKEN':
                    responseBody = 'User creation failed - CSRF token wrong';
                    break;
                case '0':
                    responseBody = 'User created successfully!';
                    alertOptions = {autoDismiss:2000};
                    break;
                case '1':
                    responseBody = 'User creation failed - username already in use!';
                    break;
                case '2':
                    responseBody = 'User creation failed - email already in use!';
                    break;
                case '3':
                    responseBody = 'User creation failed - server error!';
                    break;
                default:
            }
            alertLog(responseBody, responseType, document.body, alertOptions);
        }
    }
});