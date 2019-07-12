<?php

namespace app\common\model;

use think\helper\Time;
use think\Model;
use think\Db;

class PointLog extends Model
{
    static $_type = [
        1 => '后台充值',
        2 => '下单消费',
        3 => '分享赚取',//分享赚取，一级返利
        4 => '转账',
        5 => '收账',
        6 => '释放',
        7 => '二级返利',
        11 => '下单增加待收货积分',
        12 => '申请退款成功减少待收货积分',
        13 => '确认收货待收货减少',
        14 => '确认收货待释放增加',
        15 => '订单退款',//订单积分支付，退款成功，退至可用积分
    ];

    public static function getTypeName($type = 1)
    {
        return self::$_type[$type] ?: '';
    }

    // 推荐奖励积分
    public static function getTeamPoint($user_id = 0)
    {
        return $user_id > 0 ? Db::name('point_log')
            ->where(['user_id' => $user_id])
            ->where(function ($query) {
                $query->whereor('type', 'eq', 3)->whereor('type', 'eq', 7);
            })->sum('point') : 0;
    }

}
