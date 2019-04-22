<div id="userReg" ng-controller="regCtrl">

    <form novalidate>

        <input ng-style="uStyle" type="text" id="u_reg" name="u" placeholder="username" ng-model="u" required>
        <a href="#"  id="u_reg-tooltip">?</a><br>
        <input ng-style="pStyle" type="password" id="p_reg" name="p" placeholder="password" ng-model="p" required>
        <a href="#"  id="p_reg-tooltip">?</a><br>
        <input ng-style="p2Style" type="password" id="p2_reg" placeholder="repeat password" ng-model="p2" required><br>
        <input ng-style="mStyle" type="email" id="m_reg" name="m" placeholder="mail" ng-model="m" required><br>
        <select id="req_reg" name="req" ng-model="req" required value="real" hidden>
            <option value="test" selected>Test</option>
            <option value="real">Real</option>
        </select>

        <button ng-click="reg()">Register</button>

    </form>

    <p hidden>test = {{test1}} </p>

    <p hidden>inputs = {{inputs}}</p>

    <p hidden>{{resp}}</p>
</div>


<script>
    //***************************
    //***USER REGISTRATION APP***
    //***************************//

    //-------------------------------------------------------------------------------------
    //Manually initiate all tooltips
    document.addEventListener('DOMContentLoaded', function(e) {
        document.popupHandler = new ezPopup("pop-up-tooltip");
        document.popupHandler.initPopup('u_reg-tooltip','Must be 6-16 characters long, Must contain numbers and latters','');
        document.popupHandler.initPopup('p_reg-tooltip',"Must be 8-20 characters long<br> Must include latters and numbers<br>Can include special characters except '>' and '<'",'');
    }, true);

    var userRegApp = angular.module('userReg', []);
    userRegApp.controller('regCtrl', function($scope,$http) {

        $scope.reg = function() {
            var errors = 0;
            //output user inputs for testing
            $scope.inputs="Username:"+$scope.u+", password:"+
                $scope.p+", email:"+$scope.m+", req:"+$scope.req;

            //validate username
            if( ($scope.u.length>16) ||($scope.u.length<6) || ($scope.u.match(/\W/g)!=null) ){
                $scope.uStyle = {"background-color" : "red"};
                errors++;
            }
            else $scope.uStyle = {"background-color" : "rgb(142, 255, 188)"};

            //validate password
            if( ($scope.p.length>64) ||($scope.p.length<8) || ($scope.p.match(/(\s|<|>)/g)!=null)
                || ($scope.p.match(/[0-9]/g) == null) || ($scope.p.match(/[a-z]|[A-Z]/g) == null) ){
                $scope.pStyle = {"background-color" : "red"};
                errors++;
            }
            else $scope.pStyle = {"background-color" : "rgb(142, 255, 188)"};

            //validate 2nd pass
            if($scope.p!=$scope.p2 || $scope.p2==undefined){
                $scope.p2Style = {"background-color" : "red"};
                errors++;
            }
            else $scope.p2Style = {"background-color" : "rgb(142, 255, 188)"};

            //validate email
            if($scope.m==undefined){
                $scope.mStyle = {"background-color" : "red"};
                errors++;
            }
            else $scope.mStyle = {"background-color" : "rgb(142, 255, 188)"};

            //if no errors - send data
            if(errors<1){
                $scope.test1="Posting..";
                //Data to be sent
                var data = $.param({
                    action: 'addUser',
                    u: $scope.u,
                    p: $scope.p,
                    m: $scope.m,
                    req: $scope.req
                });
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
                        /*This will display messages that the addUser page could return
                         *0 - success
                         *1 - failed - incorrect input - wrong or missing
                         *2 - failed - username already in use
                         *3 - failed - email already in use
                         *4 - failed - server error
                         **/
                        switch(response.data){
                            case '-2':
                                $scope.resp = 'User creation failed - authorization failure!';
                                respType='warning';
                                break;
                            case '-1':
                                $scope.resp = 'User creation failed - incorrect input';
                                respType='warning';
                                break;
                            case '0':
                                $scope.resp = 'User created successfully!';
                                respType='success';
                                break;
                            case '1':
                                $scope.resp = 'User creation failed - username already in use!';
                                respType='warning';
                                $scope.uStyle = {"background-color" : "#fcf8e3"};
                                break;
                            case '2':
                                $scope.resp = 'User creation failed - email already in use!';
                                respType='warning';
                                $scope.mStyle = {"background-color" : "#fcf8e3"};
                                break;
                            case '3':
                                $scope.resp = 'User creation failed - server error!';
                                respType='warning';
                                break;
                            default:
                                $scope.resp = response.data;
                                respType='warning';
                        }

                        //Use AlertLog to tell the user what the resault was
                        //Can be implemented differently in different apps
                        alertLog($scope.resp,respType);

                    },(function(response) {
                        // error
                        $scope.test1 = "Posted: "+data+"to"+document.pathToRoot+"_siteAPI/userAPI.php"+" ,Failed in getting response to post.";
                        $scope.resp = response.data;
                    })
                );
            }
            //Error
            else $scope.test1="Didn't post due to errors";

        };

    });

    //************************
    // Bootstrap app manually
    //************************
    angular.element(document).ready(function() {
        angular.bootstrap(document.getElementById('userReg'), ['userReg']);
    });

</script>