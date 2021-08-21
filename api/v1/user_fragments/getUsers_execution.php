<?php

require_once __DIR__ . '/../../../IOFrame/Handlers/UserHandler.php';
$UserHandler = new \IOFrame\Handlers\UserHandler($settings,['SQLHandler'=>$SQLHandler]);

$tempRes = [];

switch ($action){
    case 'getUsers':
        $result = $UserHandler->getUsers([
            'idAtLeast' =>$inputs['idAtLeast'],
            'idAtMost' => $inputs['idAtMost'],
            'rankAtLeast' => $inputs['rankAtLeast'],
            'rankAtMost' => $inputs['rankAtMost'],
            'usernameLike' =>$inputs['usernameLike'],
            'emailLike' =>$inputs['emailLike'],
            'isActive' => $inputs['isActive'],
            'isBanned' =>$inputs['isBanned'],
            'isSuspicious' => $inputs['isSuspicious'],
            'createdBefore' => $inputs['createdBefore'],
            'createdAfter' => $inputs['createdAfter'],
            'orderBy' =>$inputs['orderBy'],
            'orderType' => $inputs['orderType'],
            'limit' =>$inputs['limit'],
            'offset' =>$inputs['offset'],
            'test'=>$test
        ]);

        if(is_array($result))
            foreach($result as $id=>$res){
                if($id === '@')
                    $tempRes[$id] = $res;
                else{
                    $TFA = \IOFrame\Util\is_json($res['Two_Factor_Auth'])? json_decode($res['Two_Factor_Auth'],true) : [];
                    $tempRes[$id] = [
                        'id'=>$res['ID'],
                        'username'=>$res['Username'],
                        'email'=>$res['Email'],
                        'phone'=>$res['Phone'],
                        'active'=>$res['Active']? true : false,
                        'rank'=>$res['Auth_Rank'],
                        'created'=>DateTime::createFromFormat('YmdHis', $res['Created_On'])->getTimestamp(),
                        'bannedUntil'=>(int)$res['Banned_Until'],
                        'require2FA'=>!empty($TFA['require2FA']),
                        'has2FAApp'=>!empty($TFA['2FADetails']['secret']),
                        'suspiciousUntil'=>(int)$res['Suspicious_Until']
                    ];
                }
            }
        break;
    case 'getMyUser':
        $result = $UserHandler->getUsers([
            'idAtLeast' =>$inputs['id'],
            'idAtMost' => $inputs['id'],
            'test'=>$test
        ]);
        if(empty($result['@']['#']) || empty($result[$inputs['id']]))
            exit('-1');
        $TFA = \IOFrame\Util\is_json($result[$inputs['id']]['Two_Factor_Auth'])? json_decode($result[$inputs['id']]['Two_Factor_Auth'],true) : [];
        $tempRes = [
            'id'=>$result[$inputs['id']]['ID'],
            'username'=>$result[$inputs['id']]['Username'],
            'email'=>$result[$inputs['id']]['Email'],
            'phone'=>$result[$inputs['id']]['Phone'],
            'active'=>$result[$inputs['id']]['Active']? true : false,
            'rank'=>$result[$inputs['id']]['Auth_Rank'],
            'created'=>DateTime::createFromFormat('YmdHis', $result[$inputs['id']]['Created_On'])->getTimestamp(),
            'require2FA'=>!empty($TFA['require2FA']),
            'has2FAApp'=>!empty($TFA['2FADetails']['secret'])
        ];
        break;
    default:
        exit('-1');
}

$result = $tempRes;