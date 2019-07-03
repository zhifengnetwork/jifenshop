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
        $Page = new Page($count, 15);
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
        $type = input('type\d', 0);
        if (!in_array($type, [2, 3, 4])) {
            $this->ajaxReturn(['status' => -2, 'msg' => 'type！']);
        }
        if ($type == 4 && (!$this->_member->alipay || !$this->_member->alipay_name)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '请先绑定支付宝账号！']);
        }
        $card_id = input('card_id\d', 0);
        if ($type == 3 && (!$card_id || ($card = Db::name('card')->where(['id' => $card_id, 'user_id' => $this->_mId, 'status' => 1])->find()))) {
            $this->ajaxReturn(['status' => -2, 'msg' => '银行卡信息不存在！']);
        }

        $money = input('money', 0);
        $money = bcadd($money, '0.00', 2);
        if ($money < 0.01 || $money > 1000000) {
            $this->ajaxReturn(['status' => -2, 'msg' => '提现金额不正确！']);
        }

        $balance = Db::name('member_balance')->where(['user_id' => $this->_mId, 'is_tixian' => 1])->field('sum(balance) as balance')->find();
        $member_balance = $balance['balance'] ?: 0;
        $yu = bcsub($member_balance, $money, 2);
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
        $insert = [
            'user_id' => $this->_mId,
            'money' => $money,
            'type' => $type,
            'openid' => $number,
            'rate' => 0.06,//提现费率做成配置
            'account' => $money - $money * 0.06,
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
            $Page = new Page($count, 16);
            $account_log = Db::name('menber_balance_log')->where(['user_id' => $this->_mId, 'balance_type' => 0])->where(['log_type' => 1])->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else if ($type == 0) {
            //消费
            $count = Db::name('menber_balance_log')->where(['user_id' => $this->_mId, 'balance_type' => 0])->where(['log_type' => 0])->count();
            $Page = new Page($count, 16);
            $account_log = Db::name('menber_balance_log')->where(['user_id' => $this->_mId, 'balance_type' => 0])->where(['log_type' => 0])->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else {
            //全部
            $count = Db::name('menber_balance_log')->where(['user_id' => $this->_mId, 'balance_type' => 0])->count();
            $Page = new Page($count, 16);
            $account_log = Db::name('menber_balance_log')->where(['user_id' => $this->_mId, 'balance_type' => 0])->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
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

    // 积分明细  ->释放时间  已释放  待释放
    public function points_list()
    {
        $count = Db::name('menber_balance_log')->where(['user_id' => $this->_mId, 'balance_type' => 1])->count();
        $Page = new Page($count, 16);
        $account_log = Db::name('menber_balance_log')->where(['user_id' => $this->_mId, 'balance_type' => 1])->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();

        $this->ajaxReturn([
            'status' => 1,
            'msg' => '获取成功',
            'data' => [[
                'id' => 1,
                'yi' => 12,
                'dai' => 12,
                'time' => '2019-12-01 12:12:12'
            ],
                [
                    'id' => 2,
                    'yi' => 121,
                    'dai' => 121,
                    'time' => '2019-12-02 12:12:12'
                ]]
        ]);

    }

    // 转账记录 ->时间（time）、名称（用户名，id）、积分、备注
    public function transfer_list()
    {
        $count = Db::name('menber_balance_log')->where(['user_id' => $this->_mId, 'balance_type' => 1])->count();
        $Page = new Page($count, 16);
        $account_log = Db::name('menber_balance_log')->where(['user_id' => $this->_mId, 'balance_type' => 1])->order('id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $this->ajaxReturn([
            'status' => 1,
            'msg' => '获取成功',
            'data' => [[
                'id' => 1,
                'nickname' => '风火',
                'user_id' => 1211,
                'point' => '50',
                'note' => '积分积分积分'
            ],
                [
                    'id' => 2,
                    'nickname' => '火风',
                    'user_id' => 1212,
                    'point' => '100',
                    'note' => '积分积分积分'
                ]]
        ]);
    }

    // 积分记录  消费、赚取->订单,日期date,积分+-
    public function point_log()
    {
        $type = I('type', 0);    //获取类型
        if ($type == 1) {//赚取
            $data = [
                [
                    'id' => 1,
                    'no' => '123425436547',
                    'date' => '2019-12-01',
                    'point' => '1212',
                    'note'=>'分享赚取'
                ],
                [
                    'id' => 2,
                    'no' => '213425436547',
                    'date' => '2019-12-02',
                    'point' => '2121',
                    'note'=>'分享赚取'
                ]
            ];
        }elseif($type == 0){//消费
            $data = [
                [
                    'id' => 3,
                    'no' => '456768798076',
                    'date' => '2019-12-01',
                    'point' => '-1212',
                    'note'=>'下单消费'
                ],
                [
                    'id' => 4,
                    'no' => '34635456879',
                    'date' => '2019-12-02',
                    'point' => '-2121',
                    'note'=>'下单消费'
                ]
            ];
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

        $this->ajaxReturn([
            'status' => 1,
            'msg' => '获取成功',
            'data' => [[
                'user_id' => 12,
                'nickname' => '小美人',
                'avatar' => $this->_member->avatar,
            ],
                [
                    'user_id' => 13,
                    'nickname' => '小美女',
                    'avatar' => $this->_member->avatar,
                ]]
        ]);
    }

    // 积分转账操作
    public function point()
    {
        input('to_user');
        input('point');
        input('note');
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
        //latitude=23.2412150000&longitude=113.2931790000
        $data['consignee'] = input('consignee');
        $data['longitude'] = input('lng');
        $data['latitude'] = input('lat');
        $data['mobile'] = input('mobile');
        $data['is_default'] = input('is_default') ?: 0;

        if($data['latitude'] && $data['longitude']){
            $url = "http://api.map.baidu.com/geocoder/v2/?ak=gOuAqF169G6cDdxGnMmB7kBgYGLj3G1j&callback=renderReverse&location={$data['latitude']},{$data['longitude']}&output=json";
            $res = request_curl($url);
            if($res){
                $res = explode('Reverse(',$res)[1];
                $res = substr($res,0,strlen($res)-1);
                $res = json_decode($res,true)['result']['addressComponent'];

                $data['province'] = Db::table('region')->where('area_name',$res['province'])->value('area_id');
                $data['city'] = Db::table('region')->where('area_name',$res['city'])->value('area_id');
                $data['district'] = Db::table('region')->where('area_name',$res['district'])->value('area_id');
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