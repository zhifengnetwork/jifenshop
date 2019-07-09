<?php

namespace app\api\controller;

use think\Db;

class Weixin
{
    /**
     * 处理接收推送消息
     */
    public function index()
    {
        $data = file_get_contents("php://input");

        if ($data) {
            $re = $this->xmlToArray($data);

            $wx_message['eventkey'] = $re['EventKey'];
            $wx_message['openid'] = $re['FromUserName'];
            $wx_message['event'] = $re['Event'];

            DB::name('wx_message')->insert($wx_message);

            $this->weixin_fh($re['EventKey'], $re['FromUserName'], $re['Event']);
        }

        // ob_clean();
        // exit($_GET["echostr"]);

        $config['appid'] =  M('config')->where(['name'=>'appid'])->value('value');
        $config['appsecret'] = M('config')->where(['name'=>'appsecret'])->value('value');

        $logic = new WechatLogic($config);
        $logic->handleMessage();

    }

    public function weixin_fh($eventkey, $openid, $event)
    {

        if ($event == 'SCAN') {
            $this->deal($openid, $eventkey);
        }

        if ($event == 'subscribe') {
            $shangji_user_id = substr($eventkey, strlen('qrscene_'));
            $this->deal($openid, $shangji_user_id);
        }

        return true;
    }

    //处理关系
    public function deal($xiaji_openid, $shangji_user_id)
    {

        if (is_numeric($shangji_user_id) == false) {
            write_log('------上级shangji_user_id不是数字----' . $xiaji_openid);
        }

        if (!$xiaji_openid) {
            write_log('------下级openid不存在----' . $xiaji_openid);
        }

        if (!$shangji_user_id) {
            write_log('------上级user_id不存在----' . $xiaji_openid);
        }

        if (!$xiaji_openid) {
            return false;
        }
        if (!$shangji_user_id) {
            return false;
        }

        write_log($xiaji_openid . '-------处理--------' . $shangji_user_id);

        //有用户绑定
        $xiaji = M('member')->where(['openid' => $xiaji_openid])->find();
        if (!$xiaji) {

            //注册用户
            $new_data = array(
                'openid' => $xiaji_openid,
                'nickname' => '用户'.time(),
                'createtime' => time()
            );
            $xiaji_user_id = M('member')->insertGetId($new_data);

            //先注册 users 表

            // $oauth_data = array(
            //     'openid' => $xiaji_openid,
            //     'user_id' => $xiaji_user_id
            // );
            //M('oauth_users')->add($new_data);
            $new = 1;
            write_log($xiaji_user_id . '------注册成功-----' . $shangji_user_id);
        } else {
            $new = 0;
            $xiaji_user_id = $xiaji['id'];
        }

        //注册好了，
        // 绑定关系
        share_deal_after($xiaji_user_id, $shangji_user_id, $new);

        write_log($xiaji_user_id . '-------绑定操作--------' . $shangji_user_id);

        $xiaji_user_id = $xiaji['user_id'];

    }

    public function xmlToArray($xml)
    {
        $obj = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $json = json_encode($obj);
        $arr = json_decode($json, true);
        return $arr;
    }

  

}
