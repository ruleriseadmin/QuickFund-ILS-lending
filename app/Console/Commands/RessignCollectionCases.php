<?php

namespace App\Console\Commands;

use Illuminate\Support\Carbon;
use Illuminate\Console\Command;
use App\Models\{CollectionCase, User};

class RessignCollectionCases extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'collection-cases:reassign';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reassign collection cases to collectors.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        /**
         * Get the "OPEN" collection cases that were assigned to users and have been assigned to them at least
         * 7 days
         */
        $collectionCases = CollectionCase::where('status', CollectionCase::OPEN)
                                        ->whereDate('assigned_at', '<=', Carbon::parse(now()->timezone(config('quickfund.date_query_timezone'))->subDays(7)))
                                        ->get();

        // Get the collectors of the application
        $collectors = User::whereHas('role', fn($query) => $query->where('permissions', 'LIKE', '%collection-cases%'))
                        ->get();

        foreach ($collectionCases as $collectionCase) {
            // Check if the application has collectors
            if ($collectors->isNotEmpty()) {
                // Get the index of the user in the collection
                $assignedCollectorIndex = $collectors->search(fn($collector) => $collector->id === $collectionCase->user_id);

                // Check if the collector is still a registered collector in the system
                if (is_int($assignedCollectorIndex)) {
                    // We assign the case to the next collector
                    $newCollectorIndex = $assignedCollectorIndex + 1;

                    // Assign a collector to the overdue loan
                    $newCollector = $collectors->slice($newCollectorIndex, 1)->first();

                    /**
                     * We check if a collector with the index exists, else we start assign to the first collector
                     */
                    if (isset($newCollector)) {
                        // New collector exists in the collection. We check if the new collector exists in the database
                        if ($newCollector->exists) {
                            $collectionCase->forceFill([
                                'user_id' => $newCollector->id,
                                'assigned_at' => now()
                            ])->save();
                        }
                    } else {
                        // We assign it to the first collector
                        $firstCollector = $collectors->first();

                        /**
                         * We still check if the collector exists just in case
                         */
                        if (isset($firstCollector)) {
                            // We still check if the first collector exists in the database
                            if ($firstCollector->exists) {
                                $collectionCase->forceFill([
                                    'user_id' => $firstCollector->id,
                                    'assigned_at' => now()
                                ])->save();
                            }
                        }
                    }
                }
            }
        }
    }
}
