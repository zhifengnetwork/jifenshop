<?php

namespace app\cron\controller;

use app\common\logic\PointLogic;
use app\common\model\Member;
use think\Controller;
use think\Db;

/**
 * 积分
 * Class Point
 * @package app\cron\controller
 */
class Point extends Controller
{
    // 释放积分
    function release()
    {
        $config_day = PointLogic::getSettingDay();
        $config_percent = PointLogic::getSettingPercent();

        // 未完结的积分释放数据
        $list = Db::name('point_release')->where(['is_finished' => 0, 'type' => 1])->order('id asc')->select();
        foreach ($list as $v) {
            $day = (time() - $v['update_time']) / 86400;
            if ($day >= $config_day) {
                $point = bcmul($v['amount'], $config_percent, 2);
                $unreleased = bcsub($v['unreleased'], $point, 2);
                $released = bcadd($v['released'], $point, 2);
                $finished = 0;
                if ($point === '0.00' || $unreleased <= 0) {
                    $unreleased = 0;
                    $finished = 1;
                    $released = $v['amount'];
                    $point = $v['unreleased'];
                }

                \think\Db::startTrans();
                //释放积分更新
                $res = Db::name('point_release')->where(['id' => $v['id']])
                    ->update(['unreleased' => $unreleased, 'released' => $released, 'is_finished' => $finished, 'update_time' => time()]);
                if (!$res) \think\Db::rollback();

                //用户可用积分、待释放积分更新
                $member = Member::get($v['user_id']);
                $before_point = $member->ky_point;
                $after_point = bcadd($before_point, $point, 2);
                $dsf_point = bcsub($member->dsf_point, $point, 2);
                $res = $member->save(['ky_point' => $after_point, 'dsf_point' => $dsf_point > 0 ? $dsf_point : 0]);
                if (!$res) \think\Db::rollback();

                //新增释放记录
                $res = \think\Db::name('point_log')->insert([
                    'type' => 6,
                    'user_id' => $v['user_id'],
                    'point' => $point,
                    'operate_id' => $v['id'],
                    'calculate' => 1,
                    'before' => $before_point,
                    'after' => $after_point,
                    'data' => json_encode(['unreleased' => $unreleased]),
                    'create_time' => time()
                ]);
                if (!$res) \think\Db::rollback();
                \think\Db::commit();
            }
        }
        die('done');
    }
}