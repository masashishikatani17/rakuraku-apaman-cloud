<?php

use App\Http\Controllers\AccountTitleController;
use App\Http\Controllers\BalanceSheetReportController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BusinessOwnerController;
use App\Http\Controllers\CashBankLedgerController;
use App\Http\Controllers\ClosingAdjustmentJournalController;
use App\Http\Controllers\ContractTenantController;
use App\Http\Controllers\ContractTenantAnnualIncomeReportController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DepartmentTrialBalanceController;
use App\Http\Controllers\DepreciableAssetController;
use App\Http\Controllers\ExpenseLedgerController;
use App\Http\Controllers\GeneralLedgerController;
use App\Http\Controllers\IncomeStatementReportController;
use App\Http\Controllers\JournalDescriptionController;
use App\Http\Controllers\JournalDiaryController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\MonthlyPaymentScheduleController;
use App\Http\Controllers\MonthlyTrendReportController;
use App\Http\Controllers\OpeningBalanceController;
use App\Http\Controllers\PaymentAccountController;
use App\Http\Controllers\PaymentItemController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\PaymentScheduleController;
use App\Http\Controllers\RentalPaymentJournalController;
use App\Http\Controllers\RentalContractReportController;
use App\Http\Controllers\PropertyPaymentReportController;
use App\Http\Controllers\PropertyAnnualIncomeReportController;
use App\Http\Controllers\PropertyCategoryController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\PropertyLedgerReportController;
use App\Http\Controllers\PropertyOwnerController;
use App\Http\Controllers\PropertyUnitController;
use App\Http\Controllers\SubAccountTitleController;
use App\Http\Controllers\SubAccountReportController;
use App\Http\Controllers\SubAccountLedgerController;
use App\Http\Controllers\TrialBalanceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('business-owners.index');
});

Route::get('/business-owners', [BusinessOwnerController::class, 'index'])
    ->name('business-owners.index');

Route::get('/business-owners/create', [BusinessOwnerController::class, 'create'])
    ->name('business-owners.create');

Route::post('/business-owners', [BusinessOwnerController::class, 'store'])
    ->name('business-owners.store');

Route::get('/books', [BookController::class, 'index'])
    ->name('books.index');

Route::get('/books/create', [BookController::class, 'create'])
    ->name('books.create');

Route::post('/books', [BookController::class, 'store'])
    ->name('books.store');

Route::get('/opening-balances', [OpeningBalanceController::class, 'index'])
    ->name('opening-balances.index');

Route::post('/opening-balances', [OpeningBalanceController::class, 'store'])
    ->name('opening-balances.store');

Route::get('/property-owners', [PropertyOwnerController::class, 'index'])
    ->name('property-owners.index');

Route::get('/property-owners/create', [PropertyOwnerController::class, 'create'])
    ->name('property-owners.create');

Route::post('/property-owners', [PropertyOwnerController::class, 'store'])
    ->name('property-owners.store');

Route::get('/property-owners/{propertyOwner}/edit', [PropertyOwnerController::class, 'edit'])
    ->name('property-owners.edit');

Route::put('/property-owners/{propertyOwner}', [PropertyOwnerController::class, 'update'])
    ->name('property-owners.update');

Route::delete('/property-owners/{propertyOwner}', [PropertyOwnerController::class, 'destroy'])
    ->name('property-owners.destroy');

Route::get('/property-categories', [PropertyCategoryController::class, 'index'])
    ->name('property-categories.index');

Route::get('/property-categories/create', [PropertyCategoryController::class, 'create'])
    ->name('property-categories.create');

Route::post('/property-categories', [PropertyCategoryController::class, 'store'])
    ->name('property-categories.store');

Route::get('/property-categories/{propertyCategory}/edit', [PropertyCategoryController::class, 'edit'])
    ->name('property-categories.edit');

Route::put('/property-categories/{propertyCategory}', [PropertyCategoryController::class, 'update'])
    ->name('property-categories.update');

Route::delete('/property-categories/{propertyCategory}', [PropertyCategoryController::class, 'destroy'])
    ->name('property-categories.destroy');

Route::get('/properties', [PropertyController::class, 'index'])
    ->name('properties.index');

