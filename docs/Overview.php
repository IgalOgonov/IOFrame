<?php

if(!require __DIR__ . '/../main/coreInit.php')
    echo 'Core utils unavailable!'.'<br>';

?>

<!DOCTYPE html>
<?php require $settings->getSetting('absPathToRoot').'front/ioframe/templates/headers.php';


echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<script src="highlight.js/highlight.pack.js"></script>';
echo '<script src="'.$dirToRoot.'front/ioframe/js/ezPopup.js"></script>';
echo '<script>hljs.initHighlightingOnLoad();</script>';
echo '<link rel="stylesheet" href="ioframe-highlight.css">';
echo '<link rel="stylesheet" href="IOFrameDocs.css">';
if($auth->isAuthorized(0))
    echo '<script src="'.$dirToRoot.'front/ioframe/js/vue/2.6.10/vue.js"></script>';
else
    echo '<script src="'.$dirToRoot.'front/ioframe/js/vue/2.6.10/vue.min.js"></script>';

echo '<title>IOFrame - Overview</title>';
?>

<!--
This document is meant to host all documentation of the IOFrame framework. Inside the actual framework website, contents may be split, depending on the layout.
-->

<body>
<header>
    <h1><a name="top"></a>IOFrame - Overview</h1>
    <h6>Version updated - 0.2.0.0</h6>
</header>
<span id="toc-frame">
    <h2><a name="ToC"></a>Table of Contents</h2>
    <ul>
        <li><a href="#GenM">General Information</a>
            <ol>
                <li><a href="#Gen1">Designated Purpose</a></li>
                <li><a href="#Gen2">Requirements</a></li>
                <li><a href="#Gen3">Versioning Information</a></li>
            </ol>
        </li>
        <li><a href="#FraM">Framework Structure</a>
            <ol>
                <li><a href="#Fra1">General Structure</a></li>
                <li><a href="#Fra2">Functions</a></li>
                <li><a href="#Fra3">Handlers</a></li>
                <li><a href="#Fra4">Settings Structure</a></li>
                <li><a href="#Fra5">Global Definitions</a></li>
                <li><a href="#Fra6">hArray, SafeString</a></li>
                <li><a href="#Fra7">External Works</a></li>
            </ol>
        </li>
        <li><a href="#DatM">Database Structure</a>
            <ol>
                <li><a href="#Dat1">Table Descriptions</a></li>
                <li><a href="#Dat2">Database Initiation Function</a></li>
            </ol>
        </li>
        <li><a href="#FunM">Functions and Handlers</a>
            <ol>
                <li><a href="#Fun1">Settings Handler</a></li>
                <li><a href="#Fun2">Core Function (Bootstrap)</a></li>
                <li><a href="#Fun3">Helper Functions</a></li>
                <li><a href="#Fun4">Session Handler</a></li>
                <li><a href="#Fun5">Security Handler</a></li>
                <li><a href="#Fun6">Authorization Handler</a></li>
                <li><a href="#Fun7">User Registration & Login Shared Code</a></li>
                <li><a href="#Fun8">User Registration & Registration Confirmation API</a></li>
                <li><a href="#Fun9">User Login, Automated Login, and Logout</a></li>
                <li><a href="#Fun10">Mail Handler & API</a></li>
                <li><a href="#Fun11">Password Reset API</a></li>
                <li><a href="#Fun12">General API</a></li>
                <li><a href="#Fun13">Framework Installation File</a></li>
            </ol>
        </li>
    </ul>
</span>
<span id="doc-frame">


<!--General Information-->
<article>
    <h1><a name="GenM"></a>General Information</h1>
    <h6>Back to <a href="#ToC">Table of Content</a> , <a href="#top">Top</a></h6><br>

    <h2><a name="Gen1"></a>Designated Purpose</h2>
    <h6>Back to <a href="#GenM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>

    <div>
        I know words. I have the best words. You know, it really doesn’t matter what you write as long as you’ve got a young, and beautiful, piece of text. We have so many things that we have to do better... and certainly ipsum is one of them. You know, it really doesn’t matter what you write as long as you’ve got a young, and beautiful, piece of text. I have a 10 year old son. He has words. He is so good with these words it's unbelievable.<br>

        You know, it really doesn’t matter what you write as long as you’ve got a young, and beautiful, piece of text.<br>

        I think the only difference between me and the other placeholder text is that I’m more honest and my words are more beautiful. The concept of Lorem Ipsum was created by and for the Chinese in order to make U.S. design jobs non-competitive. I have a 10 year old son. He has words. He is so good with these words it's unbelievable.<br>

        Lorem Ipsum is unattractive, both inside and out. I fully understand why it’s former users left it for something else. They made a good decision. I’m the best thing that ever happened to placeholder text.<br>

        I write the best placeholder text, and I'm the biggest developer on the web by far... While that's mock-ups and this is politics, are they really so different? If Trump Ipsum weren’t my own words, perhaps I’d be dating it.<br>
    </div>
    <!--Tests here!-->
