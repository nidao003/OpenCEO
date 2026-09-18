<?php

use Illuminate\Support\Facades\Route;
use Leantime\Core\Middleware\VerifyCsrfToken;
use Leantime\Domain\OpenCEO\Controllers\Desk;

Route::get('/openceo/desk', [Desk::class, 'show'])->name('openceo.desk');

Route::post('/openceo/desk', [Desk::class, 'post'])
    ->middleware(VerifyCsrfToken::class);