Route::get('/properties/create', [PropertyController::class, 'create'])
    ->name('properties.create');

Route::post('/properties', [PropertyController::class, 'store'])
    ->name('properties.store');

Route::get('/properties/{property}/edit', [PropertyController::class, 'edit'])
    ->name('properties.edit');

Route::put('/properties/{property}', [PropertyController::class, 'update'])
    ->name('properties.update');

Route::delete('/properties/{property}', [PropertyController::class, 'destroy'])
    ->name('properties.destroy');

Route::get('/property-units', [PropertyUnitController::class, 'index'])
    ->name('property-units.index');

Route::get('/property-units/create', [PropertyUnitController::class, 'create'])
    ->name('property-units.create');

Route::post('/property-units', [PropertyUnitController::class, 'store'])
    ->name('property-units.store');

Route::get('/property-units/{propertyUnit}/edit', [PropertyUnitController::class, 'edit'])
    ->name('property-units.edit');

Route::put('/property-units/{propertyUnit}', [PropertyUnitController::class, 'update'])
    ->name('property-units.update');

Route::delete('/property-units/{propertyUnit}', [PropertyUnitController::class, 'destroy'])
    ->name('property-units.destroy');

Route::get('/contract-tenants', [ContractTenantController::class, 'index'])
    ->name('contract-tenants.index');

Route::get('/contract-tenants/create', [ContractTenantController::class, 'create'])
    ->name('contract-tenants.create');

Route::post('/contract-tenants', [ContractTenantController::class, 'store'])
    ->name('contract-tenants.store');

Route::get('/contract-tenants/{contractTenant}/edit', [ContractTenantController::class, 'edit'])
    ->name('contract-tenants.edit');

Route::put('/contract-tenants/{contractTenant}', [ContractTenantController::class, 'update'])
    ->name('contract-tenants.update');

Route::delete('/contract-tenants/{contractTenant}', [ContractTenantController::class, 'destroy'])
    ->name('contract-tenants.destroy');

Route::get('/payment-items', [PaymentItemController::class, 'index'])
    ->name('payment-items.index');

Route::get('/payment-items/create', [PaymentItemController::class, 'create'])
    ->name('payment-items.create');

Route::post('/payment-items', [PaymentItemController::class, 'store'])
    ->name('payment-items.store');

Route::get('/payment-items/{paymentItem}/edit', [PaymentItemController::class, 'edit'])
    ->name('payment-items.edit');

Route::put('/payment-items/{paymentItem}', [PaymentItemController::class, 'update'])
    ->name('payment-items.update');

Route::delete('/payment-items/{paymentItem}', [PaymentItemController::class, 'destroy'])
    ->name('payment-items.destroy');

Route::get('/payment-accounts', [PaymentAccountController::class, 'index'])
    ->name('payment-accounts.index');

Route::get('/payment-accounts/create', [PaymentAccountController::class, 'create'])
    ->name('payment-accounts.create');

Route::post('/payment-accounts', [PaymentAccountController::class, 'store'])
    ->name('payment-accounts.store');

Route::get('/payment-accounts/{paymentAccount}/edit', [PaymentAccountController::class, 'edit'])
    ->name('payment-accounts.edit');

Route::put('/payment-accounts/{paymentAccount}', [PaymentAccountController::class, 'update'])
    ->name('payment-accounts.update');

Route::delete('/payment-accounts/{paymentAccount}', [PaymentAccountController::class, 'destroy'])
    ->name('payment-accounts.destroy');

Route::get('/monthly-payment-schedules/create', [MonthlyPaymentScheduleController::class, 'create'])
    ->name('monthly-payment-schedules.create');

Route::post('/monthly-payment-schedules', [MonthlyPaymentScheduleController::class, 'store'])
    ->name('monthly-payment-schedules.store');

Route::get('/payment-schedules', [PaymentScheduleController::class, 'index'])
    ->name('payment-schedules.index');

Route::get('/payment-schedules/create', [PaymentScheduleController::class, 'create'])
    ->name('payment-schedules.create');

Route::post('/payment-schedules', [PaymentScheduleController::class, 'store'])
    ->name('payment-schedules.store');

