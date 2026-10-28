<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health/ready', function () {
    try {
        DB::select('SELECT 1');
    } catch (QueryException|PDOException $exception) {
        report($exception);

        return response()->json(['status' => 'unavailable'], 503)
            ->header('Cache-Control', 'no-store');
    }

    return response()->json(['status' => 'ok'])
        ->header('Cache-Control', 'no-store');
})->withoutMiddleware([
    ShareErrorsFromSession::class,
    StartSession::class,
    PreventRequestForgery::class,
])->name('health.ready');
