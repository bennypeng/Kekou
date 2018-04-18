<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EsdkController extends Controller
{
    public function __construct(){

    }

    //  登陆验证接口
    public function login() {
        //  构造请求
        $urlQueryData = array(
            'app' => filter_input(INPUT_GET, "app"),
            'sdk' => filter_input(INPUT_GET, "sdk"),
            'uin' => urlencode(filter_input(INPUT_GET, "uin")),
            'sess' => urlencode(filter_input(INPUT_GET, "sess"))
        );
        $paramStr = http_build_query($urlQueryData);
        $checkLoginUrl = config('constants.CHECK_LOGIN_URL').$paramStr;

        //  发送请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $checkLoginUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $result = curl_exec($ch);
        $clientHttpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);

        if($clientHttpCode != 200 || $result == null || (is_array($result) && count($result) == 0)){
            $ret = "ERROR";
        }else{
            if(intval($result) == config('constants.LOGIN_RESULT_SUCCESS')){
                $ret = "SUCCESS";
            }else{
                $ret = "ERROR";
            }
        }

        Log::info("debug", $urlQueryData);

        return response($ret)
            ->header('Content-Type', "text/html; charset=utf-8");
    }


    //  支付验证接口
    public function notify(Request $request) {
        $urlQueryData = array(
            'app' => filter_input(INPUT_GET, "app"),
            'cbi' => filter_input(INPUT_GET, "cbi"),
            'ct' => filter_input(INPUT_GET, "ct"),
            'fee' => filter_input(INPUT_GET, "fee"),
            'pt' => filter_input(INPUT_GET, "pt"),
            'sdk' => filter_input(INPUT_GET, "sdk"),
            'ssid' => filter_input(INPUT_GET, "ssid"),
            'st' => filter_input(INPUT_GET, "st"),
            'tcd' => filter_input(INPUT_GET, "tcd"),
            'uid' => filter_input(INPUT_GET, "uid"),
            'ver' => filter_input(INPUT_GET, "ver")
        );
        $paramStr = http_build_query($urlQueryData);
        $fromSign = filter_input(INPUT_GET, "sign");
        $sign = md5($paramStr.config('constants.PRIVATE_KEY'));

        if($fromSign === $sign){
            $ret = "SUCCESS";
        }else{
            $ret = "ERROR";
        }

        Log::info("debug", $request->all());
        Log::info("debug", array_merge($urlQueryData, array("sign" => $fromSign)));

        return response($ret)
            ->header('Content-Type', "text/html; charset=utf-8");
    }

    //  Oppo渠道比较特殊，需要做中转
    public function oppoNotify(Request $request) {
        Log::info("debug", $request->all());
    }
}
