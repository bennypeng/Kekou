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
 * 易接相关接口
 */
Route::post('/1sdk/login', 'EsdkController@login');
Route::get('/1sdk/notify', 'EsdkController@notify');
