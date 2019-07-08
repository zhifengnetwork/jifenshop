<?php

namespace app\common\model;

use think\helper\Time;
use think\Model;
use think\Db;

class Sysset extends Model
{
    static function getPointArr()
    {
        $config = Db::name('sysset')->where(['id' => 1])->value('point');
        $config = $config ? json_decode($config, true) : [];
        return $config;
    }

    static function getSetsArr()
    {
        $sets = Db::table('sysset')->where(['id' => 1])->value('sets');
        $sets = unserialize($sets);
        return $sets;
    }
}
