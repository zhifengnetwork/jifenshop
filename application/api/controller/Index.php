<?php
/**
 * 用户API
 */
namespace app\api\controller;

use app\common\model\Sysset;
use app\common\model\VipCard;
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
        $page_id = request()->param('page_id', 1);
        $list = Db::table('advertisement')->field('picture,url')->where(['state' => ['<>', -1], 'page_id' => $page_id])->limit(5)->order('type asc sort asc')->select();

        for ($i = 0; $i < count($list); $i++) {
            $list[$i]['picture'] = SITE_URL . '/public' . $list[$i]['picture'];
        }

        $data['banner'] = $list;

        /**
         *  公告信息
         */
        $notice = Db::table('config')->field('value')->where(['name' => ['=', 'notice']])->select();
        $data['notice'] = $notice;

        /**
         *  分类导航
         */
        $navlist = Db::table('catenav')->field('title,image,url')->where(['status' => ['<>', -1]])->limit(4)->select();
        for ($i = 0; $i < count($navlist); $i++) {
            $navlist[$i]['image'] = SITE_URL . '/public/' . $navlist[$i]['image'];
        }

        $data['catenav'] = $navlist;

        /**
         *  热销商品
         */
        $hotgoodslist = Db::name('goods')
            ->field('a.goods_id,a.goods_name,a.price,a.original_price,b.picture')
            ->alias('a')
            ->join('goods_img b', 'a.goods_id = b.goods_id')
            ->where(['a.is_hotgoods' => 1, 'a.is_del' => 0])->limit(4)
            ->select();

        for ($i = 0; $i < count($hotgoodslist); $i++) {
            $hotgoodslist[$i]['picture'] = SITE_URL . '/public/upload/images/' . $hotgoodslist[$i]['picture'];

        }

        $data['hotgoods'] = $hotgoodslist;

        /**
         *  推荐商品
         */
        $commendgoodslist = Db::name('goods')
            ->field('a.goods_id,a.goods_name,a.price,a.original_price,b.picture')
            ->alias('a')
            ->join('goods_img b', 'a.goods_id = b.goods_id')
            ->where(['a.is_commend' => 1, 'a.is_del' => 0])->limit(10)
            ->select();
        // 10 个数量

        for ($i = 0; $i < count($commendgoodslist); $i++) {
            $commendgoodslist[$i]['picture'] = SITE_URL . '/public/upload/images/' . $commendgoodslist[$i]['picture'];
        }

        $data['commendgoods'] = $commendgoodslist;

        $user_id = $this->get_user_id('/index/index/index');
        
        $card = $user_id > 0 ? VipCard::getByUser($user_id) : null;
        $data['card'] = ['money' => Sysset::getCardMoney(), 'number' => $card && $card['is_pay'] == 1 ? $card['number'] : ''];

        $this->ajaxReturn(['status' => 1, 'msg' => '获取数据成功', 'data' => $data]);
    }

    public function changeTableVal()
    {
        $table = I('table'); // 表名
        $id_name = I('id_name'); // 表主键id名
        $id_value = I('id_value'); // 表主键id值
        $field = I('field'); // 修改哪个字段
        $value = I('value'); // 修改字段值
        $res = M($table)->where([$id_name => $id_value])->update(array($field => $value));
        if ($res) {
            $this->ajaxReturn(['status' => 1, 'msg' => '修改成功']);
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => '无修改']);
        }
        // 根据条件保存修改的数据
    }

    public function changeTableCommend()
    {
        $table = I('table'); // 表名
        $id_name = I('id_name'); // 表主键id名
        $id_value = I('id_value'); // 表主键id值
        $field = I('field'); // 修改哪个字段
        $value = I('value'); // 修改字段值

        $res = M($table)->where([$id_name => $id_value])->update(array($field => $value));
        if ($res) {
            $this->ajaxReturn(['status' => 1, 'msg' => '修改成功']);
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => '无修改']);
        }
    }

}
