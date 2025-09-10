<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use App\Models\{Fee, User, Role};
use Tests\TestCase;

class UpdateFeeTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Fee not found
     */
    public function test_fee_is_not_found()
    {
        $fee = Fee::factory()->create();
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->putJson(route('fees.update', [
            'fee' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_updating_fee()
    {
        $fee = Fee::factory()->create();
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->putJson(route('fees.update', [
            'fee' => $fee
        ]), [
            'amount' => null
        ]);

        $response->assertInvalid(['amount']);
        $response->assertUnprocessable();
    }

    /**
     * Fee is successfully updated
     */
    public function test_fee_is_successfully_updated()
    {
        $fee = Fee::factory()->create([
            'amount' => 10000
        ]);
        $oldAmount = $fee->amount;
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->putJson(route('fees.update', [
            'fee' => $fee
        ]), [
            'name' => $this->faker->name,
            'amount' => 12000
        ]);
        $fee->refresh();

        $response->assertValid();
        $response->assertOk();
        $this->assertNotEquals($oldAmount, $fee->amount);
    }

}
