<div id="objectManager" ng-controller="objCtrl">
    <form novalidate id="selection">
        <label> Create Object: <input type="radio" name="action" value="false" ng-model="c" ng-Click="updateForm('c')"> </label> <br/>
        <label> Read Objects: <input  type="radio" name="action" value="false" ng-model="r" ng-Click="updateForm('r')"></label> <br/>
        <label> Update Object: <input type="radio" name="action" value="false" ng-model="u" ng-Click="updateForm('u')">  </label><br/>
        <label> Delete Object:  <input type="radio" name="action" value="false" ng-model="d"  ng-Click="updateForm('d')"> </label><br/>
        <label> Read Page-Object Assignments:  <input type="radio" name="action" ng-model="ga" value="false" ng-Click="updateForm('ga')"> </label><br/>
        <label> Create new Page-Object Assignment: <input type="radio" name="action" ng-model="a" value="false" ng-Click="updateForm('a')"> </label><br/>
    </form>

    <form novalidate id="cForm" hidden="">
        <h1>Create object</h1> <br/>
        <label> <div>Object:</div> <textarea ng-model="cObj"></textarea> </label> <br/>
        <label> Minimum rank to view: <input type="number" value="false" ng-model="cMinV" min="-1" max="10000"></label> <br/>
        <label> Minimum rank to modify: <input type="number" value="false" ng-model="cMinM" min="0" max="10000">  </label><br/>
        <label> Group (optional):  <input type="text" value="false" ng-model="cGroup" > </label><br/>
        <label> Test Query?  <input type="checkbox" ng-model="cTest" value="false"> </label><br/>
        <input type="button" ng-Click="cSubmit()" value="Create Object"><br/>
    </form>

    <form novalidate  id="rForm"  hidden="">
        <h1>Read objects</h1> <br/>
        <a id="rFormTarget"></a>
        <div id="rObj_1">
            <span><input type="number" placeholder="Object ID"></span>
            <span><input type="number" placeholder="Time Updated (0 default)" value="0" min="0" max="10000000000"></span>
        </div>
        <div>
            <span><input type="button" value="More" ng-model="rAdd" ng-Click="addObj()"></span>
            <span><input type="button" value="Less" ng-model="rRem" ng-Click="remObj()"></span>
        </div>
        <label> Test Query?  <input type="checkbox" ng-model="rTest" value="false"> </label><br/>
        <input type="button" ng-Click="rSubmit()" value="Read Objects"><br/>
    </form>

    <form novalidate id="uForm" hidden="">
        <h1>Update object </h1><br/>
        <label> Object ID: <input type="number"  ng-model="uObjID" min="0"></label> <br/>
        <label> <div>Object (optional):</div> <textarea ng-model="uObj"></textarea> </label> <br/>
        <label> Group (optional):  <input type="text" value="false" ng-model="uGroup" > </label><br/>
        <label> Minimum rank to view (optional): <input type="number" value="false" ng-model="uMinV" min="-1" max="10000"></label> <br/>
        <label> Minimum rank to modify (optional): <input type="number" value="false" ng-model="uMinM" min="0" max="10000">  </label><br/>
        <label> Change Main Owner (optional): <input type="number" value="false" ng-model="uMainO" min="0"></label> <br/>
        <label> Add secondary Owner (optional): <input type="number" value="false" ng-model="uAddSecO" min="0"></label> <br/>
        <label> Remove secondary Owner (optional): <input type="number" value="false" ng-model="uRemSecO" min="0"></label> <br/>
        <label> Test Query?  <input type="checkbox" ng-model="uTest" value="false"> </label><br/>
        <input type="button" ng-Click="uSubmit()" value="Update Object"><br/>
    </form>

    <form novalidate  id="dForm" hidden="">
        <h1>Delete object</h1> <br/>
        <label> Object ID: <input type="number"  ng-model="dObjID"></label> <br/>
        <label> Test Query?  <input type="checkbox" ng-model="dTest" value="false"> </label><br/>
        <input type="button" ng-Click="dSubmit()" value="Delete Object"><br/>
    </form>

    <form novalidate id="gaForm" hidden="">
        <h1>Get object-page assignments</h1> <br/>
        <label> Page Name: <input type="text"  ng-model="gaPageName"></label> <br/>
        <input type="button" ng-Click="gaSubmit()" value="Get Assignment"><br/>
    </form>

    <form novalidate id="aForm" hidden="">
        <h1>Assign object to page / Remove Assignment</h1> <br/>
        <label> Object ID: <input type="number"  ng-model="aObjID" min="0"></label> <br/>
        <label> Page Name: <input type="text"  ng-model="aPageName"></label> <br/>
        <label> Remove? (default = assign) <input type="checkbox" ng-model="aRem" value="false"> </label><br/>
        <label> Test Query?  <input type="checkbox" ng-model="aTest" value="false"> </label><br/>
        <input type="button" ng-Click="aSubmit()" value="Assign"><br/>
    </form>

    <p>inputs = {{inputs}}</p>

    <p>response = {{resp}}</p>

    <br>
