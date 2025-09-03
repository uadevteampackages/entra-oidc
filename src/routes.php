<?php

use Illuminate\Support\Facades\Route;
use UaDevTeamPackages\EntraOidc\Http\Controllers\AuthController;

Route::group(['middleware' => ['web']], function () {
    Route::get('/auth/callback', [AuthController::class, 'callback'])->name('entra-oidc.callback');
    Route::get('logout', [AuthController::class, 'logout'])->name('entra-oidc.logout');
    Route::get('postLogout', [AuthController::class, 'postLogout'])->name('entra-oidc.postLogout');
});
