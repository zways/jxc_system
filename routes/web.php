<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app');
});

// 为了支持Vue Router的History模式，我们需要添加一个通配符路由
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
