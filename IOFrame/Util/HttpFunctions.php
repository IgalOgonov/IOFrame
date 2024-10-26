<?php
namespace IOFrame\Util{

    define('IOFrameUtilHTTPFunctions',true);

    class HttpFunctions{
        /** Parses raw HTTP PUT request
         * @param array $aData
         * @param array $serverParams
         * @link https://stackoverflow.com/questions/5483851/manually-parse-raw-multipart-form-data-data-with-php
         */
        public static function parseRawHTTPRequest(array &$aData, array $serverParams): void {
            if(empty($serverParams['CONTENT_TYPE']))
                return;

            // read incoming data
            $input = file_get_contents('php://input');

            // grab multipart boundary from content type header
            preg_match('/boundary=(.*)$/', $serverParams['CONTENT_TYPE'], $matches);
            $boundary = $matches[1];

            // split content by boundary and get rid of last -- element
            $a_blocks = preg_split("/-+$boundary/", $input);
            array_pop($a_blocks);

            // loop data blocks
            foreach ($a_blocks as $block)
            {
                if (empty($block))
                    continue;

                // you'll have to var_dump $block to understand this and maybe replace \n or \r with a visibile char

                // parse uploaded files
                if (str_contains($block, 'application/octet-stream'))
                {
                    // match "name", then everything after "stream" (optional) except for prepending newlines
                    preg_match("/name=\"([^\"]*)\".*stream[\n|\r]+([^\n\r].*)?$/s", $block, $matches);
                }
                // parse all other fields
                else
                {
                    // match "name" and optional value in between newline sequences
                    preg_match('/name=\"([^\"]*)\"[\n|\r]+([^\n\r].*)?\r$/s', $block, $matches);
                }
                if(isset($matches[2]))
                    $aData[$matches[1]] = $matches[2];
            }
        }
    }


}