</div>

<script>
    //***************************
    //******OBJECT MANAGER APP***
    //***************************//
    var objectManagerApp = angular.module('objectManager', []);
    objectManagerApp.controller('objCtrl', function($scope,$http) {
        $scope.currRObj = 1;
        $scope.currVis = null;
        $scope.prevVis = null;
        //Switch between forms
        $scope.updateForm = function() {

            $scope.prevVis = $scope.currVis;
            $scope.currVis = arguments[0];
            if($scope.prevVis == $scope.currVis)
                return;

            let currId = $scope.currVis + 'Form';
            let prevId = $scope.prevVis + 'Form';
            $("#"+currId).removeAttr("hidden");
            $("#"+prevId).attr("hidden","");
            $scope.inputs = '';
            $scope.resp = '';
        };

        //Add object to the Read Objects form
        $scope.addObj = function () {
            let oldObj = '';
            ($scope.currRObj == 0)?
                oldObj = 'rFormTarget' : oldObj = 'rObj_'+$scope.currRObj;
            $scope.currRObj += 1;
            let newObj = 'rObj_'+$scope.currRObj;
            $("#"+oldObj).after('<div id="'+newObj+'"><span><input type="number" placeholder="Object ID"></span><span><input type="number" placeholder="Time Updated (0 default)" value="0" min="0" max="10000000000"></span></div>');
        };

        //Remove object from the Read Objects form
        $scope.remObj = function () {
            let remove = true;
            let remObj = '';
            ($scope.currRObj == 0)?
                remove = false : remObj = 'rObj_'+$scope.currRObj;
            if(remove){
                $scope.currRObj -=1;
                $("#"+remObj).remove();
            }
        };

        /*******Function for each form******/

        //Create Object
        $scope.cSubmit = function(){
            if($scope.cTest === undefined)
                $scope.cTest = false;
            if($scope.cGroup === undefined)
                $scope.cGroup = '';

            $scope.inputs ='Object: '+$scope.cObj+', MinV: '+$scope.cMinV+', MinM: '+$scope.cMinM+', Group: '+$scope.cGroup+
                ', Test: ' + $scope.cTest;
            //Front-end Input Validation goes here!
            //Data to be sent
            let params = {};
            params.obj = $scope.cObj;
            params.minViewRank = $scope.cMinV;
            params.minModifyRank = $scope.cMinM;
            params.group = $scope.cGroup;
            params["?"] = $scope.cTest;

            var data = $.param({
                type: 'c',
                params: JSON.stringify(params)
            });
            //Configure header - the server needs to recieve this as a POST!
            var config = {
                headers : {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=utf-8;'
                }
            };
            //Send it away
            $http.post(document.pathToRoot+"_siteApi/objectAPI.php",data,config)
                .then(function(response) {
                    $scope.resp = response.data;

                    switch (response.data){
                        //What to do in different cases
                    }
                },(function(response) {
                    console.log('Error! Could not reach '+document.pathToRoot+"_siteApi/objectAPI.php")
                })
            );
        };

        //Read Objects
        $scope.rSubmit = function(){
            if($scope.rTest === undefined)
                $scope.rTest = false;
            let objReadArr = [];
            for(let i=1; i<=$scope.currRObj; i++){
                objReadArr.push($("#rObj_"+i+" > span:nth-child(1) > input").val());
                objReadArr.push($("#rObj_"+i+" > span:nth-child(2) > input").val());
            }
            $scope.inputs =objReadArr.toString()+', test: '+$scope.rTest;
            //Front-end Input Validation goes here!

            //Data to be sent
            let temp = {};
            objReadArr.forEach(function (val, index){
                if(index%2)
                    temp[objReadArr[index-1]] = objReadArr[index];
                });
            let params = {"@":JSON.stringify(temp), "?":+$scope.rTest};

            var data = $.param({
                type: 'r',
                params: JSON.stringify(params)
            });
            //Configure header - the server needs to recieve this as a POST!
            var config = {
                headers : {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=utf-8;'
                }
            };
            //Send it away
            $http.post(document.pathToRoot+"_siteApi/objectAPI.php",data,config)
                .then(function(response) {
                    let resp = response.data;
                    let content ='';
                    let groups;
                    let errors;
                    for (let key in resp){
                        if (resp.hasOwnProperty(key)) {
                            if(key != 'groups' && key != 'errors')
                                content += resp[key]+' {FOLLOWED BY} ';
                            if(key ==='groups' )
                                groups = resp[key];
                            if(key ==='errors' )
                                errors = resp[key];
                        }
                    }
                    content = content.substring(0,content.length-15);
                    $scope.resp = 'Content: '+content+', Groups:'+groups+', Errors:'+errors;

                    switch (response.data){
                        //What to do in different cases
                    }
                },(function(response) {
                    console.log('Error! Could not reach '+document.pathToRoot+"_siteApi/objectAPI.php")
                })
            );
        };

        //Update Object
        $scope.uSubmit = function(){
            if($scope.uTest === undefined)
                $scope.uTest = false;
            if($scope.uObj === undefined)
                $scope.uObj = '';
            if($scope.uGroup === undefined)
                $scope.uGroup = '';
            if($scope.uMinV === undefined)
                $scope.uMinV = '';
            if($scope.uMinM === undefined)
                $scope.uMinM = '';
            if($scope.uMainO === undefined)
                $scope.uMainO = '';
            if($scope.uAddSecO === undefined)
                $scope.uAddSecO = '';
            if($scope.uRemSecO === undefined)
                $scope.uRemSecO = '';


            $scope.inputs ='Object ID: '+$scope.uObjID+', Content: '+$scope.uObj+', Group:'+$scope.uGroup+
                ', MinV: '+$scope.uMinV+', MinM: '+$scope.uMinM+', New Main Owner: '+$scope.uMainO+', Add 2nd Owner:'+
                $scope.uAddSecO+', Remove 2nd Owner:'+$scope.uRemSecO+', Test: ' + $scope.uTest;
            //Front-end Input Validation goes here!
            //Data to be sent
            let params = {};
            params.id = $scope.uObjID;
            params.content = $scope.uObj;
            params.group = $scope.uGroup;
            params.newVRank = $scope.uMinV;
            params.newMRank = $scope.uMinM;
            params.mainOwner = $scope.uMainO;
            $scope.uAddSecO == ''?
                params.addOwners = '' : params.addOwners = '{"'+$scope.uAddSecO+'":'+$scope.uAddSecO+'}';
            $scope.uRemSecO == ''?
                params.remOwners = '' : params.remOwners = '{"'+$scope.uRemSecO+'":'+$scope.uRemSecO+'}';
            params["?"] = $scope.uTest;

            var data = $.param({
                type: 'u',
                params: JSON.stringify(params)
            });
            //Configure header - the server needs to recieve this as a POST!
            var config = {
                headers : {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=utf-8;'
                }
            };
            //Send it away
            $http.post(document.pathToRoot+"_siteApi/objectAPI.php",data,config)
                .then(function(response) {
                    $scope.resp = response.data;

                    switch (response.data){
                        //What to do in different cases
                    }
                },(function(response) {
                    console.log('Error! Could not reach '+document.pathToRoot+"_siteApi/objectAPI.php")
                })
            );

        };

        //Delete Object
        $scope.dSubmit = function(){
            if($scope.dTest === undefined)
                $scope.dTest = false;

            $scope.inputs ='Object ID: '+$scope.dObjID+', Test: ' + $scope.dTest;
            //Front-end Input Validation goes here!
            //Data to be sent
            let params = {};
            params.id = $scope.dObjID;
            params["?"] = $scope.dTest;

            var data = $.param({
                type: 'd',
                params: JSON.stringify(params)
            });
            //Configure header - the server needs to recieve this as a POST!
            var config = {
                headers : {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=utf-8;'
                }
            };
            //Send it away
            $http.post(document.pathToRoot+"_siteApi/objectAPI.php",data,config)
                .then(function(response) {
                    $scope.resp = response.data;

                    switch (response.data){
                        //What to do in different cases
                    }
                },(function(response) {
                    console.log('Error! Could not reach '+document.pathToRoot+"_siteApi/objectAPI.php")
                })
            );

        };

        //Get Page Assignment Object
        $scope.gaSubmit = function(){
            $scope.inputs ='Page: '+$scope.gaPageName;
            //Front-end Input Validation goes here!
            //Data to be sent
            let params = {};
            params.page = $scope.gaPageName;
            params.date = 0;
            var data = $.param({
                type: 'ga',
                params: JSON.stringify(params)
            });
            //Configure header - the server needs to recieve this as a POST!
            var config = {
                headers : {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=utf-8;'
                }
            };
            //Send it away
            $http.post(document.pathToRoot+"_siteApi/objectAPI.php",data,config)
                .then(function(response) {
                    $scope.resp = response.data;

                    switch (response.data){
                        //What to do in different cases
                    }
                },(function(response) {
                    console.log('Error! Could not reach '+document.pathToRoot+"_siteApi/objectAPI.php")
                })
            );
        };

        //Create/Remove Page Assignment
        $scope.aSubmit = function(){
            if($scope.aTest === undefined)
                $scope.aTest = false;
            let typeToSend;
            ($scope.aRem === undefined || $scope.aRem === false)?
                typeToSend = 'a' : typeToSend = 'ra';
            $scope.inputs ='Object ID: '+$scope.aObjID+', Page: '+$scope.aPageName+
                ', Remove: '+$scope.aRem+', Test: ' + $scope.aTest;
            //Front-end Input Validation goes here!
            //Data to be sent
            let params = {};
            params.id = $scope.aObjID;
            params.page = $scope.aPageName;
            params['?'] = $scope.aTest;
            var data = $.param({
                type: typeToSend,
                params: JSON.stringify(params)
            });
            //Configure header - the server needs to recieve this as a POST!
            var config = {
                headers : {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=utf-8;'
                }
            };
            //Send it away
            $http.post(document.pathToRoot+"_siteApi/objectAPI.php",data,config)
                .then(function(response) {
                    $scope.resp = response.data;

                    switch (response.data){
                        //What to do in different cases
                    }
                },(function(response) {
                    console.log('Error! Could not reach '+document.pathToRoot+"_siteApi/objectAPI.php")
                })
            );

        };



    });

    //************************
    // Bootstrap app manually
    //************************
    angular.element(document).ready(function() {
        angular.bootstrap(document.getElementById('objectManager'), ['objectManager']);
    });
</script>