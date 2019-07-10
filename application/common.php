<?php
use think\Db;
use think\Cache;
use app\common\model\Team;
use app\common\logic\PointLogic;

function pre($data){
    echo '<pre>';
    print_r($data);
}

function pred($data){
    echo '<pre>';
    print_r($data);die;
}
function request_curl( $url , $data = null ){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    if( !empty($data) )
    {
        @curl_setopt($ch, CURLOPT_POST, 1); 
        @curl_setopt($ch, CURLOPT_POSTFIELDS, $data);   
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $str  = curl_exec($ch);
    curl_close($ch);
    return $str;
}
/**
 * 只保留字符串首尾字符，隐藏中间用*代替（两个字符时只显示第一个）
 * @param string $user_name 姓名
 * @param int $head  左侧保留位数
 * @param int $foot 右侧保留位数
 * @return string 格式化后的姓名
 */
function substr_cut($user_name, $head=1, $foot=1){
    $strlen     = mb_strlen($user_name, 'utf-8');
    $firstStr     = mb_substr($user_name, 0, $head, 'utf-8');
    $lastStr     = mb_substr($user_name, -$foot, $foot, 'utf-8');
    return $strlen == 2 ? $firstStr . str_repeat('*', mb_strlen($user_name, 'utf-8') - 1) : $firstStr . str_repeat("*", $strlen - ($head + $foot)) . $lastStr;
}

function get_randMoney($money_total = 20 , $personal_num = 10){
    $min_money    = $money_total/$personal_num - $money_total/$personal_num*0.1;
    $money_right  = $money_total;
    $randMoney=[];
    for($i=1;$i<=$personal_num;$i++){
        if($i== $personal_num){
            $money=$money_right;
        }else{
            $max=$money_right*100 - ($personal_num - $i ) * $min_money *100;
            $money= rand($min_money*100,$max) /100;
            $money=sprintf("%.2f",$money);
            }
            $randMoney[]=$money;
            $money_right=$money_right - $money;
            $money_right=sprintf("%.2f",$money_right);
    }
    shuffle($randMoney);
    return  $randMoney;
}
//获取区间刀
function get_qujian($chopper_id){
    $section = Db::name('goods_chopper')->where(['chopper_id' =>$chopper_id])->value('section');
    $section = unserialize($section);
    $qe_amount = ($section['end'] - $section['start'] + 1) * $section['amount'];
    $res     = '第'.$section['start'].'刀到第'.$section['end'].'刀每刀砍价'.$section['amount'].'元一共'.$qe_amount.'元';
    return $res;
}

//树结构
function getTree1($items,$pid ="pid") {
    $map  = [];
    $tree = [];
    foreach ($items as &$it){ $map[$it['cat_id']] = &$it; }  //数据的ID名生成新的引用索引树
    foreach ($items as &$at){
        $parent = &$map[$at[$pid]];
        if($parent) {
            $parent['children'][] = &$at;
        }else{
            $tree[] = &$at;
        }
    }
    return $tree;
}

function checkMobile($mobilePhone)
{
    if (preg_match("/^1[345678]\d{9}$/", $mobilePhone)) {
        return $mobilePhone;
    } else {
        return false;
    }
}

function send_zhangjun($mobile,$code){//掌骏
    
    $content = "【ETH】您的手机验证码为：".$code."，该短信1分钟内有效。如非本人操作，可不用理会！";
    $time=date('ymdhis',time());
    $arr=array('uname'=>"hsxx40",'pwd'=>"hsxx40",'time'=>$time);
    $signPars='';
    foreach($arr as $v) {
        $signPars .=$v;
    }
    $sign = strtolower(md5($signPars));
    $arrs=array('userid'=>"9795",'timestamp'=>$time,'sign'=>$sign,'mobile'=>$mobile,'content'=>$content,'action'=>'send');
    $url='http://120.77.14.55:8888/v2sms.aspx';
    $ret=call($url, $arrs);
    return $ret;
}
function access_token()
{
    $appid=M('config')->where(['name'=>'appid'])->value('value');
    $appsecret=M('config')->where(['name'=>'appsecret'])->value('value');
    if(Cache::get('access_token')){
        return Cache::get('access_token');
    }
    $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appsecret}";
    $return = httpRequest($url, 'GET');
    $return = json_decode($return, 1);
    $web_expires = time() + 7140; // 提前60秒过期
    if ($return['access_token']) {
        Cache::set('access_token',$return['access_token'],7140);
    }
    return $return['access_token'];
}
function share_deal_after($xiaji, $shangji,$new=0)
{
    write_log("xiaji:" . $xiaji);
    write_log("shangji:" . $shangji);
    $Users = M('member');
    if ($xiaji == $shangji) {
        $xiaji_openid = $Users->where(['id' => $xiaji])->value('openid');
        $wx_content = "此次扫码，不能绑定上下级关系。原因：请不要扫自己的二维码！你的ID:".$xiaji;
        $wechat = new \app\common\logic\wechat\WechatUtil();
        $wechat->sendMsg($xiaji_openid, 'text', $wx_content);
        return false;
    }

    $is_shangji = $Users->where(['id' => $xiaji])->value('first_leader');
    if ($is_shangji && (int)$is_shangji > 0) {
        $xiaji_openid = $Users->where(['id' => $xiaji])->value('openid');
        $wx_content = "此次扫码，不能绑定上下级关系。原因：已经存在上级！你的ID:".$xiaji;

        write_log("Common 147 line wx_content :" . $wx_content);

        $wechat = new \app\common\logic\wechat\WechatUtil();

        write_log("Common 150 line xiaji_openid :" . $xiaji_openid);
        
        $wechat->sendMsg($xiaji_openid, 'text', $wx_content);
        return false;
    }
    /*
    //看下级的注册时间
    $reg_time = M('users')->where(['user_id' => $xiaji])->value('reg_time');
    if ( (( time() - $reg_time ) > 86400 ) && $reg_time > 0) {
        write_log("xiaji（after 24 hour）:" . $xiaji);
        $xiaji_openid = M('users')->where(['user_id' => $xiaji])->value('openid');
        $wx_content = "此次扫码，不能绑定上下级关系。原因：新用户扫码时才能绑定关系！你的ID:".$xiaji;
        $wechat = new \app\common\logic\wechat\WechatUtil();
        $wechat->sendMsg($xiaji_openid, 'text', $wx_content);
        return false;
    }*/
    //超过24小时 不再绑定上下级

    $shangUsers = $Users->where(['id'=>$shangji])->find();
    $top_leader = $Users->where(['id'=>$shangji])->value('first_leader');
    $res = $Users->where(['id' => $xiaji])->update(['first_leader' => $shangji,'second_leader'=>$top_leader]);
    
    $team_data['team_user_id']=$shangji;
    $team_data['user_id']=$xiaji;
    $team_data['user_name'] = get_nickname_new($xiaji);
    $team_data['add_time'] = time();
    $member = M('member')->where(['id'=>$xiaji])->find();
    $team_data['user_avatar'] = $member['avatar'];
    Db::table('team')->insert($team_data);

    \app\common\logic\User::vip($shangUsers);

    // 一级返佣积分，二级返佣积分
    $share = PointLogic::getSettingFirst();
    $ky_point = bcadd($shangUsers['ky_point'], $share, 2);
    $Users->where(['id' => $shangji])->update(['ky_point' => $ky_point]);
    Db::name('point_log')->insert([
        'type' => 3,
        'user_id' => $shangji,
        'point' => $share,
        'operate_id' => $xiaji,
        'calculate' => 1,
        'before' => $shangUsers['ky_point'],
        'after' => $ky_point,
        'create_time' => time()
    ]);
    $team = Db::table('team')->where(['user_id' => $shangji])->find();
    if ($team && ($leader = Db::name('member')->field('ky_point')->where(['id' => $team['team_user_id']])->find())) {
        $share = PointLogic::getSettingSecond();
        $ky_point = bcadd($leader['ky_point'], $share, 2);
        $Users->where(['id' => $team['team_user_id']])->update(['ky_point' => $ky_point]);
        Db::name('point_log')->insert([
            'type' => 7,
            'user_id' => $team['team_user_id'],
            'point' => $share,
            'operate_id' => $team['user_id'],
            'calculate' => 1,
            'before' => $leader['ky_point'],
            'after' => $ky_point,
            'create_time' => time()
        ]);
    }


    if ($res) {
        $before = '成功';
    }

    
    //给上级发送消息
    $shangji_openid = $Users->where(['user_id' => $shangji])->value('openid');
    if($shangji_openid){
        $xiaji_nickname = $Users->where(['user_id' => $xiaji])->value('nickname');
        if($xiaji_nickname == ''){
            $xiaji_nickname = get_nickname_new($xiaji);
        }
        $wx_content = "您的一级创客[" . $xiaji_nickname . "][ID:" . $xiaji . "]" . $before . "关注了公众号";
        $wechat = new \app\common\logic\wechat\WechatUtil();
        $wechat->sendMsg($shangji_openid, 'text', $wx_content);
    }

    return true;
}


