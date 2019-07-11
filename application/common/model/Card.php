<?php

namespace app\common\model;

use think\Db;
use think\Model;

/**
 * 版本管理
 */
class Card extends Model
{
    public static $status_list = [
        -1 => '审核失败',
        0 => '申请中',
        1 => '审核通过',
    ];

    public static function getStatusTextBy($value)
    {
        return self::$status_list[$value];
    }

    public function getStatusTextAttr($value, $data)
    {
        return self::$status_list[$data['status']];
    }

}