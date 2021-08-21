<?php


require 'main/coreInit.php';
$dirToRoot = IOFrame\Util\htmlDirDist($_SERVER['REQUEST_URI'],$rootFolder);
echo '<headers>';
echo '<script src="front/ioframe/js/ext/vue/2.6.10/vue.js"></script>';
echo '</headers>';


//determine which tab to open by default
$requireOnlyTab = isset($_REQUEST['requireOnlyTab']) ? $_REQUEST['requireOnlyTab'] : '';

//determine which tab to open by default
$openTab = isset($_REQUEST['openTab']) ? $_REQUEST['openTab'] : $requireOnlyTab;

// --------------- CSS
echo '
<style>

section {
    background: rgba(0,0,0,0.1);
    width: calc(100% - 10px);
    margin: auto;
    padding: 5px;
}

section.closed {
    display: none;
}

#test h1 {
    font-size: 150%;
    background: rgb(220,230,240);
}

#test .test-menu {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
}

#test .test-menu button{
    padding: 5px 10px;
    margin: 5px;
    background: rgb(236, 251, 255);
    color: rgb(54 145 160);
    border: 1px rgb(161,233,246) solid;
    font-weight: 800;
    min-width: 250px;
    max-width: 250px;
    transition: 0.2s ease-in-out;
}
#test .test-menu button:hover,
#test .test-menu button.selected{
    background: rgb(161,233,246);
    color: rgb(111, 121, 166);
    border: 1px rgb(161,233,246) solid;
}

seperator{
    border: 1px black solid;
    width: 100%;
    display: block;
    margin: 10px 0px;
}
</style>
';

echo '<body>';
echo '<div id="test">';

echo '<nav class="test-menu">

      <button
      @click="requireOnlyTab()"
      v-text="\'All tests\'"
      :class="{selected:requiredTab === \'\'}"
      ></button>

      <button
      v-for="(open,tabName) in tabs"
      @click="requireOnlyTab(tabName)"
      :class="{selected:requiredTab == tabName}"
      v-text="tabName +\' tests\'"
      ></button>

</nav>';

