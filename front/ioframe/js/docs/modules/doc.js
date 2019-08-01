if(document.eventHub === undefined)
    document.eventHub = new Vue();

//***************************
//******BAP PRODUCT CATEGORIES APP*******
//***************************//
var IOFrameDocsDoc = new Vue({
    el: '#doc',
    data: {
    },
    created:function(){
        //Listen to events
        document.eventHub.$on('display', this.display);
    },
    computed:{
    },
    methods:{
        display: function(inputObject){
            console.log('Sent by '+inputObject.sender);
            console.log('Displaying '+inputObject.args);
        }
    },
    watch: {
    }
});