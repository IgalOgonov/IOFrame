
<span id="doc">


    <!--General Information-->
    <article>
        <header>
            <h1><a name="title"></a>Framework Overview</h1>
            <h6> <button>General Information</button> <span>/</span> <button class="selected">Framework Overview</button> </h6>
        </header>

        <div>
            I know words. I have the best words. You know, it really doesn't matter what you write as long as you've got a young,
            and beautiful, piece of text. We have so many things that we have to do better... and certainly ipsum is one of them.
            You know, it really doesn't matter what you write as long as you've got a young, and beautiful, piece of text.
            I have a 10 year old son. He has words. He is so good with these words it's unbelievable.<br>

            You know, it really doesn't matter what you write as long as you've got a young, and beautiful, piece of text.<br>

            I think the only difference between me and the other placeholder text is that I'm more honest and my words are more beautiful.
            The concept of Lorem Ipsum was created by and for the Chinese in order to make U.S. design jobs non-competitive.
            I have a 10 year old son. He has words. He is so good with these words it's unbelievable.<br>

            Lorem Ipsum is unattractive, both inside and out. I fully understand why it's former users left it for something else.
            They made a good decision. I'm the best thing that ever happened to placeholder text.<br>

            I write the best placeholder text, and I'm the biggest developer on the web by far...
            While that's mock-ups and this is politics, are they really so different?
            If Trump Ipsum weren't my own words, perhaps I'd be dating it.<br>
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
    </article>

</span>
