<?php
namespace app\api\controller;

use think\Controller;

class Menu extends Controller
{

    public function index()
    {
        $access_token = access_token();
        echo $access_token;
    }

   
    public function create()
    {
        $access_token = access_token();

        $json='{
            "button":[
                {
                    "type":"view",
                    "name":"商城首页",
                    "url":"https://ji.zhifengwangluo.com"
                }
               
            ]
        }';

        $url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token=" . $access_token;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $out = curl_exec($ch);
        curl_close($ch);
        var_dump($out);

    }
}

