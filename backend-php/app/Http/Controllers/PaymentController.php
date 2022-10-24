<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use MercadoPago\SDK;
use Illuminate\Support\Facades\Validator;
use Ramsey\Uuid\Uuid;
use App\Payment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class PaymentController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        //
    }

    public function index(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 403);
        }

        $payment_id = $request->payment_id;

        if (!$payment_id) {
            return response()->json([
                'message' => 'Payment id not found or inexist.'
            ], 403);
        }

        $token = getenv('TOKEN');

        SDK::setAccessToken($token);

        $payment = new \MercadoPago\Payment();

        $data = $payment->find_by_id($payment_id);

        if (!$data || $data == null) {
            return response()->json([
                'message' => 'Payment not found or inexist.'
            ], 403);
        }

        return response()->json([
            'payment' => $data->toArray()
        ], 200);
    }

    public function showPayments(Request $request)
    {
        $item = Payment::orderByDesc('id')->get();

        if (!$item || $item == null) {
            return response()->json([
                'message' => 'No items to show.'
            ], 403);
        }

        return response()->json([
            'items' => $item,
        ], 200);
    }

    public function storePaymentPix(Request $request)
    {
        //Instalação do Mercado pago
        //composer require "mercadopago/dx-php:2.5.1"

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric',
            'description' => 'string|max:255|nullable',
            'email' => 'string|max:255|nullable',
            'first_name' => 'string|max:255|nullable',
            'first_number' => 'string|max:255|nullable',
            'cpf' => 'required|numeric|max:99999999999',
            'zip_code' => 'numeric|max:99999999|nullable',
            'street_name' => 'string|max:255|nullable',
            'street_number' => 'numeric|max:99999|nullable',
            'neighborhood' => 'string|max:255|nullable',
            'city' => 'string|max:255|nullable',
            'federal_unit' => 'string|max:2|min:2|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 403);
        }

        $token = getenv('TOKEN');

        SDK::setAccessToken($token);

        $external_reference = Uuid::uuid1();

        $payment = new \MercadoPago\Payment();

        $payment->transaction_amount = $request->amount;
        $payment->description = $request->description;
        $payment->external_reference = $external_reference;
        $payment->payment_method_id = "pix";
        $payment->notification_url = getenv('WEBHOOK');
        $payment->payer = array(
            "email" => $request->email,
            "first_name" => $request->first_name,
            "last_name" => $request->last_name,
            "identification" => array(
                "type" => "CPF",
                "number" => $request->cpf
            ),
            "address" =>  array(
                "zip_code" => $request->zip_code,
                "street_name" => $request->street_name,
                "street_number" => $request->street_number,
                "neighborhood" => $request->neighborhood,
                "city" => $request->city,
                "federal_unit" => $request->federal_unit
            )
        );

        $payment->save();

        if ($payment->error != null && isset($payment->error->message)) {

            return response()->json([
                'message' => $payment->error->message
            ], 403);
        }

        if ($payment->error != null && !isset($payment->error->message)) {

            return response()->json([
                'message' => 'Erro não informado'
            ], 403);
        }

        DB::beginTransaction();
        try {

            $db_payment = Payment::create([
                "external_reference" => $payment->external_reference,
                "status" => $payment->status,
                "api_id" => $payment->id
            ]);

            $db_payment->save();

            DB::commit();

            return response()->json([
                'message' => 'Pagamento criado com sucesso.',
                'qrcodebase64' => "data:image/png;base64," . $payment->point_of_interaction->transaction_data->qr_code_base64,
                'qrcode' => $payment->point_of_interaction->transaction_data->qr_code,
                'id' => $db_payment->id
            ], 200);

            return true;
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Não foi possível salvar no Banco de Dados'
            ], 403);
        }
    }

    public function callbackPayment(Request $request)
    {

        $ref = @$request->query('id') ?? false;
        $topic = @$request->query('topic') ?? false;

        Log::debug($request->query());

        if ($ref && $topic == 'payment') {

            $token = getenv('TOKEN');

            SDK::setAccessToken($token);

            $payment = new \MercadoPago\Payment();

            $data = $payment->find_by_id($ref);

            if (!$data || $data == null) {
                return response()->json([
                    'message' => 'Payment not found or inexist.'
                ], 403);
            }

            $external_reference = $data->external_reference;

            $item = Payment::where('external_reference', $external_reference)->first();

            if (!$item || $item == null) {
                return response()->json([
                    'message' => 'Payment not found by external reference.'
                ], 403);
            }

            if ($data->status == 'pending') {

                return false;
            }

            DB::beginTransaction();

            try {

                $item->update([
                    'status' => $data->status,
                    'paid_at' => Carbon::now()
                ]);

                DB::commit();

                return response()->json([
                    'message' => 'pagamento: ' . $item->id . ' atualizado com sucesso.',
                ], 200);

                return true;
            } catch (\Throwable $th) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Não foi possível atualizar no Banco de Dados'
                ], 403);
            }
        }
    }

    public function checkPayment(Request $request)
    {

        $id = $request->payment_ref;

        $payment = Payment::find($id);

        if ($payment && $payment->status == 'approved') {

            return response()->json([
                'status' => true
            ], 200);
        } else {
            return response()->json([
                'status' => false
            ], 403);
        }
    }

    public function cancelPayment(Request $request)
    {

        $ref = $request->payment_ref;

        $validator = Validator::make($request->all(), [
            'payment_ref' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 403);
        }

        if (!$ref || $ref == null) {
            return response()->json([
                'message' => 'Payment not found or inexist.'
            ], 403);
        }

        $item = Payment::where('id', $ref)->first();

        if (!$item || $item == null) {
            return response()->json([
                'message' => 'Payment not found by id.'
            ], 403);
        }

        if ($item->status == 'cancelled') {
            return response()->json([
                'message' => 'Payment already cancelled.'
            ], 403);
        }

        $token = getenv('TOKEN');

        SDK::setAccessToken($token);

        $payment = new \MercadoPago\Payment();

        $data = $payment->find_by_id($item->api_id);

        if (!$data || $data == null) {
            return response()->json([
                'message' => 'Payment not found or inexist.'
            ], 403);
        }

        if ($data->status != 'pending' && $data->status != 'in proccess') {

            return response()->json([
                'message' => 'Payment already processed.'
            ], 403);
        }

        $data->status = "cancelled";
        $data->update();

        DB::beginTransaction();
        try {

            $item->update([
                'status' => $data->status,
                'paid_at' => Carbon::now()
            ]);

            DB::commit();

            return response()->json([
                'message' => 'pagamento cancelado com sucesso!',
            ], 200);

            return true;
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Não foi possível realizar esta operação.'
            ], 403);
        }
    }

    public function refundPayment(Request $request)
    {
        $ref = $request->payment_ref;

        $validator = Validator::make($request->all(), [
            'payment_ref' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 403);
        }

        if (!$ref || $ref == null) {
            return response()->json([
                'message' => 'Payment not found or inexist.'
            ], 403);
        }

        $item = Payment::where('id', $ref)->first();

        if (!$item || $item == null) {
            return response()->json([
                'message' => 'Payment not found by external reference.'
            ], 403);
        }

        $token = getenv('TOKEN');

        $tk = SDK::setAccessToken($token);

        $payment = new \MercadoPago\Payment();

        $data = $payment->find_by_id($item->api_id);

        if (!$data || $data == null) {
            return response()->json([
                'message' => 'Payment not found or inexist.'
            ], 403);
        }

        $di = $data->date_created;
        $df = now();
        $diff = $df->diff($di)->format('%d');

        if($diff > 180){
            return response()->json([
                'message' => 'Time to refund expired.'
            ], 403);
        }

        $url = "https://api.mercadopago.com/v1/payments/".$data->id."/refunds";
        $tk = "Bearer ".$token;

        $client = new Client();

        try {
            $response = $client->post($url, [
                'headers' => [
                    'Authorization' => $tk,
                    'Content-Type' => 'application/json'
                ]
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th
            ], 403);
        }

        DB::beginTransaction();
        try {

            $item->update([
                'status' => 'refunded',
                'paid_at' => Carbon::now()
            ]);

            DB::commit();

            return response()->json([
                'message' => 'reembolso gerado com sucesso.',
            ], 200);

            return true;
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Não foi possível atualizar no Banco de Dados'
            ], 403);
        }
    }
}
