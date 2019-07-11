<?php

namespace app\admin\controller;

use app\common\model\Card;
use app\common\model\Collection;
use app\common\model\Member;
use app\common\model\MenberBalanceLog;
use app\common\model\PointLog;
use app\common\model\PointRelease;
use think\Db;
use app\common\model\Order as OrderModel;
use app\common\model\Member as MemberModel;
use app\common\model\MemberWithdrawal;
use think\Request;

class Finance extends Common
{
    // 余额记录
    public function balance_logs()
    {
        $begin_time = input('begin_time', '');
        $end_time = input('end_time', '');
        $kw = input('realname', '');
        $source_type = input('source_type', '');
        $level = input('level', '');
        $where = [];
        if (!empty($source_type)) {
            $where['log.source_type'] = $source_type;
        }

        if (!empty($kw)) {
            is_numeric($kw) ? $where['m.mobile'] = ['like', "%{$kw}%"] : $where['m.realname'] = ['like', "%{$kw}%"];
        }
        if ($begin_time && $end_time) {
            $where['log.create_time'] = [['EGT', strtotime($begin_time)], ['LT', strtotime($end_time)]];
        } elseif ($begin_time) {
            $where['log.create_time'] = ['EGT', strtotime($begin_time)];
        } elseif ($end_time) {
            $where['log.create_time'] = ['LT', strtotime($end_time)];
        }

        // 携带参数
        $carryParameter = [
            'kw' => $kw,
            'level' => $level,
            'source_type' => $source_type,
            'begin_time' => $begin_time,
            'end_time' => $end_time,
        ];

        // 导出
        $tplType = input('tplType', '');
        if ($tplType == 'export') {
            $carryParameter['tplType'] = 'export';
            $list = OrderModel::alias('uo')->field('uo.*,d.order_id as order_idd,d.invoice_no,a.realname')
                ->join("delivery_doc d", 'uo.order_id=d.order_id', 'LEFT')
                ->join("member a", 'a.id=uo.user_id', 'LEFT')
                ->where($where)
                ->order('uo.order_id DESC')
                ->select();
            $str = "订单ID,用户id,订单金额\n";

            foreach ($list as $key => &$val) {
                $str .= $val['order_id'] . ',' . $val['user_id'] . ',' . $val['order_amount'] . ',';
                $str .= "\n";
            }
            export_to_csv($str, '余额记录', $carryParameter);
        } else {
            $list = Db::name('menber_balance_log')->alias('log')
                ->field('log.id,m.id as mid, log.user_id,log.source_type,m.realname,m.avatar,m.weixin,log.note,log.source_type,m.nickname,m.mobile,log.old_balance,log.money,log.balance,log.log_type,log.create_time')
                ->join("member m", 'm.id=log.user_id', 'LEFT')
                ->where($where)
                ->where(['log.balance_type' => 0])
                ->order('log.create_time DESC')
                ->paginate(10, false, ['query' => $carryParameter]);
        }

        // 模板变量赋值
        return $this->fetch('', [
            'list' => $list,
            'exportParam' => $carryParameter,
            'kw' => $kw,
            'level' => $level,
            'source_type' => $source_type,
            'type_list' => MenberBalanceLog::$type_list,
            'levels' => MemberModel::getLevels(),
            'begin_time' => empty($begin_time) ? '' : $begin_time,
            'end_time' => empty($end_time) ? '' : $end_time,
            'meta_title' => '余额记录',
        ]);
    }

    // 余额充值
    public function balance_recharge()
    {
        $uid = input('id/d', 27);
        $profile = MemberModel::get($uid);
        $balance_info = get_balance($uid, 0);
        if (Request::instance()->isPost()) {
            $num = input('num/f');
            if ($num <= 0) {
                $this->error('输入的金额有误');
            }

            MemberModel::setBalance($uid, 0, $num, array(UID, '余额充值'));
            $this->success('充值成功', url('member/member_edit', ['id' => $profile['id']]));
        }
        $profile['balance'] = $balance_info['balance'];
        $this->assign('profile', $profile);
        $this->assign('meta_title', '余额充值');
        return $this->fetch();
    }

