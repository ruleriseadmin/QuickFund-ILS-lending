<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Models\Customer;
use Illuminate\Http\Request;
use App\Services\CreditBureau\Crc;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\CreditBureau\FirstCentral;

class TestController extends Controller
{
    /**
     * 
     */
    public function index(Request $request)
    {

        $response = [
            'success' => false
        ];

        $loginResponse = Http::post(config('services.first_central.reporting_base_url') . '/login', [
            'username' => config('services.first_central.reporting_username'),
            'password' => config('services.first_central.reporting_password'),
        ]);

        if (!$loginResponse->ok()) {
            $response['success'] = false;
            $response['message'] = "FirstCentral login failed";
            $response['context'] = $loginResponse->body();
            return response()->json($response);
        }

        $token = $loginResponse->json('0.DataTicket');
        if (!$token) {
            $response['success'] = false;
            $response['message'] = "FirstCentral token missing";
            $response['context'] = $loginResponse->body();
            return response()->json($response);
        }


        $customers = Customer::whereHas('loans')->take(3)->get();
        $firstCentral = new FirstCentral();
        $crcService = new Crc();
        $results = [];


        foreach ($customers as $key => $customer) {
            $results[] = $firstCentral->reportCustomerLoans($customer, $token);
            $results[] = $crcService->reportCustomerLoans($customer);
        }

        $response['results'] = $results;
        $response['success'] = true;

        return response()->json($response);

    }


}
