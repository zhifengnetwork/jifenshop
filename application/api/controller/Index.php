<?php
/**
 * 用户API
 */
namespace app\api\controller;
use app\common\model\Users;
use app\common\logic\UsersLogic;
use think\Db;


class Index extends ApiBase
{

    /**
     * 首页接口
     */
    public function index()
    {
        /**
         *  首页轮播图
         */
        $page_id = request()->param('page_id',1);
        $list = Db::table('advertisement')->where(['state'=>['<>',-1],'page_id'=>$page_id])->order('type asc sort asc')->select();

        for($i=0;$i<count($list);$i++)
        {
            $list[$i]['picture'] = SITE_URL.$list[$i]['picture'];

        }

        $data['banner'] = $list;

        /**
         *  公告信息
         */
        $notice = Db::table('config')->where(['name'=>['=','notice']])->select();
        $data['notice'] = $notice;
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取数据成功','data'=>$data]);
    }



    

    
}
