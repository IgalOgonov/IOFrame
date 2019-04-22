<div id="userLog" ng-controller="logCtrl">
    <form novalidate>

        <input ng-style="mStyle" type="email" id="m_log" name="m" placeholder="email" ng-model="m" required>
        <input ng-style="pStyle" type="password" id="p_log" name="p" placeholder="password" ng-model="p" required>
        <br>
        <input type="checkbox" name="rMe" ng-model="rMe" checked>Remember Me!<br>
        <select id="req_log" name="req" ng-model="req" value="real" required hidden>
            <option value="test" selected>Test</option>
            <option value="real">Real</option>
        </select>
        <button ng-click="log()" ng-model="login">Login</button>

    </form>
    <p hidden>test = {{test1}} </p>

    <p hidden>inputs = {{inputs}}</p>

    <p hidden>{{resp}}</p>

    <br>
</div>


<script>
    //***************************
    //******USER LOGIN APP*******
    //***************************//
    var userLogApp = angular.module('userLog', []);
    userLogApp.controller('logCtrl', function($scope,$http) {
        $scope.rMe = true;
        $scope.log = function() {
            var errors=0;
            //output user inputs for testing
            $scope.inputs="Email:"+$scope.m+", password:"+$scope.p;
            //validate email
            if($scope.m==undefined){
                $scope.mStyle = {"background-color" : "red"};
                errors++;
            }
            //validate password
            if( ($scope.p.length>64) ||($scope.p.length<8) || ($scope.p.match(/(\s|<|>)/g)!=null)
                || ($scope.p.match(/[0-9]/g) == null) || ($scope.p.match(/[a-z]|[A-Z]/g) == null) ){
                $scope.pStyle = {"background-color" : "red"};
                errors++;
            }
            else
                $scope.pStyle = {"background-color" : ""};
            if(errors<1){
                $scope.resp = "Logging in...";
                //Data to be sent
                var data = $.param({
                    action:'logUser',
                    m: $scope.m,
                    p: $scope.p,
                    req: $scope.req,
                    log: 'in'
                });
                if($scope.rMe)
                    data +='&userID='+localStorage.getItem('deviceID');
                //data +='&sesKey=a1123af79aaa46f923c735d7795b113ad1af77fe037dbf28f219d676d6003de2';
                //Configure header - the server needs to recieve this as a POST!
                var config = {
                    headers : {
                        'Content-Type': 'application/x-www-form-urlencoded;charset=utf-8;'
                    }
                };
                //Send it away
                $http.post(document.pathToRoot+"_siteAPI/userAPI.php",data,config)
                    .then(function(response) {
                        // success
                        $scope.test1 = "Posted: "+data+"to"+document.pathToRoot+"_siteAPI/userAPI.php"+" ,Succeeded in getting response to post.";
                        $scope.resp = response.data;


                        /*This will display messages that the addUser page could return
                         *0 - success
                         *1 - failed - incorrect input - wrong or missing
                         *2 - failed - username and password combination is wrong
                         *3 - failed - server error
                         **/
                        switch(response.data){
                            case '-1':
                                $scope.resp = 'User login failed - incorrect input';
                                respType='warning';
                                respColor='rgb(252, 248, 227)';
                                break;
                            case '0':
                                $scope.resp = 'User logged in successfully!';
                                respType='success';
                                respColor='rgb(210, 210, 256)';
                                //Remember to udate session info!
                                updateSesInfo(document.pathToRoot,true);
                                //If we specifically don't want to be remembered - we wont be.
                                if($scope.rMe){
                                    localStorage.removeItem("sesID");
                                    localStorage.removeItem("sesIV");
                                }
                                break;
                            case '1':
                                $scope.resp = 'User login failed - username and password combination is wrong!';
                                respType='danger';
                                respColor = 'red';
                                break;
                            case '2':
                                $scope.resp = 'User login failed - expired auto-login';
                                respType='warning';
                                break;
                            case '3':
                                $scope.resp = 'User login failed - login type not allowed  (why you using this API?!)';
                                respType='warning';
                                respColor='rgb(252, 248, 227)';
                                break;
                            //TODO Drop auto-login cached data if it exists
                            default:
                                if( ( JSON.stringify(response.data).length > 20) &&
                                    (JSON.stringify(response.data).match(/(\s)/g)==null) ){
                                    //Means this is just a new sesID
                                    if (JSON.stringify(response.data).match(/({)/g) == null) {
                                        localStorage.setItem("sesID",JSON.stringify(response.data));
                                    }
                                    //Means this is a new sesID and IV
                                    else {
                                        localStorage.setItem("sesID",response.data['sesID']);
                                        localStorage.setItem("sesIV",response.data['iv']);
                                    }
                                    $scope.resp = 'User logged in successfully!';
                                    respType='success';
                                    respColor = 'rgb(210, 210, 256)';
                                    //Remember to udate session info!
                                    updateSesInfo(document.pathToRoot,true);
                                }
                                //Means this is something else...
                                else{
                                    $scope.resp = response.data;
                                    respType='danger';
                                    respColor = 'red';
                                }
                        }

                        //Use AlertLog to tell the user what the resault was
                        //Can be implemented differently in different apps
                        alertLog($scope.resp,respType);
                        $scope.mStyle = {"background-color" : respColor};
                        $scope.pStyle = {"background-color" : respColor};

                    },(function(response) {
                        // error
                        $scope.test1 = "Posted: "+data+"to"+document.pathToRoot+"_siteAPI/userAPI.php"+" ,Failed in getting response to post.";
                        $scope.resp = response.data;
                    })
                );


            }
        }
    });

    //************************
    // Bootstrap app manually
    //************************
    angular.element(document).ready(function() {
        angular.bootstrap(document.getElementById('userLog'), ['userLog']);
    });
</script>