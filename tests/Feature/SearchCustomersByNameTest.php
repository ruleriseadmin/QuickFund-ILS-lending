<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use App\Models\{User, Role, Customer};
use Tests\TestCase;

class SearchCustomersByNameTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_fetching_customers_by_name()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('customers.search-by-name'));
        
        $response->assertUnprocessable();
    }

    /**
     * Customers successfully fetched
     */
    public function test_customers_were_successfully_fetched()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('customers.search-by-name', [
            'q' => 'oluyemi'
        ]));

        $response->assertOk();
    }

}
