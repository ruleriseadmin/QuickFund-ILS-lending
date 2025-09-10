<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use App\Models\{User, Customer, Role};
use Tests\TestCase;

class CreateBlacklistTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_creating_blacklist()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('blacklists.store'), [
            'phone_number' => null
        ]);

        $response->assertInvalid(['phone_number']);
        $response->assertUnprocessable();
    }

    /**
     * Blacklist is successfully created
     */
    public function test_blacklist_is_successfully_created()
    {
        $customer = Customer::factory()->create();

        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->postJson(route('blacklists.store'), [
            'phone_number' => $customer->phone_number,
        ]);

        $response->assertValid();
        $response->assertCreated();
        $this->assertDatabaseCount('blacklists', 1);
    }

}
