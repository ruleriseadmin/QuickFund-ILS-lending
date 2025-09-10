<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\{Role, Whitelist, User};

class DeleteWhitelistTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Whitelist not found
     */
    public function test_whitelist_is_not_found()
    {
        $whitelist = Whitelist::factory()->create();
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->deleteJson(route('whitelists.destroy', [
            'customerId' => 'non-existent-id'
        ]));

        $response->assertNotFound();
    }

    /**
     * Whitelist was successfully deleted
     */
    public function test_whitelist_is_successfully_deleted()
    {
        $whitelist = Whitelist::factory()->create();
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->deleteJson(route('whitelists.destroy', [
            'customerId' => $whitelist->phone_number
        ]));

        $response->assertOk();
        $this->assertModelMissing($whitelist);
    }
}
