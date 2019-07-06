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
        //$new = 0;
        if ($data) {
            $re = $this->xmlToArray($data);

            $wx_message['eventkey'] = $re['EventKey'];
            $wx_message['openid'] = $re['FromUserName'];
            $wx_message['event'] = $re['Event'];

            DB::name('wx_message')->insert($wx_message);

            // $this->write_log(json_encode($re));

            $this->weixin_fh($re['EventKey'], $re['FromUserName'], $re['Event']);

//            $url = SITE_URL.'/mobile/message/index?eventkey='.$re['EventKey'].'&openid='.$re['FromUserName'].'&event='.$re['Event'];
            //            httpRequest($url);
            //$new = 1;
        }

//        $config = Db::name('wx_user')->find();
        //        $config['new'] = $new;
        //        if ($config['wait_access'] == 0) {
        //            ob_clean();
        //            exit($_GET["echostr"]);
        //        }
        //        $logic = new WechatLogic($config);
        //        $logic->handleMessage();

        // ob_clean();
        // exit($_GET["echostr"]);

    }

    public function weixin_fh($eventkey, $openid, $event)
    {
        // SITE_URL.'/mobile/message/index?eventkey='.$re['EventKey'].'&openid='.$re['FromUserName'].'&event='.$re['Event'];

        if ($event == 'SCAN') {
            $this->deal($openid, $eventkey);
        }

        if ($event == 'subscribe') {
            $shangji_user_id = substr($eventkey, strlen('qrscene_'));
            $this->deal($openid, $shangji_user_id);
        }

        //$this->handle();
        return true;
    }

    //处理关系
    public function deal($xiaji_openid, $shangji_user_id)
    {

        if (is_numeric($shangji_user_id) == false) {
            $this->write_log('------上级shangji_user_id不是数字----' . $xiaji_openid);
        }

        if (!$xiaji_openid) {
            $this->write_log('------下级openid不存在----' . $xiaji_openid);
        }

        if (!$shangji_user_id) {
            $this->write_log('------上级user_id不存在----' . $xiaji_openid);
        }

        if (!$xiaji_openid) {
            return false;
        }
        if (!$shangji_user_id) {
            return false;
        }

        $this->write_log($xiaji_openid . '-------处理--------' . $shangji_user_id);

        //有用户绑定
        $xiaji = M('member')->where(['openid' => $xiaji_openid])->find();
        if (!$xiaji) {

            //注册用户
            $new_data = array(
                'openid' => $xiaji_openid,
                'nickname' => '用户'.time()
            );
            $xiaji_user_id = M('member')->insertGetId($new_data);

            //先注册 users 表

            // $oauth_data = array(
            //     'openid' => $xiaji_openid,
            //     'user_id' => $xiaji_user_id
            // );
            //M('oauth_users')->add($new_data);
            $new = 1;
            $this->write_log($xiaji_user_id . '------注册成功-----' . $shangji_user_id);
        } else {
            $new = 0;
            $xiaji_user_id = $xiaji['id'];
        }

        //注册好了，
        // 绑定关系
        share_deal_after($xiaji_user_id, $shangji_user_id, $new);

        $this->write_log($xiaji_user_id . '-------绑定操作--------' . $shangji_user_id);

        $xiaji_user_id = $xiaji['user_id'];

    }

    public function xmlToArray($xml)
    {
        $obj = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        $json = json_encode($obj);
        $arr = json_decode($json, true);
        return $arr;
    }

    public function write_log($content)
    {
        $content = "[" . date('Y-m-d H:i:s') . "]" . $content . "\r\n";
        $dir = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/') . '/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $path = $dir . '/' . date('Ymd') . '.txt';
        file_put_contents($path, $content, FILE_APPEND);
    }

}
