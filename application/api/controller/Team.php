<?php
/**
 * 订单API
 */
namespace app\api\controller;
use think\Db;
use app\common\model\Member;
use app\common\logic\ShareLogic;

class Team extends ApiBase
{
    private $_mId;
    private $_member;

    public function __construct()
    {
        $this->_mId = $this->get_user_id();
        if (!$this->_mId || !($this->_member = Member::get($this->_mId))) {
            $this->ajaxReturn(['status' => -2, 'msg' => '用户不存在']);
        };
    }

    /**
     * 分享链接  暂时数据虚拟
     */
    public function share(){

        $share_img=$this->fenxiang1($this->_mId);
        $data=[
            'id' => $this->_mId,
            'nickname' => $this->_member->nickname,
            'avatar' => $this->_member->avatar,
            'share_img' =>$share_img
        ];
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => $data]);
    }
    /**
     * 新的分享
     */
    public function fenxiang1($user_id)
    {

        if(!$user_id){
            $this->redirect('fenxiang_no');
            exit;
        }


        define('IMGROOT_PATH', str_replace("\\","/",realpath(dirname(dirname(__FILE__)).'/../../'))); //图片根目录（绝对路径）
        if(I('refresh') == '1'){
            //删掉文件
            @unlink(IMGROOT_PATH.'/public/share/code/'.$user_id.'.jpg');//删除头像
            @unlink(IMGROOT_PATH.'/public/share/head/'.$user_id.'.jpg');//删除头像
            @unlink(IMGROOT_PATH."/public/share/picture_ok44/'.$user_id.'.jpg");//删除 44
            @unlink(IMGROOT_PATH."/public/share/picture_888/".$user_id.".jpg");

            //强制获取头像
            $openid = session('user.openid');
            $access_token = access_token();
            $url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token='.$access_token.'&openid='.$openid.'&lang=zh_CN';
            $resp = httpRequest($url, "GET");
            $res = json_decode($resp, true);

            $head_pic = $res['headimgurl'];
            if($head_pic){
                //得到头像
                M('users')->where(['openid'=>$openid])->update(['head_pic'=>$head_pic]);
            }
        }

        $head_pic_url = M('users')->where(['user_id'=>$user_id])->value('head_pic');

        $logic = new ShareLogic();
        $ticket = $logic->get_ticket($user_id);

        if( strlen($ticket) < 3){
            $this->error("ticket不能为空");
            exit;
        }
        $url= "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=".$ticket;

        $url222 = IMGROOT_PATH . '/public/share/code/'.$user_id.'.jpg';
        if( @fopen( $url222, 'r' ) )
        {
            //已经有二维码了
            $url_code = IMGROOT_PATH . '/public/share/code/'.$user_id.'.jpg';
        }else{
            //还没有二维码
            $re = $logic->getImage($url,IMGROOT_PATH . '/public/share/code', $user_id.'.jpg');
            $url_code = $re['save_path'];
        }

        //判断图片大小
        $logo_url = \think\Image::open($url_code);
        $logo_url_logo_width = $logo_url->height();
        $logo_url_logo_height = $logo_url->width();

        if($logo_url_logo_height > 420 || $logo_url_logo_width > 420){
            //压缩图片
            $url_code = IMGROOT_PATH . '/public/share/code/'.$user_id.'.jpg';
            $logo_url->thumb(152, 152)->save($url_code , null, 100);
        }
        return SITE_URL.'/public/share/code/'.$user_id.'.jpg';
    }
    /**
     * 团队列表
     */
    public function my_team(){
        $user_id=$this->_mId;
        $page = input('page',1);
        $team_list=Db::name('team')->where('team_user_id', $user_id)->paginate(10,false,['page'=>$page])->toArray();
        foreach ($team_list["data"] as $key=>&$value){
            $value["add_time"]=date('Y-m-d H:i:s',$value['add_time']);
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => $team_list['data']]);
    }
    /**
     * 我的团队订单
     */
    public function my_team_order(){
        $user_id=$this->_mId;
        $page = input('page',1);
        $uid = input('uid');
        $order=Db::table('order')->alias('o')
            ->join('team t','t.user_id=o.user_id','LEFT')
            ->where('t.team_user_id',$uid)
//            ->where('order_status',4)
            ->group('o.user_id')
            ->order('o.add_time DESC')
            ->field('o.order_id,t.user_id,t.user_name,o.mobile')
            ->paginate(10,false,['page'=>$page])->toArray();
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => $order['data']]);
    }
    /**
     * 我的团队订单详情
     */
    public function team_order_detailed(){
        $user_id = input('user_id');
        $page = input('page',1);
        $order_list=Db::table('order')
            ->where('user_id',$user_id)
//            ->where('order_status',4)
            ->order('add_time DESC')
            ->field('order_id,user_id,order_sn,total_amount')
            ->paginate(10,false,['page'=>$page])->toArray();
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => $order_list['data']]);
    }
}
