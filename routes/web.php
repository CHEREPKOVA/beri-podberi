<?php

use App\Http\Controllers\Admin\CatalogController as AdminCatalogController;
use App\Http\Controllers\Admin\CatalogProductController as AdminCatalogProductController;
use App\Http\Controllers\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Admin\DeliveryMethodController as AdminDeliveryMethodController;
use App\Http\Controllers\Admin\DirectoriesController as AdminDirectoriesController;
use App\Http\Controllers\Admin\ProductAnalogController as AdminProductAnalogController;
use App\Http\Controllers\Admin\ProductAttributeController as AdminProductAttributeController;
use App\Http\Controllers\Admin\ProductCategoryController as AdminProductCategoryController;
use App\Http\Controllers\Admin\RegionController as AdminRegionController;
use App\Http\Controllers\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Admin\SystemSettingController as AdminSystemSettingController;
use App\Http\Controllers\Admin\TransportCompanyController as AdminTransportCompanyController;
use App\Http\Controllers\Admin\UnitTypeController as AdminUnitTypeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\Manufacturer\ProductController as ManufacturerProductController;
use App\Http\Controllers\Manufacturer\ProfileController as ManufacturerProfileController;
use App\Http\Controllers\Manufacturer\WarehouseController as ManufacturerWarehouseController;
use App\Http\Controllers\RoleSelectionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::middleware(['auth', 'user.active'])->group(function () {
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
        Route::post('/staff/{staff}/suspend', [AdminStaffController::class, 'suspend'])->name('staff.suspend');
        Route::post('/staff/{staff}/activate', [AdminStaffController::class, 'activate'])->name('staff.activate');

        Route::get('/companies', [AdminCompanyController::class, 'index'])->name('companies.index');
        Route::get('/companies/{companyKey}', [AdminCompanyController::class, 'show'])->name('companies.show');
        Route::put('/companies/{companyKey}', [AdminCompanyController::class, 'updateCompany'])->name('companies.update');
        Route::put('/companies/{companyKey}/users/{user}', [AdminCompanyController::class, 'updateUser'])->name('companies.users.update');
        Route::post('/companies/{companyKey}/users/{user}/suspend', [AdminCompanyController::class, 'suspendUser'])->name('companies.users.suspend');
        Route::post('/companies/{companyKey}/users/{user}/activate', [AdminCompanyController::class, 'activateUser'])->name('companies.users.activate');
        Route::post('/companies/{companyKey}/users/{user}/reset-password', [AdminCompanyController::class, 'resetPassword'])->name('companies.users.reset-password');
        Route::delete('/companies/{companyKey}/users/{user}', [AdminCompanyController::class, 'deleteUser'])->name('companies.users.delete');

        Route::get('/directories', [AdminDirectoriesController::class, 'index'])->name('directories.index');
        Route::resource('regions', AdminRegionController::class)->except(['show']);
        Route::resource('delivery-methods', AdminDeliveryMethodController::class)->except(['show']);
        Route::resource('transport-companies', AdminTransportCompanyController::class)->except(['show']);
        Route::resource('unit-types', AdminUnitTypeController::class)->except(['show']);
        Route::get('/system-settings', [AdminSystemSettingController::class, 'index'])->name('system-settings.index');
        Route::put('/system-settings', [AdminSystemSettingController::class, 'update'])->name('system-settings.update');

        Route::get('/catalog', [AdminCatalogController::class, 'index'])->name('catalog.index');
        Route::get('/catalog/quality', [AdminCatalogController::class, 'quality'])->name('catalog.quality');

        Route::get('/catalog/categories', [AdminProductCategoryController::class, 'index'])->name('catalog.categories.index');
        Route::get('/catalog/categories/create', [AdminProductCategoryController::class, 'create'])->name('catalog.categories.create');
        Route::post('/catalog/categories', [AdminProductCategoryController::class, 'store'])->name('catalog.categories.store');
        Route::get('/catalog/categories/{category}/edit', [AdminProductCategoryController::class, 'edit'])->name('catalog.categories.edit');
        Route::put('/catalog/categories/{category}', [AdminProductCategoryController::class, 'update'])->name('catalog.categories.update');
        Route::delete('/catalog/categories/{category}', [AdminProductCategoryController::class, 'destroy'])->name('catalog.categories.destroy');

        Route::get('/catalog/products', [AdminCatalogProductController::class, 'index'])->name('catalog.products.index');
        Route::get('/catalog/products/{product}', [AdminCatalogProductController::class, 'show'])->name('catalog.products.show');
        Route::get('/catalog/products/{product}/edit', [AdminCatalogProductController::class, 'edit'])->name('catalog.products.edit');
        Route::put('/catalog/products/{product}', [AdminCatalogProductController::class, 'update'])->name('catalog.products.update');

        Route::get('/catalog/attributes', [AdminProductAttributeController::class, 'index'])->name('catalog.attributes.index');
        Route::get('/catalog/attributes/create', [AdminProductAttributeController::class, 'create'])->name('catalog.attributes.create');
        Route::post('/catalog/attributes', [AdminProductAttributeController::class, 'store'])->name('catalog.attributes.store');
        Route::get('/catalog/attributes/{attribute}/edit', [AdminProductAttributeController::class, 'edit'])->name('catalog.attributes.edit');
        Route::put('/catalog/attributes/{attribute}', [AdminProductAttributeController::class, 'update'])->name('catalog.attributes.update');
        Route::delete('/catalog/attributes/{attribute}', [AdminProductAttributeController::class, 'destroy'])->name('catalog.attributes.destroy');

        Route::get('/catalog/analogs', [AdminProductAnalogController::class, 'index'])->name('catalog.analogs.index');
        Route::get('/catalog/analogs/{product}/edit', [AdminProductAnalogController::class, 'edit'])->name('catalog.analogs.edit');
        Route::put('/catalog/analogs/{product}', [AdminProductAnalogController::class, 'update'])->name('catalog.analogs.update');
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
        Route::get('/catalog/product/{product}', [ManufacturerProductController::class, 'catalogShow'])->name('catalog.show');
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

Route::post('/logout', function (Request $request) {
    Auth::logout();
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
