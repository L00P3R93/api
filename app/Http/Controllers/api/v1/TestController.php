<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Mpesa\Init as Mpesa;
use App\Mpesa\Auth;
use App\Mpesa\Core;

class TestController extends Controller {
    public function index() {
        $key = config('mpesa.apps.b2c.consumer_key');
        $secret = config('mpesa.apps.b2c.consumer_secret');

        // Generate access token
        $credentials = base64_encode("$key:$secret");
        $ch = curl_init("https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic '.$credentials]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);

        $response = json_decode($result);
        if (!isset($response->access_token)) {
            die('Failed to retrieve access token: ' . $result);
        }
        $accessToken = $response->access_token;


        $data = [
            'InitiatorName' => config('mpesa.b2c.initiator_name'),
            'SecurityCredential' => Core::computeSecurityCredentials(config('mpesa.b2c.security_credential'), true),
            'CommandID' => config('mpesa.b2c.default_command_id'),
            'Amount' => 0,
            'PartyA' => config('mpesa.b2c.short_code'),
            'PartyB' => 254795702455,
            'Remarks' => 'Business Payment',
            'QueueTimeOutURL' => config('mpesa.b2c.timeout_url'),
            'ResultURL' => config('mpesa.b2c.result_url'),
            "Occasion" => "",
        ];
        $ch = curl_init("https://api.safaricom.co.ke/mpesa/b2c/v1/paymentrequest");

        // Set the HTTP headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer '.$accessToken,
            'Content-Type: application/json'
        ]);

        // Set the POST request options
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);
        curl_close($ch);

        // Output the response
        echo $response;
    }
}
