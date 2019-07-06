<?php
namespace app\api\controller;

use think\Db;
use think\Loader;
use think\Request;
use think\Session;
use think\captcha\Captcha;


class Login extends ApiBase
{

    /**
     * 获取code 的 url
     */
    public function get_code_url() {
        
        $baseUrl = I('baseUrl');
        if(!$baseUrl){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'当前地址参数baseUrl为空','data'=>'']);
        }

        $appid = M('config')->where(['name'=>'appid'])->value('value');
        $appsecret = M('config')->where(['name'=>'appsecret'])->value('value');
        if(!$appid || !$appsecret){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'后台参数appid或appsecret配置为空','data'=>'']);
        }
    
        $baseUrl = urlencode($baseUrl);

        $url = $this->__CreateOauthUrlForCode($baseUrl,$appid,$appsecret); // 获取 code地址

        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$url]);
    }

     /**
     * 凭 code 登录
     */
    public function login_by_code() {
        
        $code = I('code');
        if(!$code){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'参数code为空','data'=>'']);
        }

        $appid = M('config')->where(['name'=>'appid'])->value('value');
        $appsecret = M('config')->where(['name'=>'appsecret'])->value('value');
        if(!$appid || !$appsecret){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'后台参数appid或appsecret配置为空','data'=>'']);
        }
    
        $data = $this->getOpenidFromMp($code,$appid,$appsecret);//获取网页授权access_token和用户openid
        if(isset($data['errcode'])){
            $this->ajaxReturn(['status' => -1 , 'msg'=>$data['errmsg'],'data'=>'']);
        }

        write_log("openid:".$data['openid']);

        $data2 = $this->GetUserInfo($data['access_token'],$data['openid']);//获取微信用户信息
        $data['nickname'] = empty($data2['nickname']) ? '微信用户' : trim($data2['nickname']);
        $data['sex'] = $data2['sex'];
        $data['head_pic'] = $data2['headimgurl']; 
        $data['oauth_child'] = 'mp';
        $data['oauth'] = 'weixin';
        if(isset($data2['unionid'])){
            $data['unionid'] = $data2['unionid'];
        }
       
        //判断是否注册
      
        $field = 'id,openid,avatar';
        $userinfo = M('member')->where(['openid'=>$data['openid']])->field($field)->find();
        if(!$userinfo){
            $newdata = array(
                'openid' => $data['openid'],
                'nickname' => $data['nickname'],
                'createtime' => time(),
                'avatar' => $data['head_pic']
            );
            M('member')->insert($newdata);

            //再次查找
            $userinfo = M('member')->where(['openid'=>$data['openid']])->field($field)->find();
        }

        //创建token
        if(!$userinfo['id']){
            $this->ajaxReturn(['status' => -1 , 'msg'=> '注册或登录出错' ,'data'=>'']);
        }

        $userinfo['token'] = $this->create_token($userinfo['id']);

        $this->ajaxReturn(['status' => 1 , 'msg'=>'登录成功，你很棒棒','data'=>$userinfo]);
    }

    // {
    //     "status": 1,
    //     "msg": "获取成功",
    //     "data": {
    //         "access_token": "23_BW9BvJkD074_W9enPO35TYficTwdo-7v-1hmLJHNva51O9kSAsWShi4muBkvcFjmWonjX0z4XHms5Vh4iEG4G4uHoa7VDsWLEluOVrEpf1g",
    //         "expires_in": 7200,
    //         "refresh_token": "23_ygEN-F2IwC1zdZI0yRZbhaNmkIBVjy5xJPKIC5cbw0l-rXS0iQJZ8-FDqx0GuUaypuDLaEEjLLGnJnzqmKtGDJ3IeN45kWJqfEpAJee6844",
    //         "openid": "oj0ty1rLtNAnkVXfW7LTyGQcdvZY",
    //         "scope": "snsapi_userinfo",
    //         "nickname": "精",
    //         "sex": 1,
    //         "head_pic": "http://thirdwx.qlogo.cn/mmopen/vi_32/eOb0Z9PVwFlt7Zm25XljEbN4GFqgGzN9DOoc07qGMwzdlZZQnia5OUFnTsxCiceiaH18NgI8Q8WhibGov7lq4859SQ/132",
    //         "oauth_child": "mp",
    //         "oauth": "weixin"
    //     }
    // }

    /**
     * 获取 ticket
     */
    public function get_ticket(){
        $user_id=89;
        $ticket = M('ticket')->where(array('user_id'=>$user_id))->find();
        if(!empty($ticket)){

            return  $ticket['ticket'];

        }else{
            $access_token = access_token();
            $url="https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=".$access_token;
            $json = array(
                'action_name'=>"QR_LIMIT_STR_SCENE",
                'action_info'=>array(
                    'scene'=>array(
                        'scene_str'=>$user_id,
                    ),
                ),
            );
            $json = json_encode($json);
            $ch=curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $out=curl_exec($ch);
            curl_close($ch);
            $out = json_decode($out);
            $newticket = $out->{'ticket'};
            $url = $out->{'url'};
            M('ticket')->save(array('user_id'=>$user_id,'ticket'=>$newticket,'scene_id'=>$user_id,'url'=>$url));

            return  $newticket;
        }

    }
    /**
     *
     * 通过access_token openid 从工作平台获取UserInfo      
     * @return openid
     */
    public function GetUserInfo($access_token,$openid)
    {         
        // 获取用户 信息
        $url = $this->__CreateOauthUrlForUserinfo($access_token,$openid);
        $ch = curl_init();//初始化curl        
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);//设置超时
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);         
        $res = curl_exec($ch);//运行curl，结果以jason形式返回            
        $data = json_decode($res,true);            
        curl_close($ch);
      
        return $data;
    }

    /**
     *
     * 构造获取拉取用户信息(需scope为 snsapi_userinfo)的url地址     
     * @return 请求的url
     */
    private function __CreateOauthUrlForUserinfo($access_token,$openid)
    {
        $urlObj["access_token"] = $access_token;
        $urlObj["openid"] = $openid;
        $urlObj["lang"] = 'zh_CN';        
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/userinfo?".$bizString;                    
    }    
    

    /**
     *
     * 通过code从工作平台获取openid机器access_token
     * @param string $code 微信跳转回来带上的code
     *
     * @return openid
     */
    public function GetOpenidFromMp($code,$appid,$appsecret)
    {
        //通过code获取网页授权access_token 和 openid 。网页授权access_token是一次性的，而基础支持的access_token的是有时间限制的：7200s。
    	//1、微信网页授权是通过OAuth2.0机制实现的，在用户授权给公众号后，公众号可以获取到一个网页授权特有的接口调用凭证（网页授权access_token），通过网页授权access_token可以进行授权后接口调用，如获取用户基本信息；
    	//2、其他微信接口，需要通过基础支持中的“获取access_token”接口来获取到的普通access_token调用。
        $url = $this->__CreateOauthUrlForOpenid($code,$appid,$appsecret);       
        $ch = curl_init();//初始化curl        
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);//设置超时
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);         
        $res = curl_exec($ch);//运行curl，结果以jason形式返回            
        $data = json_decode($res,true);         
        curl_close($ch);
        return $data;
    }

       /**
     *
     * 构造获取open和access_toke的url地址
     * @param string $code，微信跳转带回的code
     *
     * @return 请求的url
     */
    private function __CreateOauthUrlForOpenid($code,$appid,$appsecret)
    {
        $urlObj["appid"] = $appid;
        $urlObj["secret"] = $appsecret;
        $urlObj["code"] = $code;
        $urlObj["grant_type"] = "authorization_code";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/oauth2/access_token?".$bizString;
    }

    /**
     *
     * 构造获取code的url连接
     * @param string $redirectUrl 微信服务器回跳的url，需要url编码
     *
     * @return 返回构造好的url
     */
    private function __CreateOauthUrlForCode($redirectUrl,$appid,$appsecret)
    {
        $urlObj["appid"] = $appid;
        $urlObj["redirect_uri"] = "$redirectUrl";
        $urlObj["response_type"] = "code";
        //$urlObj["scope"] = "snsapi_base";
        $urlObj["scope"] = "snsapi_userinfo";
        $urlObj["state"] = "STATE"."#wechat_redirect";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?".$bizString;
    }

    /**
     * 登录接口
     */
    public function login()
    {


        $mobile    = input('mobile');
        $password1 = input('password');
        $password  = md5('TPSHOP'.$password1);

        $data = Db::name("users")->where('mobile',$mobile)
            ->field('password,user_id')
            ->find();

        if(!$data){
            exit(json_encode(['status' => -1 , 'msg'=>'手机不存在或错误','data'=>null]));
        }
        if ($password != $data['password']) {
            exit(json_encode(['status' => -2 , 'msg'=>'登录密码错误','data'=>null]));
        }
        unset($data['password']);
        //重写
        $data['token'] = $this->create_token($data['user_id']);
        

        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$data]);
    }

     /**
     *
     * 拼接签名字符串
     * @param array $urlObj
     *
     * @return 返回已经拼接好的字符串
     */
    private function ToUrlParams($urlObj)
    {
        $buff = "";
        foreach ($urlObj as $k => $v)
        {
            if($k != "sign"){
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }

}