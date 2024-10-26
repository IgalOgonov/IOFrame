<?php


require 'main/core_init.php';
if(!(
    $siteSettings->getSetting('devMode') ||
    ( $siteSettings->getSetting('allowTesting') && $auth->isAuthorized() )
    )
){
    \IOFrame\Util\DefaultErrorTemplatingFunctions::handleGenericHTTPError(
        $settings,
        [
            'error'=>401,
            'errorInMsg'=>false,
            'errorHTTPMsg'=>'Testing unauthorized',
            'mainMsg'=>'Testing Unauthorized',
            'subMsg'=>'May not access testing functionality',
            'mainFilePath'=>$settings->getSetting('_templates_unauthorized_generic'),
        ]
    );
}

$dirToRoot = \IOFrame\Util\FrameworkUtilFunctions::htmlDirDist($_SERVER['REQUEST_URI'],$rootFolder);
echo '<headers>';
echo '<script src="front/ioframe/js/ext/vue/2.7.16/vue.js"></script>';
echo '</headers>';


//determine which tab to open by default
$requireOnlyTab = $_REQUEST['requireOnlyTab'] ?? '';

//determine which tab to open by default
$openTab = $_REQUEST['openTab'] ?? $requireOnlyTab;

// --------------- CSS
echo '
<style>
#test {
    background: rgb(20,20,20);
}

#test section {
    background: rgb(50,50,50);
    color: rgb(230,230,230);
    width: calc(100% - 10px);
    margin: auto;
    padding: 5px;
}

#test section.closed {
    display: none;
}

#test h1 {
    font-size: 150%;
    background: rgb(100,100,100);
    color: rgb(200,200,200);
}

#test .test-menu {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
}

#test .test-menu button{
    padding: 5px 10px;
    margin: 5px;
    background: rgb(100, 100, 100);
    color: rgb(230,230,230);
    border: 1px rgb(230,230,2310) solid;
    font-weight: 800;
    min-width: 250px;
    max-width: 250px;
    transition: 0.2s ease-in-out;
}
#test .test-menu button:hover,
#test .test-menu button.selected{
    background: rgb(50, 50, 50);
}

seperator{
    border: 1px black solid;
    width: 100%;
    display: block;
    margin: 10px 0px;
}

#test table{
    background: orange;
}
</style>
';

echo '<body>';
echo '<div id="test">';

echo '<nav class="test-menu">

      <button
      @click="requireOnlyTab(null,true)"
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

if(!$requireOnlyTab)
    echo '<div>
           <button
          @click="openAllTabs()"
          v-text="\'Open All Tabs\'"
          ></button>
     
           <button
          @click="closeAllTabs()"
          v-text="\'Close All Tabs\'"
          ></button>
     
     </div>';

$tests = [
    'serverInfo' => [
        'title'=>'All Server Side Properties info',
        'url'=>'sandbox/serverInfo.php'
    ],
    'sessionInfo' => [
        'title'=>'Session Info',
        'url'=>'sandbox/sessionInfo.php'
    ],
    'settingsInfo' => [
        'title'=>'Settings info',
        'url'=>'sandbox/settingsInfo.php'
    ],
    'generalSandbox' => [
        'title'=>'General sandbox',
        'url'=>'sandbox/generalSandbox.php'
    ],
    'SQLSandbox' => [
        'title'=>'SQL sandbox',
        'url'=>'sandbox/SQLSandbox.php'
    ],
    'cryptoSandbox' => [
        'title'=>'Crypto sandbox',
        'url'=>'sandbox/cryptoSandbox.php'
    ],
    'cURLSandbox' => [
        'title'=>'Curl sandbox',
        'url'=>'sandbox/cURLSandbox.php'
    ],
    'GeoIPSandbox' => [
        'title'=>'GeoIP sandbox',
        'url'=>'sandbox/GeoIPSandbox.php'
    ],
    'settingsTest' => [
        'title'=>'Settings test',
        'url'=>'test/verbose/settingsTest.php'
    ],
    'userTest' => [
        'title'=>'User test',
        'url'=>'test/verbose/userTest.php'
    ],
    'securityTest' => [
        'title'=>'Security test',
        'url'=>'test/verbose/securityTest.php'
    ],
    'authTest' => [
        'title'=>'Auth test',
        'url'=>'test/verbose/authTest.php'
    ],
    'timingTest' => [
        'title'=>'Timing Test',
        'url'=>'test/verbose/timingTest.php'
    ],
    'safeStringTest' => [
        'title'=>'safeString test',
        'url'=>'test/verbose/safeStringTest.php'
    ],
    'tokenTest' => [
        'title'=>'Token test',
        'url'=>'test/verbose/tokenTest.php'
    ],
    'loggingTest' => [
        'title'=>'Logging test',
        'url'=>'test/verbose/loggingTest.php'
    ],
    'mailTest' => [
        'title'=>'Mail test',
        'url'=>'test/verbose/mailTest.php'
    ],
    'pluginsTest' => [
        'title'=>'Plugins test',
        'url'=>'test/verbose/pluginsTest.php'
    ],
    'IPHandlerTest' => [
        'title'=>'IP Handler Test',
        'url'=>'test/verbose/IPHandlerTest.php'
    ],
    'orderTest' => [
        'title'=>'Order test',
        'url'=>'test/verbose/orderTest.php'
    ],
    'routingTest' => [
        'title'=>'Routing test',
        'url'=>'test/verbose/routingTest.php'
    ],
    'resourceTest' => [
        'title'=>'Resources test',
        'url'=>'test/verbose/resourceTest.php'
    ],
    'frontEndResourceTest' => [
        'title'=>'Frontend Resource (CSS, JS, SCSS) test',
        'url'=>'test/verbose/frontEndResourceTest.php'
    ],
    'mediaResourceTest' => [
        'title'=>'Frontend Resources - Media test',
        'url'=>'test/verbose/mediaResourceTest.php'
    ],
    'contactsTest' => [
        'title'=>'Contacts test',
        'url'=>'test/verbose/contactsTest.php'
    ],
    'ordersTest' => [
        'title'=>'Orders test',
        'url'=>'test/verbose/ordersTest.php'
    ],
    'paymentTest' => [
        'title'=>'Payment test',
        'url'=>'test/verbose/paymentTest.php'
    ],
    'shippingTest' => [
        'title'=>'Shipping test',
        'url'=>'test/verbose/shippingTest.php'
    ],
    'templatesTest' => [
        'title'=>'Templates test',
        'url'=>'test/verbose/templatesTest.php'
    ],
    'objectAuthTest' => [
        'title'=>'Object Auth test',
        'url'=>'test/verbose/objectAuthTest.php'
    ],
    'articleTest' => [
        'title'=>'Article test',
        'url'=>'test/verbose/articleTest.php'
    ],
    'menuTest' => [
        'title'=>'Menu test',
        'url'=>'test/verbose/menuTest.php'
    ],
    'rateLimitTest' => [
        'title'=>'Rate Limiting test',
        'url'=>'test/verbose/rateLimitTest.php'
    ],
    'captchaTest' => [
        'title'=>'Captcha test',
        'url'=>'test/verbose/captchaTest.php'
    ],
    'cliTest' => [
        'title'=>'CLI test',
        'url'=>'test/verbose/cliTest.php'
    ],
    'concurrencyTest' => [
        'title'=>'Concurrency test',
        'url'=>'test/verbose/concurrencyTest.php'
    ],
];

