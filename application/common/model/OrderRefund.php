<?php
namespace app\common\model;
use Payment\Common\PayException;
use Payment\Client\Refund;
//use Payment\Config;
use Payment\Common\WxConfig;
use think\Model;
use think\Db;

class OrderRefund extends Model
{
    protected $updateTime = false;

    protected $autoWriteTimestamp = true;

    /***
     * 订单退款
     * pay_type 1支付宝 2微信 3余额
     */
    public static function refund_obj($data){
        require_once ROOT_PATH.'vendor/riverslei/payment/autoload.php';
        $pay_type      = $data['pay_type'];//支付类型
        $order_sn      = $data['order_sn'];//订单号
        $order_amount  = $data['order_amount'];//退款金额
        $member = Db::name('member')->where(['id' => $data['user_id']])->find();

        Db::startTrans();
        //改变订单状态
        $update = [
            'order_status'  => 7,
        ];
        $status = Db::name('order')->where(['order_sn' => $order_sn])->update($update);

        if(!$status){
            Db::rollback();
            return false;
        }
        if($pay_type == 4){// 积分支付，退款到积分
            $ky_point = $member['ky_point'];
            $point = bcadd($ky_point, $order_amount, 2);
            $res =  Db::table('member')->where(['id' => $data['user_id']])->update(['ky_point'=>$point]);
            if(!$res){
                Db::rollback();
                return false;
            }

            $res = Db::name('point_log')->insert([
                'type' => 15,
                'user_id' => $data['user_id'],
                'point' => $order_amount,
                'operate_id' => $order_sn,
                'calculate' => 1,
                'before' => $ky_point,
                'after' => $point,
                'create_time' => time()
            ]);
            if(!$res){
                Db::rollback();
                return false;
            }

        }else{//余额退款
            $old_balance = Db::name('member')->where(['id' => $data['user_id']])->value('balance');

            $balance = [
                'balance'       =>  Db::raw('balance+'.$order_amount.''),
            ];
            $res =  Db::table('member')->where(['id' => $data['user_id']])->update($balance);

            if(!$res){
                Db::rollback();
                return false;
            }

            $balance = bcadd($old_balance, $order_amount, 2);
            $res = Db::name('menber_balance_log')->insert([
                'user_id' => $data['user_id'],
                'balance_type' => 0,
                'log_type' => 1,
                'source_type' => 5,
                'source_id' => $data['refund_sn'],
                'money' => $order_amount,
                'old_balance' => $old_balance,
                'balance' => $balance,
                'create_time' => time(),
                'note' => '退款返回到余额'
            ]);
            if(!$res){
                Db::rollback();
                return false;
            }

        }
        //减待收货积分
        $dsh_point = $member['dsh_point'];
        $point = bcsub($dsh_point, $order_amount, 2);
        $res =  Db::table('member')->where(['id' => $data['user_id']])->update(['dsh_point'=>$point]);

        if(!$res){
            Db::rollback();
            return false;
        }

        $res = Db::name('point_log')->insert([
            'type' => 16,
            'user_id' => $data['user_id'],
            'point' => $order_amount,
            'operate_id' => $order_sn,
            'calculate' => 0,
            'before' => $dsh_point,
            'after' => $point,
            'create_time' => time()
        ]);
        if(!$res){
            Db::rollback();
            return false;
        }

        // 提交事务
        Db::commit();
        return true;
        try {
//            $ret = Refund::run(Config::ALI_REFUND, $pay_config, $paydata);
        } catch (PayException $e) {
            echo $e->errorMessage();
            exit;
        }
//        $res = json_encode($ret, JSON_UNESCAPED_UNICODE);

    }
}
