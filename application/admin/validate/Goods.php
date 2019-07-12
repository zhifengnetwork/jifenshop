<?php
namespace app\admin\validate;
use think\Validate;
class Goods extends Validate
{
    protected $rule = [
        'goods_name'     => 'require',
        'cat_id1'        => 'require',
        'cat_id2'        => 'require',
        'type_id'        => 'require',
        'stock'         => 'require',
        'imgyanzheng'         => 'require',
//        'goods_th[1][]'  => 'require',
    ];

    protected $message = [
        'goods_name.require'    => '商品名称必须填写',
        'cat_id1.require'       => '分类必须选择',
        'cat_id2.require'       => '分类必须选择',
        'type_id.require'       => '类型必须选择',
        'goods_th[1][].require' => '商品规格必须填写',
        'stock.require'         => '商品库存必须填写',
        'imgyanzheng.require'         => '商品封面必须上传图片',
    ];

    protected $scene = [
        'add'     => ['goods_name','cat_id1','goods_th[1][]','stock','imgyanzheng'],
        'edit'    => ['goods_name','cat_id1','stock','img[]','imgyanzheng'],
    ];
}
