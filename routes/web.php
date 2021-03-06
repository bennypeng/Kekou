<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

/**
 * 日志接口
 */
Route::get('logs', '\Rap2hpoutre\LaravelLogViewer\LogViewerController@index');

/**
 * 易接相关接口
 */
Route::post('/1sdk/login', 'EsdkController@login');
Route::get('/1sdk/notify', 'EsdkController@notify');
Route::post('/1sdk/client/notify', 'EsdkController@clientNotify');

/**
 * 用户信息相关接口
 */
Route::post('/users/coin', 'UsersController@changeCoin');

//  临时接口，测试用
Route::get('/1sdk/tools', 'EsdkController@testTools');
