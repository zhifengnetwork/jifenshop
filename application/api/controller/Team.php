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
        $page = input('page',1);
        $order=Db::table('order')->alias('o')
            ->join('team t','t.user_id=o.user_id','LEFT')
            ->where('t.team_user_id',$user_id)
//            ->where('order_status',4)
            ->group('o.order_id')
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
