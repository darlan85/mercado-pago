<?php

use Illuminate\Http\Request;
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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('pix', 'PaymentController@storePaymentPix');
Route::post('webhook/px', 'PaymentController@callbackPayment');
Route::post('check-payment', 'PaymentController@checkPayment');
Route::put('cancel-payment', 'PaymentController@cancelPayment');
Route::post('refund-payment', 'PaymentController@refundPayment');
Route::post('index', 'PaymentController@index');
Route::post('show', 'PaymentController@showPayments');
