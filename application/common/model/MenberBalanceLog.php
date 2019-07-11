<?php

namespace app\common\model;

use think\helper\Time;
use think\Model;
use think\Db;

class MenberBalanceLog extends Model
{
    public static $type_list = [
        1 => '下单消费',
        2 => '下级成为vip返佣',
        3 => '申请提现',
        4 => '提现审核失败返还',
        5 => '退款返还',
        7 => '后台充值',
    ];

    public static function getTypeTextBy($value)
    {
        return self::$type_list[$value] ?: '';
    }
}
