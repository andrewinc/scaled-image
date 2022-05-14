<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ScaledImage404Controller;

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


//список
Route::get('/', function () {
    //return view('welcome');
    $files=[]; //['0.jpg', '1.jpg', '2.jpg',...];
    foreach(scandir(ScaledImage404Controller::fsPath()) as $f) {
        if (('.' != $f{0}) && (!is_dir(ScaledImage404Controller::fsPath($f)))) $files[] = $f;
    }
    return view('index', ['files'=>$files]);
});

//карточка
Route::get('/card/{file}', function ($file) {
    return view('card', ['file'=>$file]);
});
