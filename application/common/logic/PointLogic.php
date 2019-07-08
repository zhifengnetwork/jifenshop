<?php

namespace app\common\logic;


use app\common\model\Sysset;
/**
 * 默认周期天数
 */
const DRFAULT_PRE_DAY = 5;

/**
 * 默认释放比例，单位(%)
 */
const DRFAULT_PERCENT = 50;

class PointLogic
{
    public static function getSettingDay()
    {
        $config = Sysset::getPointArr();
        return isset($config['preday']) ? $config['preday'] : DRFAULT_PRE_DAY;
    }

    public static function getSettingPercent()
    {
        $config = Sysset::getPointArr();
        var_dump($config);
        $percent = isset($config['percent']) ? $config['percent'] : DRFAULT_PERCENT;
        $percent = (float)$percent / 100;
        return $percent;
    }
}
