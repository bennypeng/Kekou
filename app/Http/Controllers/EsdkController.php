<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        curl_close($ch);

        if($clientHttpCode != 200 || $result == null || (is_array($result) && count($result) == 0)){
            $ret = "ERROR";
        }else{
            if(intval($result) == config('constants.LOGIN_RESULT_SUCCESS')){
                $ret = "SUCCESS";
            }else{
                $ret = "ERROR";
            }
        }

        //  需要把原始的SQL查询方式改写为model
        //  数据入库及查询没有发货的订单
        $list = [];
        if ($ret == "SUCCESS") {
            $row = DB::table('users')
                ->where('uin', '=', $urlQueryData['uin'])
                ->first();
            if (!$row) {
                DB::table('users')
                    ->insertGetId([
                        'uin' => $urlQueryData['uin'], 'sdkid' => $urlQueryData['sdk'], 'appid' => $urlQueryData['app']
                    ]);
            }
            if ($row) {
                $orders = DB::table('orders')
                    ->whereRaw("uin=? and status=?", [$row->uin, '0'])->get();
                foreach($orders as $k => $v) {
                    $list[$v->tcd] = $v->extra;
                }
            }
        }

        Log::info("debug", $urlQueryData);

        return response()
            ->json([
                'ret' => $ret,
                'list' => $list
            ]);
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
        $paramStr = urldecode(http_build_query($urlQueryData));
        $fromSign = filter_input(INPUT_GET, "sign");
        $sign = md5($paramStr.config('constants.PRIVATE_KEY'));

        if($fromSign === $sign){
            $ret = "SUCCESS";
        }else{
            $ret = "ERROR";
        }

        Log::info("debug", array_merge($urlQueryData, array("sign" => $fromSign, "ret" => $ret)));

        //  数据入库
        if ($ret == "SUCCESS") {
            $row = DB::table('orders')
                ->where('ssid', '=', $urlQueryData['ssid'], 'and')
                ->where('tcd', '=', $urlQueryData['tcd'])
                ->first();

            if (!$row) {
                DB::insert('INSERT INTO orders(uin, appid, sdkid, extra, fee, ssid, tcd, ver, st, ct, pt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $urlQueryData['uid'], $urlQueryData['app'], $urlQueryData['sdk'], $urlQueryData['cbi'], $urlQueryData['fee'], $urlQueryData['ssid'],
                        $urlQueryData['tcd'], $urlQueryData['ver'], $urlQueryData['st'], $urlQueryData['ct'], $urlQueryData['pt']
                    ]
                );
            }
        }

        return response($ret)
            ->header('Content-Type', "text/html; charset=utf-8");
    }


    //  客户端请求发货(只能发一次)
    public function clientNotify(Request $request) {
        $uin = $request->get('uin');
        $ordersStr = $request->get('tcds');
        if ($uin && $ordersStr) {
            $ordersArr = explode('_', $ordersStr);
            DB::table('orders')->whereIn('tcd', $ordersArr)->update(['status' => '1']);
            $ret = "SUCCESS";
        } else {
            $ret = "ERROR";
        }

        Log::info("debug-clientNotify", $request->all());

        return response($ret)
            ->header('Content-Type', "text/html; charset=utf-8");
    }

}
