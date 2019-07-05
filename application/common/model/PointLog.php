<?php

namespace app\common\model;

use think\helper\Time;
use think\Model;
use think\Db;

class PointLog extends Model
{
    static $_type = [
        2 => '下单消费',
        3 => '分享赚取',//分享赚取，一级返利
        4 => '转账',
        5 => '收账',
        6 => '释放',
        7 => '二级返利',
    ];

    public static function getTypeName($type = 1)
    {
        return self::$_type[$type] ?: '';
    }

}
