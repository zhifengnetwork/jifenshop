<?php
/**
 * 用户API
 */
namespace app\api\controller;
use app\common\model\Users;
use app\common\logic\UsersLogic;
use app\common\logic\GoodsLogic;
use app\common\logic\GoodsPromFactory;
use app\common\model\GoodsCategory;
use think\AjaxPage;
use think\Page;
use think\Db;

class Goods extends ApiBase
{

    /**
    * 商品分类接口
    */
    /*
    public function categoryList1(){
        $list = Db::name('category')->where('is_show',1)->field('cat_id,cat_name,pid,img')->order('sort DESC,cat_id DESC')->select();
        $list  = getTree1($list);
        
        if($list){
            foreach($list as $key=>&$value){
                //热销
                $list[$key]['hot'] = Db::table('goods')->alias('g')
                                        ->join('goods_img gi','gi.goods_id=g.goods_id','LEFT')
                                        ->where('cat_id1',$value['cat_id'])
                                        ->where('g.is_show',1)
                                        ->where('gi.main',1)
                                        ->where('FIND_IN_SET(3,g.goods_attr)')
                                        ->field('g.goods_id,goods_name,gi.picture img,price,original_price,g.goods_attr')
                                        ->select();
                if(isset($value['children'])){
                    foreach($value['children'] as $ke=>&$val){

                        $val['goods'] = Db::table('goods')->alias('g')
                                ->join('goods_img gi','gi.goods_id=g.goods_id','LEFT')
                                ->where('cat_id2',$val['cat_id'])
                                ->where('g.is_show',1)
                                ->where('gi.main',1)
                                ->field('g.goods_id,goods_name,gi.picture img,price,original_price,g.goods_attr')
                                ->select();
                        if($val['goods']){
                            foreach($val['goods'] as $g=>$v){
                                if(strpos($v['goods_attr'], '3') !== false){
                                    $list[$key]['hot'][] = $v;
                                }
                            }
                        }
                    }
                }
                if( $list[$key]['hot'] ){
                    $list[$key]['hot'] = array_unique($list[$key]['hot'],SORT_REGULAR);
                    foreach($list[$key]['hot'] as $hot=>$hot_val){
                        $list[$key]['hot'][$hot]['attr_name'] = Db::table('goods_attr')->where('attr_id','in',$hot_val['goods_attr'])->field('attr_name')->select();
                    }
                }
            }
        }
        
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$list]);
    }

*/




