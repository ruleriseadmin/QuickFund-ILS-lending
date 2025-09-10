<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\{Customer, Role, User};

class GetACustomerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Customer not found
     */
    public function test_customer_is_not_found()
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
        $response = $this->getJson(route('customers.show', [
            'customer' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Customer was successfully fetched
     */
    public function test_customer_is_successfully_fetched()
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
        $response = $this->getJson(route('customers.show', [
            'customer' => $customer
        ]));

        $response->assertOk();
    }
}