function get_nickname_new($user_id){

    $user = M('member')->where(['id'=>$user_id])->find();

    $access_token = access_token();
    $url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token='.$access_token.'&openid='.$user['openid'].'&lang=zh_CN';
    $resp = httpRequest($url, "GET");
    $res = json_decode($resp, true);
    if($res['nickname'] == ''){
        return '用户'.time();
    }

//    if($user['nickname'] == ''){
    if($user){
        $data = array(
            'nickname'=>$res['nickname'],
            'avatar'=>$res['headimgurl']
        );
        M('member')->where(['id'=>$user_id])->update($data);
    }

//    }
    return $res['nickname'];
}
/**
 * http请求
 * @param string $url
 * @param string $method
 * @param string|array $fields
 * @return string
 */
function httpRequest($url, $method = 'GET', $fields = [])
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $method = strtoupper($method);
    if ($method == 'GET' && !empty($fields)) {
        is_array($fields) && $fields = http_build_query($fields);
        $url = $url . (strpos($url,"?")===false ? "?" : "&") . $fields;
    }
    curl_setopt($ch, CURLOPT_URL, $url);

    if ($method != 'GET') {
        $hadFile = false;
        curl_setopt($ch, CURLOPT_POST, true);
        if (!empty($fields)) {
            if (is_array($fields)) {
                /* 支持文件上传 */
                if (class_exists('\CURLFile')) {
                    curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
                    foreach ($fields as $key => $value) {
                        if ($this->isPostHasFile($value)) {
                            $fields[$key] = new \CURLFile(realpath(ltrim($value, '@')));
                            $hadFile = true;
                        }
                    }
                } elseif (defined('CURLOPT_SAFE_UPLOAD')) {
                    foreach ($fields as $key => $value) {
                        if ($this->isPostHasFile($value)) {
                            curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
                            $hadFile = true;
                            break;
                        }
                    }
                }
            }
            $fields = (!$hadFile && is_array($fields)) ? http_build_query($fields) : $fields;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        }
    }

    /* 关闭https验证 */
    if ("https" == substr($url, 0, 5)) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }

    $content = curl_exec($ch);
    curl_close($ch);

    return $content;
}

