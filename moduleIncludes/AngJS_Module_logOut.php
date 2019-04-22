
<div id="userLogOut" ng-controller="logOutCtrl">
    <form>
        <button ng-click="logOut()">Log Out</button>
    </form>
</div>


<script>
    //***************************
    //*****APP THREE - LOGOUT****
    //***************************//
    var userLogOutApp = angular.module('userLogOut', []);
    userLogOutApp.controller('logOutCtrl', function($scope) {
        $scope.logOut = function() {
            //Send logout request
            var request = $.ajax({
                url:  document.pathToRoot+"_siteAPI\/userAPI.php"
                ,data: "action=logUser&log=out"
                ,method: 'post'
            });
            request.done(function(response) {
                //If we logged out, update current session.
                //Set 2nd parameter to false or remove it if you don't want a page reload.
                localStorage.removeItem("sesID");
                localStorage.removeItem("sesIV");
                localStorage.removeItem("myMail");
                updateSesInfo(document.pathToRoot,true);
            });

        }
    });

    //************************
    // Bootstrap app manually
    //************************
    angular.element(document).ready(function() {
        angular.bootstrap(document.getElementById('userLogOut'), ['userLogOut']);
    });
</script>
