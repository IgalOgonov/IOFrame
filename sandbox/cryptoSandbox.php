<?php

//Random cipher test
$bytes = openssl_random_pseudo_bytes(15, $cstrong);
$hex   = bin2hex($bytes);
$iv = '';
$iv_secure = false;
while(!$iv_secure)
    $iv=openssl_random_pseudo_bytes(16,$iv_secure);

$data = \IOFrame\Util\PureUtilFunctions::stringScrumble('b360e5938c1feb6efc375a8fb3b4d13c','f03245b527eefa2b7707cce9ca652d89');//$hex;
$method = 'aes-256-ecb';
$key = \IOFrame\Util\PureUtilFunctions::stringScrumble('b360e5938c1feb6efc375a8fb3b4d13c','f03245b527eefa2b7707cce9ca652d89');//bin2hex(openssl_random_pseudo_bytes(32,$iv_secure));
//$iv = '84e0cc3947f083b1b7d2613861bccf1d';//bin2hex($iv);

$e = openssl_encrypt( $data, $method, hex2bin( $key ), OPENSSL_ZERO_PADDING);
$c = openssl_decrypt( $e, $method, hex2bin( $key ), OPENSSL_ZERO_PADDING);
echo 'Ciphertext: ['.bin2hex( base64_decode( $e )).']'.EOL;
//echo 'iv: ['.$iv.']<br>';
echo 'Key:        ['.$key.']'.EOL;
echo 'Cleartext:  ['.$c.']'.EOL;
echo 'Descrumbled: ['.IOFrame\Util\PureUtilFunctions::stringDescrumble($c)[0].', '.IOFrame\Util\PureUtilFunctions::stringDescrumble($c)[1].']'.EOL;

echo EOL;