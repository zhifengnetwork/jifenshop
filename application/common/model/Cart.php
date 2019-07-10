<?php
namespace app\common\model;
use think\Model;
use think\Db;

class Cart extends Model
{
    protected $table = 'cart';

    public function cartList($where = array())
    {

        $cart_list = $this->field('id,selected,user_id,goods_id,goods_name,goods_price,member_goods_price,subtotal_price,sku_id,goods_num,spec_key_name')->where($where)->order('id DESC')->select();

        $arr = [];
        if($cart_list){
            foreach($cart_list as $key=>$value){
                $value['img']= Db::table('goods_img')->where('goods_id',$value['goods_id'])->where('main',1)->value('picture');
                $value['img']=SITE_URL.Config('c_pub.img').$value['img'];
                $goods_res= Db::table('goods')->where('goods_id',$value['goods_id'])->where('is_show',1)->value('goods_id');
                if($goods_res){
                    $arr[]=$value;
                }
            }
        }

        $arr = ota($arr);
        $arr = array_values( $arr );
        return $arr;
    }
    //少参
    public function cartList_no($where = array())
    {

        $cart_list = $this->field('id,user_id,goods_id,goods_name,goods_price,member_goods_price,subtotal_price,sku_id,goods_num,spec_key_name')->where($where)->order('id DESC')->select();

        $arr = [];
        if($cart_list){
            foreach($cart_list as $key=>$value){
                $value['img']= Db::table('goods_img')->where('goods_id',$value['goods_id'])->where('main',1)->value('picture');
                $value['img']=SITE_URL.Config('c_pub.img').$value['img'];
                $arr[]=$value;
            }
        }

        $arr = ota($arr);
        $arr = array_values( $arr );
        return $arr;
    }
    public function cartList2($where = array())
    {

        $cart_list = $this->field('id,selected,user_id,groupon_id,goods_id,goods_sn,goods_name,market_price,goods_price,member_goods_price,subtotal_price,sku_id,goods_num,spec_key_name')->where($where)->order('id DESC')->select();

        $arr = [];
        if($cart_list){
            $flag = false;
            foreach($cart_list as $key=>$value){
                if($value['groupon_id'] == 0){
                    if($flag === 1){
                        $this->where('groupon_id','>',0)->where('user_id',$where['user_id'])->delete();
                        continue;
                    }
                    $flag = true;
                }else if($flag === true && $value['groupon_id']){
                    $this->where('groupon_id','>',0)->where('user_id',$where['user_id'])->delete();
                    continue;
                }else if($value['groupon_id']){
                    $flag = 1;
                }

                if( isset($arr[$value['goods_id']]) ){
                    $arr[$value['goods_id']]['cart_id'] = $arr[$value['goods_id']]['cart_id'] . ',' . $value['id'];
                    $arr[$value['goods_id']]['subtotal_price'] = sprintf("%.2f",$arr[$value['goods_id']]['subtotal_price'] + $value['subtotal_price']);
                    $arr[$value['goods_id']]['goods_num'] = $arr[$value['goods_id']]['goods_num'] + $value['goods_num'];
                    $arr[$value['goods_id']]['spec'][] = $value;
                }else{
                    $arr[$value['goods_id']]['cart_id'] = $value['id'];
                    $arr[$value['goods_id']]['groupon_id'] = $value['groupon_id'];
                    $arr[$value['goods_id']]['goods_id'] = $value['goods_id'];
                    $arr[$value['goods_id']]['goods_name'] = $value['goods_name'];
                    $arr[$value['goods_id']]['goods_sn'] = $value['goods_sn'];
                    $arr[$value['goods_id']]['img'] = Db::table('goods_img')->where('goods_id',$value['goods_id'])->where('main',1)->value('picture');
                    $arr[$value['goods_id']]['market_price'] = $value['market_price'];
                    $arr[$value['goods_id']]['subtotal_price'] = $value['subtotal_price'];
                    $arr[$value['goods_id']]['goods_num'] = $value['goods_num'];
                    $arr[$value['goods_id']]['spec'][] = $value;
                }
            }
        }

        $arr = ota($arr);
        $arr = array_values( $arr );
        return $arr;
    }

    public function cartList1($where = array())
    {   

        $cart_list = $this->field('id,selected,user_id,groupon_id,goods_id,goods_sn,goods_name,market_price,goods_price,member_goods_price,subtotal_price,sku_id,goods_num,spec_key_name')->where($where)->order('id DESC')->select();

        $arr = [];
        if($cart_list){
            $flag = false;
            foreach($cart_list as $key=>$value){

                if($value['groupon_id'] == 0){
                    if($flag === 1){
                        $this->where('groupon_id','>',0)->where('user_id',$where['user_id'])->delete();
                        unset($cart_list[$k]);
                        continue;
                    }
                    $flag = true;
                }else if($flag === true && $value['groupon_id']){
                    $this->where('groupon_id','>',0)->where('user_id',$where['user_id'])->delete();
                    unset($cart_list[$key]);
                    continue;
                }else if($value['groupon_id']){
                    $flag = 1;
                    $k = $key;
                }

                $cart_list[$key]['img'] = Db::table('goods_img')->where('goods_id',$value['goods_id'])->where('main',1)->value('picture');
                // $cart_list[$key]['single_number'] = Db::table('goods')->where('goods_id',$value['goods_id'])->value('single_number');
                // $cart_list[$key]['spec'] = action('goods/getGoodsSpec',['goods_id'=>$value['goods_id']]);
            }
        }

        $arr = ota($cart_list);
        $arr = array_values( $arr );
        return $arr;
    }
}
