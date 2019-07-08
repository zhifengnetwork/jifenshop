<?php

namespace app\common\model;

use think\Db;
use think\Model;

class Team extends Model
{
    static function getXiaCount($userId = 0)
    {
        return $userId > 0 ? self::where(['team_user_id' => $userId])->count() : 0;
    }
}