function isPostHasFile($value)
{
    if (is_string($value) && strpos($value, '@') === 0 && is_file(realpath(ltrim($value, '@')))) {
        return true;
    }
    return false;
}
function call($url,$arr,$second = 30){
    $ch = curl_init();
    //设置超时
    curl_setopt($ch, CURLOPT_TIMEOUT, $second);
    curl_setopt($ch,CURLOPT_URL, $url);
    curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
    curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,0);//严格校验

    //设置header
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    //要求结果为字符串且输出到屏幕上
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    //post提交方式
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $arr);
    //运行curl
    $data = curl_exec($ch);
    $data = xmlToArray($data);
    return $data;
}

function xmlToArray($xml){
    //禁止引用外部xml实体
    libxml_disable_entity_loader(true);
    $values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    return $values;
}
//判断是否是微信    
function is_weixin() {
    if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
        return true;
    } return false;
}

/***
 * 调用微信sdk
 */
function wxJSSDK(){
    $wx_config     = config('wx_config');
    $appId         = $wx_config['appid'];
    $appSecret     = $wx_config['appsecret'];
    vendor('wxsdk.wxaction');
    $jssdk = new JSSDK($appId, $appSecret);
    return $jssdk;
}

/**
 * 对象转数组操作
 **/
function ota($data){
    $array = json_decode(json_encode($data), true);
    return $array;
}

