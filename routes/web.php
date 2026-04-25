<?php

use App\Http\Controllers\AccountTitleController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BusinessOwnerController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\GeneralLedgerController;
use App\Http\Controllers\JournalDescriptionController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\SubAccountTitleController;
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

Route::get('/trial-balances', [TrialBalanceController::class, 'index'])
    ->name('trial-balances.index');

Route::get('/general-ledgers', [GeneralLedgerController::class, 'index'])
    ->name('general-ledgers.index');