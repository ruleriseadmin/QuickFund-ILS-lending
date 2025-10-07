<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AccountResolutionController, ActivityLogController, AuthController, BlacklistController, CollectionCaseController, CollectionRemarkController, CollectorController, CrcController, CrcHistoryController, CreditScoreController, CustomerController, DepartmentController, FeeController, FirstCentralController, FirstCentralHistoryController, LoanController, LoanOfferController, NameEnquiryController, TestController, OfferController, RoleController, SettingController, SmsController, StaffController, TransactionController, WhitelistController};

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
// Tests Routes
Route::get('/test', [TestController::class, 'index']);
// End of Test Routes


/**
 * Beginning of Interswitch Routes
 */
Route::post('/name-enquiry', NameEnquiryController::class);

Route::middleware(['auth.basic', 'interswitch'])
    ->group(function () {
        Route::name('offers')
            ->prefix('/offers')
            ->group(function () {

                Route::get('', [OfferController::class, 'interswitch'])->name('.index'); // Interswitch Route
                Route::post('/accept', [OfferController::class, 'accept'])->name('.accept'); // Interswitch Route
            });


        Route::name('loans')
            ->prefix('/loans')
            ->group(function () {

                Route::get('/status/{loanOfferId}', [LoanController::class, 'status'])->name('.status'); // Interswitch Route
            });


        Route::name('transactions')
            ->prefix('/transactions')
            ->group(function () {

                Route::post('/notification', [TransactionController::class, 'notification'])->name('.notification'); // Interswitch Route
            });
    });
/**
 * End of Interswitch Routes
 */



