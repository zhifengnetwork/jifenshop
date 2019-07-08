<?php

namespace app\common\logic;


use app\common\model\Sysset;
/**
 * 默认周期天数
 */
const DEFAULT_PRE_DAY = 5;

/**
 * 默认释放比例，单位(%)
 */
const DEFAULT_PERCENT = 50;

/**
 * 默认一级返积分
 */
const DEFAULT_FIRST = 200;
/**
 * 默认二级返积分
 */
const DEFAULT_SECOND = 100;

class PointLogic
{
    public static function getSettingDay()
    {
        $config = Sysset::getPointArr();
        return isset($config['preday']) ? $config['preday'] : DEFAULT_PRE_DAY;
    }

    public static function getSettingPercent()
    {
        $config = Sysset::getPointArr();
        $percent = isset($config['percent']) ? $config['percent'] : DEFAULT_PERCENT;
        $percent = (float)$percent / 100;
        return $percent;
    }

    public static function getSettingFirst()
    {
        $config = Sysset::getPointArr();
        return isset($config['first_share']) ? $config['first_share'] : DEFAULT_FIRST;
    }

    public static function getSettingSecond()
    {
        $config = Sysset::getPointArr();
        return isset($config['second_share']) ? $config['second_share'] : DEFAULT_SECOND;
    }
}
