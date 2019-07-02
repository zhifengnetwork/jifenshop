<?php

namespace app\common\model;

use think\helper\Time;
use think\Model;
use think\Db;

class Collection extends Model
{
    public static function getCountBy($userId)
    {
        return self::where(['user_id' => $userId])->count();
    }
}
