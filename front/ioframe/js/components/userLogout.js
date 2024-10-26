Vue.component('user-logout', {
    props:{
        text: {
            type: Object,
            default: function(){
                return {
                    logOutButton: 'Log Out'
                };
            }
        },
        //Identifier
        identifier: {
            type: String,
            default: ''
        },
        test:{
            type: Boolean,
            default: false
        },
        verbose:{
            type: Boolean,
            default: false
        },
    },
    data: function(){return {
        m:{
            val:'',
            class:''
        },
        p:{
            val:'',
            class:''
        },
        rMe: true,
        resp: ''
    }
    },
    methods:{
        logOut: function(){
            let data = 'action=logUser&log=out&req=new';
            let url=document.ioframe.pathToRoot+"api\/v1\/users";
            //Send logout request
            let xhr = new XMLHttpRequest();
            xhr.open('POST', url+'?'+data);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded;charset=utf-8;');
            xhr.send(null);
            xhr.onreadystatechange = function () {
                let DONE = 4; // readyState 4 means the request is done.
                let OK = 200; // status 200 is a successful return.
                if (xhr.readyState === DONE) {
                    if (xhr.status === OK){
                        //If we logged out, update current session.
                        localStorage.removeItem("sesID");
                        localStorage.removeItem("sesIV");
                        localStorage.removeItem("myMail");
                        //Set 2nd parameter to false or remove it if you don't want a page reload.
                        updateSesInfo(document.ioframe.pathToRoot,{
                            'sessionInfoUpdated': function(){
                                location.reload();
                            }
                        });
                    }
                } else {
                    if(xhr.status < 200 || xhr.status > 299 ){
                        // error
                        console.log('Logout request failed! Could not reach target API!');
                        alertLog('Logout request failed! Could not reach target API!','danger');
                    }
                }
            };
        }
    },
    template: '<button @click.prevent="logOut" v-text="text.logOutButton"></button>'
});