<pre><code class="php">
    function checkUserInput(){
    $res=true;
    isset($_REQUEST['id']) ? $uID=$_REQUEST['id'] : $uID='';
    isset($_REQUEST['code']) ? $uCode=$_REQUEST['code'] : $uCode='';

    if($uID!='' && (preg_match_all('/[0-9]/',$uID)&#60;strlen($uID)) ){
    $res = false;
    }

    if($uCode!='' && (preg_match_all('/[a-z]|[A-Z]|[0-9]/',$uCode)&#60;strlen($uCode)) ){
    $res = false;
    }

    return $res;
    }

    class treeHandler extends dbWithCacheAbstract
    {
    /** @var string[] $treeNames Names of our trees.
    */
    protected $treeNames = [];

    /** @var int $isInitArray Used for lazy tree initiation. */
    public $int = 51111+'strong';
    }
</code></pre>
<pre><code class="javascript">
    function $initHighlight(block, cls) {
    try {
    if (cls.search(/\bno\-highlight\b/) != -1)
    return process(block, true, 0x0F) +
    `   class="${cls}"`;
    }
    catch (e) {
    /* handle exception */
    }

    for (var i = 0 / 2; i < classes.length; i++) {
    if (checkCondition(classes[i]) === undefined)
    console.log('undefined');
    }
    }

    export  $initHighlight;
</code></pre>
    <br>
    <h2><a name="Gen2"></a>Requirements</h2>
    <h6>Back to <a href="#GenM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>

    <h2><a name="Gen3"></a>Versioning Information</h2>
    <h6>Back to <a href="#GenM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>
</article>


<!--Framework Structure-->
<article>
    <h1><a name="FraM"></a>Framework Structure</h1>
    <h6>Back to <a href="#ToC">Table of Content</a> , <a href="#top">Top</a></h6><br>

    <h2><a name="Fra1"></a>General Structure</h2>
    <h6>Back to <a href="#FraM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>

    <h2><a name="Fra2"></a>Functions</h2>
    <h6>Back to <a href="#FraM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>

    <h2><a name="Fra3"></a>Handlers</h2>
    <h6>Back to <a href="#FraM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>

    <h2><a name="Fra4"></a>Settings Structure</h2>
    <h6>Back to <a href="#FraM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>

    <h2><a name="Fra5"></a>Global Definitions</h2>
    <h6>Back to <a href="#FraM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>

    <h2><a name="Fra6"></a>hArray, SafeString</h2>
    <h6>Back to <a href="#FraM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>

    <h2><a name="Fra7"></a>External Works</h2>
    <h6>Back to <a href="#FraM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>
</article>


<!--Database Structure-->
<article>
    <h1><a name="DatM"></a>Database Structure</h1>
    <h6>Back to <a href="#ToC">Table of Content</a> , <a href="#top">Top</a></h6><br>

    <h2><a name="Dat1"></a>Table Descriptions</h2>
    <h6>Back to <a href="#DatM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>

    <h2><a name="Dat2"></a>Database Initiation Function</h2>
    <h6>Back to <a href="#DatM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>

    <!--Functions and Handlers-->
    <h1><a name="FunM"></a>Functions and Handlers</h1>
    <h6>Back to <a href="#ToC">Table of Content</a> , <a href="#top">Top</a></h6><br>

    <h2><a name="Fun1"></a>Settings Handler</h2>
    <h6>Back to <a href="#FunM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>

    <h2><a name="Fun2"></a>Core Function (Bootstrap)</h2>
    <h6>Back to <a href="#FunM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>

    <h2><a name="Fun3"></a>Helper Functions</h2>
    <h6>Back to <a href="#FunM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>

    <h2><a name="Fun4"></a>Session Handler</h2>
    <h6>Back to <a href="#FunM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>

    <h2><a name="Fun5"></a>Security Handler</h2>
    <h6>Back to <a href="#FunM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>

    <h2><a name="Fun6"></a>Authorization Handler</h2>
    <h6>Back to <a href="#FunM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>

    <h2><a name="Fun7"></a>User Registration & Login Shared Code</h2>
    <h6>Back to <a href="#FunM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>

    <h2><a name="Fun8"></a>User Registration & Registration Confirmation API</h2>
    <h6>Back to <a href="#FunM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>

    <h2><a name="Fun9"></a>User Login, Automated Login, and Logout</h2>
    <h6>Back to <a href="#FunM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>

    <h2><a name="Fun10"></a>Mail Handler & API</h2>
    <h6>Back to <a href="#FunM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>

    <h2><a name="Fun11"></a>Password Reset API</h2>
    <h6>Back to <a href="#FunM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>

    <h2><a name="Fun12"></a>General API</h2>
    <h6>Back to <a href="#FunM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>

    <h2><a name="Fun13"></a>Framework Installation File</h2>
    <h6>Back to <a href="#FunM">Main Subject</a> , <a href="#ToC">Table of Content</a></h6><br>
</article>

</span>

</body>



<?php require $settings->getSetting('absPathToRoot').'front/ioframe/templates/footers.php';