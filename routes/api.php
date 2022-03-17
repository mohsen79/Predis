<?php

use App\Http\Controllers\Api\IndexController;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::controller(IndexController::class)->group(function () {
    Route::get('products', 'getProducts');
    Route::post('products', 'storeProduct');
    Route::post('tags', 'addTag');
    Route::post('product/{product}/tag', 'productsTags');
    Route::post('tag/products/{product}', 'addProductsToTags');
});
