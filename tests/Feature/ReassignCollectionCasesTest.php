<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\{CollectionCase, LoanOffer, Role, User};
use Tests\TestCase;

class ReassignCollectionCasesTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Collection case was reassigned
     */
    public function test_collection_case_was_successfully_reassigned()
    {
        $collectorRole = Role::factory()
                            ->collector()
                            ->create();
        $user1 = User::factory()
                    ->for($collectorRole)
                    ->create();
        $user2 = User::factory()
                    ->for($collectorRole)
                    ->create();
        $loanOffer1 = LoanOffer::factory()
                            ->create();
        $loanOffer2 = LoanOffer::factory()
                            ->create();
        $collectionCase1 = CollectionCase::factory()
                                        ->for($loanOffer1)
                                        ->create([
                                            'assigned_at' => now()->timezone(config('quickfund.date_query_timezone'))->subDays(7),
                                            'user_id' => $user1->id
                                        ]);
        $collectionCase2 = CollectionCase::factory()
                                        ->for($loanOffer2)
                                        ->create([
                                            'assigned_at' => now()->timezone(config('quickfund.date_query_timezone'))->subDays(7),
                                            'user_id' => $user2->id
                                        ]);

        $this->artisan('collection-cases:reassign');
        $collectionCase1->refresh();
        $collectionCase2->refresh();

        $this->assertSame($user1->id, $collectionCase2->user_id);
        $this->assertSame($user2->id, $collectionCase1->user_id);
    }
}
