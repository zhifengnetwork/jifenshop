<?php
/**
 * 我的API
 */

namespace app\api\controller;

use app\common\model\Users;
use app\common\logic\UsersLogic;
use app\common\model\UserVideo;
use think\AjaxPage;
use think\Config;
use think\Db;
use Think\Page;

class Home extends ApiBase
{
    private $_userId;

    /**
     * @var Users
     */
    private $_user;

    public function __construct()
    {
        $this->_userId = 36;
        if (!$this->_userId || !($this->_user = Users::get($this->_userId))) {
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在', 'data' => '']);
        };
    }

    // 总览
    public function index()
    {
        $data = Db::name('users')
            ->field('mobile,nickname,head_pic')
            ->where(['user_id' => $this->_userId])
            ->find();
        if (empty($data)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '会员不存在！', 'data' => '']);
        }
        $data['id'] = $this->_userId;

        $data['level'] = '普通用户';
        if ($this->_user->is_agent == 1) {
            $userLevel = M("user_level")->where(['level' => $this->_user->agent_user])->find();
            $data['level'] = $userLevel['level_name'] ?: '分销商';
            //区域代理
            $area_agent = M('user_regional_agency')->where('user_id', $this->_userId)->find();
            if ($area_agent) {
                $agency_name = M('config_regional_agency')->where('agency_level', $area_agent['agency_level'])->value('agency_name');
            }
            if ($agency_name) $data['level'] .= "[ {$agency_name} ]";
        } else {
            if ($this->_user->is_distribut == 1) {
                $data['level'] = '分销商';
            }
        }

        $logic = new UsersLogic();
        $user_info = $logic->get_info($this->_userId);

        $data['waitPay'] = $user_info['result']['waitPay'];
        $data['waitSend'] = $user_info['result']['waitSend'];
        $data['waitReceive'] = $user_info['result']['waitReceive'];
        $data['waitComment'] = $user_info['result']['uncomment_count'];
        $data['return'] = Db::name('return_goods')->where('user_id', $this->_userId)->count();

        $data['money'] = $this->_user->user_money;
        $data['point'] = $this->_user->pay_points;
        $data['collect'] = $logic->get_goods_collect_count($this->_userId);


        $data['team_underling'] = $this->_user->underling_number ?: 0;
        $data['team_point'] = $this->_user->underling_number ?: 0;
        $data['team_today'] = $this->_user->underling_number ?: 0;

        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => $data]);
    }

    //  绑定手机号
    public function binding_mob()
    {
        // 当前用户已有手机号
        if ($this->_user->mobile) {
            $this->ajaxReturn(['status' => -2, 'msg' => '已绑定手机号！', 'data' => '']);
        }
        $mobile = input('mobile', '');

        if (!checkMobile($mobile)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机格式错误！', 'data' => '']);
        }
        if (Users::get(['mobile' => $mobile])) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机号不可用！', 'data' => '']);
        }
        $res1 = $this->_user->update(['mobile' => $mobile]);

        if ($res1 === false) {
            $this->ajaxReturn(['status' => -2, 'msg' => '绑定失败！', 'data' => '']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '绑定成功！', 'data' => ['mobile' => $mobile]]);
    }

    // 手机号换绑
    public function change_mobile()
    {
        $new_mobile = input('mobile');
        $code = input('code');

        if ($this->_user->mobile == $new_mobile) {
            $this->ajaxReturn(['status' => -2, 'msg' => '手机号不能相同！']);
        }

        $res = action('PhoneAuth/phoneAuth', [$new_mobile, $code]);
        if ($res === '-1') {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码已过期！']);
        } else if (!$res) {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码错误！']);
        }

        $res = $this->_user->update(['mobile' => $new_mobile]);
        if ($res !== false) {
            $this->ajaxReturn(['status' => 1, 'msg' => '换绑成功', 'data' => []]);
        }
        $this->ajaxReturn(['status' => -2, 'msg' => '换绑失败']);
    }

    // 重置密码
    public function reset_pwd()
    {
        $password1 = input('password1');
        $password2 = input('password2');
        if ($password1 != $password2) {
            $this->ajaxReturn(['status' => -2, 'msg' => '确认密码错误', 'data' => '']);
        }
        $type = input('type');//1登录密码 2支付密码
        $code = input('code');
        $res = action('PhoneAuth/phoneAuth', [$this->_user->mobile, $code]);
        if ($res === '-1') {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码已过期！', 'data' => '']);
        } else if (!$res) {
            $this->ajaxReturn(['status' => -2, 'msg' => '验证码错误！', 'data' => '']);
        }
        if ($type == 1) {
            $stri = 'password';
        } else {
            $stri = 'pwd';
        }
        $password = md5($this->_user->salt . $password2);
        if ($password == $this->_user->{$stri}) {
            $this->ajaxReturn(['status' => -2, 'msg' => '新密码和旧密码不能相同']);
        } else {
            $update = $this->_user->data(array($stri => $password))->update();
            if (!$update) {
                $this->ajaxReturn(['status' => -2, 'msg' => '修改失败']);
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '修改成功']);
        }

    }

    /**账户明细*/
    public function account_list()
    {
        $type = I('type', 'all');    //获取类型
        $this->assign('type', $type);
        if ($type == 'recharge') {
            //充值明细
            $count = M('recharge')->where("user_id", $this->user_id)->count();
            $Page = new Page($count, 16);
            $account_log = M('recharge')->where("user_id", $this->user_id)->order('order_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else if ($type == 'points') {
            //积分记录明细
            $count = M('account_log')->where(['user_id' => $this->user_id, 'pay_points' => ['<>', 0]])->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $this->user_id, 'pay_points' => ['<>', 0]])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else {
            //全部
            $count = M('account_log')->where(['user_id' => $this->user_id])->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $this->user_id])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        }
        $show = $Page->show();
        $this->assign('account_log', $account_log);
        $this->assign('page', $show);
        $this->assign('listRows', $Page->listRows);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_points');
            exit;
        }
        return $this->fetch();
    }

    // 积分明细
    public function points_list()
    {
        $type = I('type', 'all');
        $usersLogic = new UsersLogic;
        $result = $usersLogic->points($this->user_id, $type);

        $this->assign('type', $type);
        $showpage = $result['page']->show();
        $this->assign('account_log', $result['account_log']);
        $this->assign('page', $showpage);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_points');
        }
        return $this->fetch();
    }

    // 余额页面
    function account()
    {
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => ['money' => $this->_user->user_money]]);
    }

    // 充值
    function recharge()
    {

    }

    // 充值明细
    public function recharge_list()
    {
        $usersLogic = new UsersLogic;
        $result = $usersLogic->get_recharge_log($this->_userId);  //充值记录
        $this->assign('page', $result['show']);
        $this->assign('lists', $result['result']);
        if (I('is_ajax')) {
            return $this->fetch('ajax_recharge_list');
        }
        return $this->fetch();
    }

    // 提现
    function withdraw()
    {
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => ['money' => $this->_user->user_money, 'alipay' => $this->_user->alipay_name]]);
    }

    // 提现操作
    function withdrawHandel()
    {
        // 金额
    }

    /**
     * 申请提现
     */
    public function withdrawals()
    {
        C('TOKEN_ON', true);
        $cash_open = tpCache('cash.cash_open');
        if ($cash_open != 1) {
            $this->error('提现功能已关闭,请联系商家');
        }
        if (IS_POST) {
            $cash_open = tpCache('cash.cash_open');
            if ($cash_open != 1) {
                $this->ajaxReturn(['status' => 0, 'msg' => '提现功能已关闭,请联系商家']);
            }

            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $data['create_time'] = time();
            $cash = tpCache('cash');

            if (encrypt($data['paypwd']) != $this->user['paypwd']) {
                $this->ajaxReturn(['status' => 0, 'msg' => '支付密码错误']);
            }
            if ($data['money'] > $this->user['user_money']) {
                $this->ajaxReturn(['status' => 0, 'msg' => "本次提现余额不足"]);
            }
            if ($data['money'] <= 0) {
                $this->ajaxReturn(['status' => 0, 'msg' => '提现额度必须大于0']);
            }

            // 统计所有0，1的金额
            //$status = ['in','0,1'];
            // $status
            $total_money = Db::name('withdrawals')->where(array('user_id' => $this->user_id, 'status' => 0))->sum('money');
            if ($total_money + $data['money'] > $this->user['user_money']) {
                $this->ajaxReturn(['status' => 0, 'msg' => "您有提现申请待处理，本次提现余额不足"]);
            }

            if ($cash['cash_open'] == 1) {
                $taxfee = round($data['money'] * $cash['service_ratio'] / 100, 2);
                // 限手续费
                if ($cash['max_service_money'] > 0 && $taxfee > $cash['max_service_money']) {
                    $taxfee = $cash['max_service_money'];
                }
                if ($cash['min_service_money'] > 0 && $taxfee < $cash['min_service_money']) {
                    $taxfee = $cash['min_service_money'];
                }
                if ($taxfee >= $data['money']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => '提现额度必须大于手续费！']);
                }
                $data['taxfee'] = $taxfee;

                // 每次限最多提现额度
                if ($cash['min_cash'] > 0 && $data['money'] < $cash['min_cash']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => '每次最少提现额度' . $cash['min_cash']]);
                }
                if ($cash['max_cash'] > 0 && $data['money'] > $cash['max_cash']) {
                    $this->ajaxReturn(['status' => 0, 'msg' => '每次最多提现额度' . $cash['max_cash']]);
                }

                $status = ['in', '0,1,2,3'];
                $create_time = ['gt', strtotime(date("Y-m-d"))];
                // 今天限总额度
                if ($cash['count_cash'] > 0) {
                    $total_money2 = Db::name('withdrawals')->where(array('user_id' => $this->user_id, 'status' => $status, 'create_time' => $create_time))->sum('money');
                    if (($total_money2 + $data['money'] > $cash['count_cash'])) {
                        $total_money = $cash['count_cash'] - $total_money2;
                        if ($total_money <= 0) {
                            $this->ajaxReturn(['status' => 0, 'msg' => "你今天累计提现额为{$total_money2},金额已超过可提现金额."]);
                        } else {
                            $this->ajaxReturn(['status' => 0, 'msg' => "你今天累计提现额为{$total_money2}，最多可提现{$total_money}账户余额."]);
                        }
                    }
                }
                // 今天限申请次数
                if ($cash['cash_times'] > 0) {
                    $total_times = Db::name('withdrawals')->where(array('user_id' => $this->user_id, 'status' => $status, 'create_time' => $create_time))->count();
                    if ($total_times >= $cash['cash_times']) {
                        $this->ajaxReturn(['status' => 0, 'msg' => "今天申请提现的次数已用完."]);
                    }
                }
            } else {
                $data['taxfee'] = 0;
            }

            if (M('withdrawals')->add($data)) {

                accountLog($this->user['user_id'], -$data['money'], 0, '提现扣款', 0, 0, '');

                // 发送公众号消息给用户
                $user = Db::name('OauthUsers')->where(['user_id' => $this->user['user_id']])->find();
                if ($user) {
                    $wx_content = "您的提现申请已提交，正在处理...";
                    $wechat = new \app\common\logic\wechat\WechatUtil();
                    $wechat->sendMsg($user['openid'], 'text', $wx_content);
                }

                $this->ajaxReturn(['status' => 1, 'msg' => "已提交申请", 'url' => U('User/account', ['type' => 2])]);
            } else {
                $this->ajaxReturn(['status' => 0, 'msg' => '提交失败,联系客服!']);
            }
        }
        $user_extend = Db::name('user_extend')->where('user_id=' . $this->user_id)->find();

        //获取用户绑定openId
        $oauthUsers = M("OauthUsers")->where(['user_id' => $this->user_id, 'oauth' => 'wx'])->find();
        $openid = $oauthUsers['openid'];
        if (empty($oauthUsers)) {
            $openid = Db::name('oauth_users')->where(['user_id' => $this->user_id])->value('openid');
        }

        $this->assign('user_extend', $user_extend);
        $this->assign('cash_config', tpCache('cash'));//提现配置项
        $this->assign('user_money', $this->user['user_money']);    //用户余额
        $this->assign('openid', $openid);    //用户绑定的微信openid
        return $this->fetch();
    }

    /**
     * 申请记录列表
     */
    public function withdrawals_list()
    {
        $res = (new UsersLogic())->get_withdrawals_log($this->_userId);
        var_dump($res);
        die;
    }

    // 支付宝
    function alipay()
    {
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => ['money' => $this->_user->user_money]]);
    }

    // 绑定支付宝
    function bind_alipay()
    {

        //post alipay,name
        $name = input('name', '');
        $alipay = input('alipay', '');
        if (empty($alipay_name) || strlen($alipay_name) > 20) {
            $this->ajaxReturn(['status' => -2, 'msg' => '支付宝真实姓名有误！', 'data' => '']);
        }

        if (empty($alipay_number) || strlen($alipay_number) > 20) {
            $this->ajaxReturn(['status' => -2, 'msg' => '支付宝账号！', 'data' => '']);
        }

        $res = Db::table('users')->where(['id' => $this->_userId])->update(['alipay' => $alipay_number, 'alipay_name' => $alipay_name]);

        if ($res !== false) {
            $this->ajaxReturn(['status' => 1, 'msg' => '修改成功', 'data' => '']);
        }

        $this->ajaxReturn(['status' => 1, 'msg' => '修改失败', 'data' => '']);
    }

    // 收藏
    function collect_list()
    {
        $where = ['status' => 1];
        $count = (new UsersLogic())->get_goods_collect_count($this->_userId);
        $page_count = 20;
        $page = new AjaxPage($count, $page_count);
        $list = Db::name('goods_collect')->where($where)->order('id DESC')
            ->limit($page->firstRow . ',' . $page->listRows)
            ->select();
        $this->ajaxReturn([
            'status' => 1,
            'msg' => '获取成功',
            'data' => [
                'count' => $count,
                'list_count' => count($list),
                'page_count' => $page_count,
                'current_count' => $page_count * I('p'),
                'p' => I('p')
            ]]);
    }

    // 删除收藏
    function del_collect()
    {
        $ids = I('ids');
        is_array($ids) && $ids = implode(',', $ids);
        $ids = rtrim($ids, ',');
        if (empty($ids)) {
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在', 'data' => '']);
        }

        $r = M('system_menu')->where("id in ($ids)")->delete();
        if (!$r) {
            $this->ajaxReturn(['status' => -2, 'msg' => '删除失败', 'data' => '']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '删除成功', 'data' => ['ids' => $ids]]);
    }

    /**
     * +---------------------------------
     * 地址组件原数据
     * +---------------------------------
     */
    public function get_address()
    {
        $user_id = $this->get_user_id();
        if (!$user_id) {
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在', 'data' => '']);
        }
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
        $user_id = $this->get_user_id();
        if (!$user_id) {
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在', 'data' => '']);
        }
        $data = Db::name('user_address')->where('user_id', $user_id)->select();
        $region_list = Db::name('region')->field('*')->column('area_id,area_name');
        foreach ($data as &$v) {
            $v['province'] = $region_list[$v['province']];
            $v['city'] = $region_list[$v['city']];
            $district = Db::name('region')->where(['area_id' => $v['district']])->value('code');
            $v['code'] = $district;
            $v['district'] = $region_list[$v['district']];

            if ($v['twon'] == 0) {
                $v['twon'] = '';
            } else {
                $v['twon'] = $region_list[$v['twon']];
            }

        }
        unset($v);
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => $data]);
    }

    /**
     * +---------------------------------
     * 添加地址
     * +---------------------------------
     */
    public function add_address()
    {
        $user_id = $this->get_user_id();
        if (!$user_id) {
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在', 'data' => '']);
        }

        $consignee = input('consignee');
        $longitude = input('lng');
        $latitude = input('lat');
        $address_district = input('address_district');
        $address_twon = input('address_twon');
        $address = input('address');
        $mobile = input('mobile');
        $is_default = input('is_default');

        $address = $address_twon . $address;

        $post_data['consignee'] = $consignee;
        $post_data['longitude'] = $longitude;
        $post_data['latitude'] = $latitude;
        $post_data['mobile'] = $mobile;
        $post_data['is_default'] = $is_default;

        if ($latitude && $longitude) {
            $url = "http://api.map.baidu.com/geocoder/v2/?ak=gOuAqF169G6cDdxGnMmB7kBgYGLj3G1j&callback=renderReverse&location={$latitude},{$longitude}&output=json";
            $res = request_curl($url);
            if ($res) {
                $res = explode('Reverse(', $res)[1];
                $res = substr($res, 0, strlen($res) - 1);
                $res = json_decode($res, true)['result']['addressComponent'];

                $post_data['province'] = Db::table('region')->where('area_name', $res['province'])->value('area_id');
                $post_data['city'] = Db::table('region')->where('area_name', $res['city'])->value('area_id');
                $post_data['district'] = Db::table('region')->where('area_name', $res['district'])->value('area_id');
                if ($res['town']) {
                    $post_data['town'] = Db::table('region')->where('area_name', $res['town'])->value('area_id');
                }
            }
        }
        $post_data['address'] = $address;

        $addressM = Model('UserAddr');
        $return = $addressM->add_address($user_id, 0, $post_data);
        $this->ajaxReturn($return);
    }


    /**
     * +---------------------------------
     * 地址编辑
     * +---------------------------------
     */
    public function edit_address()
    {
        $user_id = $this->get_user_id();
        $id = input('address_id');
        $address = Db::name('user_address')->where(array('address_id' => $id, 'user_id' => $user_id))->find();
        if (!$address) {
            $this->ajaxReturn(['status' => -2, 'msg' => '地址id不存在！', 'data' => '']);
        }

        $consignee = input('consignee');
        $longitude = input('lng');
        $latitude = input('lat');
        $address_district = input('address_district');
        $address_twon = input('address_twon');
        $address = input('address');
        $mobile = input('mobile');
        $is_default = input('is_default');

        $address = $address_twon . $address;

        $post_data['consignee'] = $consignee;
        $post_data['longitude'] = $longitude;
        $post_data['latitude'] = $latitude;
        $post_data['mobile'] = $mobile;
        $post_data['is_default'] = $is_default;

        if ($latitude && $longitude) {
            $url = "http://api.map.baidu.com/geocoder/v2/?ak=gOuAqF169G6cDdxGnMmB7kBgYGLj3G1j&callback=renderReverse&location={$latitude},{$longitude}&output=json";
            $res = request_curl($url);
            if ($res) {
                $res = explode('Reverse(', $res)[1];
                $res = substr($res, 0, strlen($res) - 1);
                $res = json_decode($res, true)['result']['addressComponent'];

                $post_data['province'] = Db::table('region')->where('area_name', $res['province'])->value('area_id');
                $post_data['city'] = Db::table('region')->where('area_name', $res['city'])->value('area_id');
                $post_data['district'] = Db::table('region')->where('area_name', $res['district'])->value('area_id');
                if ($res['town']) {
                    $post_data['town'] = Db::table('region')->where('area_name', $res['town'])->value('area_id');
                }
            }
        }

        $post_data['address'] = $address;


        $addressM = Model('UserAddr');
        $return = $addressM->add_address($user_id, $id, $post_data);
        $this->ajaxReturn($return);
    }


    // 删除地址
    public function del_address()
    {
        $id = input('address_id/d', 86);
        $address = Db::name('user_address')->where(["address_id" => $id])->find();
        if (!$address || $address['user_id'] != $this->_userId) {
            $this->ajaxReturn(['status' => -2, 'msg' => '地址id不存在！', 'data' => '']);
        }
        $row = Db::name('user_address')->where(array('user_id' => $this->_userId, 'address_id' => $id))->delete();
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if ($address['is_default'] == 1) {
            $address2 = Db::name('user_address')->where(["user_id" => $this->_userId])->find();
            $address2 && Db::name('user_address')->where(["address_id" => $address2['address_id']])->update(array('is_default' => 1));
        }
        if ($row !== false)
            $this->ajaxReturn(['status' => 1, 'msg' => '删除地址成功', 'data' => $row]);
        else
            $this->ajaxReturn(['status' => -2, 'msg' => '删除失败', 'data' => '']);
    }


}