   /**
    * 商品分类接口
    */
    public function categoryList11()
    {
        
        $list = Db::name('category')->where('is_show',1)->order('sort DESC,cat_id DESC')->select();
        $list  = getTree1($list);
        foreach($list as $key=>$value){
            $list[$key]['goods'] = Db::table('goods')->alias('g')
                                ->join('goods_attr ga','FIND_IN_SET(ga.attr_id,g.goods_attr)','LEFT')
                                ->where('cat_id1',$value['cat_id'])
                                ->where('g.is_show',1)
                                ->where('gi.main',1)
                                ->group('g.goods_id')
                                ->join('goods_img gi','gi.goods_id=g.goods_id','LEFT')
                                ->order('g.goods_id DESC')
                                ->limit(4)
                                ->field('g.goods_id,goods_name,gi.picture img,price,original_price,GROUP_CONCAT(ga.attr_name) attr_name,g.cat_id1 comment,g.desc')
                                ->select();
            if($list[$key]['goods']){
                foreach($list[$key]['goods'] as $k=>$v){
                    if($v['attr_name']){
                        $list[$key]['goods'][$k]['attr_name'] = explode(',',$v['attr_name']);
                    }else{
                        $list[$key]['goods'][$k]['attr_name'] = array();
                    }

                    $list[$key]['goods'][$k]['comment'] = Db::table('goods_comment')->where('goods_id',$v['goods_id'])->count();
                }
            }
        }
        
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$list]);
    }
    /**
     * 商品所有分类接口
     *
     */
    public function categoryList(){
        $list = Db::name('category')->where('is_show',1)->order('sort DESC,cat_id DESC')->select();
        foreach($list as $key=>$value){
            $list[$key]['img']=SITE_URL.Config('c_pub.img').$list[$key]['img'];
        }

        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$list]);
    }
    /**
     * 商品分类接口
     */
    public function categoryGetGoods()
    {
        $cat_id = input('cat_id');
        $page = input('page',1);
        $goodsList = Db::table('goods')->alias('g')
                ->join('goods_attr ga','FIND_IN_SET(ga.attr_id,g.goods_attr)','LEFT')
                ->where('cat_id1',$cat_id)
                ->where('g.is_show',1)
                ->where('g.is_del',0)
                ->where('gi.main',1)
                ->group('g.goods_id')
                ->join('goods_img gi','gi.goods_id=g.goods_id','LEFT')
                ->order('g.goods_id DESC')
                ->field('g.goods_id,goods_name,gi.picture img,price,original_price,GROUP_CONCAT(ga.attr_name) attr_name,g.cat_id1 comment,g.desc')
                ->paginate(10,false,['page'=>$page])->toArray();
        foreach($goodsList['data'] as $k=>&$v){
            if($v['attr_name']){
                        $goodsList[$k]['attr_name'] = explode(',',$v['attr_name']);
                    }else{
                        $goodsList[$k]['attr_name'] = array();
                    }
                    $v['img']=SITE_URL.Config('c_pub.img').$v['img'];
                    $goodsList[$k]['comment'] = Db::table('goods_comment')->where('goods_id',$v['goods_id'])->count();
                }

        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$goodsList['data']]);
    }
    public function category(){
        $cat_id = input('cat_id');
        $cat_id2 = 'cat_id1';
        $sort = input('sort');
        $goods_attr = input('goods_attr');
        $page = input('page',1);

        $where = [];
        $whereRaw = [];
        $pageParam = ['query' => []];
        if($cat_id){
            $cate_list = Db::name('category')->where('is_show',1)->where('cat_id',$cat_id)->value('pid');
            if($cate_list){
                $cate_list = Db::name('category')->where('is_show',1)->where('pid',$cate_list)->select();
                $cat_id2 = 'cat_id2';
            }else{
                $cate_list = Db::name('category')->where('is_show',1)->where('pid',$cat_id)->select();
            }
            $where[$cat_id2] = $cat_id;
            $pageParam['query'][$cat_id2] = $cat_id;
        }else{
            $cate_list = Db::name('category')->where('is_show',1)->order('sort DESC,cat_id ASC')->select();
        }
        $cate_list  = getTree1($cate_list);

        if($goods_attr){
            $whereRaw = "FIND_IN_SET($goods_attr,goods_attr)";
            $pageParam['query']['goods_attr'] = $goods_attr;
        }

        if($sort){
            $order['price'] = $sort;
        }else{
            $order['goods_id'] = 'DESC';
        }
        
        $goods_list = Db::name('goods')->alias('g')
                        ->join('goods_img gi','gi.goods_id=g.goods_id','LEFT')
                        ->where('gi.main',1)
                        ->where('is_show',1)
                        ->where($where)
                        ->where($whereRaw)
                        ->order($order)
                        ->field('g.goods_id,gi.picture img,goods_name,desc,price,original_price,g.goods_attr')
                        ->paginate(10,false,$pageParam)
                        ->toArray();
        if($goods_list['data']){
            foreach($goods_list['data'] as $key=>&$value){
                $value['comment'] = Db::table('goods_comment')->where('goods_id',$value['goods_id'])->count();
                $value['attr_name'] = Db::table('goods_attr')->where('attr_id','in',$value['goods_attr'])->column('attr_name');
            }
        }
        
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>['cate_list'=>$cate_list,'goods_list'=>$goods_list['data']]]);

    }

    /**
     * 配送信息
     */
    public function shipping(){
        $goods_id = input('goods_id');
        $areaname = input('areaname');
        $shipping_price = 0;
        $goods_res = Db::table('goods')->field('shipping_setting,shipping_price,delivery_id')->where('goods_id',$goods_id)->find();
        if($goods_res['shipping_setting'] == 1){
            $shipping_price = sprintf("%.2f",$shipping_price + $goods_res['shipping_price']);
        }else if($goods_res['shipping_setting'] == 2){
            if( !$goods_res['delivery_id'] ){

                $deliveryWhere['is_default'] = 1;

            }else{
                $deliveryWhere['delivery_id'] = $goods_res['delivery_id'];
            }
            $delivery = Db::table('goods_delivery')->where($deliveryWhere)->find();
            if($delivery){
                $delivery['areas'] = unserialize($delivery['areas']);

                foreach ($delivery['areas']['citys'] as $key => $value){
                    $areas = explode(';',$value);
                    if(in_array($areaname,$areas)){
                        $areaskey = $key;break;
                    }
                }

                if( $delivery ){
                    if($delivery['type'] == 2){
                        $shipping_price = sprintf("%.2f",$shipping_price + $delivery['areas']['firstprice_qt'][$areaskey]);   //计算该商品的运费
                    }
                }
            }


        }
        $data['shipping_price'] = $shipping_price;  //该商品的运费

        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$data]);
    }

    /**
     * 商品详情
     */
    public function goodsDetail(){
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $goods_id = input('goods_id');
        if(!$goods_id){
            $this->ajaxReturn(['status' => -2 , 'msg'=>'商品id不能为空','data'=>'']);
        }
        $goodsinfo = Db::table('goods')->field('g.content,g.goods_name,g.price,g.original_price,g.is_own,g.stock,g.number_sales')->alias('g')
                    ->join('goods_img b','g.goods_id=b.goods_id')
                    ->where(['g.is_show'=>1,'g.is_del'=>0])
                    ->find($goods_id);
        if (empty($goodsinfo)) {
            $this->ajaxReturn(['status' => -2 , 'msg'=>'商品不存在！']);
        }
        $regular = '/src="/';
        $replacement = 'width="100%" src="'.SITE_URL;

        $goodsinfo['content'] = preg_replace($regular,$replacement,$goodsinfo['content']);
        $goodsRes['goodsinfo'] = $goodsinfo;



//
//        if($goodsRes['attr_name']){
//            $goodsRes['attr_name'] = explode(',',$goodsRes['attr_name']);
//        }else{
//            $goodsRes['attr_name'] = [];
//        }
        //商品规格
        $goodsRes['spec'] = $this->getGoodsSpec($goods_id);
        //库存
//        $goodsRes['stock'] = $goodsRes['spec']['count_num'];
//        $goodsRes['groupon_price'] = $goodsRes['spec']['min_groupon_price'];
        unset($goodsRes['spec']['count_num'],$goodsRes['spec']['min_groupon_price']);

        //组图
        $goodsRes['img'] = Db::table('goods_img')->where('goods_id',$goods_id)->field('picture')->order('main DESC')->select();
//        print_r($goodsRes['img']);die;
        for($i=0;$i<count($goodsRes['img']);$i++)
        {
            $goodsRes['img'][$i]['picture'] = SITE_URL.'/public/upload/images/'.$goodsRes['img'][$i]['picture'];

        }

        
        //收藏
        $goodsRes['collection'] = Db::table('collection')->where('user_id',$user_id)->where('goods_id',$goods_id)->find();
        if($goodsRes['collection']){
            $goodsRes['collection'] = 1;
        }else{
            $goodsRes['collection'] = 0;
        }
        /**
         *  客服
         */
        $service = Db::table('config')->where('name','service')->find();
        $goodsRes['service'] = $service['value'];


        //评论总数
        $goodsRes['comment_count'] = Db::table('goods_comment')->where('goods_id',$goods_id)->count();
        //评论列表
        $pageParam['query']['goods_id'] = $goods_id;
        $comment = Db::table('goods_comment')->alias('gc')
            ->join('member m','m.id=gc.user_id','LEFT')
            ->field('m.avatar,m.nickname,gc.content,gc.star_rating,gc.replies,gc.praise,gc.add_time,gc.img,gc.sku_id')
            ->where('gc.goods_id',$goods_id)
            ->paginate(10,false,$pageParam);


        $comment = $comment->all();


        if (empty($comment)) {
            $comment = '暂无评论！';
            $goodsRes['commentlist'] = $comment;
        }else{
            foreach($comment as $key=>$value ){

//                $comment[$key]['mobile'] = $value['mobile'] ? substr_cut($value['mobile']) : '';
                $comment[$key]['add_time'] = date("Y-m-d",$comment[$key]['add_time']);
                if($value['img']){

                    $comment[$key]['img'] = explode(',',$value['img']);
                    foreach ($comment[$key]['img'] as $k=>$v){
                        $comment[$key]['img'][$k] = SITE_URL.'/public/upload/images/'.$comment[$key]['img'][$k];
                    }
                }else{
                    $comment[$key]['img'] = [];
                }

                $comment[$key]['spec'] = $this->get_sku_str($value['sku_id']);

//                $comment[$key]['is_praise'] = Db::table('goods_comment_praise')->where('comment_id',$value['comment_id'])->where('user_id',$user_id)->count();

            }
            $goodsRes['commentlist'] = $comment;
        }
        //参数
        $parameter = Db::name('goods_spec')

            ->field('a.spec_name,b.val_name')
            ->alias('a')
            ->join('goods_spec_val b','a.spec_id = b.spec_id')
            ->where('b.goods_id',$goods_id)
            ->find();
        if($parameter['val_name']){
            $parameter['spec_name'] =unserialize($parameter['spec_name']);
            $parameter['val_name'] =unserialize($parameter['val_name']);
            $k = 0;
            foreach ($parameter['spec_name'] as $value){
                $spec[$k]['spec_name']=$value;
                $k++;
            }
            $j=0;
            foreach ($parameter['val_name'] as $value){
                $spec[$j]['val_name']=$value;
                $j++;
            }
            $goodsRes['parameter'] = $spec;

        }


        //限时购
//        $goodsRes['is_limited'] = 0;
//        $attr = explode(',',$goodsRes['goods_attr']);
//        if( in_array(6,$attr) ){
//            if($goodsRes['limited_end'] < time()){
//                $k =  array_search(6,$attr);
//                unset($attr[$k]);
//                $goods_attr = implode(',',$attr);
//                Db::table('goods')->where('goods_id',$goods_id)->update(['goods_attr'=>$goods_attr]);
//                $goodsRes['is_limited'] = 0;
//            }else{
//                $goodsRes['is_limited'] = 1;
//            }
//        }

        //优惠券
//        $where = [];
//        $where['start_time'] = ['<', time()];
//        $where['end_time'] = ['>', time()];
//        $where['goods_id'] = ['in',$goods_id.',0'];
//        $goodsRes['coupon'] = Db::table('coupon')->where($where)->select();
//        if($goodsRes['coupon']){
//            foreach($goodsRes['coupon'] as $key=>$value){
//                $res = Db::table('coupon_get')->where('user_id',$user_id)->where('coupon_id',$value['coupon_id'])->find();
//                if($res){
//                    $goodsRes['coupon'][$key]['is_lq'] = 1;
//                }else{
//                    $goodsRes['coupon'][$key]['is_lq'] = 0;
//                }
//            }
//        }
        
        //拼团
//        $goodsRes['group'] = [];
//        $goodsRes['group_user'] = [];
//        $group = Db::table('goods_groupon')->where('goods_id',$goods_id)->where('is_show',1)->where('is_delete',0)->where('status',2)->order('period DESC')->find();
//        if($group){
//            $goodsRes['group'] = $group;
//            $goodsRes['group']['surplus'] = $group['target_number'] - $group['sold_number'];      //剩余量
//
//            //过期或者拼团人数已满，重新生成新团购信息
//            if( !$goodsRes['group']['surplus'] || $group['end_time'] < time() ){
//                //更改团购过期状态
//                $update_res = Db::name('goods_groupon')->where('groupon_id',$group['groupon_id'])->update(['is_show'=>0,'status'=>3]);
//                if($update_res){
//                    //生成新一期团购
//                    $new_roupon = action('Groupon/new_groupon',[$group]);
//                    if ($new_roupon) $goodsRes['group'] = $new_roupon;
//                }
//            }else{
//                $goodsRes['group']['surplus_percentage'] = $goodsRes['group']['surplus'] / $group['target_number'];      //剩余百分比
//
//                $group_list = Db::table('order')->alias('o')
//                                ->join('member m','m.id=o.user_id','LEFT')
//                                ->where('o.groupon_id',$group['groupon_id'])
//                                ->where('o.pay_status',1)
//                                ->order('o.order_id DESC')
//                                ->field('id user_id,nickname,realname,avatar')
//                                ->select();
//                if($group_list){
//                    for($i=0;$i<$group['sold_number'];$i++){
//                        $group_list[$i]['cha'] = $group['target_number'] - $group['sold_number'] + $i;
//                    }
//                }
//
//                $goodsRes['group_user'] = $group_list;
//            }
//        }

        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$goodsRes]);

    }

    /**
     * 获取评论列表
     */
    public function comment_list(){

//        $user_id = $this->get_user_id();
//        if(!$user_id){
//            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
//        }

        $goods_id = input('goods_id');
        $page = input('page');

        $pageParam['query']['goods_id'] = $goods_id;

        $comment = Db::table('goods_comment')->alias('gc')
                ->join('member m','m.id=gc.user_id','LEFT')
                ->field('m.mobile,gc.user_id,gc.id comment_id,gc.content,gc.star_rating,gc.replies,gc.praise,gc.add_time,gc.img,gc.sku_id')
                ->where('gc.goods_id',$goods_id)
                ->paginate(10,false,$pageParam);

        $comment = $comment->all();

        if (empty($comment)) {
            $this->ajaxReturn(['status' => 1 , 'msg'=>'暂无评论！','data'=>[]]);
        }
        
        foreach($comment as $key=>$value ){
            
            $comment[$key]['mobile'] = $value['mobile'] ? substr_cut($value['mobile']) : '';

            if($value['img']){
                $comment[$key]['img'] = explode(',',$value['img']);
            }else{
                $comment[$key]['img'] = [];
            }

            $comment[$key]['spec'] = $this->get_sku_str($value['sku_id']);

            $comment[$key]['is_praise'] = Db::table('goods_comment_praise')->where('comment_id',$value['comment_id'])->where('user_id',$user_id)->count();
        }
        
        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$comment]);
    }


    public function getGoodsSpec($goods_id){

        //从规格-属性表中查到所有规格id
        $spec = Db::name('goods_spec_attr')->field('spec_id')->where('goods_id',$goods_id)->select();

        $specArray = array();
        foreach ($spec as $spec_k => $spec_v){
            array_push($specArray,$spec_v['spec_id']);
        }

        $specArray = array_unique($specArray);
        $specStr = implode(',',$specArray);

        $specRes = Db::name('goods_spec')->field('spec_id,spec_name')->where('spec_id','in',$specStr)->select();

        $data = array();
        $data['goods_id'] = $goods_id;
        foreach ($specRes as $key=>$value) {
            //商品规格下的属性
            $data['spec_id'] = $value['spec_id'];
            $specRes[$key]['res'] = Db::name('goods_spec_attr')->field('attr_id,attr_name')->where($data)->select();
        }

        //sku信息
        $skuRes = Db::name('goods_sku')->where('goods_id',$goods_id)->select();
        $count_num = 0;
        $min = [];
        foreach ($skuRes as $sku_k=>$sku_v){
            $min[] = $sku_v['groupon_price'];
            $skuRes[$sku_k]['inventory'] = $skuRes[$sku_k]['inventory'] - $skuRes[$sku_k]['frozen_stock'];
            $count_num += $skuRes[$sku_k]['inventory'];
            $skuRes[$sku_k]['sku_attr'] = preg_replace("/(\w*):/",  '"$1":' ,  $sku_v['sku_attr']);

            $str = preg_replace("/(\w*):/",  '"$1":' ,  $sku_v['sku_attr']);
            $arr = json_decode($str,true);
            $str = '';
            foreach($arr as $k=>$v){
                $str .= $v . ',';
            }
            $str = rtrim($str,',');
            $skuRes[$sku_k]['sku_attr1'] = $str;



            $str2 = preg_replace("/(\w*):/",  '"$1":' ,  $sku_v['sku_attr']);

            $arr2 = json_decode($str2,true);

            $str2 = '';
            $xui = 0;
            if($arr2){
                foreach ($arr2 as $k=>$v) {
                    $str2 .= '"'."$xui".'"'.':'.$v . ',';
                    $xui++;
                }
            }

            $str2 = rtrim($str2,',');
            $str2 = substr_replace($str2, '{', 0, 0);
            $laststr = substr($str2, -1).'}';
            $str2 = substr_replace($str2, $laststr, -1, 1);
//            print_r( $str);die;
            $skuRes[$sku_k]['sku_attr2'] = $str2;

            // $skuRes[$sku_k]['sku_attr'] = json_decode($sku_v['sku_attr'],true);
        }

        $specData = array();
        $specData['spec_attr'] = $specRes;
        $specData['goods_sku'] = $skuRes;
        $specData['count_num'] = $count_num;
        $specData['min_groupon_price'] = min($min);
        return $specData;
    }

    //获取商品sku字符串
    public function get_sku_str($sku_id)
    {
        $sku_attr = Db::name('goods_sku')->where('sku_id', $sku_id)->value('sku_attr');
        
        $sku_attr = preg_replace("/(\w*):/",  '"$1":' ,  $sku_attr);
        $sku_attr = json_decode($sku_attr, true);

        if($sku_attr) {
            foreach ($sku_attr as $key => $value) {
                $spec_name = Db::table('goods_spec')->where('spec_id', $key)->value('spec_name');
                $attr_name = Db::table('goods_spec_attr')->where('attr_id', $value)->value('attr_name');
                $sku_attr[$spec_name] = $attr_name;
                unset($sku_attr[$key]);
            }
        }

        $sku_attr = json_encode($sku_attr, JSON_UNESCAPED_UNICODE);
        $sku_attr = str_replace(array('{', '"', '}'), array('', '', ''), $sku_attr);

        return $sku_attr;
    }

    /**
     *  商品分类
     */
    public function goods_cate () {
        $where['level'] = 1;
        $where['is_show'] = 1;
        $field = 'cat_id,cat_name,img,is_show';
        $list = Db::table('category')->where($where)->order('sort desc')->field($field)->select();
        return json(['cede'=>1,'msg'=>'','data'=>$list]);
    }

    /**
     *  商品列表
     */
    public function goods_list () {
        $keyword = request()->param('keyword','');
        $cat_id = request()->param('cat_id',0,'intval');
        $page = request()->param('page',0,'intval');
        $goods = new Goods();
        $list = $goods->getGoodsList($keyword,$cat_id,$page);
        if (!empty($list)){
            return json(['code'=>1,'msg'=>'','data'=>$list]);
        }else{
            return json(['code'=>-2,'msg'=>'没有数据哦','data'=>$list]);
        }
    }

    /**
     *  指定属性商品
     */
    public function selling_goods ()
    {
        $where['is_show'] = 1;
        $where['is_del'] = 0;
        $page = request()->param('page',0,'intval');
        $attr = request()->param('attr',0,'intval');
        $field = 'goods_id,goods_name,price,stock,number_sales,desc';
        $list = model('Goods')->where($where)->where("find_in_set($attr,`goods_attr`)")->field($field)->paginate(4,'',['page'=>$page]);
        if (!empty($list)){
            foreach ($list as &$v){
                $v['picture'] = Db::table('goods_img')->where(['goods_id'=>$v['goods_id'],'main'=>1])->value('picture');
            }
        }
        return json(['code'=>1,'msg'=>'','data'=>$list]);
    }

    /**
     * 限时购
     */
    public function limited_list(){

        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        //限时购专区图片
        $limited_img = Db::table('category')->where('cat_name','like',"%限时购%")->value('img');

        $page = input('page');
        
        $where['is_show'] =  1;
        $where['main'] =  1;
        $where['is_del'] =  0;

        $pageParam['query']['is_show'] = 1;
        $pageParam['query']['is_del'] = 0;


        $list = Db::table('goods')->alias('g')
                ->join('goods_img gi','gi.goods_id=g.goods_id','LEFT')
                ->where($where)
                ->where("FIND_IN_SET(6,goods_attr)")
                ->field('g.goods_id,goods_name,goods_attr,gi.picture img,desc,limited_start,limited_end,price,original_price,stock,stock1')
                ->paginate(5,false,$pageParam);
        $list = $list->all();
        $arr = [];
        if($list){
            foreach($list as $key=>$value){
                if($value['limited_end'] < time()){
                    $attr = explode(',',$value['goods_attr']);
                    $k =  array_search(6,$attr);
                    unset($attr[$k]);
                    $goods_attr = implode(',',$attr);
                    Db::table('goods')->where('goods_id',$value['goods_id'])->update(['goods_attr'=>$goods_attr]);
                    continue;
                }
                $value['purchased'] = $value['stock1'] - $value['stock'];
                $value['surplus'] = $value['stock1'] - $value['purchased'];      //剩余量
                if($value['surplus']){
                    $value['surplus_percentage'] = $value['surplus'] / $value['stock1'];      //剩余百分比
                }else{
                    $value['surplus_percentage'] = 0;      //剩余百分比
                }
                unset($value['goods_attr'],$value['stock'],$value['stock1']);

                $arr[] = $value;
            }
        }

        $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>['list'=>$arr,'limited_img'=>$limited_img]]);
    }

    function ts(){
        phpinfo();
    }

    public function praise(){

        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }

        $comment_id = input('comment_id');

        $where['comment_id'] = $comment_id;
        $where['user_id'] = $user_id;

        $res = Db::table('goods_comment_praise')->where($where)->find();

        if($res){
            Db::table('goods_comment')->where('id',$comment_id)->setDec('praise',1);
            Db::table('goods_comment_praise')->where($where)->delete();
            
            $this->ajaxReturn(['status' => 1 , 'msg'=>'取消点赞成功！','data'=>'']);
        }else{
            Db::table('goods_comment')->where('id',$comment_id)->setInc('praise',1);
            Db::table('goods_comment_praise')->insert($where);
            $this->ajaxReturn(['status' => 1 , 'msg'=>'点赞成功！','data'=>'']);
        }
    }

    //搜索商品
    public function search_goods()
    {
        $page = input('page',1);
        $num = input('num',10);
        $keyword = input('keyword','');
        if(!$keyword){
            $result['status'] = -1;
            $result['msg'] = '请输入关键字';
            $this->ajaxReturn($result);
        }
        // 保存历史
        $user_id = $this->get_user_id();
        if($user_id){
            $res = Db::table('search_history')->where('keyword',$keyword)->count();
            if($res){
                Db::table('search_history')->where(['keyword'=>$keyword,'user_id'=>$user_id])->update(['addtime'=>time()]);
            }else{
                Db::table('search_history')->insert(['addtime'=>time(),'keyword'=>$keyword,'user_id'=>$user_id]);
            }
        }
        $where['goods_name'] = array('like','%'.$keyword.'%');
        $where['is_show'] = 1;
        $where['is_del'] = 0;
        $list = Db::table('goods')->field('goods_id,goods_name,price')->where($where)->order('add_time desc')->page($page,$num)->select();
        if(!$list){
            $result['data'] = array();
            $result['status'] = 1;
            $result['msg'] = '获取数据成功';
            $this->ajaxReturn($result);
        }
        $goods_id = array();
        foreach($list as $key=>$val){
            $goods_id[] = $val['goods_id'];
        }
        //获取商品图片
        $goods_img = Db::table('goods_img')->where('goods_id','in', $goods_id)->column('goods_id,picture');
        //获取商品评论好评数
        $goods_comment = Db::table('goods_comment')->field('goods_id,star_rating')->where('goods_id','in', $goods_id)->select();
        $path = SITE_URL.'/public/upload/images/';
        foreach($list as $k=>$v){
            $list[$k]['picture'] =  $goods_img[$v['goods_id']]?$path.$goods_img[$v['goods_id']]:'';
            $v['comment_num'] = 0;
            $v['goods_num'] =0;
            foreach($goods_comment as $vo){
                if($vo['goods_id'] == $v['goods_id']){
                    $v['comment_num'] += 1;
                    if($vo['star_rating'] > 3){
                        $v['goods_num'] += 1;
                    }
                }
            }
            if($v['comment_num'] == 0){
                $list[$k]['praise'] = '100%';
            }else{
                $list[$k]['praise'] = (sprintf("%.2f",$v['goods_num'] /  $v['comment_num'])*100).'%';
            }
            $list[$k]['praise'] = '100%';
        }
        $result['data'] = $list;
        $result['status'] = 1;
        $result['msg'] = '获取数据成功';
        $this->ajaxReturn($result);
    }

    //搜索历史
    public function search_history()
    {
        $user_id = $this->get_user_id();
        if(!$user_id){
            $result['status'] = 1;
            $result['msg'] = '获取成功';
            $result['data'] = array();
            $this->ajaxReturn($result);
        }
        $list = Db::table('search_history')->field('id,keyword')->where('user_id',$user_id)->order('addtime desc')->limit(6)->select();
        $result['status'] = 1;
        $result['msg'] = '获取数据成功';
        $result['data'] = $list;
        $this->ajaxReturn($result);
    }
}
