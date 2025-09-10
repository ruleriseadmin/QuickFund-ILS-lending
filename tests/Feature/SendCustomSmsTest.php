<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\{Http, Bus};
use Laravel\Sanctum\Sanctum;
use App\Models\{Customer, User, LoanOffer, Role, Loan, VirtualAccount};
use App\Jobs\Interswitch\SendSms;
use Tests\TestCase;

class SendCustomSmsTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_sending_custom_sms()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('sms'), [
            'message' => null
        ]);

        $response->assertInvalid(['message']);
        $response->assertUnprocessable();
    }

    /**
     * Custom SMS was successfully sent
     */
    public function test_sms_was_successfully_sent()
    {
        $this->withoutExceptionHandling();
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),
        ]);
        Bus::fake();
        $customer = Customer::factory()->create();
        $loanOffer = LoanOffer::factory()
                            ->for($customer)
                            ->create([
                                'status' => LoanOffer::OPEN
                            ]);
        $loan = Loan::factory()
                    ->for($loanOffer)
                    ->create();
        $virtualAccount = VirtualAccount::factory()
                                        ->for($customer)
                                        ->create();

        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        
        Sanctum::actingAs($user, ['*']);                    
        $response = $this->postJson(route('sms'), [
            'message' => $this->faker->sentence(),
            'customer_ids' => [$customer->id]
        ]);

        $response->assertOk();
        Bus::assertDispatched(SendSms::class);
    }

}