if(!$requireOnlyTab || $requireOnlyTab === 'serverInfo'){
    echo '<h1>'.'All Server Side Properties info'.'</h1>';
    echo '<button @click = "tabs.serverInfo = !tabs.serverInfo">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.serverInfo, closed:!tabs.serverInfo}">';
    require 'sandbox/serverInfo.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'sessionInfo'){
    echo '<h1>'.'Session Info'.'</h1>';
    echo '<button @click = "tabs.sessionInfo = !tabs.sessionInfo">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.sessionInfo, closed:!tabs.sessionInfo}">';
    require 'sandbox/sessionInfo.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'settingsInfo'){
    echo '<h1>'.'Settings info'.'</h1>';
    echo '<button @click = "tabs.settingsInfo = !tabs.settingsInfo">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.settingsInfo, closed:!tabs.settingsInfo}">';
    require 'sandbox/settingsInfo.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'settingsTest'){
    echo '<h1>'.'Settings test'.'</h1>';
    echo '<button @click = "tabs.settingsTest = !tabs.settingsTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.settingsTest, closed:!tabs.settingsTest}">';
    require 'test/verbose/settingsTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'treeTest'){
    echo '<h1>'.'Tree test'.'</h1>';
    echo '<button @click = "tabs.treeTest = !tabs.treeTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.treeTest, closed:!tabs.treeTest}">';
    require 'test/verbose/treeTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'userTest'){
    echo '<h1>'.'User test'.'</h1>';
    echo '<button @click = "tabs.userTest = !tabs.userTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.userTest, closed:!tabs.userTest}">';
    require 'test/verbose/userTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'securityTest'){
    echo '<h1>'.'Security test'.'</h1>';
    echo '<button @click = "tabs.securityTest = !tabs.securityTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.securityTest, closed:!tabs.securityTest}">';
    require 'test/verbose/securityTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'authTest'){
    echo '<h1>'.'Auth test'.'</h1>';
    echo '<button @click = "tabs.authTest = !tabs.authTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.authTest, closed:!tabs.authTest}">';
    require 'test/verbose/authTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'timingTest'){
    echo '<h1>'.'Timing Test'.'</h1>';
    echo '<button @click = "tabs.timingTest = !tabs.timingTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.timingTest, closed:!tabs.timingTest}">';
    require 'test/verbose/timingTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'safeStringTest'){
    echo '<h1>'.'safeString test'.'</h1>';
    echo '<button @click = "tabs.safeStringTest = !tabs.safeStringTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.safeStringTest, closed:!tabs.safeStringTest}">';
    require 'test/verbose/safeStringTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'tokenTest'){
    echo '<h1>'.'Token test'.'</h1>';
    echo '<button @click = "tabs.tokenTest = !tabs.tokenTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.tokenTest, closed:!tabs.tokenTest}">';
    require 'test/verbose/tokenTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'loggingTest'){
    echo '<h1>'.'logging test'.'</h1>';
    echo '<button @click = "tabs.loggingTest = !tabs.loggingTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.loggingTest, closed:!tabs.loggingTest}">';
    require 'test/verbose/loggingTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'mailTest'){
    echo '<h1>'.'Mail test'.'</h1>';
    echo '<button @click = "tabs.mailTest = !tabs.mailTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.mailTest, closed:!tabs.mailTest}">';
    require 'test/verbose/mailTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'pluginsTest'){
    echo '<h1>'.'Plugins test'.'</h1>';
    echo '<button @click = "tabs.pluginsTest = !tabs.pluginsTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.pluginsTest, closed:!tabs.pluginsTest}">';
    require 'test/verbose/pluginsTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'IPHandlerTest'){
    echo '<h1>'.'IP Handler Test'.'</h1>';
    echo '<button @click = "tabs.IPHandlerTest = !tabs.IPHandlerTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.IPHandlerTest, closed:!tabs.IPHandlerTest}">';
    require 'test/verbose/IPHandlerTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'objectsTest'){
    echo '<h1>'.'Objects test'.'</h1>';
    echo '<button @click = "tabs.objectsTest = !tabs.objectsTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.objectsTest, closed:!tabs.objectsTest}">';
    require 'test/verbose/objectsTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'orderTest'){
    echo '<h1>'.'Order test'.'</h1>';
    echo '<button @click = "tabs.orderTest = !tabs.orderTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.orderTest, closed:!tabs.orderTest}">';
    require 'test/verbose/orderTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'routingTest'){
    echo '<h1>'.'Routing test'.'</h1>';
    echo '<button @click = "tabs.routingTest = !tabs.routingTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.routingTest, closed:!tabs.routingTest}">';
    require 'test/verbose/routingTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'resourceTest'){
    echo '<h1>'.'Resource test'.'</h1>';
    echo '<button @click = "tabs.resourceTest = !tabs.resourceTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.resourceTest, closed:!tabs.resourceTest}">';
    require 'test/verbose/resourceTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'frontEndResourceTest'){
    echo '<h1>'.'Frontend Resource test'.'</h1>';
    echo '<button @click = "tabs.frontEndResourceTest = !tabs.frontEndResourceTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.frontEndResourceTest, closed:!tabs.frontEndResourceTest}">';
    require 'test/verbose/frontEndResourceTest.php';
    echo '</section>';
}