    // 提现设置
    public function withdrawalset()
    {
        $sysset = Db::table('sysset')->field('*')->find();
        $set = unserialize($sysset['sets']);

        if (Request::instance()->isPost()) {
            $set['withdrawal']['bank'] = trim(input('bank'));
            $set['withdrawal']['lines'] = trim(input('lines'));//最小提现金额

            $max = input('max/f', 0);
            $fushi1 = input('fushi1/f', 0);
            $fushi2 = input('fushi2/f', 0);

            if (input('max') > 0) {
                $set['withdrawal']['max'] = $max;//最大提现金额
            } else {
                $this->error('每次最高提现金额不能少于0');
            }

            $max_preday = input('max_preday', 0);
            if ($max_preday > 0) {
                $set['withdrawal']['max_preday'] = $max_preday;//最大提现金额
            } else {
                $this->error('每个用户每天最高提现金额不能少于0');
            }
            if ($fushi1 > 0) {
                $set['withdrawal']['fushi1'] = $fushi1;//购买金额
            } else {
                $set['withdrawal']['fushi1'] = 0;//购买金额
            }
            if ($fushi2 > 0) {
                $set['withdrawal']['fushi2'] = $fushi2;//购买金额
            } else {
                $set['withdrawal']['fushi2'] = 0;//购买金额
            }

            $rate = input('rate');
            if ($rate < 0.01 || $rate > 100) {
                $this->error('提现手续费0.01-100');
            }
            $set['withdrawal']['rate'] = $rate;
            $set['withdrawal']['tool'] = empty(input('tool/a')) || !is_array(input('tool/a')) ? '' : input('tool/a');
            $set['withdrawal']['ok'] = input('ok/d', 0);
            $set['withdrawal']['card_num'] = input('card_num/d', 0);
            $set['withdrawal']['times'] = input('times/d', 0);
            $res = Db::name('sysset')->where(['id' => 1])->update(['sets' => serialize($set)]);
            if ($res !== false) {
                $this->success('编辑成功', url('finance/withdrawalset'));
            }
            $this->error('编辑失败');

        }
        $this->assign('set', $set);
        $this->assign('meta_title', '积分充值');
        return $this->fetch();
    }

    // 提现列表
    public function withdrawal_list()
    {
        $where = array();
        $type = input('type/d', 0);
        $status = input('status');
        $kw = input('kw');
        $begin_time = input('begin_time', '');
        $end_time = input('end_time', '');
        $ckbegin_time = input('ckbegin_time', '');
        $ckend_time = input('ckend_time', '');

        if ($type > 0) $where['w.type'] = $type;
        if ($status != '') $where['w.status'] = $status;
        if (!empty($kw)) is_numeric($kw) ? $where['m.mobile'] = ['like', "%{$kw}%"] : $where['m.realname'] = ['like', "%{$kw}%"];

        if ($begin_time && $end_time) {
            $where['w.createtime'] = [['EGT', strtotime($begin_time)], ['LT', strtotime($end_time)]];
        } elseif ($begin_time) {
            $where['w.createtime'] = ['EGT', strtotime($begin_time)];
        } elseif ($end_time) {
            $where['w.createtime'] = ['LT', strtotime($end_time)];
        }

        if ($ckbegin_time && $ckend_time) {
            $where['w.checktime'] = [['EGT', strtotime($ckbegin_time)], ['LT', strtotime($ckend_time)]];
        } elseif ($ckbegin_time) {
            $where['w.checktime'] = ['EGT', strtotime($ckbegin_time)];
        } elseif ($ckend_time) {
            $where['w.checktime'] = ['LT', strtotime($ckend_time)];
        }

        $list = MemberWithdrawal::alias('w')
            ->field('w.*, m.id as mid , m.level , m.avatar , m.nickname , m.realname , m.mobile ,m.weixin')
            ->join("member m", 'm.id = w.user_id', 'LEFT')
            ->where($where)
            ->order('w.id DESC')
            ->paginate(10, false, ['query' => $where]);
        return $this->fetch('finance/withdrawal_list', [
            'type' => $type,
            'status' => $status,
            'kw' => $kw,
            'begin_time' => $begin_time,
            'end_time' => $end_time,
            'ckbegin_time' => $ckbegin_time,
            'ckend_time' => $ckend_time,
            'type_list' => MemberWithdrawal::$type_list,
            'status_list' => MemberWithdrawal::$status_list,
            'list' => $list,
            'meta_title' => '余额提现列表',
        ]);
    }

