<?php

$tempRes = [];

switch ($action){
    case 'getUsers':
        $result = $UsersHandler->getUsers([
            'idAtLeast' =>$inputs['idAtLeast'],
            'idAtMost' => $inputs['idAtMost'],
            'rankAtLeast' => $inputs['rankAtLeast'],
            'rankAtMost' => $inputs['rankAtMost'],
            'usernameLike' =>$inputs['usernameLike'],
            'emailLike' =>$inputs['emailLike'],
            'isActive' => $inputs['isActive'],
            'isBanned' =>$inputs['isBanned'],
            'isLocked' =>$inputs['isLocked'],
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
                    $TFA = \IOFrame\Util\PureUtilFunctions::is_json($res['Two_Factor_Auth'])? json_decode($res['Two_Factor_Auth'],true) : [];
                    $tempRes[$id] = [
                        'id'=>$res['ID'],
                        'username'=>$res['Username'],
                        'email'=>$res['Email'],
                        'phone'=>$res['Phone'],
                        'active'=>$res['Active'],
                        'rank'=>$res['Auth_Rank'],
                        'created'=>DateTime::createFromFormat('YmdHis', $res['Created'])->getTimestamp(),
                        'bannedUntil'=>(int)$res['Banned_Until'],
                        'lockedUntil'=>(int)$res['Locked_Until'],
                        'suspiciousUntil'=>(int)$res['Suspicious_Until'],
                        'require2FA'=>!empty($TFA['require2FA']),
                        'has2FAApp'=>!empty($TFA['2FADetails']['secret'])
                    ];
                }
            }
        break;
    case 'getMyUser':
        $result = $UsersHandler->getUsers([
            'idAtLeast' =>$inputs['id'],
            'idAtMost' => $inputs['id'],
            'test'=>$test
        ]);
        if(empty($result['@']['#']) || empty($result[$inputs['id']]))
            exit('-1');
        $TFA = \IOFrame\Util\PureUtilFunctions::is_json($result[$inputs['id']]['Two_Factor_Auth'])? json_decode($result[$inputs['id']]['Two_Factor_Auth'],true) : [];
        $tempRes = [
            'id'=>$result[$inputs['id']]['ID'],
            'username'=>$result[$inputs['id']]['Username'],
            'email'=>$result[$inputs['id']]['Email'],
            'phone'=>$result[$inputs['id']]['Phone'],
            'active'=>$result[$inputs['id']]['Active'],
            'rank'=>$result[$inputs['id']]['Auth_Rank'],
            'created'=>DateTime::createFromFormat('YmdHis', $result[$inputs['id']]['Created'])->getTimestamp(),
            'require2FA'=>!empty($TFA['require2FA']),
            'has2FAApp'=>!empty($TFA['2FADetails']['secret'])
        ];
        break;
    default:
        exit('-1');
}

$result = $tempRes;