/**
 * 创建盐
 * @author tangtanglove <dai_hang_love@126.com>
 */
function create_salt($length = -6)
{
    return $salt = substr(uniqid(rand()), $length);
}

/**
 * minishop md5加密方法
 * @author tangtanglove <dai_hang_love@126.com>
 */
function minishop_md5($string, $salt)
{
    return md5(md5($string) . $salt);
}

/**
 * 获取菜单列表
 */
function get_menu_list()
{
    static $menu_tree;
    if (!$menu_tree) {
        $menuList  = Db::table('menu')->order('sort ASC')->where('status', 1)->select();
        $menu_tree = list_to_tree($menuList);
    }
    return $menu_tree;
}

/**
 * 获取活动类型下拉列表
 */
function get_menu_list_html($selid = -1, $def_tit = '无')
{
    $arr = get_menu_list();

    $list = '<option value="0">' . $def_tit . '</option>';
    foreach ($arr as $val) {
        $list .= '<option value="' . $val['id'] . '"' . ($val['id'] == $selid ? ' selected' : '') . '>' . $val['title'] . '</option>';
        if (!isset($val['_child']) || !$val['_child']) {
            continue;
        }
        foreach ($val['_child'] as $v) {
            $list .= '<option value="' . $v['id'] . '"' . ($v['id'] == $selid ? ' selected' : '') . '>' . '------' . $v['title'] . '</option>';
        }
    }
    return $list;
}

/**
 * 时间戳格式化
 * @param int $time
 * @return string 完整的时间显示
 */
function time_format($time = null, $format = 'Y-m-d H:i:s')
{
    $time = $time === null ? time() : intval($time);
    return date($format, $time);
}

/**
 * 把返回的数据集转换成Tree
 * @param array $list 要转换的数据集
 * @param string $pid parent标记字段
 * @param string $level level标记字段
 * @return array
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
function list_to_tree($list, $pk = 'id', $pid = 'pid', $child = '_child', $root = 0)
{
    // 创建Tree
    $tree = array();
    if (is_array($list)) {
        // 创建基于主键的数组引用
        $refer = array();
        foreach ($list as $key => $data) {
            $refer[$data[$pk]] = &$list[$key];
        }
        foreach ($list as $key => $data) {
            // 判断是否存在parent
            $parentId = $data[$pid];
            if ($root == $parentId) {
                $tree[] = &$list[$key];
            } else {
                if (isset($refer[$parentId])) {
                    $parent           = &$refer[$parentId];
                    $parent[$child][] = &$list[$key];
                }
            }
        }
    }
    return $tree;
}

/**
 * 验证手机号是否正确
 */
function isMobile($mobile)
{
    if (!is_numeric($mobile)) {
        return false;
    }
    return preg_match('#^13[\d]{9}$|^14[5,7]{1}\d{8}$|^15[^4]{1}\d{8}$|^17[0,6,7,8]{1}\d{8}$|^18[\d]{9}$#', $mobile) ? true : false;
}

function curl_post_query($url, $data)
{
    //初始化
    $ch = curl_init(); //初始化一个CURL对象

    // curl_setopt($ch, CURLOPT_URL, $url);
    // curl_setopt($ch, CURLOPT_FAILONERROR, false);
    // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    // curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    // curl_setopt($ch, CURLOPT_HTTPHEADER, array('content-type: application/x-www-form-urlencoded;charset=UTF-8'));
    // curl_setopt($ch, CURLOPT_POSTFIELDS, $data);


    curl_setopt($ch1, CURLOPT_URL, $url);
    //设置你所需要抓取的URL
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
    //设置curl参数，要求结果是否输出到屏幕上，为true的时候是不返回到网页中
    curl_setopt($ch1, CURLOPT_POST, 1);
    curl_setopt($ch1, CURLOPT_HEADER, false);
    //post提交
    curl_setopt($ch1, CURLOPT_POSTFIELDS, http_build_query($data));
    $data = curl_exec($ch);
    // var_dump(curl_error($ch));
    //运行curl,请求网页。
    curl_close($ch);
    
    // var_dump($data);
    // exit;
    //显示获得的数据
    return $data;
}

