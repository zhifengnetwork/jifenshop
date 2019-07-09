<?php

namespace app\common\model;

use think\helper\Time;
use think\Model;
use think\Db;

class VipCard extends Model
{
    // 生成随机会员卡号
    static function generate()
    {
        $alpha = '123456789';
        $number = '0' . $alpha;
        $numJoin = strlen($alpha);
        while (true) {
            $code = $alpha[mt_rand(0, $numJoin - 1)];
            $len = 9;
            while ($len--) {
                $code .= $number[mt_rand(0, $numJoin)];
            }
            if (!Db::name('vip_card')->where(['number' => $code])->find()) {
                break;
            }
        }
        return $code;
    }

    static function getByUser($user_id)
    {
        return Db::name('vip_card')->where(['user_id' => $user_id])->find();
    }
}