Route::get('/payment-schedules/{paymentSchedule}/edit', [PaymentScheduleController::class, 'edit'])
    ->name('payment-schedules.edit');

Route::put('/payment-schedules/{paymentSchedule}', [PaymentScheduleController::class, 'update'])
    ->name('payment-schedules.update');

Route::delete('/payment-schedules/{paymentSchedule}', [PaymentScheduleController::class, 'destroy'])
    ->name('payment-schedules.destroy');

Route::get('/payment-receipts', [PaymentReceiptController::class, 'index'])
    ->name('payment-receipts.index');

Route::get('/payment-receipts/create', [PaymentReceiptController::class, 'create'])
    ->name('payment-receipts.create');

Route::post('/payment-receipts', [PaymentReceiptController::class, 'store'])
    ->name('payment-receipts.store');

Route::get('/payment-receipts/{paymentReceipt}/edit', [PaymentReceiptController::class, 'edit'])
    ->name('payment-receipts.edit');

Route::put('/payment-receipts/{paymentReceipt}', [PaymentReceiptController::class, 'update'])
    ->name('payment-receipts.update');

Route::delete('/payment-receipts/{paymentReceipt}', [PaymentReceiptController::class, 'destroy'])
    ->name('payment-receipts.destroy');

Route::get('/rental-payment-journals', [RentalPaymentJournalController::class, 'index'])
    ->name('rental-payment-journals.index');
 
Route::post('/rental-payment-journals/bulk', [RentalPaymentJournalController::class, 'bulkStore'])
    ->name('rental-payment-journals.bulk-store');

Route::post('/rental-payment-journals/{paymentReceipt}', [RentalPaymentJournalController::class, 'store'])
    ->name('rental-payment-journals.store');

Route::delete('/rental-payment-journals/{paymentReceipt}', [RentalPaymentJournalController::class, 'destroy'])
    ->name('rental-payment-journals.destroy');

Route::get('/account-titles', [AccountTitleController::class, 'index'])
    ->name('account-titles.index');

Route::get('/account-titles/create', [AccountTitleController::class, 'create'])
    ->name('account-titles.create');

Route::post('/account-titles', [AccountTitleController::class, 'store'])
    ->name('account-titles.store');

Route::get('/sub-account-titles', [SubAccountTitleController::class, 'index'])
    ->name('sub-account-titles.index');

Route::get('/sub-account-titles/create', [SubAccountTitleController::class, 'create'])
    ->name('sub-account-titles.create');

Route::post('/sub-account-titles', [SubAccountTitleController::class, 'store'])
    ->name('sub-account-titles.store');

Route::get('/journal-descriptions', [JournalDescriptionController::class, 'index'])
    ->name('journal-descriptions.index');

Route::get('/journal-descriptions/create', [JournalDescriptionController::class, 'create'])
    ->name('journal-descriptions.create');

Route::post('/journal-descriptions', [JournalDescriptionController::class, 'store'])
    ->name('journal-descriptions.store');

Route::get('/departments', [DepartmentController::class, 'index'])
    ->name('departments.index');

Route::get('/departments/create', [DepartmentController::class, 'create'])
    ->name('departments.create');

Route::post('/departments', [DepartmentController::class, 'store'])
    ->name('departments.store');

Route::get('/journal-entries', [JournalEntryController::class, 'index'])
    ->name('journal-entries.index');

Route::get('/journal-entries/create', [JournalEntryController::class, 'create'])
    ->name('journal-entries.create');

Route::post('/journal-entries', [JournalEntryController::class, 'store'])
    ->name('journal-entries.store');

Route::get('/journal-entries/{journalEntry}/edit', [JournalEntryController::class, 'edit'])
    ->name('journal-entries.edit');

Route::put('/journal-entries/{journalEntry}', [JournalEntryController::class, 'update'])
    ->name('journal-entries.update');

Route::delete('/journal-entries/{journalEntry}', [JournalEntryController::class, 'destroy'])
    ->name('journal-entries.destroy');

Route::get('/journal-diaries', [JournalDiaryController::class, 'index'])
    ->name('journal-diaries.index');

