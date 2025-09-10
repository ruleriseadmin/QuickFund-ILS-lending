<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use App\Models\{User, Role};
use Tests\TestCase;

class GetDueInDaysTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_fetching_due_in_days_loans()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('loan-offers.due-in-days'));

        $response->assertInvalid(['days']);
        $response->assertUnprocessable();
    }

    /**
     * Data was successfully fetched
     */
    public function test_data_is_successfully_fetched()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('loan-offers.due-in-days', [
            'days' => $this->faker->numberBetween(1, 14)
        ]));

        $response->assertOk();
    }
}
