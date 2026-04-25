<?php

use App\Http\Controllers\AccountTitleController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BusinessOwnerController;
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