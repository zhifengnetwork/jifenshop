<?php

namespace app\common\model;

use think\Model;

class Team extends Model
{
    static function getXiaCount($userId = 0, $time = 0)
    {
        $where = ['team_user_id' => $userId];
        if ($time > 0) {
            $begin_time = mktime(0, 0, 0, date('m',$time), date('d',$time), date('Y',$time));
            $where['add_time'] = [['EGT', $begin_time], ['LT', $time]];
        }
        return $userId > 0 ? self::where($where)->count() : 0;
    }
}
