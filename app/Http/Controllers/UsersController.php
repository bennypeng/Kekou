<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class UsersController extends Controller
{
    public function __construct(){

    }

    //  修改金币接口
    public function changeCoin(Request $request) {
        $uin = $request->get('uin');
        $gold = $request->get('gold');

        $urlQueryData = array(
            'gold' => $gold,
            'src' => $request->get('src'),
            'ts' => $request->get('ts'),
            'uin' => $uin
        );
        $paramStr = urldecode(http_build_query($urlQueryData));
        $fromSign = $request->get('sign');
        $sign = md5($paramStr."#".config('constants.GAME_PRIVATE_KEY'));

        $ret = $fromSign === $sign ? "SUCCESS" : "ERROR";

        Log::info("debug", array_merge($urlQueryData, array("sign" => $fromSign, "ret" => $ret)));

        $userInfo = DB::table('users')
            ->where('uin', $uin)
            ->get();

        $userCoin = isset($userInfo[0]) ? $userInfo[0]->coin : 0;

        //  数据入库
        if ($ret == "SUCCESS") {
            $key = $sign;
            Redis::Select(config('constants.CHEAT_COIN_DB_INDEX'));
            if (!Redis::Exists($key)) {
                $userCoin = $userCoin + $gold < 0 ? 0 : $userCoin + $gold;

                DB::table('users')->where('uin', $uin)->update(['coin' => $userCoin]);

                //  缓存订单详情
                Redis::set($key, 1);
                Redis::Expire($key, 86400*7);

                //  修改用户缓存
                //$userkey = $uin."_".$urlQueryData['sdk'];
                $userkey = $uin;
                Redis::Select(config('constants.USERS_DB_INDEX'));
                Redis::Hmset($userkey, [
                    "coin"  => $userCoin
                ]);

                $ret = "SUCCESS";
            } else {
                //  重复请求
                $ret = "ERROR";
            }
        }

        return response()
            ->json([
                'ret' => $ret,
                'totalGold' => $userCoin
            ]);
    }

}

