<?php

namespace app\common\logic;

use app\common\model\Member;
use app\common\model\Sysset;
use app\common\model\Team;
use app\common\model\Users;
use app\common\model\VipCard;
use app\common\util\TpshopException;
use think\Model;
use think\Db;

/**
 * 用户类
 * Class CatsLogic
 * @package Home\Logic
 */
class User
{
    private $user;
    public $coupon = [];

    public function setUserById($user_id)
    {
        $this->user = Users::get($user_id);
    }

    public function setUserByMobile($mobile)
    {
        $this->user = Users::get(['mobile' => $mobile]);
    }

    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * 绑定账号
     */
    public function checkOauthBind()
    {
        if (empty($this->user)) {
            throw new TpshopException('关联账号', 0, ['status' => 0, 'msg' => '账号不存在']);
        }
        $thirdOauth = session('third_oauth');
        $thirdName = ['weixin' => '微信', 'qq' => 'QQ', 'alipay' => '支付宝', 'miniapp' => '微信小程序'];
        $openid = $thirdOauth['openid'];   //第三方返回唯一标识
        $unionid = $thirdOauth['unionid'];   //第三方返回唯一标识
        $oauth = $thirdOauth['oauth'];      //来源
        $oauthCN = $platform = $thirdName[$oauth];
        if ((empty($unionid) && empty($oauth)) || empty($openid)) {
            throw new TpshopException('关联账号', 0, ['status' => 0, 'msg' => '第三方平台参数有误[openid:' . $openid . ' , unionid:' . $unionid . ', oauth:' . $oauth . ']']);
        }
        //1.判断一个账号绑定多个QQ
        //2.判断一个QQ绑定多个账号
        if ($unionid) {
            //此oauth是否已经绑定过其他账号
            $thirdUser = Db::name('oauth_users')->where(['unionid' => $unionid, 'oauth' => $oauth])->find();
            if ($thirdUser && $this->user['user_id'] != $thirdUser['user_id']) {
                throw new TpshopException('关联账号', 0, ['status' => 0, 'msg' => '此' . $oauthCN . '已绑定其它账号', 'result' => ['unionid' => $unionid]]);
            }

            //1.2此账号是否已经绑定过其他oauth
            $thirdUser = Db::name('oauth_users')->where(['user_id' => $this->user['user_id'], 'oauth' => $oauth])->find();
            if ($thirdUser && $thirdUser['unionid'] != $unionid) {
                throw new TpshopException('关联账号', 0, ['status' => 0, 'msg' => '此账号已绑定其它' . $oauthCN . '账号', 'result' => ['unionid' => $unionid]]);
            }
        } else {
            //2.1此oauth是否已经绑定过其他账号
            $thirdUser = Db::name('oauth_users')->where(['openid' => $openid, 'oauth' => $oauth])->find();
            if ($thirdUser) {
                throw new TpshopException('关联账号', 0, ['status' => 0, 'msg' => '此' . $oauthCN . '已绑定其它账号', 'result' => ['openid' => $openid]]);
            }
            //2.2此账号是否已经绑定过其他oauth
            $thirdUser = Db::name('oauth_users')->where(['user_id' => $this->user['user_id'], 'oauth' => $oauth])->find();
            if ($thirdUser) {
                throw new TpshopException('关联账号', 0, ['此账号已绑定其它' . $oauthCN . '账号']);
            }
        }
    }

    public function oauthBind()
    {
        $thirdOauth = session('third_oauth');
        Db::name('oauth_users')->save(['oauth' => $thirdOauth['oauth'], 'openid' => $thirdOauth['openid'], 'user_id' => $this->user['user_id'], 'unionid' => $thirdOauth['unionid'], 'oauth_child' => $thirdOauth['oauth_child']]);
        $ruser['token'] = md5(time() . mt_rand(1, 999999999));
        $ruser['last_login'] = time();
        $this->user->token = md5(time() . mt_rand(1, 999999999));
        $this->user->last_login = time();
        $this->user->save();
        $user_array = $this->user->toArray();
        $oauth_users = Db::name('oauth_users')->where(['user_id' => $this->user['user_id'], 'oauth' => 'weixin', 'oauth_child' => 'mp'])->find();
        if ($oauth_users) {
            $user_array['open_id'] = $oauth_users['open_id'];
        }
        session('user', $user_array);
    }

