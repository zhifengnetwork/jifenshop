<?php
/**
 * 订单API
 */
namespace app\api\controller;
use think\Db;
use app\common\model\Member;

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
        $data=[
            'id' => $this->_mId,
            'nickname' => $this->_member->nickname,
            'avatar' => $this->_member->avatar,
            'share_img' =>SITE_URL.Config('c_pub.img').'aaa.jpg'
        ];
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => $data]);
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
        $team_list=Db::name('team')->where('team_user_id', $user_id)->select();
        $page = input('page',1);
        $user_ids='';
        foreach ($team_list as $key=>$value){
            if($user_ids){
                $user_ids=$user_ids.','.$value['user_id'];
            }else{
                $user_ids=$value['user_id'];
            }

        }
        $where['user_id'] = array('in',$user_ids);
        $order=[];
        $order_list=Db::name('order')->where($where)->field('order_id,user_id,mobile')->order('add_time')->paginate(10,false,['page'=>$page])->toArray();
        foreach ($order_list['data'] as $key=>$value){
            $value['user_name']='111';
            $order[]=$value;
        }
        $this->ajaxReturn(['status' => 1, 'msg' => '获取成功', 'data' => $order]);
    }
    function user_id($user_id){

    }
}
