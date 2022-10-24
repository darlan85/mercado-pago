<?php

require_once './../vendor/autoload.php'; // You have to require the library from your Composer vendor folder

MercadoPago\SDK::setAccessToken("APP_USR-2062059069607063-031223-94c2136699928b59b21d2b4df85e4f94-325787187"); // Either Production or SandBox AccessToken

$payment = new MercadoPago\Payment();
$payment->transaction_amount = 10;
$payment->description = "Título do produto";
$payment->payment_method_id = "pix";
$payment->notification_url = "https://webhook.site/6f6e7bb1-9d58-4a62-8355-c230fb3d2184";
$payment->payer = array(
    "email" => "test@test.com",
    "first_name" => "Test",
    "last_name" => "User",
    "identification" => array(
        "type" => "CPF",
        "number" => "19119119100"
     ),
    "address"=>  array(
        "zip_code" => "06233200",
        "street_name" => "Av. das Nações Unidas",
        "street_number" => "3003",
        "neighborhood" => "Bonfim",
        "city" => "Osasco",
        "federal_unit" => "SP"
     )
  );

$payment->save();

// header('Content-type: application/json');

print_r($payment);