    public function doLeader()
    {
        $first_leader = cookie('first_leader');  //推荐人id
        if ($first_leader) {
            $this->user->first_leader = $first_leader;
            $this->user->save();
            $firstLeaderUser = Users::get(['user_id' => $first_leader]);
            if ($firstLeaderUser) {
                //他上线分销的下线人数要加1
                $firstLeaderUser->underling_number = $firstLeaderUser->underling_number + 1;
                $firstLeaderUser->save();
                Db::name('users')->where(['user_id' => $firstLeaderUser['second_leader']])->setInc('underling_number');
                Db::name('users')->where(['user_id' => $firstLeaderUser['third_leader']])->setInc('underling_number');
            }
        } else {
            if ($this->user->first_leader != 0) {
                $this->user->first_leader = 0;
                $this->user->save();
            }
        }
    }

    public function refreshCookie()
    {
        setcookie('user_id', $this->user['user_id'], null, '/');
        setcookie('is_distribut', $this->user['is_distribut'], null, '/');
        $nick_name = empty($this->user['nickname']) ? $this->user['mobile'] : $this->user['nickname'];
        setcookie('uname', urlencode($nick_name), null, '/');
        setcookie('head_pic', urlencode($this->user['head_pic']), null, '/');
        setcookie('cn', 0, time() - 3600, '/');
    }

    /**
     * 更新用户等级
     * @throws \think\Exception
     */
    function updateUserLevel()
    {
        $total_amount = Db::name('order')->master()->where(['user_id' => $this->user['user_id'], 'pay_status' => 1, 'order_status' => ['NOTIN', [3, 5]]])->sum('order_amount+user_money');
        $level_info = Db::name('user_level')->where(['amount' => ['elt', $total_amount]])->order('amount desc')->find();
        // 客户没添加用户等级，上报没有累计消费的bug
        if ($level_info) {
            $update['level'] = $level_info['level_id'];
            $update['discount'] = $level_info['discount'] / 100;
        }
        $update['total_amount'] = $total_amount;//更新累计修复额度
        Db::name('users')->where("user_id", $this->user['user_id'])->save($update);
    }

    public function getUser()
    {
        return $this->user;
    }

    // 判断用户会员状态，上级是vip就返佣
    public static function vip($member = [])
    {
        $card = VipCard::getByUser($member['id']);
        if ($member['is_vip'] == 0 && $card && $card['is_pay'] == 1 && Team::getXiaCount($member['id']) >= Sysset::getVipMember()) {
            if (!Db::name('member')->where(['id' => $member['id']])->update(['is_vip' => 1])) {
                return ['status' => -2, 'msg' => '操作失败'];
            }
            $team_user = Db::name('team')->alias('t')
                ->field('m.id,m.balance,m.is_vip')
                ->join('member m', 't.team_user_id=m.id')->where(['user_id' => $member['id']])->find();
            if ($team_user['is_vip'] == 1) {
                $money = bcadd($team_user['balance'], Sysset::getVipCommission(), 2);
                $res = Db::name('member')->where(['id' => $team_user['id']])->update(['balance' => $money]);
                $res && $res = Db::name('menber_balance_log')->insert([
                    'user_id' => $team_user['id'],
                    'balance_type' => 0,
                    'log_type' => 1,
                    'source_type' => 2,
                    'source_id' => $member['id'],
                    'money' => Sysset::getVipCommission(),
                    'old_balance' => $team_user['balance'],
                    'balance' => $money,
                    'create_time' => time(),
                    'note' => '下级成为vip返佣'
                ]);
                if (!$res) {
                    return ['status' => -2, 'msg' => '操作失败'];
                }
            }
            return ['status' => 1, 'msg' => '操作成功'];
        }
        return ['status' => 1, 'msg' => '操作成功'];
    }

}