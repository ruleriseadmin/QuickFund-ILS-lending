<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Maatwebsite\Excel\Facades\Excel;
use Laravel\Sanctum\Sanctum;
use App\Models\{User, Role};
use Tests\TestCase;

class GenerateCrcReportTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /**
     * Validation errors
     */
    public function test_validation_errors_occur_while_generating_report()
    {
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('crcs.report', [
            'from_date' => null
        ]));

        $response->assertInvalid(['from_date']);
        $response->assertUnprocessable();
    }

    /**
     * Report successfully generate
     */
    public function test_data_is_successfully_fetched()
    {
        Excel::fake();
        $reportDate = now()->format('Y-m-d');
        $role = Role::factory()
                    ->administrator()
                    ->create();
        $user = User::factory()
                    ->application()
                    ->administrators()
                    ->create();

        Sanctum::actingAs($user, ['*']);
        $response = $this->getJson(route('crcs.report', [
            'from_date' => $reportDate,
            'to_date' => $reportDate
        ]));

        $response->assertOk();
        Excel::assertDownloaded("crc-report-for-{$reportDate}.xlsx");
    }

}