$allVerboseTests = \IOFrame\Util\FileSystemFunctions::fetchAllFolderAddresses(__DIR__.'/test/verbose',['include'=>['\.php$']]);
foreach ($allVerboseTests as $testAddr){
    $testAddr = substr($testAddr,strlen(__DIR__));
    $exploded = explode('/',$testAddr);
    $testName = substr(array_pop($exploded),0,-4);
    if(empty($tests[$testName]))
        $tests[$testName] = ['url'=>$testAddr];
}

//File / Util functions should already be imported from core_init
try{
    $extraTests = \IOFrame\Util\FileSystemFunctions::readFile(__DIR__.'/test/verbose','extraTests.json');
    if(!\IOFrame\Util\PureUtilFunctions::is_json($extraTests))
        $extraTests = [];
    else
        $extraTests = json_decode($extraTests,true);
}
catch (\Exception $e){
    $extraTests = [];
}

$allTests = array_merge($tests,$extraTests);
ksort($allTests);
$allTestKeys = array_keys($allTests);

foreach ($allTests as $testKey => $params){
    $params['title'] = $params['title']??$testKey;
    if(!$requireOnlyTab || $requireOnlyTab === $testKey){
        echo '<h1>'.$params['title'].'</h1>';
        echo '<button @click = "tabs.'.$testKey.' = !tabs.'.$testKey.'">Toggle Visibility</button>';
        echo '<section :class="{open:tabs.'.$testKey.', closed:!tabs.'.$testKey.'}">';
        require __DIR__.'/'.$params['url'];
        echo '</section>';
    }
}

$allTabs = '';
foreach ($allTestKeys as $key){
    $allTabs .= $key.':false,';
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
            '.
            $allTabs
            .'
        }
    },
    created: function(){
        if(this.openTab )
            this.tabs[this.openTab] = true;
        const context = this;
        const sortedTabs = Objectkeys(context.tabs).sort.reduce(
          (obj, key) => { 
            obj[key] = context.tabs[key]; 
            return obj;
          }, 
          {}
        );
        Vue.set(this,"tabs",sortedTabs);
    },
    methods: {
        requireOnlyTab: function(tab = "", root = false){
            if(tab===this.requiredTab)
                return;
            let redirect = location.href;
            redirect = redirect.substring(0, redirect.indexOf("test.php")+8);
            if(tab){
                redirect += "?requireOnlyTab="+tab;
            }
            else if(!root)
                redirect += "&openTab="+this.requiredTab;
            location.assign(redirect);
        },
        toggleAllTabs: function(state = true){
            for (let i in this.tabs){
                this.tabs[i] = state;
            }
        },
        openAllTabs: function (){
            this.toggleAllTabs();
        },
        closeAllTabs: function (){
            this.toggleAllTabs(false);
        }
    }
    });

</script>';