/**
 * Beginning of Application Routes
 */
Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::middleware([
    'auth:sanctum',
    'application',
    'activity_log'
])
    ->group(function () {

        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('/account-resolution', AccountResolutionController::class)->name('account-resolution');

        Route::put('/change-password', [AuthController::class, 'changePassword'])
            ->name('change-password')
            ->middleware('ability:change-password');

        Route::post('/sms', SmsController::class)
            ->middleware('ability:sms')
            ->name('sms');

        Route::name('offers')
            ->prefix('/offers')
            ->middleware('abilities:offers,fees')
            ->group(function () {

                Route::post('', [OfferController::class, 'store'])->name('.store');
                Route::get('/application', [OfferController::class, 'application'])->name('.application');
                Route::get('/{offer}', [OfferController::class, 'show'])->name('.show');
                Route::put('/{offer}', [OfferController::class, 'update'])->name('.update');
                Route::delete('/{offer}', [OfferController::class, 'destroy'])->name('.destroy');
            });

        Route::name('loan-offers')
            ->prefix('/loan-offers')
            ->middleware('ability:loans')
            ->group(function () {

                Route::get('/search', [LoanOfferController::class, 'search'])
                    ->name('.search')
                    ->middleware('ability:loan-search');

                Route::get('/fbn', [LoanOfferController::class, 'index'])
                    ->name('.index')
                    ->middleware('ability:loan-search');

                Route::get('/due-in-days', [LoanOfferController::class, 'dueInDays'])
                    ->name('.due-in-days');

                Route::get('/{loanOffer}', [LoanOfferController::class, 'show'])
                    ->name('.show');

                Route::post('/{loanOffer}/debit', [LoanOfferController::class, 'debit'])
                    ->name('.debit')
                    ->middleware('ability:loan-debit');

                Route::post('/{loanOffer}/credit', [LoanOfferController::class, 'credit'])
                    ->name('.credit')
                    ->middleware('ability:loan-credit');

                Route::put('/{loanOffer}/status', [LoanOfferController::class, 'status'])
                    ->name('.status')
                    ->middleware('ability:loan-status');

                Route::get('/{loanOffer}/transactions', [LoanOfferController::class, 'transactions'])
                    ->name('.transactions')
                    ->middleware('ability:loan-transactions');

                Route::post('/{loanOffer}/refund', [LoanOfferController::class, 'refund'])
                    ->name('.refund')
                    ->middleware('ability:loan-refund');

                Route::post('/{loanOffer}/sms-choice', [LoanOfferController::class, 'smsChoice'])
                    ->name('.sms-choice')
                    ->middleware('ability:loan-sms-choice');

                Route::post('/{loanOffer}/payment-processing', [LoanOfferController::class, 'paymentProcessing'])
                    ->name('.payment-processing')
                    ->middleware('ability:loan-payment-processing');
            });

        Route::name('transactions')
            ->prefix('/transactions')
            ->middleware('ability:transactions')
            ->group(function () {

                Route::get('', [TransactionController::class, 'index'])
                    ->name('.index');

                Route::get('/successful', [TransactionController::class, 'successful'])
                    ->name('.successful');

                Route::get('/search', [TransactionController::class, 'search'])
                    ->name('.search')
                    ->middleware('ability:transaction-search');

                Route::get('/{transaction}', [TransactionController::class, 'show'])
                    ->name('.show');

                Route::get('/{transaction}/query', [TransactionController::class, 'query'])
                    ->name('.query')
                    ->middleware('ability:transaction-query');
            });

        Route::name('customers')
            ->prefix('/customers')
            ->middleware('ability:customers')
            ->group(function () {

                Route::get('', [CustomerController::class, 'index'])
                    ->name('.index');

                Route::get('/loaned', [CustomerController::class, 'loaned'])
                    ->name('.loaned');

                Route::get('/search', [CustomerController::class, 'search'])
                    ->name('.search');

                Route::get('/search/bvn', [CustomerController::class, 'searchByBvn'])
                    ->name('.search-by-bvn')
                    ->middleware('ability:customer-bvn-search');

                Route::get('/search/name', [CustomerController::class, 'searchByName'])
                    ->name('.search-by-name')
                    ->middleware('ability:customer-name-search');

                Route::get('/{customer}', [CustomerController::class, 'show'])
                    ->name('.show');

                Route::put('/{customer}', [CustomerController::class, 'update'])
                    ->middleware('abilities:collection-cases,super-collector')
                    ->name('.update');

                Route::get('/{customer}/credit-score', [CustomerController::class, 'creditScore'])
                    ->name('.credit-score')
                    ->middleware('ability:customer-credit-score');

                Route::get('/{customer}/credit-score-history', [CustomerController::class, 'creditScoreHistory'])
                    ->name('.credit-score-history')
                    ->middleware('ability:customer-credit-score');

                Route::get('/{customer}/loan-offers', [CustomerController::class, 'loanOffers'])
                    ->name('.loan-offers')
                    ->middleware('ability:customer-loans');

                Route::get('/{customer}/virtual-accounts', [CustomerController::class, 'virtualAccounts'])
                    ->name('.virtual-accounts')
                    ->middleware('ability:customer-virtual-accounts');

                Route::get('/{customer}/crc', [CustomerController::class, 'crc'])
                    ->name('.crc')
                    ->middleware('ability:customer-credit-bureau-data');

                Route::get('/{customer}/first-central', [CustomerController::class, 'firstCentral'])
                    ->name('.first-central')
                    ->middleware('ability:customer-credit-bureau-data');
            });

        Route::name('fees')
            ->prefix('/fees')
            ->middleware('ability:fees')
            ->group(function () {

                Route::get('', [FeeController::class, 'index'])->name('.index');
                Route::post('', [FeeController::class, 'store'])->name('.store');
                Route::get('/{fee}', [FeeController::class, 'show'])->name('.show');
                Route::put('/{fee}', [FeeController::class, 'update'])->name('.update');
                Route::delete('/{fee}', [FeeController::class, 'destroy'])->name('.destroy');
            });

        Route::name('whitelists')
            ->prefix('/whitelists')
            ->middleware('ability:whitelists')
            ->group(function () {

                Route::get('', [WhitelistController::class, 'index'])->name('.index');
                Route::post('', [WhitelistController::class, 'store'])->name('.store');
                Route::get('/{customerId}', [WhitelistController::class, 'show'])->name('.show');
                Route::delete('/{customerId}', [WhitelistController::class, 'destroy'])->name('.destroy');
            });

        Route::name('blacklists')
            ->prefix('/blacklists')
            ->middleware('ability:blacklists')
            ->group(function () {

                Route::get('', [BlacklistController::class, 'index'])->name('.index');
                Route::post('', [BlacklistController::class, 'store'])->name('.store');
                Route::get('/count', [BlacklistController::class, 'count'])->name('.count');
                Route::get('/{customerId}', [BlacklistController::class, 'show'])->name('.show');
                Route::delete('/{customerId}', [BlacklistController::class, 'destroy'])->name('.destroy');
            });

        Route::name('crcs')
            ->prefix('/crcs')
            ->middleware('ability:crcs')
            ->group(function () {

                Route::get('', [CrcController::class, 'index'])->name('.index');

                Route::get('/report', [CrcController::class, 'report'])
                    ->name('.report')
                    ->middleware('ability:crc-reports');

                Route::get('/bureau-check-reports', [CrcController::class, 'bureauCheckReports'])
                    ->name('.bureauCheckReports')
                    ->middleware('ability:crc-reports');

            });

        Route::name('first-centrals')
            ->prefix('/first-centrals')
            ->middleware('ability:first-centrals')
            ->group(function () {

                Route::get('', [FirstCentralController::class, 'index'])->name('.index');

                Route::get('/report', [FirstCentralController::class, 'report'])
                    ->name('.report')
                    ->middleware('ability:first-central-reports');

                Route::get('/bureau-check-reports', [FirstCentralController::class, 'bureauCheckReports'])
                    ->name('.bureauCheckReports')
                    ->middleware('ability:first-central-reports');
            });

        Route::name('crc-histories')
            ->prefix('/crc-histories')
            ->middleware('abilities:crcs,crc-histories')
            ->group(function () {

                Route::get('', [CrcHistoryController::class, 'index'])->name('.index');
            });

        Route::name('first-central-histories')
            ->prefix('/first-central-histories')
            ->middleware('abilities:first-centrals,first-central-histories')
            ->group(function () {

                Route::get('', [FirstCentralHistoryController::class, 'index'])->name('.index');
            });

        Route::name('loans')
            ->prefix('/loans')
            ->middleware('abilities:loans,loan-metrics')
            ->group(function () {

                Route::get('/disbursed-total', [LoanController::class, 'disbursedTotal'])->name('.disbursed-total');
                Route::get('/disbursed-principal', [LoanController::class, 'disbursedPrincipal'])->name('.disbursed-principal');
                Route::get('/disbursed-interest', [LoanController::class, 'disbursedInterest'])->name('.disbursed-interest');
                Route::get('/disbursed-count', [LoanController::class, 'disbursedCount'])->name('.disbursed-count');

                Route::get('/open-total', [LoanController::class, 'openTotal'])->name('.open-total');
                Route::get('/open-principal', [LoanController::class, 'openPrincipal'])->name('.open-principal');
                Route::get('/open-interest', [LoanController::class, 'openInterest'])->name('.open-interest');
                Route::get('/open-count', [LoanController::class, 'openCount'])->name('.open-count');

                Route::get('/closed-total', [LoanController::class, 'closedTotal'])->name('.closed-total');
                Route::get('/closed-principal', [LoanController::class, 'closedPrincipal'])->name('.closed-principal');
                Route::get('/closed-interest', [LoanController::class, 'closedInterest'])->name('.closed-interest');
                Route::get('/closed-count', [LoanController::class, 'closedCount'])->name('.closed-count');

                Route::get('/overdue-total', [LoanController::class, 'overdueTotal'])->name('.overdue-total');
                Route::get('/overdue-principal', [LoanController::class, 'overduePrincipal'])->name('.overdue-principal');
                Route::get('/overdue-interest', [LoanController::class, 'overdueInterest'])->name('.overdue-interest');
                Route::get('/overdue-count', [LoanController::class, 'overdueCount'])->name('.overdue-count');

                Route::get('/due-today-total', [LoanController::class, 'dueTodayTotal'])->name('.due-today-total');
                Route::get('/due-today-principal', [LoanController::class, 'dueTodayPrincipal'])->name('.due-today-principal');
                Route::get('/due-today-interest', [LoanController::class, 'dueTodayInterest'])->name('.due-today-interest');

                Route::get('/days-past-due-total', [LoanController::class, 'daysPastDueTotal'])->name('.days-past-due-total');
                Route::get('/days-past-due-principal', [LoanController::class, 'daysPastDuePrincipal'])->name('.days-past-due-principal');
                Route::get('/days-past-due-interest', [LoanController::class, 'daysPastDueInterest'])->name('.days-past-due-interest');

                Route::get('/penalties', [LoanController::class, 'penalties'])->name('.penalties');
                Route::get('/penalties-count', [LoanController::class, 'penaltiesCount'])->name('.penalties-count');
                Route::get('/penalties-due', [LoanController::class, 'penaltiesDue'])->name('.penalties-due');
                Route::get('/penalties-due-count', [LoanController::class, 'penaltiesDueCount'])->name('.penalties-due-count');
                Route::get('/penalties-collected', [LoanController::class, 'penaltiesCollected'])->name('.penalties-collected');

                Route::get('/total-successful-applications', [LoanController::class, 'totalSuccessfulApplications'])->name('.total-successful-applications');
                Route::get('/total-failed-applications', [LoanController::class, 'totalFailedApplications'])->name('.total-failed-applications');
                Route::get('/total-amount-disbursed-today', [LoanController::class, 'totalAmountDisbursedToday'])->name('.total-amount-disbursed-today');
                Route::get('/total-amount-recovered', [LoanController::class, 'totalAmountRecovered'])->name('.total-amount-recovered');
                Route::get('/total-interest-recovered', [LoanController::class, 'totalInterestRecovered'])->name('.total-interest-recovered');

                Route::get('/npl', [LoanController::class, 'npl'])->name('.npl');
            });

        Route::name('activity-logs')
            ->prefix('/activity-logs')
            ->middleware('ability:activity-logs')
            ->group(function () {

                Route::get('', [ActivityLogController::class, 'index'])->name('.index');
            });

        Route::name('credit-scores')
            ->prefix('/credit-scores')
            ->middleware('ability:credit-scores')
            ->group(function () {

                Route::get('', [CreditScoreController::class, 'index'])->name('.index');
            });

        Route::name('collection-cases')
            ->prefix('/collection-cases')
            ->middleware('ability:collection-cases')
            ->group(function () {

                Route::get('', [CollectionCaseController::class, 'index'])->name('.index');
                Route::get('/search', [CollectionCaseController::class, 'search'])->name('.search');

                Route::get('/allotted', [CollectionCaseController::class, 'allotted'])->name('.allotted');

                Route::get('/worked-on', [CollectionCaseController::class, 'workedOn'])->name('.worked-on');

                Route::get('/ptp', [CollectionCaseController::class, 'ptp'])->name('.ptp');
                Route::get('/ptp-today', [CollectionCaseController::class, 'ptpToday'])->name('.ptp-today');

                Route::get('/allotted-arrears', [CollectionCaseController::class, 'allottedArrears'])->name('.allotted-arrears');

                Route::get('/paid-today', [CollectionCaseController::class, 'paidToday'])->name('.paid-today');

                Route::get('/paid-today-count', [CollectionCaseController::class, 'paidTodayCount'])->name('.paid-today-count');

                Route::get('/total-allotted', [CollectionCaseController::class, 'totalAllotted'])
                    ->middleware('ability:super-collector')
                    ->name('.total-allotted');

                Route::get('/total-worked-on', [CollectionCaseController::class, 'totalWorkedOn'])
                    ->middleware('ability:super-collector')
                    ->name('.total-worked-on');

                Route::get('/total-ptp', [CollectionCaseController::class, 'totalPtp'])
                    ->middleware('ability:super-collector')
                    ->name('.total-ptp');

                Route::get('/total-ptp-today', [CollectionCaseController::class, 'totalPtpToday'])
                    ->middleware('ability:super-collector')
                    ->name('.total-ptp-today');

                Route::get('/total-allotted-arrears', [CollectionCaseController::class, 'totalAllottedArrears'])
                    ->middleware('ability:super-collector')
                    ->name('.total-allotted-arrears');

                Route::get('/total-paid-today', [CollectionCaseController::class, 'totalPaidToday'])
                    ->middleware('ability:super-collector')
                    ->name('.total-paid-today');

                Route::get('/total-paid-today-count', [CollectionCaseController::class, 'totalPaidTodayCount'])
                    ->middleware('ability:super-collector')
                    ->name('.total-paid-today-count');

                Route::get('/{collectionCase}', [CollectionCaseController::class, 'show'])->name('.show');
                Route::put('/{collectionCase}', [CollectionCaseController::class, 'update'])->name('.update');

                Route::post('/{collectionCase}/assign', [CollectionCaseController::class, 'assign'])
                    ->middleware('ability:super-collector')
                    ->name('.assign');
            });

        Route::get('/collectors', CollectorController::class)
            ->name('collectors')
            ->middleware('ability:collection-cases');

        Route::name('collection-remarks')
            ->prefix('/collection-remarks')
            ->middleware('ability:super-collector')
            ->group(function () {

                Route::get('', [CollectionRemarkController::class, 'index'])->name('.index');
                Route::post('', [CollectionRemarkController::class, 'store'])->name('.store');
                Route::get('/{collectionRemark}', [CollectionRemarkController::class, 'show'])->name('.show');
                Route::put('/{collectionRemark}', [CollectionRemarkController::class, 'update'])->name('.update');
                Route::delete('/{collectionRemark}', [CollectionRemarkController::class, 'destroy'])->name('.destroy');
            });


        /**
         * Beginning of Administrator Routes
         */
        Route::middleware('administrator')
            ->group(function () {

                Route::name('roles')
                    ->prefix('/roles')
                    ->group(function () {

                        Route::get('', [RoleController::class, 'index'])->name('.index');
                        Route::post('', [RoleController::class, 'store'])->name('.store');
                        Route::get('/{role}', [RoleController::class, 'show'])->name('.show');
                        Route::put('/{role}', [RoleController::class, 'update'])->name('.update');
                        Route::delete('/{role}', [RoleController::class, 'destroy'])->name('.destroy');
                    });

                Route::name('departments')
                    ->prefix('/departments')
                    ->group(function () {

                        Route::get('', [DepartmentController::class, 'index'])->name('.index');
                        Route::post('', [DepartmentController::class, 'store'])->name('.store');
                        Route::get('/{department}', [DepartmentController::class, 'show'])->name('.show');
                        Route::put('/{department}', [DepartmentController::class, 'update'])->name('.update');
                        Route::delete('/{department}', [DepartmentController::class, 'destroy'])->name('.destroy');
                    });

                Route::name('staff')
                    ->prefix('/staff')
                    ->group(function () {

                        Route::get('', [StaffController::class, 'index'])->name('.index');
                        Route::post('', [StaffController::class, 'store'])->name('.store');
                        Route::get('/{userId}', [StaffController::class, 'show'])->name('.show');
                        Route::put('/{userId}', [StaffController::class, 'update'])->name('.update');
                        Route::delete('/{userId}', [StaffController::class, 'destroy'])->name('.destroy');
                    });

                Route::name('settings')
                    ->prefix('/settings')
                    ->group(function () {

                        Route::get('', [SettingController::class, 'index'])->name('.index');
                        Route::post('', [SettingController::class, 'store'])->name('.store');
                    });
            });
        /**
         * End of Administrator Routes
         */
    });
/**
 * End of Application Routes
 */