/**
 * 秒转时分秒
 */
function changeTimeType($seconds)
{
    if ($seconds > 3600) {
        $hours   = intval($seconds / 3600);
        $minutes = $seconds % 3600;
        $time    = $hours . "时" . gmstrftime('%M', $minutes) . "分" . gmstrftime('%S', $minutes) . "秒";
    } else {
        $time = gmstrftime('%M', $seconds) . "分" . gmstrftime('%S', $seconds) . "秒";
    }
    return $time;
}

/**
 * 日期处理函数 （如：2017-8-15 改成2天前）
 * @param unknown $the_time
 * @return unknown|string
 */
function dayfast($the_time)
{
    $now_time = date("Y-m-d H:i:s", time());
    $now_time = strtotime($now_time);
    $dur      = $now_time - $the_time;
    if ($dur < 0) {
        return $the_time;
    } else {
        if ($dur < 60) {
            return $dur . '秒前';
        } else {
            if ($dur < 3600) {
                return floor($dur / 60) . '分钟前';
            } else {
                if ($dur < 86400) {
                    return floor($dur / 3600) . '小时前';
                } else {
                    if ($dur < 259200) {
//3天内
                        return floor($dur / 86400) . '天前';
                    } else {
                        $the_time = date("Y-m-d", $the_time);
                        return $the_time;
                    }
                }
            }
        }
    }
}

function get_real_ip()
{
    $ip = false;
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(', ', $_SERVER['HTTP_X_FORWARDED_FOR']);
        if ($ip) {
            array_unshift($ips, $ip);
            $ip = false;}
        for ($i = 0; $i < count($ips); $i++) {
            if (!preg_match('/^(10│172.16│192.168)./i', $ips[$i])) {
                $ip = $ips[$i];
                break;
            }
        }
    }
    return ($ip ? $ip : $_SERVER['REMOTE_ADDR']);
}

/**
 * 系统加密方法
 * @param string $data 要加密的字符串
 * @param string $key  加密密钥
 * @param int $expire  过期时间 单位 秒
 * @return string
 *
 */
function think_encrypt($data, $key = '', $expire = 0)
{
    $key  = md5(empty($key) ? config('api_auth_key') : $key);
    $data = base64_encode($data);
    $x    = 0;
    $len  = strlen($data);
    $l    = strlen($key);
    $char = '';

    for ($i = 0; $i < $len; $i++) {
        if ($x == $l) {
            $x = 0;
        }

        $char .= substr($key, $x, 1);
        $x++;
    }

    $str = sprintf('%010d', $expire ? $expire + time() : 0);

    for ($i = 0; $i < $len; $i++) {
        $str .= chr(ord(substr($data, $i, 1)) + (ord(substr($char, $i, 1))) % 256);
    }
    return str_replace(array('+', '/', '='), array('-', '_', ''), base64_encode($str));
}

/**
 * 系统解密方法
 * @param  string $data 要解密的字符串 （必须是think_encrypt方法加密的字符串）
 * @param  string $key  加密密钥
 * @return string
 */

function think_decrypt($data, $key = '')
{
    $key  = md5(empty($key) ? config('api_auth_key') : $key);
    $data = str_replace(array('-', '_'), array('+', '/'), $data);
    $mod4 = strlen($data) % 4;
    if ($mod4) {
        $data .= substr('====', $mod4);
    }
    $data   = base64_decode($data);
    $expire = substr($data, 0, 10);
    $data   = substr($data, 10);

    /*if($expire > 0 && $expire < time()) {
    return '';
    }*/
    $x    = 0;
    $len  = strlen($data);
    $l    = strlen($key);
    $char = $str = '';

    for ($i = 0; $i < $len; $i++) {
        if ($x == $l) {
            $x = 0;
        }

        $char .= substr($key, $x, 1);
        $x++;
    }

    for ($i = 0; $i < $len; $i++) {
        if (ord(substr($data, $i, 1)) < ord(substr($char, $i, 1))) {
            $str .= chr((ord(substr($data, $i, 1)) + 256) - ord(substr($char, $i, 1)));
        } else {
            $str .= chr(ord(substr($data, $i, 1)) - ord(substr($char, $i, 1)));
        }
    }
    return base64_decode($str);
}

