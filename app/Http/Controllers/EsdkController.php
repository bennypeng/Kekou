<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

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
        $flag = false;
        $list = [];
        if ($ret == "SUCCESS") {
            Redis::Select(config('constants.USERS_DB_INDEX'));
            $key = $urlQueryData['uin']."_".$urlQueryData['sdk'];
            if (!Redis::Exists($key)) {
                $row = DB::table('users')
                    ->where('uin', '=', $urlQueryData['uin'])
                    ->first();
                $id = $row->id;
                if (!$row) {
                    $id = DB::table('users')
                        ->insertGetId([
                            'uin' => $urlQueryData['uin'], 'sdkid' => $urlQueryData['sdk'], 'appid' => $urlQueryData['app']
                        ]);
                }
                Redis::Hmset($key, [
                    "id"    => $id,
                    "uin"   => $urlQueryData['uin'],
                    "sdkid" => $urlQueryData['sdk'],
                    "appid" => $urlQueryData['app'],
                ]);
                Redis::Expire($key, 86400*7);
                $flag = true;
            } else {
                $flag = true;
            }

            //  查询未发货订单
            if ($flag) {
                $orderKey = $urlQueryData['uin'];
                Redis::Select(config('constants.ORDERS_DB_INDEX'));
                if (Redis::Exists($orderKey)) {
                    $orderIds = Redis::Hkeys($orderKey);
                    foreach ($orderIds as $v) {
                        $orderInfoKey = $urlQueryData['uin']."_".$v;
                        list($tcd, $extra) = Redis::Hmget($orderInfoKey, ['tcd', 'extra']);
                        $list[] = array(
                            'orderId' => $tcd,
                            'productId' => $extra,
                        );
                    }
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

        $ret = $fromSign === $sign ? "SUCCESS" : "ERROR";

        Log::info("debug", array_merge($urlQueryData, array("sign" => $fromSign, "ret" => $ret)));

        //  数据入库
        if ($ret == "SUCCESS") {
            $key = $urlQueryData['uid']."_".$urlQueryData['tcd'];
            Redis::Select(config('constants.ORDERS_DB_INDEX'));
            if (!Redis::Exists($key)) {
                DB::insert('INSERT INTO orders(uin, appid, sdkid, extra, fee, ssid, tcd, ver, st, ct, pt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $urlQueryData['uid'], $urlQueryData['app'], $urlQueryData['sdk'], $urlQueryData['cbi'], $urlQueryData['fee'], $urlQueryData['ssid'],
                        $urlQueryData['tcd'], $urlQueryData['ver'], $urlQueryData['st'], $urlQueryData['ct'], $urlQueryData['pt']
                    ]
                );
                //  缓存订单详情
                Redis::Hmset($key, [
                    "uin"    => $urlQueryData['uid'],
                    "appid"   => $urlQueryData['app'],
                    "sdkid" => $urlQueryData['sdk'],
                    "extra" => $urlQueryData['cbi'],
                    "fee"    => $urlQueryData['fee'],
                    "ssid"   => $urlQueryData['ssid'],
                    "tcd" => $urlQueryData['tcd'],
                    "ver" => $urlQueryData['ver'],
                    "st"   => $urlQueryData['st'],
                    "ct" => $urlQueryData['ct'],
                    "pt" => $urlQueryData['pt'],
                ]);
                Redis::Expire($key, 86400*7);

                //  缓存用户未发货订单号
                Redis::Hset($urlQueryData['uid'], $urlQueryData['tcd'], 0);
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

            Redis::Select(config('constants.ORDERS_DB_INDEX'));
            $orderKey = $uin;
            foreach($ordersArr as $v) {
                Redis::Hdel($orderKey, $v);
            }

            $ret = "SUCCESS";
        } else {
            $ret = "ERROR";
        }

        Log::info("debug-clientNotify", $request->all());

        return response($ret)
            ->header('Content-Type', "text/html; charset=utf-8");
    }


}
