<?php

namespace app\common\model;

use think\helper\Time;
use think\Model;
use think\Db;

class PointRelease extends Model
{
    static $_type = [
        1 => '购物积分',
    ];

    public static function getTypeName($type = 1)
    {
        return self::$_type[$type] ?: '';
    }

}
