<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use App\Jobs\Interswitch\SendSms;
use Tests\TestCase;

class InterswitchSmsTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Interswitch SMS request is being sent
     */
    public function test_interswitch_sms_request_is_being_sent()
    {
        Http::fake([
            config('services.interswitch.oauth_token_url') => Http::response([
                'access_token' => 'access token'
            ], 200, [
                'Content-Type' => 'application/json'
            ]),

            config('services.interswitch.base_url').'sms' => Http::response([
                'responseCode' => '00',
                'responseMessage' => 'Successful'
            ], 200, [
                'Content-Type' => 'application/json'
            ])
        ]);
        
        (new SendSms(
            'access token',
            $this->faker->sentence(),
            $this->faker->phoneNumber(),
            $this->faker->randomDigitNotZero()
        ))->handle();

        Http::assertSent(fn(Request $request) => $request->url() === config('services.interswitch.base_url').'sms');
    }

}