/**
 * 支付完成修改订单
 * @param $order_sn 订单号
 * @param array $ext 额外参数
 * @return bool|void
 * //成功后，执行这个
 */
function update_pay_status($order_sn,$ext=array())
{
    write_log('common line 684   '.$order_sn);
    $data=$ext;
    write_log('common line 686   '.json_encode($ext));
    write_log('common line 688   '.$data['total_fee'].'  order_sn   '.$data["out_trade_no"]);
    $amount=sprintf("%.2f",$data['total_fee']/100);
    $order = Db::table('order')->where(['order_sn' => $data['out_trade_no']])->field('order_id,groupon_id,user_id,pay_status')->find();
    write_log('common line 689   order===  '.$order);
    if(!$order||$order['pay_status']==1){
        return false;
    }
    write_log('common line 692   '.$order);
    $update = [
//        'seller_id'      => $data['seller_id'],
        'transaction_id' => $data['transaction_id'],
        'order_status'   => 1,
        'pay_status'     => 1,
        'pay_time'       => strtotime($data['time_end']),
    ];

    Db::startTrans();

    Db::name('order')->where(['order_sn' => $order_sn])->update($update);
    write_log('common line 704   ');

    $goods_res = Db::table('order_goods')->field('goods_id,goods_name,goods_num,spec_key_name,goods_price,sku_id')->where('order_id',$order['order_id'])->select();
    foreach($goods_res as $key=>$value){

        $goods = Db::table('goods')->where('goods_id',$value['goods_id'])->field('less_stock_type,gift_points')->find();
        //付款减库存
        if($goods['less_stock_type']==2){
            Db::table('goods_sku')->where('sku_id',$value['sku_id'])->setDec('inventory',$value['goods_num']);
            Db::table('goods_sku')->where('sku_id',$value['sku_id'])->setDec('frozen_stock',$value['goods_num']);
            Db::table('goods')->where('goods_id',$value['goods_id'])->setDec('stock',$value['goods_num']);
        }
    }
    write_log('common line 717   ');
    $member     = Db::name('member')->where(["id" => $order['user_id']])->find();
    $dsh_point = bcadd($amount, $member['dsh_point'], 2);
    $result = Db::table('member')->update(['id' => $order['user_id'], 'dsh_point' => $dsh_point]);
    $result && $result = Db::name('point_log')->insert([
        'type' => 11,
        'user_id' => $order['user_id'],
        'point' => $amount,
        'operate_id' => $order_sn,
        'calculate' => 1,
        'before' => $member['dsh_point'],
        'after' => $dsh_point,
        'create_time' => time()
    ]);
    write_log('common line 731   ');
    if($result){
        Db::commit();
        return true;
    }else{
        Db::rollback();
        return false;
    }



}


/**
 * 写入日志文件
 */
function write_log($content)
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


/**
 * 文件保存名（用房间唯一id和当前局数拼接）
 */
function get_file_name($id, $rounds, $bankerWinCount)
{
    $savename = 'data' . DS . substr(md5($id . 'HIJABC' . $rounds), 0, 8) . DS . $id . '_' . $rounds . $bankerWinCount . '.txt';
    return $savename;
}


function get_balance($user_id,$type){
    return Db::name('member')->where(['id' => $user_id])->find();
}
/**
 * 订单支付时, 获取订单商品名称
 * @param unknown $order_id
 * @return string|Ambigous <string, unknown>
 */
function getPayBody($order_id)
{

    if (empty($order_id)) return "订单ID参数错误";
    $goodsNames = Db::name('order_goods')->where('order_id', $order_id)->column('goods_name');
    $gns = implode($goodsNames, ',');
    $payBody = getSubstr($gns, 0, 18);
    return $payBody;
}


