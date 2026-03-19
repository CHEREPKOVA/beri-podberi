<?php

use App\Http\Controllers\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\Manufacturer\ProductController as ManufacturerProductController;
use App\Http\Controllers\Manufacturer\ProfileController as ManufacturerProfileController;
use App\Http\Controllers\Manufacturer\WarehouseController as ManufacturerWarehouseController;
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

    // Панель администратора (только роль admin)
    Route::middleware(['role.selected', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/staff', [AdminStaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/create', [AdminStaffController::class, 'create'])->name('staff.create');
        Route::post('/staff', [AdminStaffController::class, 'store'])->name('staff.store');
        Route::get('/staff/{staff}/edit', [AdminStaffController::class, 'edit'])->name('staff.edit');
        Route::put('/staff/{staff}', [AdminStaffController::class, 'update'])->name('staff.update');
        Route::delete('/staff/{staff}', [AdminStaffController::class, 'destroy'])->name('staff.destroy');
    });

    // Профиль производителя
    Route::middleware(['role.selected', 'role:manufacturer'])->prefix('manufacturer')->name('manufacturer.')->group(function () {
        Route::get('/profile', [ManufacturerProfileController::class, 'index'])->name('profile');
        Route::put('/profile/company', [ManufacturerProfileController::class, 'updateCompany'])->name('profile.company.update');

        // Контакты
        Route::post('/profile/contacts', [ManufacturerProfileController::class, 'storeContact'])->name('profile.contacts.store');
        Route::put('/profile/contacts/{contact}', [ManufacturerProfileController::class, 'updateContact'])->name('profile.contacts.update');
        Route::delete('/profile/contacts/{contact}', [ManufacturerProfileController::class, 'deleteContact'])->name('profile.contacts.delete');

        // Регионы
        Route::put('/profile/regions', [ManufacturerProfileController::class, 'updateRegions'])->name('profile.regions.update');

        // Склады (отдельный раздел)
        Route::get('/warehouses', [ManufacturerWarehouseController::class, 'index'])->name('warehouses.index');
        Route::post('/warehouses', [ManufacturerProfileController::class, 'storeWarehouse'])->name('warehouses.store');
        Route::put('/warehouses/{warehouse}', [ManufacturerProfileController::class, 'updateWarehouse'])->name('warehouses.update');
        Route::delete('/warehouses/{warehouse}', [ManufacturerProfileController::class, 'deleteWarehouse'])->name('warehouses.delete');
        Route::get('/warehouses/export', [ManufacturerProfileController::class, 'exportWarehouses'])->name('warehouses.export');

        // Доставка
        Route::put('/profile/delivery', [ManufacturerProfileController::class, 'updateDelivery'])->name('profile.delivery.update');

        // Документы
        Route::post('/profile/documents', [ManufacturerProfileController::class, 'storeDocument'])->name('profile.documents.store');
        Route::delete('/profile/documents/{document}', [ManufacturerProfileController::class, 'deleteDocument'])->name('profile.documents.delete');

        // Каталог (дерево категорий + товары), ЧПУ: /catalog и /catalog/{slug}
        Route::get('/catalog/products', [ManufacturerProductController::class, 'catalogProducts'])->name('catalog.products');
        Route::get('/catalog/{category?}', [ManufacturerProductController::class, 'catalog'])->name('catalog.index')->where('category', '[a-z0-9\-]+');

        // Номенклатура (товары)
        Route::get('/products', [ManufacturerProductController::class, 'index'])->name('products.index');
        Route::get('/products/create', [ManufacturerProductController::class, 'create'])->name('products.create');
        Route::post('/products', [ManufacturerProductController::class, 'store'])->name('products.store');
        Route::get('/products/import', [ManufacturerProductController::class, 'importForm'])->name('products.import');
        Route::post('/products/import', [ManufacturerProductController::class, 'import'])->name('products.import.process');
        Route::get('/products/export', [ManufacturerProductController::class, 'export'])->name('products.export');
        Route::post('/products/bulk', [ManufacturerProductController::class, 'bulkAction'])->name('products.bulk');
        Route::get('/products/{product}/edit', [ManufacturerProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [ManufacturerProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ManufacturerProductController::class, 'destroy'])->name('products.destroy');
        Route::post('/products/{product}/publish', [ManufacturerProductController::class, 'publish'])->name('products.publish');
        Route::post('/products/{product}/hide', [ManufacturerProductController::class, 'hide'])->name('products.hide');
        Route::delete('/products/images/{image}', [ManufacturerProductController::class, 'deleteImage'])->name('products.image.delete');
        Route::post('/products/images/{image}/primary', [ManufacturerProductController::class, 'setPrimaryImage'])->name('products.image.primary');
        Route::delete('/products/documents/{document}', [ManufacturerProductController::class, 'deleteDocument'])->name('products.document.delete');
    });
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