if(!$requireOnlyTab || $requireOnlyTab === 'mediaResourceTest'){
    echo '<h1>'.'Frontend Resources - Media test'.'</h1>';
    echo '<button @click = "tabs.mediaResourceTest = !tabs.mediaResourceTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.mediaResourceTest, closed:!tabs.mediaResourceTest}">';
    require 'test/verbose/mediaResourceTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'contactsTest'){
    echo '<h1>'.'Contacts test'.'</h1>';
    echo '<button @click = "tabs.contactsTest = !tabs.contactsTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.contactsTest, closed:!tabs.contactsTest}">';
    require 'test/verbose/contactsTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'ordersTest'){
    echo '<h1>'.'Orders test'.'</h1>';
    echo '<button @click = "tabs.ordersTest = !tabs.ordersTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.ordersTest, closed:!tabs.ordersTest}">';
    require 'test/verbose/ordersTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'templatesTest'){
    echo '<h1>'.'Templates test'.'</h1>';
    echo '<button @click = "tabs.templatesTest = !tabs.templatesTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.templatesTest, closed:!tabs.templatesTest}">';
    require 'test/verbose/templatesTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'objectAuthTest'){
    echo '<h1>'.'Object Auth test'.'</h1>';
    echo '<button @click = "tabs.objectAuthTest = !tabs.objectAuthTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.objectAuthTest, closed:!tabs.objectAuthTest}">';
    require 'test/verbose/objectAuthTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'articleTest'){
    echo '<h1>'.'Article test'.'</h1>';
    echo '<button @click = "tabs.articleTest = !tabs.articleTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.articleTest, closed:!tabs.articleTest}">';
    require 'test/verbose/articleTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'menuTest'){
    echo '<h1>'.'Menu test'.'</h1>';
    echo '<button @click = "tabs.menuTest = !tabs.menuTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.menuTest, closed:!tabs.menuTest}">';
    require 'test/verbose/menuTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'rateLimitTest'){
    echo '<h1>'.'Menu test'.'</h1>';
    echo '<button @click = "tabs.rateLimitTest = !tabs.rateLimitTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.rateLimitTest, closed:!tabs.rateLimitTest}">';
    require 'test/verbose/rateLimitTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'captchaTest'){
    echo '<h1>'.'Captcha test'.'</h1>';
    echo '<button @click = "tabs.captchaTest = !tabs.captchaTest">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.captchaTest, closed:!tabs.captchaTest}">';
    require 'test/verbose/captchaTest.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'generalSandbox'){
    echo '<h1>'.'General sandbox'.'</h1>';
    echo '<button @click = "tabs.generalSandbox = !tabs.generalSandbox">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.generalSandbox, closed:!tabs.generalSandbox}">';
    require 'sandbox/generalSandbox.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'SQLSandbox'){
    echo '<h1>'.'SQL sandbox'.'</h1>';
    echo '<button @click = "tabs.SQLSandbox = !tabs.SQLSandbox">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.SQLSandbox, closed:!tabs.SQLSandbox}">';
    require 'sandbox/SQLSandbox.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'cryptoSandbox'){
    echo '<h1>'.'Crypto sandbox'.'</h1>';
    echo '<button @click = "tabs.cryptoSandbox = !tabs.cryptoSandbox">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.cryptoSandbox, closed:!tabs.cryptoSandbox}">';
    require 'sandbox/cryptoSandbox.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'cURLSandbox'){
    echo '<h1>'.'Curl sandbox'.'</h1>';
    echo '<button @click = "tabs.cURLSandbox = !tabs.cURLSandbox">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.cURLSandbox, closed:!tabs.cURLSandbox}">';
    require 'sandbox/cURLSandbox.php';
    echo '</section>';
}

if(!$requireOnlyTab || $requireOnlyTab === 'GeoIPSandbox'){
    echo '<h1>'.'GeoIP sandbox'.'</h1>';
    echo '<button @click = "tabs.GeoIPSandbox = !tabs.GeoIPSandbox">Toggle Visibility</button>';
    echo '<section :class="{open:tabs.GeoIPSandbox, closed:!tabs.GeoIPSandbox}">';
    require 'sandbox/GeoIPSandbox.php';
    echo '</section>';
}

echo '</div>';

// --------------- PHP Info
echo '<h1>'.'PHP Info'.'</h1>';
phpinfo();

// --------------- End of body
echo '</body>';

// --------------- Vue script
echo '<script>

    var test = new Vue({
    el: \'#test\',
    data: {
        openTab: \''.htmlspecialchars($openTab).'\',
        requiredTab: \''.htmlspecialchars($requireOnlyTab).'\',
        tabs:{
            serverInfo: false,
            sessionInfo: false,
            settingsInfo: false,
            settingsTest: false,
            treeTest: false,
            userTest: false,
            securityTest: false,
            authTest: false,
            timingTest: false,
            safeStringTest: false,
            tokenTest: false,
            loggingTest: false,
            mailTest: false,
            pluginsTest: false,
            IPHandlerTest: false,
            objectsTest: false,
            orderTest: false,
            routingTest: false,
            resourceTest: false,
            frontEndResourceTest: false,
            mediaResourceTest: false,
            contactsTest: false,
            ordersTest: false,
            templatesTest: false,
            objectAuthTest: false,
            articleTest: false,
            captchaTest: false,
            menuTest: false,
            rateLimitTest: false,
            generalSandbox: false,
            SQLSandbox: false,
            cryptoSandbox: false,
            cURLSandbox: false,
            GeoIPSandbox: false
        }
    },
    created: function(){
        if(this.openTab )
            this.tabs[this.openTab] = true;
    },
    methods: {
        requireOnlyTab: function(tab = ""){
            if(tab===this.requiredTab)
                return;
            let redirect = location.href;
            redirect = redirect.substring(0, redirect.indexOf("test.php")+8);
            if(tab){
                redirect += "?requireOnlyTab="+tab;
            }
            else
                redirect += "&openTab="+this.requiredTab;
            location.assign(redirect);
        }
    }
    });

</script>';

?>