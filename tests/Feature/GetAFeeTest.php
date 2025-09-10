<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\{Fee, Role, User};

class GetAFeeTest extends TestCase
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
        $response = $this->getJson(route('fees.show', [
            'fee' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Fee was successfully fetched
     */
    public function test_fee_is_successfully_fetched()
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
        $response = $this->getJson(route('fees.show', [
            'fee' => $fee
        ]));

        $response->assertOk();
    }
}