    // 积分记录
    public function integral_logs()
    {
        $begin_time = input('begin_time', '');
        $end_time = input('end_time', '');
        $kw = input('realname', '');
        $type = input('type', '');
        $where = [];
        if (!empty($type)) $where['log.type'] = $type;
        if (!empty($level)) $where['m.level'] = $level;
        if (!empty($kw)) is_numeric($kw) ? $where['m.mobile'] = ['like', "%{$kw}%"] : $where['m.realname'] = ['like', "%{$kw}%"];

        if ($begin_time && $end_time) {
            $where['m.createtime'] = [['EGT', strtotime($begin_time)], ['LT', strtotime($end_time)]];
        } elseif ($begin_time) {
            $where['m.createtime'] = ['EGT', strtotime($begin_time)];
        } elseif ($end_time) {
            $where['m.createtime'] = ['LT', strtotime($end_time)];
        }

        // 携带参数
        $carryParameter = [
            'kw' => $kw,
            'type' => $type,
            'begin_time' => $begin_time,
            'end_time' => $end_time,
        ];

        $list = Db::name('point_log')->alias('log')
            ->field('log.id,log.type,m.id as mid,log.user_id,log.point,m.realname,m.avatar,m.weixin,log.before,m.nickname,m.mobile,log.after,log.operate_id,log.create_time')
            ->join("member m", 'm.id=log.user_id', 'LEFT')
            ->where($where)
            ->order('log.create_time DESC')
            ->paginate(10, false, ['query' => $carryParameter]);

        // 模板变量赋值
        return $this->fetch('', [
            'list' => $list,
            'type_list' => PointLog::$_type,
            'kw' => $kw,
            'type' => $type,
            'begin_time' => empty($begin_time) ? '' : $begin_time,
            'end_time' => empty($end_time) ? '' : $end_time,
            'meta_title' => '积分记录',
        ]);
    }

    // 积分设置
    public function integral_set()
    {
        $sysset = Db::table('sysset')->field('*')->find();
        $set = json_decode($sysset['point'], true);

        if (Request::instance()->isPost()) {
            $set['preday'] = input('preday/d', 0);
            $set['percent'] = input('percent/d', 0);
            $set['first_share'] = bcadd(input('first_share/d', 0), 0, 2);
            $set['second_share'] = bcadd(input('second_share/d', 0), 0, 2);
            if ($set['preday'] < 1 || $set['preday'] > 1000) {
                $this->error('周期1-1000');
            }
            if ($set['percent'] < 1 || $set['percent'] > 100) {
                $this->error('百分比1-100');
            }

            if ($set['first_share'] < 0.01 || $set['first_share'] > 100000) {
                $this->error('邀请一级获得积分0.01-100000');
            }

            if ($set['second_share'] < 0.01 || $set['second_share'] > 100000) {
                $this->error('邀请二级获得积分0.01-100000');
            }
            $res = Db::name('sysset')->where(['id' => 1])->update(['point' => json_encode($set)]);
            if ($res !== false) {
                $this->success('编辑成功', url('finance/integral_set'));
            }
            $this->error('编辑失败');
        }
        $this->assign('set', $set);
        $this->assign('meta_title', '积分设置');
        return $this->fetch();
    }

    // 积分充值
    public function integral_recharge()
    {
        $uid = input('id/d', 0);
        if (!$profile = MemberModel::get($uid)) {
            $this->error('id错误');
        }
        $balance_info = get_balance($uid, 1);
        if (Request::instance()->isPost()) {
            $num = input('num/f');
            if ($num <= 0) {
                $this->error('输入的积分有误');
            }
            $before = $profile->ky_point;
            $point = bcadd($before, $num, 2);
            Db::startTrans();
            if (!$profile->save(['ky_point' => $point])) {
                Db::rollback();
                $this->error('充值失败');
            }
            //加日志
            Db::name('point_log')->insert([
                'type' => 1,
                'user_id' => $uid,
                'point' => $num,
                'operate_id' => 0,
                'calculate' => 1,
                'before' => $before,
                'after' => $point,
                'create_time' => time()
            ]);


            Db::commit();
            $this->success('充值成功', url('finance/integral_recharge', ['id' => $profile['id']]));

        }
        $profile['balance'] = $balance_info['balance'];
        $this->assign('profile', $profile);
        $this->assign('meta_title', '积分充值');
        return $this->fetch();
    }

    // 提现审核操作
    public function withdrawal()
    {
        $status = input('status/d');
        if ($status != -1 && $status != 1) {
            $this->error('状态错误');
        }
        $id = input('id/d');
        $withdrawal = MemberWithdrawal::get($id);
        if (!$withdrawal || $withdrawal->status != 0) {
            $this->error('数据没有找到或不能操作');
        }
        $content = input('content');
        if ($status == -1 && !$content) {
            $this->error('内容不能为空');
        }
        Db::startTrans();
        $res = $withdrawal->save(['status' => $status, 'content' => $content, 'checktime' => time()]);
        if (!$res) {
            Db::rollback();
            $this->error('操作失败');
        }
        // 审核失败，退回余额
        if ($status == -1) {
            $member = Db::name('member')->where(['id' => $withdrawal->user_id])->find();
            $balance = bcadd($member['balance'], $withdrawal->money, 2);
            $res = Db::name('member')->where(['id' => $withdrawal->user_id])->update(['balance' => $balance]);
            $res && $res = Db::name('menber_balance_log')->insert([
                'user_id' => $withdrawal->user_id,
                'balance_type' => 0,
                'log_type' => 1,
                'source_type' => 4,
                'source_id' => $withdrawal->id,
                'money' => $withdrawal->money,
                'old_balance' => $member['balance'],
                'balance' => $balance,
                'create_time' => time(),
                'note' => '提现审核失败返还'
            ]);
            if (!$res) {
                Db::rollback();
                $this->error('操作失败');
            }
        }

        Db::commit();
        $this->success('操作成功', url('finance/withdrawal_list'));
    }

