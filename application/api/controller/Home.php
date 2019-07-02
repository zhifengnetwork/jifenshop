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
use app\common\model\Users;
use app\common\model\Withdraw;
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
            'level' => $this->_member->getLevelName(),
            'waitPay' => OrderLogic::getCount($this->_mId, 'dfk'), //待付款数量
            'waitSend' => OrderLogic::getCount($this->_mId, 'dfh'),//待发货数量
            'waitReceive' => OrderLogic::getCount($this->_mId, 'dsh'), //待收货数量
            'waitComment' => OrderLogic::getCount($this->_mId, 'dpj'), //待评论数
            'return' => OrderLogic::getCount($this->_mId, 'tk'),
            'money' => MemberModel::getBalance($this->_mId, 0),//余额
            'point' => MemberModel::getBalance($this->_mId, 1),//积分
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

    /**账单明细*/
    public function balance_list()
    {
        $type = I('type', '');    //获取类型
        $condition = Db::name('menber_balance_log')->where(['user_id' => $this->user_id, 'balance_type' => 0]);
        if ($type == 1) {
            //赚取
            $count = $condition->where(['log_type' => 1])->count();
            $Page = new Page($count, 16);
            $account_log = $condition->where(['log_type' => 1])->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else if ($type == 0) {
            //消费
            $count = $condition->where(['log_type' => 0])->count();
            $Page = new Page($count, 16);
            $account_log = $condition->where(['log_type' => 0])->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else {
            //全部
            $count = $condition->count();
            $Page = new Page($count, 16);
            $account_log = $condition->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        }
        $res = [];
        foreach ($account_log as $v) {
            $res [] = [
                'id' => $v['id'],
                'no' => $v['source_id'],
                'date' => time_format($v['create_time'], 'Y-m-d'),
                'money' => $v['old_balance'] - $v['balance'],
                'note' => $v['note']
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
        $status = input('status');
        $where = ['user_id' => $this->_mId];
        if ($status) {
            $where['status'] = $status;
        }
        $count = M('withdraw')->where($where)->count();
        $Page = new Page($count, 15);
        $log = M('withdraw')->where($where)
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
                'status_text' => Withdraw::getStatusTextBy($v['status']) ?: '',
                'create_time' => time_format($v['create_time'], 'Y-m-d'),
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
     * 2微信 3支付宝
     */
    public function withdraw()
    {
        $withdraw_type = input('type', 2);
        $amount = input('amount', 0);
        $amount = bcadd($amount, '0.00', 2);
        if ($amount < 0.01 || $amount > 1000000) {
            $this->ajaxReturn(['status' => -2, 'msg' => '提现金额不正确！']);
        }
        $balance = Db::name('member_balance')->where(['user_id' => $this->_mId, 'is_tixian' => 1])->field('sum(balance) as balance')->find();
        $yu = bcsub($balance['balance'], $amount, 2);
        if ($yu < 0) {
            $this->ajaxReturn(['status' => -2, 'msg' => '超过可提现金额！']);
        }

        if ($withdraw_type == 2) {//微信
            $account_name = '微信';
            $account_number = $this->_member['openid'];
        } elseif ($withdraw_type == 3) {
            $account_name = '支付宝';
            $account_number = $this->_member['alipay'];
        }
        //提现申请
        $insert = [
            'user_id' => $this->_mId,
            'money' => $amount,
            'withdraw_type' => $withdraw_type,
            'account_name' => $account_name,
            'account_number' => $account_number,
            'taxfee' => $amount * 0.006,//提现费率做成配置
            'status' => 0,
            'create_time' => time(),
        ];
        $res = Db::name('withdraw')->insert($insert);

        if ($res !== false) {
            $this->ajaxReturn(['status' => 1, 'msg' => '申请成功,正在审核中！']);
        }

        $this->ajaxReturn(['status' => -2, 'msg' => '申请失败,请稍后再试！']);
    }


    // 积分明细  ->释放时间  已释放  待释放
    public function points_list()
    {
        $count = Db::name('menber_balance_log')->where(['user_id' => $this->user_id, 'balance_type' => 1])->count();
        $Page = new Page($count, 16);
        $account_log = Db::name('menber_balance_log')->where(['user_id' => $this->user_id, 'balance_type' => 1])->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

    }

    // 转账记录 ->时间（time）、名称（用户名，id）、积分、备注
    public function transfer_list()
    {
        $count = Db::name('menber_balance_log')->where(['user_id' => $this->user_id, 'balance_type' => 1])->count();
        $Page = new Page($count, 16);
        $account_log = Db::name('menber_balance_log')->where(['user_id' => $this->user_id, 'balance_type' => 1])->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

    }

    // 积分记录  消费、赚取->订单,日期date,积分+-
    public function point_log()
    {
        $count = Db::name('menber_balance_log')->where(['user_id' => $this->user_id, 'balance_type' => 0])->count();
        $Page = new Page($count, 16);
        $account_log = Db::name('menber_balance_log')->where(['user_id' => $this->user_id, 'balance_type' => 1])->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

    }

    // 积分账户列表给选择
    public function point_user()
    {

    }

    // 积分转账操作
    public function point()
    {
        input('to_user');
        input('point');
        input('note');
    }

    // 用户信息
    function get_user_info()
    {
        $this->ajaxReturn([
            'status' => 1,
            'msg' => '获取成功',
            'data' => [
                'money' => $this->_member->getYue(),
                'point' => $this->_member->getPoint(),
                'ds_money' => $this->_member->getYue(),//代收
                'ds_point' => $this->_member->getPoint(),
                'alipay' => $this->_member->alipay ?: ''
            ]
        ]);
    }

    // 绑定支付宝
    function bind_alipay()
    {
        $alipay_name = input('alipay_name', '');
        $alipay_number = input('alipay_number', '');
        if (empty($alipay_name) || strlen($alipay_name) > 20) {
            $this->ajaxReturn(['status' => -2, 'msg' => '支付宝真实姓名有误！']);
        }

        if (empty($alipay_number) || strlen($alipay_number) > 30) {
            $this->ajaxReturn(['status' => -2, 'msg' => '支付宝账号不正确！']);
        }

        $res = Db::table('member')->where(['id' => $this->_mId])->update(['alipay' => $alipay_number, 'alipay_name' => $alipay_name]);
        if ($res !== false) {
            $this->ajaxReturn(['status' => 1, 'msg' => '修改成功']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '修改失败']);
    }

    // 绑定支付宝
    function bind_card()
    {
        //银行名 姓名  卡号 开户行支行
        $alipay_name = input('alipay_name', '');
        $alipay_number = input('alipay_number', '');
        if (empty($alipay_name) || strlen($alipay_name) > 20) {
            $this->ajaxReturn(['status' => -2, 'msg' => '支付宝真实姓名有误！']);
        }

        if (empty($alipay_number) || strlen($alipay_number) > 30) {
            $this->ajaxReturn(['status' => -2, 'msg' => '支付宝账号不正确！']);
        }

        $res = Db::table('member')->where(['id' => $this->_mId])->update(['alipay' => $alipay_number, 'alipay_name' => $alipay_name]);
        if ($res !== false) {
            $this->ajaxReturn(['status' => 1, 'msg' => '修改成功']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '修改失败']);
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
                $v['picture'] = $picture ? SITE_URL . '/public' . $picture : '';
            }
        }
        $this->ajaxReturn([
            'status' => 1,
            'msg' => '获取成功',
            'data' => [
                'list' => $list,
                'count' => $count,
                'p' => I('p') ?: 1,
                'next' => $count > $page_count * I('p') && count($list) == $page_count ? 1 : 0//是否有下一页
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
        $post_data['district'] = input('district');
        $post_data['consignee'] = input('consignee');
        $post_data['mobile'] = input('mobile');
        $post_data['is_default'] = input('is_default') ?: 0;
        $post_data['address'] = input('address');

        $addressM = new UserAddr;
        $return = $addressM->add_address($this->_mId, $id > 0 ? $id : 0, $post_data);
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