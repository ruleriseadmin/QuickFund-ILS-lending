<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\{Transaction, User, Role};

class GetATransactionTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Transaction not found
     */
    public function test_transaction_is_not_found()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();
        $transaction = Transaction::factory()->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('transactions.show', [
            'transaction' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Transaction was successfully fetched
     */
    public function test_transaction_is_successfully_fetched()
    {
        $transaction = Transaction::factory()->create();
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('transactions.show', [
            'transaction' => $transaction
        ]));

        $response->assertOk();
    }
}