    public function transfer_logs(){
        $begin_time = input('begin_time', '');
        $end_time = input('end_time', '');
        $kw = input('realname', '');
        $where = [];
        if (!empty($kw)) is_numeric($kw) ? $where['m.mobile'] = ['like', "%{$kw}%"] : $where['m.realname'] = ['like', "%{$kw}%"];

        if ($begin_time && $end_time) {
            $where['logcreate_time'] = [['EGT', strtotime($begin_time)], ['LT', strtotime($end_time)]];
        } elseif ($begin_time) {
            $where['log.create_time'] = ['EGT', strtotime($begin_time)];
        } elseif ($end_time) {
            $where['log.create_time'] = ['LT', strtotime($end_time)];
        }

        // 携带参数
        $carryParameter = [
            'kw' => $kw,
            'begin_time' => $begin_time,
            'end_time' => $end_time,
        ];

        $list = Db::name('point_transfer')->alias('log')
            ->field('log.*,
            m.id as u_id,m.realname as u_realname,m.avatar as u_avatar,m.nickname as u_nickname,m.mobile as u_mobile,
            to.id as to_id,to.realname as to_realname,to.avatar as to_avatar,to.nickname as to_nickname,to.mobile as to_mobile')
            ->join("member m", 'm.id=log.user_id', 'LEFT')
            ->join("member to", 'to.id=log.to_user_id', 'LEFT')
            ->where($where)
            ->order('log.create_time DESC')
            ->paginate(10, false, ['query' => $carryParameter]);

        // 模板变量赋值
        return $this->fetch('', [
            'list' => $list,
            'kw' => $kw,
            'begin_time' => empty($begin_time) ? '' : $begin_time,
            'end_time' => empty($end_time) ? '' : $end_time,
            'meta_title' => '积分转账记录',
        ]);
    }

    public function card()
    {
        $where = array();
        $status = input('status');
        $kw = input('kw');
        $begin_time = input('begin_time', '');
        $end_time = input('end_time', '');

        if ($status != '') $where['c.status'] = $status;
        if (!empty($kw)) is_numeric($kw) ? $where['m.mobile'] = ['like', "%{$kw}%"] : $where['m.realname'] = ['like', "%{$kw}%"];

        if ($begin_time && $end_time) {
            $where['c.create_time'] = [['EGT', strtotime($begin_time)], ['LT', strtotime($end_time)]];
        } elseif ($begin_time) {
            $where['c.create_time'] = ['EGT', strtotime($begin_time)];
        } elseif ($end_time) {
            $where['c.create_time'] = ['LT', strtotime($end_time)];
        }

        $list = Card::alias('c')
            ->field('c.*, m.id as mid  , m.avatar , m.nickname , m.realname , m.mobile')
            ->join("member m", 'm.id = c.user_id', 'LEFT')
            ->where($where)
            ->order('c.id DESC')
            ->paginate(10, false, ['query' => $where]);
        return $this->fetch('', [
            'status' => $status,
            'kw' => $kw,
            'begin_time' => $begin_time,
            'end_time' => $end_time,
            'status_list' => Card::$status_list,
            'list' => $list,
            'meta_title' => '银行卡列表',
        ]);
    }

    public function check_card()
    {
        $status = input('status/d');
        if ($status != -1 && $status != 1) {
            $this->error('状态错误');
        }
        $id = input('id/d');
        $card = Card::get($id);
        if (!$card || $card->status != 0) {
            $this->error('数据没有找到或不能操作');
        }
        $content = input('msg');
        if ($status == -1 && !$content) {
            $this->error('内容不能为空');
        }
        $res = $card->save(['status' => $status, 'msg' => $content, 'check_time' => time()]);
        if (!$res) {
            $this->error('操作失败');
        }

        $this->success('操作成功', url('finance/card'));
    }
}