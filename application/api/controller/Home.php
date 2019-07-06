<?php
/**
 * 我的API
 */

namespace app\api\controller;

use app\api\model\UserAddr;
use app\common\logic\OrderLogic;
use app\common\model\Collection as CollectionM;
use app\common\model\Member;
use app\common\model\Member as MemberModel;
use app\common\model\MemberWithdrawal;
use app\common\model\PointLog;
use app\common\model\PointRelease;
use app\common\model\PointTransfer;
use app\common\model\Users;
use think\AjaxPage;
use think\Db;
use think\Page;

class Home extends ApiBase
{
    private $_mId;

    /**
     * @var Member
     */
    private $_member;

    public function __construct()
    {
        $this->_mId = $this->get_user_id();
        if (!$this->_mId || !($this->_member = Member::get($this->_mId))) {
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在']);
        };
    }

    // 总览
    public function index()
    {
        $data = [
            'id' => $this->_mId,
            'mobile' => $this->_member->mobile,
            'nickname' => $this->_member->nickname,
            'avatar' => $this->_member->avatar,
            'level' => $this->_member->is_vip == 1 ? 'VIP会员' : '普通会员',
            'waitPay' => OrderLogic::getCount($this->_mId, 'dfk'), //待付款数量
            'waitSend' => OrderLogic::getCount($this->_mId, 'dfh'),//待发货数量
            'waitReceive' => OrderLogic::getCount($this->_mId, 'dsh'), //待收货数量
            'waitComment' => OrderLogic::getCount($this->_mId, 'dpj'), //待评论数
            'return' => OrderLogic::getCount($this->_mId, 'tk'),
            'money' => $this->_member->balance,//余额
            'point' => $this->_member->ky_point,//积分
            'collect' => CollectionM::getCountBy($this->_mId),
            'team_underling' => 0,
            'team_point' => 0,
            'team_today' => 0
        ];

        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => $data]);
    }

    // 发送短信
    public function send_sms()
    {
        if ($this->_member->mobile) {
            $this->ajaxReturn(['status' => -2, 'msg' => '已绑定手机号！']);
        }
        $mobile = input('mobile', '');

        if (!checkMobile($mobile)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机格式错误！']);
        }
        if (Users::get(['mobile' => $mobile])) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机号不可用！']);
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
        if ($this->_member->mobile) {
            $this->ajaxReturn(['status' => -2, 'msg' => '已绑定手机号！']);
        }
        $mobile = input('mobile', '');
        $code = input('code');

        if (!checkMobile($mobile)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机格式错误！']);
        }
        if (Users::get(['mobile' => $mobile])) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机号不可用！']);
        }

        $res = action('PhoneAuth/phoneAuth', [$mobile, $code]);
        if ($res === '-1') {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码已过期！']);
        } else if (!$res) {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码错误！']);
        }

        $res1 = $this->_member->save(['mobile' => $mobile]);

        if ($res1 === false) {
            $this->ajaxReturn(['status' => -2, 'msg' => '绑定失败！']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '绑定成功！', 'data' => ['mobile' => $mobile]]);
    }

    // 用户信息
    function get_user_info()
    {
        $sets = Db::table('sysset')->where(['id' => 1])->value('sets');
        $sets = unserialize($sets);
        $this->ajaxReturn([
            'status' => 1,
            'msg' => '获取成功',
            'data' => [
                'money' => $this->_member->balance,
                'point' => $this->_member->ky_point,
                'ds_point' => bcadd($this->_member->dsf_point, $this->_member->dsf_point, 2),
                'alipay' => $this->_member->alipay ?: '',
                'withdraw_rate' => isset($sets['withdrawal']['rate']) ? $sets['withdrawal']['rate'] : 0,
                'withdraw_max' => isset($sets['withdrawal']['max']) ? $sets['withdrawal']['max'] : 0
            ]
        ]);
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

        $res = Db::table('member')->where(['id' => $this->_mId])->update(['alipay' => $alipay_number, 'alipay_name' => $alipay_name]);
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
        if (empty($bank) || strlen($bank) > 20) {
            $this->ajaxReturn(['status' => -2, 'msg' => '银行名！']);
        }
        if (empty($name) || strlen($name) > 30) {
            $this->ajaxReturn(['status' => -2, 'msg' => '姓名！']);
        }
        if (empty($number) || strlen($number) < 16) {
            $this->ajaxReturn(['status' => -2, 'msg' => '卡号！']);
        }
        if (Db::name('card')->where(['number' => $number])->find()) {
            $this->ajaxReturn(['status' => -2, 'msg' => '卡号已存在！']);
        }
        if (empty($zhihang) || strlen($zhihang) > 30) {
            $this->ajaxReturn(['status' => -2, 'msg' => '开户行支行！']);
        }

        $res = Db::table('card')->insert([
            'user_id' => $this->_mId,
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
        $res = [];
        if ($this->_member->alipay && $this->_member->alipay_name) {
            $res[] = [
                'withdraw_type' => 2,
                'card_id' => 0,
                'name' => '支付宝',
                'number' => substr_cut($this->_member->alipay, 0, 4),
            ];
        }

        $log = M('card')->where(['user_id' => $this->_mId, 'status' => 1])
            ->order('id desc')
            ->select();
        foreach ($log as $v) {
            $res [] = [
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
        $where = ['user_id' => $this->_mId];
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
     * 申请提现
     * 2微信 3银行卡 4支付宝
     */
    public function withdraw()
    {
        $type = input('type/d', 0);
        if (!in_array($type, [2, 3, 4])) {
            $this->ajaxReturn(['status' => -2, 'msg' => 'type！']);
        }
        if ($type == 4 && (!$this->_member->alipay || !$this->_member->alipay_name)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '请先绑定支付宝账号！']);
        }
        $card_id = input('card_id/d', 0);
        if ($type == 3 && (!$card_id || ($card = Db::name('card')->where(['id' => $card_id, 'user_id' => $this->_mId, 'status' => 1])->find()))) {
            $this->ajaxReturn(['status' => -2, 'msg' => '银行卡信息不存在！']);
        }

        $money = input('money', 0);
        $money = bcadd($money, '0.00', 2);
        if ($money < 0.01) {
            $this->ajaxReturn(['status' => -2, 'msg' => '提现金额不正确！']);
        }
        $sets = Db::table('sysset')->where(['id' => 1])->value('sets');
        $sets = unserialize($sets);
        $max = isset($sets['withdrawal']['max']) ? $sets['withdrawal']['max'] : 0;
        if ($max > 0 && $money > $max) {
            $this->ajaxReturn(['status' => -2, 'msg' => '金额超出最高可提现额度！']);
        }

        $yu = bcsub($this->_member->balance, $money, 2);
        if ($yu < 0) {
            $this->ajaxReturn(['status' => -2, 'msg' => '超过可提现金额！']);
        }

        if ($type == 2) {//微信
            $number = $this->_member->openid;
        } elseif ($type == 3) {
            $number = $card_id;
        } elseif ($type == 4) {
            $number = $this->_member->alipay;
        }
        //提现申请
        $rate = isset($sets['withdrawal']['rate']) ? $sets['withdrawal']['rate'] : 0;
        $insert = [
            'user_id' => $this->_mId,
            'type' => $type,
            'openid' => $number,
            'rate' => $rate / 100,//提现费率做成配置
            'taxfee' => $money * ($rate / 100),
            'money' => $money,
            'account' => $money - $money * ($rate / 100),
            'status' => 1,
            'createtime' => time(),
        ];
        $res = Db::name('member_withdrawal')->insert($insert);

        if ($res !== false) {
            $this->ajaxReturn(['status' => 1, 'msg' => '申请成功,正在审核中！']);
        }

        $this->ajaxReturn(['status' => -2, 'msg' => '申请失败,请稍后再试！']);
    }

    /**账单明细*/
    public function balance_list()
    {
        $type = I('type/d', 0);    //获取类型
        if ($type == 1) {
            //赚取
            $count = Db::name('menber_balance_log')->where(['user_id' => $this->_mId, 'balance_type' => 0])->where(['log_type' => 1])->count();
            $Page = new AjaxPage($count, 20);
            $account_log = Db::name('menber_balance_log')->where(['user_id' => $this->_mId, 'balance_type' => 0])->where(['log_type' => 1])->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else {
            //消费
            $count = Db::name('menber_balance_log')->where(['user_id' => $this->_mId, 'balance_type' => 0])->where(['log_type' => 0])->count();
            $Page = new AjaxPage($count, 20);
            $account_log = Db::name('menber_balance_log')->where(['user_id' => $this->_mId, 'balance_type' => 0])->where(['log_type' => 0])->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        }
        $res = [];
        foreach ($account_log as $v) {
            $res [] = [
                'id' => $v['id'],
                'no' => $v['source_id'],
                'date' => time_format($v['create_time'], 'Y-m-d'),
                'money' => $v['balance'] - $v['old_balance'],
                'note' => $v['note']
            ];
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
        $count = Db::name('point_release')->where(['user_id' => $this->_mId])->count();
        $page_count = 20;
        $page = new AjaxPage($count, $page_count);
        $log = Db::name('point_release')->where(['user_id' => $this->_mId])
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

        $count = Db::name('point_log')->where(['user_id' => $this->_mId, 'type' => 6, 'operate_id' => $id])->count();
        $page_count = 20;
        $page = new AjaxPage($count, $page_count);
        $log = Db::name('point_log')->where(['user_id' => $this->_mId, 'type' => 6, 'operate_id' => $id])
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

    // 转账记录 ->时间（time）、名称（用户名，id）、积分、备注
    public function transfer_list()
    {
        $count = Db::name('point_transfer')->where(['user_id' => $this->_mId, 'status' => 1])->count();
        $page_count = 20;
        $page = new AjaxPage($count, $page_count);
        $log = Db::name('point_transfer')->where(['user_id' => $this->_mId, 'status' => 1])
            ->order('id DESC')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();
        $data = [];
        foreach ($log as $v) {
            if ($member = Member::get($v['to_user_id'])) {
                $data[] = [
                    'id' => $v['id'],
                    'user_id' => $v['to_user_id'],
                    'nickname' => $member->nickname,
                    'time' => time_format($v['create_time']),
                    'point' => $v['point'],
                    'remark' => $v['remark']
                ];
            }

        }
        $this->ajaxReturn([
            'status' => 1,
            'msg' => '获取成功',
            'data' => $data
        ]);
    }

    //类型：2下单消费，3分享赚取，4转账，5收账，6释放
    // 积分记录  消费、赚取->订单,日期date,积分+-
    public function point_log()
    {
        $type = I('type', 0);    //获取类型

        if ($type == 1) {//赚取
            $where = ['user_id' => $this->_mId, 'type' => 3];
        } elseif ($type == 0) {//消费
            $where = ['user_id' => $this->_mId, 'type' => 2];
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
        if (!$mobile || !isMobile($mobile)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机号错误']);
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

    // 积分转账操作
    public function point()
    {
        $to_user = input('to_user/d');
        $point = input('point');
        $remark = input('remark');

        if (!Db::name('member')->where(['id' => $to_user])->find()) {
            $this->ajaxReturn(['status' => -2, 'msg' => '找不到用户', 'date' => Db::name('member')->getLastSql()]);
        }
        $point = bcadd($point, '0.00', 2);
        if ($point < 0.01 || $point > 1000000) {
            $this->ajaxReturn(['status' => -2, 'msg' => '积分不正确！']);
        }
        $balance = $this->_member->ky_point;
        $yu = bcsub($balance, $point, 2);
        if ($yu < 0) $this->ajaxReturn(['status' => -2, 'msg' => '超过用户可用积分！']);

        if ($remark && strlen($remark) > 100) $this->ajaxReturn(['status' => -2, 'msg' => '备注过长！']);
        $r = Db::name('point_transfer')->insert([
            'user_id' => $this->_mId,
            'to_user_id' => $to_user,
            'point' => $point,
            'remark' => $remark,
            'status' => 0,
            'create_time' => time()
        ]);
        if (!$r) {
            $this->ajaxReturn(['status' => -2, 'msg' => '操作失败']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '操作成功']);
    }

    // 积分转账，输入密码后续
    public function point_pay()
    {
        $pwd = input('pwd/d');
        if (strlen($pwd) != 6) {
            $this->ajaxReturn(['status' => -2, 'msg' => '密码长度错误！', 'data' => '']);
        }
        $to_user = input('to_user/d');
        $transfer = Db::name('point_transfer')->where(['user_id' => $this->_mId, 'to_user_id' => $to_user, 'status' => 0])->find();
        if (!($toUser = Member::get($to_user)) || !$transfer) {
            $this->ajaxReturn(['status' => -2, 'msg' => '转账记录不存在或已支付！', 'data' => '']);
        }
        // 再次判断积分
        $point = $this->_member->ky_point;
        $yu = bcsub($point, $transfer['point'], 2);
        if ($yu < 0) $this->ajaxReturn(['status' => -2, 'msg' => '超过用户可用积分！']);

        $password = md5($this->_member['salt'] . $pwd);
        if ($this->_member['pwd'] !== $password) {
            $this->ajaxReturn(['status' => -2, 'msg' => '支付密码错误！', 'data' => '']);
        }
        Db::startTrans();
        //转账表修改支付状态
        $res = Db::name('point_transfer')->where(['user_id' => $this->_mId, 'to_user_id' => $to_user, 'status' => 0])->update(['status' => 1]);
        if (!$res) {
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '操作失败！']);
        }
        //转账者修改积分
        $res = $this->_member->save(['ky_point' => $yu]);
        if (!$res) {
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '操作失败！']);
        }

        //收账者修改积分
        $to_user_p = $toUser['ky_point'];
        $to_user_point = bcadd($to_user_p, $transfer['point'], 2);
        $res = $toUser->save(['ky_point' => $to_user_point]);
        if (!$res) {
            Db::rollback();
            $this->ajaxReturn(['status' => -2, 'msg' => '操作失败！']);
        }

        //转账者积分记录
        $res = Db::name('point_log')->insert([
            'type' => 4,
            'user_id' => $this->_mId,
            'point' => $transfer['point'],
            'calculate' => 0,
            'operate_id' => $transfer['id'],
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
            'point' => $transfer['point'],
            'calculate' => 1,
            'operate_id' => $transfer['id'],
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
            ->where("c.user_id = $this->_mId")
            ->count();
        $page_count = 20;
        $page = new AjaxPage($count, $page_count);
        $list = Db::name('collection')->alias('c')->field('c.id,g.goods_id,g.goods_name,g.price,g.price')
            ->join('goods g', 'g.goods_id = c.goods_id', 'INNER')
            ->where("c.user_id = $this->_mId")
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
     * 地址组件原数据
     * +---------------------------------
     */
    public function get_address()
    {
        $list = Db::name('region')->field('*')->select();
        foreach ($list as $v) {
            if ($v['area_type'] == 1) {
                $address_list['province_list'][$v['code'] * 10000] = $v['area_name'];
            }
            if ($v['area_type'] == 2) {
                $address_list['city_list'][$v['code'] * 100] = $v['area_name'];
            }
            if ($v['area_type'] == 3) {
                $address_list['county_list'][$v['code']] = $v['area_name'];
            }
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取地址成功', 'data' => $address_list]);
    }


    /**
     * +---------------------------------
     * 地址管理列表
     * +---------------------------------
     */
    public function address_list()
    {
        $data = Db::name('user_address')->where('user_id', $this->_mId)->order('is_default desc')->select();
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
            $address = Db::name('user_address')->where(array('address_id' => $id, 'user_id' => $this->_mId))->find();
            if (!$address) {
                $this->ajaxReturn(['status' => -2, 'msg' => '地址id不存在！']);
            }
        } else {
            $count = Db::name('user_address')->where('user_id', $this->_mId)->count();
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
        $return = $addressM->add_address($this->_mId, $id > 0 ? $id : 0, $data);
        $this->ajaxReturn($return);
    }


    // 删除地址
    public function del_address()
    {
        $id = input('id/d', 0);
        $address = Db::name('user_address')->where(['address_id' => $id, 'user_id' => $this->_mId])->find();
        if (!$address) {
            $this->ajaxReturn(['status' => -2, 'msg' => '地址id不存在！']);
        }
        $row = Db::name('user_address')->where(array('user_id' => $this->_mId, 'address_id' => $id))->delete();
        if ($row !== false)
            $this->ajaxReturn(['status' => 1, 'msg' => '删除地址成功']);
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if ($address['is_default'] == 1) {
            $address2 = Db::name('user_address')->where(['user_id' => $this->_mId])->find();
            $address2 && Db::name('user_address')->where(['address_id' => $address2['address_id']])->update(array('is_default' => 1));
        }
        $this->ajaxReturn(['status' => -2, 'msg' => '删除失败']);
    }


}