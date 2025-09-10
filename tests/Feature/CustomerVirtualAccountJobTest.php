<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use App\Models\Customer;
use App\Jobs\CustomerVirtualAccount;
use Tests\TestCase;

class CustomerVirtualAccountJobTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Virtual account successfully fetched or created
     */
    public function test_customer_virtual_account_was_successfully_fetched()
    {
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.base_url').'payments/customer-virtual-account' => Http::response([
                'payableCode' => 'VIRTUAL_ACCOUNTMX'.rand(1000000, 999999999999),
                'accountName' => $this->faker->name(),
                'accountNumber' => '1100000000',
                'bankName' => 'Wema Bank',
                'bankCode' => 'WEMA'
            ], 200, [
                'Content-Type' => 'application/json'
            ])
        ]);

        $customer = Customer::factory()->create();

        (new CustomerVirtualAccount($customer))->handle();
        
        $this->assertDatabaseHas('virtual_accounts', [
            'customer_id' => $customer->id
        ]);
    }

}