/**
 *   实现中文字串截取无乱码的方法
 */
function getSubstr($string, $start, $length) {
    if(mb_strlen($string,'utf-8')>$length){
        $str = mb_substr($string, $start, $length,'utf-8');
        return $str.'...';
    }else{
        return $string;
    }
}

function get_period_time($type = 'day', $now = 0, $fmt = 0)
{
    $rs = false;
    !$now && ($now = time());
    switch ($type) {
        case 'all':
            $begin_time = 0;
            $end_time   = INT . MAX;
            break;
        case 'yst':
            $rs['beginTime'] = date('Y-m-d 00:00:00', strtotime('-1 days'));
            $rs['endTime']   = date('Y-m-d 23:59:59', strtotime('-1 days'));
            break;
        case 'day': //今天
            $rs['beginTime'] = date('Y-m-d 00:00:00', $now);
            $rs['endTime']   = date('Y-m-d 23:59:59', $now);
            break;
        case 'week': //本周
            $time            = '1' == date('w') ? strtotime('Monday', $now) : strtotime('last Monday', $now);
            $rs['beginTime'] = date('Y-m-d 00:00:00', $time);
            $rs['endTime']   = date('Y-m-d 23:59:59', strtotime('Sunday', $now));
            break;
        case 'ssmonth': //上月至月末
            $rs['beginTime'] = date('Y-m-01 00:00:00', strtotime('-2 month'));
            $rs['endTime']   = date('Y-m-' . intval(date('d')) . ' 23:59:59', strtotime('-2 month'));
            break;
        case 'smonth': //上月至今
            $rs['beginTime'] = date('Y-m-01 00:00:00', strtotime('-1 month'));
            $rs['endTime']   = date('Y-m-' . intval(date('d')) . ' 23:59:59', strtotime('-1 month'));
            break;
        case 'day7': //最近7天
            $rs['beginTime'] = date('Y-m-d', strtotime('-7 day'));
            $rs['endTime']   = date('Y-m-d') - 1;
            break;
        case 'day15': //最近15天
            $rs['beginTime'] = date('Y-m-d', strtotime('-15 day'));
            $rs['endTime']   = date('Y-m-d') - 1;
            break;
        case 'day30': //最近15天
            $rs['beginTime'] = date('Y-m-d', strtotime('-30 day'));
            $rs['endTime']   = date('Y-m-d') - 1;
            break;
        case 'day90': //最近15天
            $rs['beginTime'] = date('Y-m-d', strtotime('-90 day'));
            $rs['endTime']   = date('Y-m-d') - 1;
            break;
        case 'month': //本月
            $rs['beginTime'] = date('Y-m-d 00:00:00', mktime(0, 0, 0, date('m', $now), '1', date('Y', $now)));
            $rs['endTime']   = date('Y-m-d 23:39:59', mktime(0, 0, 0, date('m', $now), date('t', $now), date('Y', $now)));
            break;
        case '3month': //三个月
            $time            = strtotime('-2 month', $now);
            $rs['beginTime'] = date('Y-m-d 00:00:00', mktime(0, 0, 0, date('m', $time), 1, date('Y', $time)));
            $rs['endTime']   = date('Y-m-d 23:39:59', mktime(0, 0, 0, date('m', $now), date('t', $now), date('Y', $now)));
            break;
        case 'half_year': //半年内
            $time            = strtotime('-5 month', $now);
            $rs['beginTime'] = date('Y-m-d 00:00:00', mktime(0, 0, 0, date('m', $time), 1, date('Y', $time)));
            $rs['endTime']   = date('Y-m-d 23:39:59', mktime(0, 0, 0, date('m', $now), date('t', $now), date('Y', $now)));
            break;
        case 'year': //今年内
            $rs['beginTime'] = date('Y-m-d 00:00:00', mktime(0, 0, 0, 1, 1, date('Y', $now)));
            $rs['endTime']   = date('Y-m-d 23:39:59', mktime(0, 0, 0, 12, 31, date('Y', $now)));
            break;
        case 'b': //比较上月***
            $rs['beginTime'] = date("2017-10-10", time());
            $rs['endTime']   = date('Y-m-' . intval(date('d')) . ' 23:59:59', strtotime('-1 month'));
            break;
        case 's': //比较上2个月***
            $rs['beginTime'] = date("2017-10-10", time());
            $rs['endTime']   = date('Y-m-' . intval(date('d')) . ' 23:59:59', strtotime('-2 month'));
        case 'ss': //比较上3个月***
            $rs['beginTime'] = date("2017-10-10", time());
            $rs['endTime']   = date('Y-m-' . intval(date('d')) . ' 23:59:59', strtotime('-3 month'));
            break;

    }
    if ($rs && $fmt == 0) {
        $rs['beginTime'] = strtotime($rs['beginTime']);
        $rs['endTime']   = strtotime($rs['endTime']);
    }
    return $rs;
}
function get_month_text_html($id)
{
    if ($id == -1) {
        $id = date('m') - 1;
    }
    $time = date('Y');
    $data = [$time . '-1', $time . '-2', $time . '-3', $time . '-4', $time . '-5', $time . '-6', $time . '-7', $time . '-8', $time . '-9', $time . '-10', $time . '-11', $time . '-12'];
    $list = '<option value="-1">请选择月份</option>';
    foreach ($data as $key => $val) {
        $list .= '<option value="' . $val . '"' . ($key == $id ? ' selected' : '') . '> ' . $val . '</option>';
    }
    return $list;
}

