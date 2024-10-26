const parseLimit = {
    methods: {
        parseLimit: function(response){
            if(this.verbose)
                console.log(response);
            if(response.startsWith('RATE_LIMIT_REACHED')){
                let secondsLeft = response.split('@')[1];
                let timeArray = [];
                if(secondsLeft > 86400){
                    const days = Math.floor(secondsLeft/86400);
                    timeArray.push(days+' '+(days>1?this.text.rateLimit.days : this.text.rateLimit.day));
                    secondsLeft = secondsLeft%86400;
                }
                if(secondsLeft > 3600){
                    const hours = Math.floor(secondsLeft/3600);
                    timeArray.push(hours+' '+(hours>1?this.text.rateLimit.hours : this.text.rateLimit.hour));
                    secondsLeft = secondsLeft%3600;
                }
                if(secondsLeft > 60){
                    const minutes = Math.floor(secondsLeft/60);
                    timeArray.push(minutes+' '+(minutes>1?this.text.rateLimit.minutes : this.text.rateLimit.minute));
                    secondsLeft = secondsLeft%60;
                }
                if(secondsLeft > 0){
                    timeArray.push(secondsLeft+' '+(secondsLeft>1?this.text.rateLimit.seconds : this.text.rateLimit.second));
                }
                return this.text.rateLimit.tryAgain+' '+timeArray.join(this.text.rateLimit.connector);
            }
            return false;
        }
    }
};
