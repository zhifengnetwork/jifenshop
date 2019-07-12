<?php
/**
 * 我的API
 */

namespace app\api\controller;

use app\common\model\UserAddr;
use app\common\logic\OrderLogic;
use app\common\model\Collection as CollectionM;
use app\common\model\Member;
use app\common\model\MemberWithdrawal;
use app\common\model\PointLog;
use app\common\model\PointRelease;
use app\common\model\Sysset;
use app\common\model\Team;
use think\AjaxPage;
use think\Db;

class Home extends ApiBase
{
    private $_userId;

    /**
     * @var Member
     */
    private $_user;

    public function __construct()
    {
        $this->_userId = $this->get_user_id();
        if (!$this->_userId || !($this->_user = Member::get($this->_userId))) {
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在']);
        }
    }

    // 总览
    public function index()
    {
        $collection_count = M('collection')->alias('c')
            ->join('goods g', 'g.goods_id = c.goods_id', 'INNER')
            ->where("c.user_id = $this->_userId")
            ->where('g.is_show=1')
            ->count();
        $data = [
            'id' => $this->_userId,
            'mobile' => $this->_user->mobile,
            'nickname' => $this->_user->nickname,
            'avatar' => $this->_user->avatar,
            'level' => $this->_user->is_vip == 1 || $this->_user->is_card_vip == 1 ? 'VIP会员' : '普通会员',
            'waitPay' => OrderLogic::getCount($this->_userId, 'dfk'), //待付款数量
            'waitSend' => OrderLogic::getCount($this->_userId, 'dfh'),//待发货数量
            'waitReceive' => OrderLogic::getCount($this->_userId, 'dsh'), //待收货数量
            'waitComment' => OrderLogic::getCount($this->_userId, 'dpj'), //待评论数
            'return' => OrderLogic::getCount($this->_userId, 'tk'),
            'money' => $this->_user->balance,//余额
            'point' => $this->_user->ky_point,//积分
            'collect' => $collection_count,
            'team_underling' => Team::getXiaCount($this->_userId),
            'team_point' => PointLog::getTeamPoint($this->_userId),
            'team_today' => Team::getXiaCount($this->_userId, time())
        ];

        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => $data]);
    }

    // 发送短信
    public function send_sms()
    {
        $type = input('type', 'mobile');
        $mobile = input('mobile', '');
        if ($type == 'mobile') {
            $this->_user->mobile && $this->ajaxReturn(['status' => -2, 'msg' => '已绑定手机号！']);
            if (!checkMobile($mobile)) {
                $this->ajaxReturn(['status' => -2, 'msg' => '手机格式错误！']);
            }
            if (Member::get(['mobile' => $mobile])) {
                $this->ajaxReturn(['status' => -2, 'msg' => '手机号不可用！']);
            }
        }
        if ($type == 'pwd') {
            !$this->_user->mobile && $this->ajaxReturn(['status' => -2, 'msg' => '未绑定手机号！']);
            $mobile = $this->_user->mobile;
        }

        $res = Db::name('phone_auth')->field('exprie_time')->where('mobile', '=', $mobile)->order('id DESC')->find();
        if ($res['exprie_time'] > time()) {
            $this->ajaxReturn(['status' => -2, 'msg' => '请求频繁请稍后重试！']);
        }

        $code = mt_rand(111111, 999999);

        $data['mobile'] = $mobile;
        $data['auth_code'] = $code;
        $data['start_time'] = time();
        $data['exprie_time'] = time() + 60;

        $res = Db::table('phone_auth')->insert($data);
        if (!$res) {
            $this->ajaxReturn(['status' => -2, 'msg' => '发送失败，请重试！']);
        }

        $ret = send_zhangjun($mobile, $code);
        if ($ret['message'] == 'ok') {
            $this->ajaxReturn(['status' => 1, 'msg' => '发送成功！']);
        }
        $this->ajaxReturn(['status' => -2, 'msg' => '发送失败，请重试！']);
    }

    //  绑定手机号
    public function bind_mobile()
    {
        // 当前用户已有手机号
        if ($this->_user->mobile) {
            $this->ajaxReturn(['status' => -2, 'msg' => '已绑定手机号！']);
        }
        $mobile = input('mobile', '');
        $code = input('code');

        if (!checkMobile($mobile)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机格式错误！']);
        }
        if (Member::get(['mobile' => $mobile])) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机号不可用！']);
        }

        $res = action('PhoneAuth/phoneAuth', [$mobile, $code]);
        if ($res === '-1') {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码已过期！']);
        } else if (!$res) {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码错误！']);
        }

        $res1 = $this->_user->save(['mobile' => $mobile]);

        if ($res1 === false) {
            $this->ajaxReturn(['status' => -2, 'msg' => '绑定失败！']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '绑定成功！', 'data' => ['mobile' => $mobile]]);
    }

    // 用户信息
    function get_user_info()
    {
        $sets = Sysset::getSetsAttr();
        $this->ajaxReturn([
            'status' => 1,
            'msg' => '获取成功',
            'data' => [
                'mobile' => $this->_user->mobile ?: '',
                'money' => $this->_user->balance,
                'point' => $this->_user->ky_point,
                'ds_point' => bcadd($this->_user->dsh_point, $this->_user->dsf_point, 2),
                'alipay' => $this->_user->alipay ?: '',
                'pwd' => $this->_user->pwd ? 1 : 0,
                'show' => Sysset::getWDShow(), //开关
                'withdraw_rate' => isset($sets['withdrawal']['rate']) ? $sets['withdrawal']['rate'] : 0,
                'withdraw_max' => isset($sets['withdrawal']['max']) ? $sets['withdrawal']['max'] : 0
            ]
        ]);
    }

    // 设置/重置支付密码
    function pwd()
    {
        if (!$this->_user->mobile) {
            $this->ajaxReturn(['status' => -2, 'msg' => '未设置手机号！']);
        }
        $pwd = trim(input('pwd'));
        $pwd1 = trim(input('pwd1'));
        if (strlen($pwd) == 0) $this->ajaxReturn(['status' => -2, 'msg' => '密码不能为空！']);
        if (strlen($pwd) != 6) {
            $this->ajaxReturn(['status' => -2, 'msg' => '密码长度为6！']);
        }
        if (strlen($pwd1) == 0) $this->ajaxReturn(['status' => -2, 'msg' => '确认密码不能为空！']);
        if (strlen($pwd1) != 6) {
            $this->ajaxReturn(['status' => -2, 'msg' => '确认密码长度为6！']);
        }
        if ($pwd != $pwd1) {
            $this->ajaxReturn(['status' => -2, 'msg' => '两次密码不一致', 'data' => '']);
        }
        $code = trim(input('code/d'));
        if (!$code) {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码必填！']);
        }
        $res = action('PhoneAuth/phoneAuth', [$this->_user->mobile, $code]);
        if ($res === '-1') {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码已过期！']);
        } else if (!$res) {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码错误！']);
        }

        $password = md5($this->_user->salt . $pwd);
        if ($password != $this->_user->pwd) {
            $res = $this->_user->save(['pwd' => $password]);
            !$res && $this->ajaxReturn(['status' => -2, 'msg' => '设置失败！']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '设置成功！']);
    }

    // 修改支付密码
    function change_pwd()
    {
        $pwd = trim(input('pwd'));
        $password1 = trim(input('password1'));
        $password2 = trim(input('password2'));
        if (strlen($pwd) == 0) $this->ajaxReturn(['status' => -2, 'msg' => '原密码不能为空！']);
        if (strlen($pwd) != 6) {
            $this->ajaxReturn(['status' => -2, 'msg' => '原密码长度为6！']);
        }
        if (strlen($password1) == 0) $this->ajaxReturn(['status' => -2, 'msg' => '密码不能为空！']);
        if (strlen($password1) != 6) {
            $this->ajaxReturn(['status' => -2, 'msg' => '密码长度为6！']);
        }
        if (strlen($password2) == 0) $this->ajaxReturn(['status' => -2, 'msg' => '确认密码不能为空！']);
        if (strlen($password2) != 6) {
            $this->ajaxReturn(['status' => -2, 'msg' => '确认密码长度为6！']);
        }
        if ($password1 != $password2) {
            $this->ajaxReturn(['status' => -2, 'msg' => '两次密码不一致', 'data' => '']);
        }
        $pwd = md5($this->_user->salt . $pwd);
        $password = md5($this->_user->salt . $password2);
        if ($pwd != $this->_user->pwd) {
            $this->ajaxReturn(['status' => -2, 'msg' => '原密码错误']);
        }
        if ($password == $this->_user->pwd) {
            $this->ajaxReturn(['status' => -2, 'msg' => '新密码和原密码不能相同']);
        }
        if (!$this->_user->save(['pwd' => $password])) {
            $this->ajaxReturn(['status' => -2, 'msg' => '修改失败']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '修改成功']);
    }

    // 绑定支付宝
    function bind_alipay()
    {
        $alipay_name = input('post.name');
        $alipay_number = input('post.number');
        if (empty($alipay_name) || strlen($alipay_name) < 2 || strlen($alipay_name) > 20) {
            $this->ajaxReturn(['status' => -2, 'msg' => '支付宝真实姓名有误！']);
        }

        if (empty($alipay_number) || strlen($alipay_number) < 8 || strlen($alipay_number) > 30) {
            $this->ajaxReturn(['status' => -2, 'msg' => '支付宝账号不正确！']);
        }

        $res = Db::table('member')->where(['id' => $this->_userId])->update(['alipay' => $alipay_number, 'alipay_name' => $alipay_name]);
        if ($res !== false) {
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '操作失败']);
    }

    // 绑定银行卡
    function bind_card()
    {
        $bank = input('bank', '');
        $name = input('name', '');
        $number = input('number', '');
        $zhihang = input('zhihang', '');

        if (empty($bank)) $this->ajaxReturn(['status' => -2, 'msg' => '银行名不能为空！']);
        if (mb_strlen($bank, 'UTF8') > 25) {
            $this->ajaxReturn(['status' => -2, 'msg' => '请填写正确的银行名！']);
        }
        if (empty($name)) $this->ajaxReturn(['status' => -2, 'msg' => '姓名不能为空！']);
        if (mb_strlen($name, 'UTF8') > 5) {
            $this->ajaxReturn(['status' => -2, 'msg' => '请填写正确的姓名！']);
        }
        if (empty($number)) $this->ajaxReturn(['status' => -2, 'msg' => '卡号不能为空！']);
        if (strlen($number) < 16) {
            $this->ajaxReturn(['status' => -2, 'msg' => '请填写正确的卡号！']);
        }
        if (Db::name('card')->where(['number' => $number])->find()) {
            $this->ajaxReturn(['status' => -2, 'msg' => '卡号已存在！']);
        }
        if (empty($zhihang)) $this->ajaxReturn(['status' => -2, 'msg' => '支行不能为空！']);
        if (mb_strlen($zhihang, 'UTF8') > 50) {
            $this->ajaxReturn(['status' => -2, 'msg' => '请填写正确的开户行支行！']);
        }

        $res = Db::table('card')->insert([
            'user_id' => $this->_userId,
            'bank' => $bank,
            'name' => $name,
            'number' => $number,
            'zhihang' => $zhihang,
            'create_time' => time()
        ]);
        if ($res !== false) {
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '操作失败']);
    }

    //选择提现方式列表
    public function withdraw_way()
    {
        $max_count = Sysset::getSetsAttr()['withdrawal']['card_num'];
        $card_count = M('card')->where(['user_id' => $this->_userId, 'status' => 1])->count();
        $res = [
            'add_card' => $max_count > 0 && $max_count <= $card_count ? 0 : 1,
            'alipay' => $this->_user->alipay ? 0 : 1,
        ];
        $res['list'] = [];
        if ($this->_user->alipay && $this->_user->alipay_name) {
            $res['list'][] = [
                'withdraw_type' => 4,
                'card_id' => 0,
                'name' => '支付宝',
                'number' => substr_cut($this->_user->alipay, 0, 4),
            ];
        }

        $log = M('card')->where(['user_id' => $this->_userId, 'status' => 1])
            ->order('id desc')
            ->select();
        foreach ($log as $v) {
            $res ['list'][] = [
                'withdraw_type' => 3,
                'card_id' => $v['id'],
                'name' => $v['bank'],
                'number' => substr_cut($v['number'], 0, 4),
            ];
        }
        $this->ajaxReturn([
            'status' => 1,
            'msg' => '获取成功',
            'data' => $res
        ]);
    }

    //提现明细
    public function withdraw_list()
    {
        $where = ['user_id' => $this->_userId];
        $count = M('member_withdrawal')->where($where)->count();
        $Page = new AjaxPage($count, 20);
        $log = M('member_withdrawal')->where($where)
            ->order('id desc')
            ->limit($Page->firstRow . ',' . $Page->listRows)
            ->select();
        $res = [];
        foreach ($log as $v) {
            $res [] = [
                'id' => $v['id'],
                'money' => $v['money'],
                'taxfee' => $v['taxfee'],
                'status' => $v['status'],
                'status_text' => MemberWithdrawal::getStatusTextBy($v['status']) ?: '',
                'create_time' => time_format($v['createtime'], 'Y-m-d'),
            ];
        }
        $this->ajaxReturn([
            'status' => 1,
            'msg' => '获取成功',
            'data' => $res
        ]);
    }

    /***
     * 申请提现页面
     * 2微信 3银行卡 4支付宝
     */
    public function withdrawal()
    {
        $this->ajaxReturn([
            'status' => 1,
            'msg' => '获取成功',
            'data' => [
                'money' => $this->_user->balance,
                'rate_percent' => Sysset::getWDRate(),
                'rate_decimals' => Sysset::getWDRate('decimals'),
                'max' => Sysset::getWDMax(), //每次最高提现金额
                'times' => Sysset::getWDTimes(), //倍数
                'day_max' => Sysset::getWDPerDay(), //每个用户每天最高提现金额
                'remaining' => Sysset::getWDPerDay() - MemberWithdrawal::getTodayWDMoney($this->_userId), //用户今日剩余额度
            ]
        ]);
    }

    /***
     * 申请提现提交
     * 2微信 3银行卡 4支付宝
     */
    public function withdraw()
    {
        if (Sysset::getWDShow() == 0) {
            $this->ajaxReturn(['status' => -2, 'msg' => '提现功能暂未开放！']);
        }
        $type = input('type/d', 0);
        if (!in_array($type, [2, 3, 4])) {
            $this->ajaxReturn(['status' => -2, 'msg' => '提现方式选择错误！']);
        }
        if ($type == 4 && (!$this->_user->alipay || !$this->_user->alipay_name)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '请先绑定支付宝账号！']);
        }
        $card_id = input('card_id/d', 0);
        if ($type == 3 && (!$card_id || !($card = Db::name('card')->where(['id' => $card_id, 'user_id' => $this->_userId, 'status' => 1])->find()))) {
            $this->ajaxReturn(['status' => -2, 'msg' => '银行卡信息不存在！']);
        }

        $money = input('money/d', 0);
        // 倍数判断
        if ($money < Sysset::getWDTimes() || !is_int($money / Sysset::getWDTimes())) {
            $this->ajaxReturn(['status' => -2, 'msg' => '提现金额不是' . Sysset::getWDTimes() . '的倍数']);
        }

        // 每次可提现额度判断
        $max = Sysset::getWDMax();
        if ($money > $max) {
            $this->ajaxReturn(['status' => -2, 'msg' => '金额超出每次可提现额度！', 'data' => ['max' => $max]]);
        }

        // 每日可提现额度判断
        $withdrawed_money = MemberWithdrawal::getTodayWDMoney($this->_userId);
        $remaining = Sysset::getWDPerDay() - $withdrawed_money;
        if ($money > $remaining) {
            $this->ajaxReturn(['status' => -2, 'msg' => '金额超出每天最高可提现额度！', 'data' => [
                'day_max' => Sysset::getWDPerDay(),
                'withdrawed' => $withdrawed_money,
                'remaining' => $remaining
            ]]);
        }

        $yu = $this->_user->balance - $money;
        if ($yu < 0) {
            $this->ajaxReturn(['status' => -2, 'msg' => '超过可提现金额！']);
        }

        if ($type == 2) {//微信
            $number = $this->_user->openid;
        } elseif ($type == 3) {
            $number = $card_id;
        } elseif ($type == 4) {
            $number = $this->_user->alipay;
        }
        //提现申请
        $rate = Sysset::getWDRate('decimals');
        $taxfee = bcmul($money, $rate, 2);//向下取整
        $account = bcsub($money, $taxfee, 2);
        $withdraw_id = Db::name('member_withdrawal')->insertGetId([
            'user_id' => $this->_userId,
            'type' => $type,
            'openid' => $number,
            'rate' => $rate,
            'taxfee' => $taxfee,
            'money' => $money,
            'account' => $account,
            'status' => 0,
            'createtime' => time(),
        ]);
        if (!$withdraw_id) {
            $this->ajaxReturn(['status' => -2, 'msg' => '申请失败,请稍后再试！']);
        }

        $balance = $this->_user->balance;
        $res = $this->_user->save(['balance' => $yu]);
        if (!$res) {
            $this->ajaxReturn(['status' => -2, 'msg' => '申请失败,请稍后再试！']);
        }

        $res = Db::name('menber_balance_log')->insert([
            'user_id' => $this->_userId,
            'balance_type' => 0,
            'log_type' => 0,
            'source_type' => 3,
            'source_id' => $withdraw_id,
            'money' => $money,
            'old_balance' => $balance,
            'balance' => $yu,
            'create_time' => time(),
            'note' => '申请提现'
        ]);
        if (!$res) {
            $this->ajaxReturn(['status' => -2, 'msg' => '申请失败,请稍后再试！']);
        }

        $this->ajaxReturn(['status' => 1, 'msg' => '申请成功,正在审核中！']);
    }

    /**账单明细*/
    public function balance_list()
    {
        $type = I('type/d', 0);    //获取类型
        if ($type == 1) {
            //赚取
            $count = Db::name('menber_balance_log')->where(['user_id' => $this->_userId, 'balance_type' => 0, 'source_type' => [['=', 4], ['=', 5], ['=', 7], 'or']])->count();
            $Page = new AjaxPage($count, 20);
            $account_log = Db::name('menber_balance_log')->where(['user_id' => $this->_userId, 'balance_type' => 0, 'source_type' => [['=', 4], ['=', 5], ['=', 7], 'or']])->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else {
            //消费
            $count = Db::name('menber_balance_log')->where(['user_id' => $this->_userId, 'balance_type' => 0, 'source_type' => [['=', 1], ['=', 3], 'or']])->count();
            $Page = new AjaxPage($count, 20);
            $account_log = Db::name('menber_balance_log')->where(['user_id' => $this->_userId, 'balance_type' => 0, 'source_type' => [['=', 1], ['=', 3], 'or']])->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        }

        $res = [];
        foreach ($account_log as $v) {
            $value = [
                'id' => $v['id'],
                'date' => time_format($v['create_time'], 'Y-m-d'),
                'money' => bcsub($v['balance'], $v['old_balance'], 2),
                'note' => $v['note']
            ];
            if ($type == 0) {
                $value['no'] = $v['source_type'] == 1 ? $v['source_id'] : '';
            } elseif ($type == 1) {
                $value['nickname'] = Db::name('member')->where(['id' => $v['source_id']])->value('nickname') ?: '';
            }
            $res[] = $value;
        }
        $this->ajaxReturn([
            'status' => 1,
            'msg' => '获取成功',
            'data' => $res
        ]);
    }

    // 积分释放主表
    public function point_release()
    {
        $count = Db::name('point_release')->where(['user_id' => $this->_userId])->count();
        $page_count = 20;
        $page = new AjaxPage($count, $page_count);
        $log = Db::name('point_release')->where(['user_id' => $this->_userId])
            ->order('id DESC')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();
        $data = [];
        foreach ($log as $v) {
            $data[] = [
                'id' => $v['id'],
                'time' => time_format($v['create_time']),
                'released' => $v['released'],
                'unreleased' => $v['unreleased'],
                'type' => PointRelease::getTypeName($v['type']),
                'order_sn' => Db::name('order')->where(['order_id' => $v['order_id']])->value('order_sn'),
            ];
        }
        $this->ajaxReturn([
            'status' => 1,
            'msg' => '获取成功',
            'data' => $data
        ]);

    }

    // 积分释放日志
    public function point_release_log()
    {
        $id = input('id/d');
        $data = [];
        if (!$release = PointRelease::get($id)) {
            $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => $data]);
        }

        $count = Db::name('point_log')->where(['user_id' => $this->_userId, 'type' => 6, 'operate_id' => $id])->count();
        $page_count = 20;
        $page = new AjaxPage($count, $page_count);
        $log = Db::name('point_log')->where(['user_id' => $this->_userId, 'type' => 6, 'operate_id' => $id])
            ->order('id DESC')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();

        foreach ($log as $v) {
            $object = json_decode($v['data']);
            $data[] = [
                'id' => $v['id'],
                'time' => time_format($v['create_time']),
                'released' => $v['point'],
                'unreleased' => $object ? $object->unreleased : 0,
            ];
        }
        $this->ajaxReturn([
            'status' => 1,
            'msg' => '获取成功',
            'data' => $data
        ]);

    }

    // 转账.收账记录 ->时间（time）、名称（用户名，id）、积分(+/-)、备注
    public function transfer_list()
    {
        $count = Db::name('point_transfer')
            ->where(function ($query) {
                $query->where('user_id', $this->_userId)->whereor('to_user_id', $this->_userId);
            })->where(['status' => 1])->count();
        $page_count = 20;
        $page = new AjaxPage($count, $page_count);
        $log = Db::name('point_transfer')
            ->where(function ($query) {
                $query->where('user_id', $this->_userId)->whereor('to_user_id', $this->_userId);
            })->where(['status' => 1])
            ->order('id DESC')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();
        $data = [];
        foreach ($log as $v) {
            if ($v['user_id'] == $this->_userId) {// 转账
                if ($member = Member::get($v['to_user_id'])) {
                    $data[] = [
                        'id' => $v['id'],
                        'user_id' => $v['to_user_id'],
                        'nickname' => $member->nickname,
                        'time' => time_format($v['create_time']),
                        'point' => '-' . $v['point'],
                        'remark' => $v['remark'] ?: ''
                    ];
                }
            } else {// 收账
                if ($member = Member::get($v['user_id'])) {
                    $data[] = [
                        'id' => $v['id'],
                        'user_id' => $v['user_id'],
                        'nickname' => $member->nickname,
                        'time' => time_format($v['create_time']),
                        'point' => $v['point'],
                        'remark' => $v['remark'] ?: ''
                    ];
                }
            }
        }
        $this->ajaxReturn([
            'status' => 1,
            'msg' => '获取成功',
            'data' => $data
        ]);
    }

    // 积分记录  消费、赚取->订单,日期date,积分+-
    public function point_log()
    {
        $type = I('type', 0);    //获取类型

        if ($type == 1) {//赚取
            $where = ['user_id' => $this->_userId, 'type' => [['=', 1], ['=', 3], ['=', 7], ['=', 15], 'or']];
        } elseif ($type == 0) {//消费
            $where = ['user_id' => $this->_userId, 'type' => 2];
        }

        $count = Db::name('point_log')->where($where)->count();
        $page_count = 20;
        $page = new AjaxPage($count, $page_count);
        $log = Db::name('point_log')->where($where)
            ->order('id DESC')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();
        $data = [];
        foreach ($log as $v) {
            $value = [
                'id' => $v['id'],
                'date' => time_format($v['create_time'], 'Y-m-d'),
                'point' => ($v['calculate'] == 1 ? '' : '-') . $v['point'],
                'note' => PointLog::getTypeName($v['type'])
            ];
            if ($v['type'] == 2) {
                $value['no'] = $v['operate_id'];
            } elseif ($v['type'] == 3) {
                $value['nickname'] = Db::name('member')->where(['id' => $v['operate_id']])->value('nickname') ?: '';
            }
            $data[] = $value;
        }
        $this->ajaxReturn([
            'status' => 1,
            'msg' => '获取成功',
            'data' => $data
        ]);
    }

    // 积分账户列表给选择
    public function point_user()
    {
        $mobile = input('mobile', '');
        if (!$mobile || !checkMobile($mobile)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机号错误']);
        }
        if ($mobile == $this->_user->mobile) {
            $this->ajaxReturn(['status' => -2, 'msg' => '不能转账给自己']);
        }
        if (!($user = Db::name('member')->where(['mobile' => $mobile])->find())) {
            $this->ajaxReturn(['status' => -2, 'msg' => '找不到用户']);
        }
        $this->ajaxReturn([
            'status' => 1,
            'msg' => '获取成功',
            'data' => [
                'id' => $user['id'],
                'nickname' => $user['nickname'],
                'avatar' => $user['avatar'],
            ]
        ]);
    }

    // 验证积分转账数据
    public function transfer_check()
    {
        $to_user = input('to_user/d');
        $point = input('point');
        $remark = input('remark');
        if ($remark && strlen($remark) > 100) $this->ajaxReturn(['status' => -2, 'msg' => '备注过长！']);
        if ($to_user == $this->_userId) {
            $this->ajaxReturn(['status' => -2, 'msg' => '不能转账给自己']);
        }
        if (!Db::name('member')->where(['id' => $to_user])->value('mobile')) {
            $this->ajaxReturn(['status' => -2, 'msg' => '找不到用户']);
        }
        $point = bcadd($point, '0.00', 2);
        if ($point < 0.01 || $point > 1000000) {
            $this->ajaxReturn(['status' => -2, 'msg' => '积分不正确！']);
        }
        $balance = $this->_user->ky_point;
        $yu = bcsub($balance, $point, 2);
        if ($yu < 0) $this->ajaxReturn(['status' => -2, 'msg' => '超过用户可用积分！']);
        return $yu;
    }

    // 积分转账操作
    public function point()
    {
        $this->transfer_check();
        $this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
    }

    // 积分转账输入密码后续
    public function point_pay()
    {
        $pwd = input('pwd/d');
        if (strlen($pwd) != 6) {
            $this->ajaxReturn(['status' => -2, 'msg' => '密码长度错误！', 'data' => '']);
        }
        $to_user = input('to_user/d');
        $point = input('point');
        $remark = input('remark');
        $yu = $this->transfer_check();

        $password = md5($this->_user['salt'] . $pwd);
        if ($this->_user['pwd'] !== $password) {
            $this->ajaxReturn(['status' => -2, 'msg' => '支付密码错误！', 'data' => '']);
        }
        Db::startTrans();
        $r = Db::name('point_transfer')->insertGetId([
            'user_id' => $this->_userId,
            'to_user_id' => $to_user,
            'point' => $point,
            'remark' => $remark,
            'status' => 1,
            'create_time' => time()
        ]);
        if (!$r) {
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '操作失败']);
        }
        //转账者修改积分
        $res = $this->_user->save(['ky_point' => $yu]);
        if (!$res) {
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '操作失败！']);
        }

        //收账者修改积分
        $toUser = Member::get($to_user);
        $to_user_p = $toUser['ky_point'];
        $to_user_point = bcadd($to_user_p, $point, 2);
        $res = $toUser->save(['ky_point' => $to_user_point]);
        if (!$res) {
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '操作失败！']);
        }

        //转账者积分记录
        $res = Db::name('point_log')->insert([
            'type' => 4,
            'user_id' => $this->_userId,
            'point' => $point,
            'calculate' => 0,
            'operate_id' => $r,
            'before' => $point,
            'after' => $yu,
            'create_time' => time()
        ]);
        if (!$res) {
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '操作失败！']);
        }

        //收账者积分记录
        $res = Db::name('point_log')->insert([
            'type' => 5,
            'user_id' => $to_user,
            'point' => $point,
            'calculate' => 1,
            'operate_id' => $r,
            'before' => $to_user_p,
            'after' => $to_user_point,
            'create_time' => time()
        ]);
        if (!$res) {
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '操作失败！']);
        }
        Db::commit();
        $this->ajaxReturn(['status' => 1, 'msg' => '操作成功！']);
    }

    // 收藏
    function collection()
    {
        $count = M('collection')->alias('c')
            ->join('goods g', 'g.goods_id = c.goods_id', 'INNER')
            ->where("c.user_id = $this->_userId")
            ->where('g.is_show=1')
            ->count();
        $page_count = 20;
        $page = new AjaxPage($count, $page_count);
        $list = Db::name('collection')->alias('c')->field('c.id,g.goods_id,g.goods_name,g.price,g.price')
            ->join('goods g', 'g.goods_id = c.goods_id', 'INNER')
            ->where("c.user_id = $this->_userId")
            ->where('g.is_show=1')
            ->order('c.id DESC')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();
        if (!empty($list)) {
            foreach ($list as &$v) {
                $picture = Db::table('goods_img')->where(['goods_id' => $v['goods_id'], 'main' => 1])->value('picture');
                $v['picture'] = $picture ? SITE_URL . Config('c_pub.img') . $picture : '';
            }
        }
        $this->ajaxReturn([
            'status' => 1,
            'msg' => '获取成功',
            'data' => [
                'list' => $list
            ]
        ]);
    }

    // 删除收藏
    function del_collect()
    {
        $ids = I('ids');
        is_array($ids) && $ids = implode(',', $ids);
        $ids = rtrim($ids, ',');
        if (empty($ids)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '收藏不存在']);
        }

        $r = M('collection')->where("id in ($ids)")->delete();
        if (!$r) {
            $this->ajaxReturn(['status' => -2, 'msg' => '删除失败']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '删除成功']);
    }

    /**
     * +---------------------------------
     * 地址管理列表
     * +---------------------------------
     */
    public function address_list()
    {
        $data = Db::name('user_address')->where('user_id', $this->_userId)->order('is_default desc')->select();
        $region_list = Db::name('region')->field('*')->column('area_id,area_name');
        $res = [];
        foreach ($data as $v) {
            $res[] = [
                'id' => $v['address_id'],
                'consignee' => $v['consignee'],
                'mobile' => $v['mobile'],
                'is_default' => $v['is_default'],
                'province' => $region_list[$v['province']],
                'city' => $region_list[$v['city']],
                'district' => $region_list[$v['district']],
                'town' => $v['twon'] == 0 ? '' : $region_list[$v['twon']],
                'address' => $v['address'],
                'code' => Db::name('region')->where(['area_id' => $v['district']])->value('code') ?: '',
            ];
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => $res]);
    }

    /**
     * +---------------------------------
     * 地址编辑
     * +---------------------------------
     */
    public function edit_address()
    {
        $id = input('id');
        if ($id > 0) {
            $address = Db::name('user_address')->where(array('address_id' => $id, 'user_id' => $this->_userId))->find();
            if (!$address) {
                $this->ajaxReturn(['status' => -2, 'msg' => '地址id不存在！']);
            }
        } else {
            $count = Db::name('user_address')->where('user_id', $this->_userId)->count();
            if ($count > 19) {
                $this->ajaxReturn(['status' => -2, 'msg' => '地址最多可设置20个']);
            }
        }
        $data['consignee'] = input('consignee');
        $data['longitude'] = input('lng');
        $data['latitude'] = input('lat');
        $data['mobile'] = input('mobile');
        $data['is_default'] = input('is_default') ?: 0;

        if ($data['latitude'] && $data['longitude']) {
            $url = "http://api.map.baidu.com/geocoder/v2/?ak=gOuAqF169G6cDdxGnMmB7kBgYGLj3G1j&callback=renderReverse&location={$data['latitude']},{$data['longitude']}&output=json";
            $res = request_curl($url);
            if ($res) {
                $res = explode('Reverse(', $res)[1];
                $res = substr($res, 0, strlen($res) - 1);
                $res = json_decode($res, true)['result']['addressComponent'];

                $data['province'] = Db::table('region')->where('area_name', $res['province'])->value('area_id');
                $data['city'] = Db::table('region')->where('area_name', $res['city'])->value('area_id');
                $data['district'] = Db::table('region')->where('area_name', $res['district'])->value('area_id');
            }
        }
        $data['address'] = input('address');

        $addressM = new UserAddr;
        $return = $addressM->add_address($this->_userId, $id > 0 ? $id : 0, $data);
        $this->ajaxReturn($return);
    }


    // 删除地址
    public function del_address()
    {
        $id = input('id/d', 0);
        $address = Db::name('user_address')->where(['address_id' => $id, 'user_id' => $this->_userId])->find();
        if (!$address) {
            $this->ajaxReturn(['status' => -2, 'msg' => '地址id不存在！']);
        }
        $row = Db::name('user_address')->where(array('user_id' => $this->_userId, 'address_id' => $id))->delete();
        if ($row !== false)
            $this->ajaxReturn(['status' => 1, 'msg' => '删除地址成功']);
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if ($address['is_default'] == 1) {
            $address2 = Db::name('user_address')->where(['user_id' => $this->_userId])->find();
            $address2 && Db::name('user_address')->where(['address_id' => $address2['address_id']])->update(array('is_default' => 1));
        }
        $this->ajaxReturn(['status' => -2, 'msg' => '删除失败']);
    }


}