//获取跳转游戏房间详情url
function redirect_game_info_url($gid, $rel_id)
{

    $arr = [
        '10' => 'gold_sss_room/info',
        '20' => 'gold_bairenniu_round/info',
        '21' => 'gold_tongbiniu_round/info',
        '22' => 'gold_qzn_round/info',
        '30' => 'gold_dezhou_round/info',
        '40' => 'gold_bairenlonghu_round/info',
        '50' => 'gold_bairenhh_round/info',
        '60' => 'gold_bcbm_round/info',
        '70' => 'gold_shz_round/info',
    ];
    $url = '';
    if ($arr[$gid]) {
        $url = url($arr[$gid]) . '?id=' . $rel_id;
    }
    return $url;
}

function api_public_key()
{
    return 'gWE5zJjazR3FQgaYtSWUxWhOxmo';
}
/** 导出excel文件
 * file_name 文件名称
 * title 第一行的标题 [A,B]
 * data 封装的数组,对应title的位置[['A','B'],[]]
 * */
function excel_export($file_name,$title,$data){
	if(count($title)<1||count($data)<1){
		return false;
	}
	
	vendor('PHPExcel.PHPExcel');
	$objPHPExcel = new \PHPExcel();
	$sheet=$objPHPExcel->setActiveSheetIndex(0);
	
	$colunm_num=count($title);
	$letter=range('A', 'Z');
	$letter=array_slice($letter, 0,$colunm_num);
	
	for($j=0;$j<$colunm_num;$j++){
		$sheet->setCellValue($letter[$j].'1',$title[$j]);
	}
	
	$i=2;
		
	foreach ((array)$data as $val){
	
		//设置为文本格式
		for($j=0;$j<count($letter);$j++){
			$sheet->setCellValue($letter[$j].$i,$val[$j]);
			
			$objPHPExcel->getActiveSheet()->getStyle($letter[$j].$i)->getNumberFormat()
			->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_TEXT);
		}
	
		++$i;
	}
		
	header('Content-Type: application/vnd.ms-excel');
	header('Content-Disposition: attachment;filename="'.$file_name.'.xls"');
	header('Cache-Control: max-age=0');
	$objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
	$objWriter->save('php://output');
	exit;
	
}

//二维数组排序
function towArraySort ($data,$key,$order = SORT_ASC) {
    try{
        //        dump($data);
        $last_names = array_column($data,$key);
        array_multisort($last_names,$order,$data);
//        dump($data);
        return $data;
    }catch (\Exception $e){
        return false;
    }

}

function balance_type_text($value){
    return \app\common\model\MenberBalanceLog::getTypeTextBy($value);
}

function point_type_text($value){
    return \app\common\model\PointLog::getTypeName($value);
}
