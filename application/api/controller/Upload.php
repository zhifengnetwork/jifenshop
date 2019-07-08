<?php
/**
 * 上传图片
 */
namespace app\api\controller;
use app\common\model\Users;
use think\Db;
use think\Controller;


class Upload extends ApiBase
{

    /**
     * 接图片
     */
    public function add()
    {

        $files = request()->file('img');
        $i=0;
        foreach ($files as $k=>$file){
            // 移动到根目录/public/uploads/ 目录下
            $info = $file->move(ROOT_PATH . 'public' . DS . 'uploads');
            if ($info) {
                //成功上传后 获取上传信息
                //输出 jpg
//            echo $info->getExtension();
                //输出 20160820/42a79759f284b767dfcb2a0197904287.jpg
//            echo $info->getSaveName();
                //输出 42a79759f284b767dfcb2a0197904287.jpg
//            echo $info->getFilename();
                //echo $info->pathName;
                //获取图片的存放相对路径
                $filePath = SITE_URL. '/public/uploads/' . $info->getSaveName();
                $img[$i] = $filePath;
                $i++;

             }
        }
        $data['img']=$img;

            $this->ajaxReturn(['status' => 1 , 'msg'=>'获取成功','data'=>$data]);


    }


}