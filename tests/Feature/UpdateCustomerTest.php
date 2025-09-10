<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use App\Models\{Customer, Role, User};
use Tests\TestCase;

class UpdateCustomerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Customer not found
     */
    public function test_customer_is_not_found()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $customer = Customer::factory()
                            ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->putJson(route('customers.update', [
            'customer' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_updating_customer()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $customer = Customer::factory()
                            ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->putJson(route('customers.update', [
            'customer' => $customer
        ]), [
            'first_name' => null
        ]);

        $response->assertInvalid(['first_name']);
        $response->assertUnprocessable();
    }

    /**
     * Customer is successfully updated
     */
    public function test_customer_is_successfully_updated()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $oldFirstName = 'Eric';
        $newFirstName = 'Cross';
        $oldLastName = 'Blane';
        $newLastName = 'Dante';
        $customer = Customer::factory()
                            ->create([
                                'first_name' => $oldFirstName,
                                'last_name' => $oldLastName
                            ]);

        Sanctum::actingAs($user, ['*']);
        $response = $this->putJson(route('customers.update', [
            'customer' => $customer
        ]), [
            'first_name' => $newFirstName,
            'last_name' => $newLastName
        ]);
        $customer->refresh();

        $response->assertValid();
        $response->assertOk();
        $this->assertSame($newFirstName, $customer->first_name);
        $this->assertSame($newLastName, $customer->last_name);
    }

}
