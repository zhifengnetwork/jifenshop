<?php

namespace app\common\model;

use think\helper\Time;
use think\Model;
use think\Db;

class MenberBalanceLog extends Model
{
    public static $type_list = [
        1 => '下单消费',
        2 => '推荐返佣',
        7 => '后台充值',
    ];

    public static function getTypeTextBy($value)
    {
        return self::$type_list[$value] ?: '';
    }
}