Route::get('/depreciable-assets', [DepreciableAssetController::class, 'index'])
    ->name('depreciable-assets.index');

Route::get('/depreciable-assets/create', [DepreciableAssetController::class, 'create'])
    ->name('depreciable-assets.create');

Route::post('/depreciable-assets', [DepreciableAssetController::class, 'store'])
    ->name('depreciable-assets.store');

Route::post('/depreciable-assets/depreciation-journals', [DepreciableAssetController::class, 'storeDepreciationJournals'])
    ->name('depreciable-assets.depreciation-journals.store');

Route::get('/depreciable-assets/{depreciableAsset}/edit', [DepreciableAssetController::class, 'edit'])
    ->name('depreciable-assets.edit');

Route::put('/depreciable-assets/{depreciableAsset}', [DepreciableAssetController::class, 'update'])
    ->name('depreciable-assets.update');

Route::delete('/depreciable-assets/{depreciableAsset}', [DepreciableAssetController::class, 'destroy'])
    ->name('depreciable-assets.destroy');

Route::get('/closing-adjustment-journals', [ClosingAdjustmentJournalController::class, 'index'])
    ->name('closing-adjustment-journals.index');

Route::get('/closing-adjustment-journals/create', [ClosingAdjustmentJournalController::class, 'create'])
    ->name('closing-adjustment-journals.create');

Route::post('/closing-adjustment-journals', [ClosingAdjustmentJournalController::class, 'store'])
    ->name('closing-adjustment-journals.store');

Route::get('/closing-adjustment-journals/{journalEntry}/edit', [ClosingAdjustmentJournalController::class, 'edit'])
    ->name('closing-adjustment-journals.edit');

Route::put('/closing-adjustment-journals/{journalEntry}', [ClosingAdjustmentJournalController::class, 'update'])
    ->name('closing-adjustment-journals.update');

Route::delete('/closing-adjustment-journals/{journalEntry}', [ClosingAdjustmentJournalController::class, 'destroy'])
    ->name('closing-adjustment-journals.destroy');

Route::get('/trial-balances', [TrialBalanceController::class, 'index'])
    ->name('trial-balances.index');

Route::get('/general-ledgers', [GeneralLedgerController::class, 'index'])
    ->name('general-ledgers.index');

Route::get('/cash-ledgers', [CashBankLedgerController::class, 'cashIndex'])
    ->name('cash-ledgers.index');

Route::get('/bank-ledgers', [CashBankLedgerController::class, 'bankIndex'])
    ->name('bank-ledgers.index');

Route::get('/expense-ledgers', [ExpenseLedgerController::class, 'index'])
    ->name('expense-ledgers.index');
 
Route::get('/department-trial-balances', [DepartmentTrialBalanceController::class, 'index'])
    ->name('department-trial-balances.index');

Route::get('/reports/sub-accounts', [SubAccountReportController::class, 'index'])
    ->name('reports.sub-accounts.index');

Route::get('/sub-account-ledgers', [SubAccountLedgerController::class, 'index'])
    ->name('sub-account-ledgers.index');

Route::get('/reports/property-payments', [PropertyPaymentReportController::class, 'index'])
    ->name('reports.property-payments.index');

Route::get('/reports/property-annual-incomes', [PropertyAnnualIncomeReportController::class, 'index'])
    ->name('reports.property-annual-incomes.index');

Route::get('/reports/contract-tenant-annual-incomes', [ContractTenantAnnualIncomeReportController::class, 'index'])
    ->name('reports.contract-tenant-annual-incomes.index');

Route::get('/reports/property-ledgers', [PropertyLedgerReportController::class, 'index'])
    ->name('reports.property-ledgers.index');

Route::get('/reports/rental-contracts', [RentalContractReportController::class, 'index'])
    ->name('reports.rental-contracts.index');

Route::get('/reports/monthly-trends', [MonthlyTrendReportController::class, 'index'])
    ->name('reports.monthly-trends.index');

Route::get('/reports/income-statements', [IncomeStatementReportController::class, 'index'])
    ->name('reports.income-statements.index');

Route::get('/reports/balance-sheets', [BalanceSheetReportController::class, 'index'])
    ->name('reports.balance-sheets.index');
