<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\RoleSelectionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard')->middleware('role.selected');

    // Выбор роли при входе (несколько ролей)
    Route::get('/role-select', [RoleSelectionController::class, 'show'])->name('role.select');
    Route::post('/role-select', [RoleSelectionController::class, 'store'])->name('role.store');
    // Смена роли из раздела «Профиль»
    Route::post('/role-switch', [RoleSelectionController::class, 'switch'])->name('role.switch');
});

Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::post('/login', [LoginController::class, 'store'])->name('login.store');

Route::post('/logout', function (\Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('login');
})->name('logout');

Route::get('/signup', function () {
    return view('auth.login');
});

// Забыли пароль: форма ввода email
Route::get('/reset-password', [ForgotPasswordController::class, 'showForgotForm'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])->name('password.email');

// Сброс пароля по ссылке из письма (маршрут password.reset нужен для Laravel ResetPassword notification)
Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword'])->name('password.update');
