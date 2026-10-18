<?php

use App\Http\Controllers\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Admin\CatalogController as AdminCatalogController;
use App\Http\Controllers\Admin\CatalogProductController as AdminCatalogProductController;
use App\Http\Controllers\Admin\ClaimStatusController as AdminClaimStatusController;
use App\Http\Controllers\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Admin\CompanyTypeController as AdminCompanyTypeController;
use App\Http\Controllers\Admin\DeliveryMethodController as AdminDeliveryMethodController;
use App\Http\Controllers\Admin\DirectoriesController as AdminDirectoriesController;
use App\Http\Controllers\Admin\DocumentTypeController as AdminDocumentTypeController;
use App\Http\Controllers\Admin\FederalDistrictController as AdminFederalDistrictController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\OrderStatusController as AdminOrderStatusController;
use App\Http\Controllers\Admin\PlatformRoleController as AdminPlatformRoleController;
use App\Http\Controllers\Admin\ProductAnalogController as AdminProductAnalogController;
use App\Http\Controllers\Admin\ProductAttributeController as AdminProductAttributeController;
use App\Http\Controllers\Admin\ProductCategoryController as AdminProductCategoryController;
use App\Http\Controllers\Admin\RegionController as AdminRegionController;
use App\Http\Controllers\Admin\StaffController as AdminStaffController;
use App\Http\Controllers\Admin\SystemSettingController as AdminSystemSettingController;
use App\Http\Controllers\Admin\TransportCompanyController as AdminTransportCompanyController;
use App\Http\Controllers\Admin\UnitTypeController as AdminUnitTypeController;
use App\Http\Controllers\Admin\WarehouseTypeController as AdminWarehouseTypeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Buyer\CartController as BuyerCartController;
use App\Http\Controllers\Buyer\CatalogController as BuyerCatalogController;
use App\Http\Controllers\Buyer\CheckoutController as BuyerCheckoutController;
use App\Http\Controllers\Buyer\OrderController as BuyerOrderController;
use App\Http\Controllers\Catalog\ProductLiveController;
use App\Http\Controllers\Catalog\SearchSuggestController;
use App\Http\Controllers\CompanyInvitationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Distributor\DiscountSetController as DistributorDiscountSetController;
use App\Http\Controllers\Distributor\EmployeeController as DistributorEmployeeController;
use App\Http\Controllers\Distributor\EmployeeRoleController as DistributorEmployeeRoleController;
use App\Http\Controllers\Distributor\OrderController as DistributorOrderController;
use App\Http\Controllers\Distributor\ProductController as DistributorProductController;
use App\Http\Controllers\Distributor\ProfileController as DistributorProfileController;
use App\Http\Controllers\Distributor\PurchaseCartController as DistributorPurchaseCartController;
use App\Http\Controllers\Distributor\PurchaseCheckoutController as DistributorPurchaseCheckoutController;
use App\Http\Controllers\Distributor\PurchaseOrderController as DistributorPurchaseOrderController;
use App\Http\Controllers\Distributor\WarehouseController as DistributorWarehouseController;
use App\Http\Controllers\EmployeeInvitationController;
use App\Http\Controllers\EndCompany\EmployeeController as EndCompanyEmployeeController;
use App\Http\Controllers\EndCompany\EmployeeRoleController as EndCompanyEmployeeRoleController;
use App\Http\Controllers\EndCompany\ProfileController as EndCompanyProfileController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\Manufacturer\DiscountSetController as ManufacturerDiscountSetController;
use App\Http\Controllers\Manufacturer\EmployeeController as ManufacturerEmployeeController;
use App\Http\Controllers\Manufacturer\EmployeeRoleController as ManufacturerEmployeeRoleController;
use App\Http\Controllers\Manufacturer\OrderController as ManufacturerOrderController;
use App\Http\Controllers\Manufacturer\PartnerCatalogController as ManufacturerPartnerCatalogController;
use App\Http\Controllers\Manufacturer\ProductController as ManufacturerProductController;
use App\Http\Controllers\Manufacturer\ProfileController as ManufacturerProfileController;
use App\Http\Controllers\Manufacturer\PromoCodeController as ManufacturerPromoCodeController;
use App\Http\Controllers\Manufacturer\WarehouseController as ManufacturerWarehouseController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RoleSelectionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/dashboard');
});

Route::middleware(['auth', 'user.active'])->group(function () {
    // Выбор роли при входе (несколько ролей) — тот же UI-блок, что и форма логина
    Route::get('/role-select', [RoleSelectionController::class, 'show'])->name('role.select');
    Route::post('/role-select', [RoleSelectionController::class, 'store'])->name('role.store');
    // Смена роли из раздела «Профиль»
    Route::post('/role-switch', [RoleSelectionController::class, 'switch'])->name('role.switch');

    Route::middleware(['role.selected'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read_all');
    });

    // Панель администратора (роли: admin, manager, analyst; доступ определяется permissions)
    Route::middleware(['role.selected', 'role:admin,manager,analyst', 'admin.audit'])->prefix('admin')->name('admin.')->group(function () {
        Route::middleware('permission:staff.manage')->group(function () {
            Route::get('/staff', [AdminStaffController::class, 'index'])->name('staff.index');
            Route::get('/staff/create', [AdminStaffController::class, 'create'])->name('staff.create');
            Route::post('/staff', [AdminStaffController::class, 'store'])->name('staff.store');
            Route::get('/staff/{staff}/edit', [AdminStaffController::class, 'edit'])->name('staff.edit');
            Route::put('/staff/{staff}', [AdminStaffController::class, 'update'])->name('staff.update');
            Route::delete('/staff/{staff}', [AdminStaffController::class, 'destroy'])->name('staff.destroy');
            Route::post('/staff/{staff}/suspend', [AdminStaffController::class, 'suspend'])->name('staff.suspend');
            Route::post('/staff/{staff}/activate', [AdminStaffController::class, 'activate'])->name('staff.activate');
        });

        Route::middleware('permission:companies.manage')->group(function () {
            Route::get('/companies', [AdminCompanyController::class, 'index'])->name('companies.index');
            Route::get('/companies/create', [AdminCompanyController::class, 'create'])->name('companies.create');
            Route::post('/companies', [AdminCompanyController::class, 'store'])->name('companies.store');
            Route::get('/companies/{companyKey}', [AdminCompanyController::class, 'show'])->name('companies.show');
            Route::put('/companies/{companyKey}', [AdminCompanyController::class, 'updateCompany'])->name('companies.update');
            Route::delete('/companies/{companyKey}', [AdminCompanyController::class, 'destroy'])->name('companies.destroy');
            Route::post('/companies/{companyKey}/invitation/resend', [AdminCompanyController::class, 'resendInvitation'])->name('companies.invitation.resend');
            Route::post('/companies/{companyKey}/invitation/cancel', [AdminCompanyController::class, 'cancelInvitation'])->name('companies.invitation.cancel');
            Route::delete('/companies/{companyKey}/invitation', [AdminCompanyController::class, 'deleteInvitation'])->name('companies.invitation.delete');
            Route::post('/companies/{companyKey}/moderation/approve', [AdminCompanyController::class, 'approveRegistration'])->name('companies.moderation.approve');
            Route::post('/companies/{companyKey}/moderation/reject', [AdminCompanyController::class, 'rejectRegistration'])->name('companies.moderation.reject');
            Route::put('/companies/{companyKey}/users/{user}', [AdminCompanyController::class, 'updateUser'])->name('companies.users.update');
            Route::post('/companies/{companyKey}/users/{user}/suspend', [AdminCompanyController::class, 'suspendUser'])->name('companies.users.suspend');
            Route::post('/companies/{companyKey}/users/{user}/activate', [AdminCompanyController::class, 'activateUser'])->name('companies.users.activate');
            Route::post('/companies/{companyKey}/users/{user}/reset-password', [AdminCompanyController::class, 'resetPassword'])->name('companies.users.reset-password');
            Route::delete('/companies/{companyKey}/users/{user}', [AdminCompanyController::class, 'deleteUser'])->name('companies.users.delete');
        });

        Route::middleware('permission:orders.manage')->group(function () {
            Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
            Route::get('/orders/{order}/documents/{document}/download', [AdminOrderController::class, 'downloadDocument'])->name('orders.documents.download');
            Route::get('/orders/{order}/documents/{document}/preview', [AdminOrderController::class, 'previewDocument'])->name('orders.documents.preview');
            Route::post('/orders/{order}/messages', [AdminOrderController::class, 'storeMessage'])->name('orders.messages.store');
            Route::get('/orders/{order}/messages/attachments/{attachment}/download', [AdminOrderController::class, 'downloadMessageAttachment'])->name('orders.messages.attachments.download');
            Route::get('/orders/{order}/messages/attachments/{attachment}/preview', [AdminOrderController::class, 'previewMessageAttachment'])->name('orders.messages.attachments.preview');
            Route::delete('/orders/{order}/messages/{message}', [AdminOrderController::class, 'destroyMessage'])->name('orders.messages.destroy');
            Route::post('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.update_status');
            Route::post('/orders/{order}/comment', [AdminOrderController::class, 'storeComment'])->name('orders.comment');
            Route::post('/orders/{order}/pause', [AdminOrderController::class, 'pause'])->name('orders.pause');
            Route::post('/orders/{order}/resume', [AdminOrderController::class, 'resume'])->name('orders.resume');
        });

        Route::middleware('permission:directories.manage')->group(function () {
            Route::get('/directories', [AdminDirectoriesController::class, 'index'])->name('directories.index');
            Route::resource('regions', AdminRegionController::class)->except(['show']);
            Route::resource('federal-districts', AdminFederalDistrictController::class)->except(['show']);
            Route::resource('company-types', AdminCompanyTypeController::class)->except(['show']);
            Route::resource('platform-roles', AdminPlatformRoleController::class)->except(['show']);
            Route::resource('order-statuses', AdminOrderStatusController::class)->except(['show']);
            Route::resource('claim-statuses', AdminClaimStatusController::class)->except(['show']);
            Route::resource('delivery-methods', AdminDeliveryMethodController::class)->except(['show']);
            Route::resource('transport-companies', AdminTransportCompanyController::class)->except(['show']);
            Route::resource('warehouse-types', AdminWarehouseTypeController::class)->except(['show']);
            Route::resource('unit-types', AdminUnitTypeController::class)->except(['show']);
            Route::resource('document-types', AdminDocumentTypeController::class)->except(['show']);
            Route::get('/system-settings', [AdminSystemSettingController::class, 'index'])->name('system-settings.index');
            Route::put('/system-settings', [AdminSystemSettingController::class, 'update'])->name('system-settings.update');
        });

        Route::middleware('permission:catalog.manage')->group(function () {
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
            Route::get('/catalog/products/{product}/live', ProductLiveController::class)->name('catalog.products.live');
            Route::get('/catalog/products/{product}/edit', [AdminCatalogProductController::class, 'edit'])->name('catalog.products.edit');
            Route::put('/catalog/products/{product}', [AdminCatalogProductController::class, 'update'])->name('catalog.products.update');
            Route::delete('/catalog/products/images/{image}', [AdminCatalogProductController::class, 'deleteImage'])->name('catalog.products.image.delete');
            Route::post('/catalog/products/images/{image}/primary', [AdminCatalogProductController::class, 'setPrimaryImage'])->name('catalog.products.image.primary');
            Route::delete('/catalog/products/documents/{document}', [AdminCatalogProductController::class, 'deleteDocument'])->name('catalog.products.document.delete');

            Route::get('/catalog/attributes', [AdminProductAttributeController::class, 'index'])->name('catalog.attributes.index');
            Route::get('/catalog/attributes/create', [AdminProductAttributeController::class, 'create'])->name('catalog.attributes.create');
            Route::post('/catalog/attributes', [AdminProductAttributeController::class, 'store'])->name('catalog.attributes.store');
            Route::get('/catalog/attributes/{attribute}/edit', [AdminProductAttributeController::class, 'edit'])->name('catalog.attributes.edit');
            Route::put('/catalog/attributes/{attribute}', [AdminProductAttributeController::class, 'update'])->name('catalog.attributes.update');
            Route::delete('/catalog/attributes/{attribute}', [AdminProductAttributeController::class, 'destroy'])->name('catalog.attributes.destroy');

            Route::get('/catalog/analogs', [AdminProductAnalogController::class, 'index'])->name('catalog.analogs.index');
            Route::get('/catalog/analogs/export', [AdminProductAnalogController::class, 'export'])->name('catalog.analogs.export');
            Route::post('/catalog/analogs/import', [AdminProductAnalogController::class, 'import'])->name('catalog.analogs.import');
            Route::get('/catalog/analogs/{product}/search', [AdminProductAnalogController::class, 'search'])->name('catalog.analogs.search');
            Route::get('/catalog/analogs/{product}/edit', [AdminProductAnalogController::class, 'edit'])->name('catalog.analogs.edit');
            Route::put('/catalog/analogs/{product}', [AdminProductAnalogController::class, 'update'])->name('catalog.analogs.update');
        });

        Route::middleware('permission:audit.view')->group(function () {
            Route::get('/audit', [AdminAuditLogController::class, 'index'])->name('audit.index');
            Route::get('/audit/{source}/{id}', [AdminAuditLogController::class, 'show'])
                ->where('source', 'admin_action|partnership|distributor_product')
                ->whereNumber('id')
                ->name('audit.show');
        });
    });

    // Профиль производителя
    Route::middleware(['role.selected', 'manufacturer.cabinet'])->prefix('manufacturer')->name('manufacturer.')->group(function () {
        Route::middleware('manufacturer.cabinet:employees')->group(function () {
            Route::get('/employees', [ManufacturerEmployeeController::class, 'index'])->name('employees.index');
            Route::get('/employees/create', [ManufacturerEmployeeController::class, 'create'])->name('employees.create');
            Route::post('/employees', [ManufacturerEmployeeController::class, 'store'])->name('employees.store');
            Route::get('/employees/{employee}', [ManufacturerEmployeeController::class, 'show'])->whereNumber('employee')->name('employees.show');
            Route::put('/employees/{employee}', [ManufacturerEmployeeController::class, 'update'])->whereNumber('employee')->name('employees.update');
            Route::post('/employees/{employee}/reset-password', [ManufacturerEmployeeController::class, 'resetPassword'])->whereNumber('employee')->name('employees.reset_password');
            Route::post('/employees/{employee}/resend', [ManufacturerEmployeeController::class, 'resendInvitation'])->whereNumber('employee')->name('employees.resend');
            Route::post('/employees/{employee}/block', [ManufacturerEmployeeController::class, 'block'])->whereNumber('employee')->name('employees.block');
            Route::post('/employees/{employee}/unblock', [ManufacturerEmployeeController::class, 'unblock'])->whereNumber('employee')->name('employees.unblock');
            Route::post('/employees/roles', [ManufacturerEmployeeRoleController::class, 'store'])->name('employees.roles.store');
            Route::put('/employees/roles/{role}', [ManufacturerEmployeeRoleController::class, 'update'])->whereNumber('role')->name('employees.roles.update');
            Route::delete('/employees/roles/{role}', [ManufacturerEmployeeRoleController::class, 'destroy'])->whereNumber('role')->name('employees.roles.destroy');
            Route::delete('/employees/{employee}', [ManufacturerEmployeeController::class, 'destroy'])->whereNumber('employee')->name('employees.destroy');
        });

        Route::middleware('manufacturer.cabinet:profile')->group(function () {
            Route::get('/profile', [ManufacturerProfileController::class, 'index'])->name('profile');
            Route::put('/profile/company', [ManufacturerProfileController::class, 'updateCompany'])->name('profile.company.update');
            Route::post('/profile/contacts', [ManufacturerProfileController::class, 'storeContact'])->name('profile.contacts.store');
            Route::put('/profile/contacts/{contact}', [ManufacturerProfileController::class, 'updateContact'])->name('profile.contacts.update');
            Route::delete('/profile/contacts/{contact}', [ManufacturerProfileController::class, 'deleteContact'])->name('profile.contacts.delete');
            Route::put('/profile/regions', [ManufacturerProfileController::class, 'updateRegions'])->name('profile.regions.update');
            Route::put('/profile/delivery', [ManufacturerProfileController::class, 'updateDelivery'])->name('profile.delivery.update');
            Route::post('/profile/documents', [ManufacturerProfileController::class, 'storeDocument'])->name('profile.documents.store');
            Route::delete('/profile/documents/{document}', [ManufacturerProfileController::class, 'deleteDocument'])->name('profile.documents.delete');
        });

        Route::middleware('manufacturer.cabinet:warehouses')->group(function () {
            Route::get('/warehouses', [ManufacturerWarehouseController::class, 'index'])->name('warehouses.index');
            Route::post('/warehouses', [ManufacturerProfileController::class, 'storeWarehouse'])->name('warehouses.store');
            Route::put('/warehouses/{warehouse}', [ManufacturerProfileController::class, 'updateWarehouse'])->name('warehouses.update');
            Route::delete('/warehouses/{warehouse}', [ManufacturerProfileController::class, 'deleteWarehouse'])->name('warehouses.delete');
            Route::get('/warehouses/export', [ManufacturerProfileController::class, 'exportWarehouses'])->name('warehouses.export');
            Route::post('/warehouses/stocks/manual-update', [ManufacturerWarehouseController::class, 'updateStock'])->name('warehouses.stocks.update');
        });

        Route::middleware('manufacturer.cabinet:catalog')->group(function () {
            Route::get('/catalog/products', [ManufacturerProductController::class, 'catalogProducts'])->name('catalog.products');
            Route::get('/catalog/search/suggest', SearchSuggestController::class)->name('catalog.search.suggest');
            Route::get('/catalog/product/{product}', [ManufacturerProductController::class, 'catalogShow'])->name('catalog.show');
            Route::get('/catalog/product/{product}/live', ProductLiveController::class)->name('catalog.product.live');
            Route::get('/catalog/{category?}', [ManufacturerProductController::class, 'catalog'])->name('catalog.index')->where('category', '[a-z0-9\-]+');
        });

        Route::middleware('manufacturer.cabinet:products')->group(function () {
            Route::get('/products', [ManufacturerProductController::class, 'index'])->name('products.index');
            Route::get('/products/export', [ManufacturerProductController::class, 'export'])->name('products.export');
            Route::get('/products/{product}/analogs/search', [ManufacturerProductController::class, 'analogSearch'])->name('products.analogs.search');
        });

        Route::middleware('manufacturer.cabinet:products.manage')->group(function () {
            Route::get('/products/create', [ManufacturerProductController::class, 'create'])->name('products.create');
            Route::post('/products', [ManufacturerProductController::class, 'store'])->name('products.store');
            Route::get('/products/import', [ManufacturerProductController::class, 'importForm'])->name('products.import');
            Route::post('/products/import', [ManufacturerProductController::class, 'import'])->name('products.import.process');
            Route::post('/products/bulk', [ManufacturerProductController::class, 'bulkAction'])->name('products.bulk');
            Route::get('/products/{product}/edit', [ManufacturerProductController::class, 'edit'])->name('products.edit');
            Route::put('/products/{product}', [ManufacturerProductController::class, 'update'])->name('products.update');
            Route::delete('/products/{product}', [ManufacturerProductController::class, 'destroy'])->name('products.destroy');
            Route::post('/products/{productId}/restore', [ManufacturerProductController::class, 'restore'])->whereNumber('productId')->name('products.restore');
            Route::post('/products/{product}/publish', [ManufacturerProductController::class, 'publish'])->name('products.publish');
            Route::post('/products/{product}/hide', [ManufacturerProductController::class, 'hide'])->name('products.hide');
            Route::delete('/products/images/{image}', [ManufacturerProductController::class, 'deleteImage'])->name('products.image.delete');
            Route::post('/products/images/{image}/primary', [ManufacturerProductController::class, 'setPrimaryImage'])->name('products.image.primary');
            Route::delete('/products/documents/{document}', [ManufacturerProductController::class, 'deleteDocument'])->name('products.document.delete');
        });

        // Каталог дистрибьюторов и конечных компаний
        Route::middleware(['manufacturer.cabinet:partners', 'manufacturer.partner:view'])->group(function () {
            Route::get('/partners', [ManufacturerPartnerCatalogController::class, 'index'])->name('partners.index');
            Route::get('/partners/distributors/{distributor}', [ManufacturerPartnerCatalogController::class, 'showDistributor'])->name('partners.distributors.show');
            Route::get('/partners/companies/{company}', [ManufacturerPartnerCatalogController::class, 'showCompany'])->name('partners.companies.show');
        });
        Route::post('/partners/distributors/{distributor}/add', [ManufacturerPartnerCatalogController::class, 'addDistributor'])
            ->middleware(['manufacturer.cabinet:partners', 'manufacturer.partner:add'])
            ->name('partners.distributors.add');
        Route::delete('/partners/distributors/{distributor}', [ManufacturerPartnerCatalogController::class, 'removeDistributor'])
            ->middleware(['manufacturer.cabinet:partners', 'manufacturer.partner:remove'])
            ->name('partners.distributors.remove');
        Route::post('/partners/distributors/{distributor}/exclusive', [ManufacturerPartnerCatalogController::class, 'assignExclusive'])
            ->middleware(['manufacturer.cabinet:partners', 'manufacturer.partner:exclusive'])
            ->name('partners.distributors.exclusive');

        Route::middleware('manufacturer.cabinet:orders')->group(function () {
            Route::get('/orders', [ManufacturerOrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/export', [ManufacturerOrderController::class, 'export'])->name('orders.export');
            Route::get('/orders/{order}', [ManufacturerOrderController::class, 'show'])->name('orders.show');
            Route::get('/orders/{order}/print', [ManufacturerOrderController::class, 'print'])->name('orders.print');
            Route::get('/orders/{order}/history/export', [ManufacturerOrderController::class, 'exportHistory'])->name('orders.history.export');
            Route::get('/orders/{order}/documents/{document}/download', [ManufacturerOrderController::class, 'downloadDocument'])->name('orders.documents.download');
            Route::get('/orders/{order}/documents/{document}/preview', [ManufacturerOrderController::class, 'previewDocument'])->name('orders.documents.preview');
            Route::get('/orders/{order}/messages/attachments/{attachment}/download', [ManufacturerOrderController::class, 'downloadMessageAttachment'])->name('orders.messages.attachments.download');
            Route::get('/orders/{order}/messages/attachments/{attachment}/preview', [ManufacturerOrderController::class, 'previewMessageAttachment'])->name('orders.messages.attachments.preview');
            Route::post('/orders/{order}/messages', [ManufacturerOrderController::class, 'storeMessage'])->name('orders.messages.store');
        });
        Route::middleware('manufacturer.cabinet:orders.confirm')->group(function () {
            Route::post('/orders/{order}/assign-responsible', [ManufacturerOrderController::class, 'assignResponsible'])->name('orders.assign_responsible');
            Route::post('/orders/{order}/confirm', [ManufacturerOrderController::class, 'confirm'])->name('orders.confirm');
            Route::post('/orders/{order}/reject', [ManufacturerOrderController::class, 'reject'])->name('orders.reject');
            Route::post('/orders/{order}/send-for-approval', [ManufacturerOrderController::class, 'sendForApproval'])->name('orders.send_for_approval');
            Route::post('/orders/{order}/mark-in-work', [ManufacturerOrderController::class, 'markInWork'])->name('orders.mark_in_work');
            Route::post('/orders/{order}/mark-ready', [ManufacturerOrderController::class, 'markReady'])->name('orders.mark_ready');
            Route::post('/orders/{order}/mark-shipped', [ManufacturerOrderController::class, 'markShipped'])->name('orders.mark_shipped');
            Route::post('/orders/{order}/complete', [ManufacturerOrderController::class, 'complete'])->name('orders.complete');
            Route::post('/orders/{order}/documents', [ManufacturerOrderController::class, 'storeDocument'])->name('orders.documents.store');
            Route::delete('/orders/{order}/documents/{document}', [ManufacturerOrderController::class, 'destroyDocument'])->name('orders.documents.destroy');
        });
        Route::post('/orders/{order}/claims', [ManufacturerOrderController::class, 'storeClaim'])
            ->middleware('manufacturer.cabinet:claims')
            ->name('orders.claims.store');

        Route::middleware('manufacturer.cabinet:discounts')->group(function () {
            Route::get('/discounts', [ManufacturerDiscountSetController::class, 'index'])->name('discounts.index');
            Route::get('/discounts/export', [ManufacturerDiscountSetController::class, 'export'])->name('discounts.export');
            Route::get('/discounts/products/search', [ManufacturerDiscountSetController::class, 'searchProducts'])->name('discounts.products.search');
            Route::get('/discounts/{discountSet}', [ManufacturerDiscountSetController::class, 'show'])->whereNumber('discountSet')->name('discounts.show');
            Route::get('/discounts/{discountSet}/edit', [ManufacturerDiscountSetController::class, 'edit'])->whereNumber('discountSet')->name('discounts.edit');
            Route::get('/promo-codes', [ManufacturerPromoCodeController::class, 'index'])->name('promo-codes.index');
            Route::get('/promo-codes/{promoCode}/edit', [ManufacturerPromoCodeController::class, 'edit'])->whereNumber('promoCode')->name('promo-codes.edit');
        });
        Route::middleware('manufacturer.cabinet:discounts.manage')->group(function () {
            Route::get('/discounts/create', [ManufacturerDiscountSetController::class, 'create'])->name('discounts.create');
            Route::post('/discounts', [ManufacturerDiscountSetController::class, 'store'])->name('discounts.store');
            Route::post('/discounts/bulk', [ManufacturerDiscountSetController::class, 'bulk'])->name('discounts.bulk');
            Route::put('/discounts/{discountSet}', [ManufacturerDiscountSetController::class, 'update'])->whereNumber('discountSet')->name('discounts.update');
            Route::post('/discounts/{discountSet}/toggle', [ManufacturerDiscountSetController::class, 'toggle'])->whereNumber('discountSet')->name('discounts.toggle');
            Route::post('/discounts/{discountSet}/copy', [ManufacturerDiscountSetController::class, 'copy'])->whereNumber('discountSet')->name('discounts.copy');
            Route::post('/discounts/{discountSet}/preview', [ManufacturerDiscountSetController::class, 'preview'])->whereNumber('discountSet')->name('discounts.preview');

            Route::get('/promo-codes/create', [ManufacturerPromoCodeController::class, 'create'])->name('promo-codes.create');
            Route::post('/promo-codes', [ManufacturerPromoCodeController::class, 'store'])->name('promo-codes.store');
            Route::get('/promo-codes/generate', [ManufacturerPromoCodeController::class, 'generate'])->name('promo-codes.generate');
            Route::put('/promo-codes/{promoCode}', [ManufacturerPromoCodeController::class, 'update'])->whereNumber('promoCode')->name('promo-codes.update');
            Route::post('/promo-codes/{promoCode}/toggle', [ManufacturerPromoCodeController::class, 'toggle'])->whereNumber('promoCode')->name('promo-codes.toggle');
        });
        Route::middleware('manufacturer.cabinet:discounts.delete')->group(function () {
            Route::delete('/discounts/{discountSet}', [ManufacturerDiscountSetController::class, 'destroy'])->whereNumber('discountSet')->name('discounts.destroy');
            Route::delete('/promo-codes/{promoCode}', [ManufacturerPromoCodeController::class, 'destroy'])->whereNumber('promoCode')->name('promo-codes.destroy');
        });
    });

    // Профиль дистрибьютора
    Route::middleware(['role.selected', 'distributor.cabinet'])->prefix('distributor')->name('distributor.')->group(function () {
        Route::middleware('distributor.cabinet:employees')->group(function () {
            Route::get('/employees', [DistributorEmployeeController::class, 'index'])->name('employees.index');
            Route::get('/employees/create', [DistributorEmployeeController::class, 'create'])->name('employees.create');
            Route::post('/employees', [DistributorEmployeeController::class, 'store'])->name('employees.store');
            Route::get('/employees/{employee}', [DistributorEmployeeController::class, 'show'])->whereNumber('employee')->name('employees.show');
            Route::put('/employees/{employee}', [DistributorEmployeeController::class, 'update'])->whereNumber('employee')->name('employees.update');
            Route::post('/employees/{employee}/reset-password', [DistributorEmployeeController::class, 'resetPassword'])->whereNumber('employee')->name('employees.reset_password');
            Route::post('/employees/{employee}/resend', [DistributorEmployeeController::class, 'resendInvitation'])->whereNumber('employee')->name('employees.resend');
            Route::post('/employees/{employee}/block', [DistributorEmployeeController::class, 'block'])->whereNumber('employee')->name('employees.block');
            Route::post('/employees/{employee}/unblock', [DistributorEmployeeController::class, 'unblock'])->whereNumber('employee')->name('employees.unblock');
            Route::post('/employees/roles', [DistributorEmployeeRoleController::class, 'store'])->name('employees.roles.store');
            Route::put('/employees/roles/{role}', [DistributorEmployeeRoleController::class, 'update'])->whereNumber('role')->name('employees.roles.update');
            Route::delete('/employees/roles/{role}', [DistributorEmployeeRoleController::class, 'destroy'])->whereNumber('role')->name('employees.roles.destroy');
            Route::delete('/employees/{employee}', [DistributorEmployeeController::class, 'destroy'])->whereNumber('employee')->name('employees.destroy');
        });

        Route::middleware('distributor.cabinet:profile')->group(function () {
            Route::get('/profile', [DistributorProfileController::class, 'index'])->name('profile');
            Route::put('/profile/company', [DistributorProfileController::class, 'updateCompany'])->name('profile.company.update');
            Route::put('/profile/integration', [DistributorProfileController::class, 'updateIntegration'])->name('profile.integration.update');
            Route::post('/profile/contacts', [DistributorProfileController::class, 'storeContact'])->name('profile.contacts.store');
            Route::put('/profile/contacts/{contact}', [DistributorProfileController::class, 'updateContact'])->name('profile.contacts.update');
            Route::delete('/profile/contacts/{contact}', [DistributorProfileController::class, 'deleteContact'])->name('profile.contacts.delete');
            Route::put('/profile/regions', [DistributorProfileController::class, 'updateRegions'])->name('profile.regions.update');
            Route::put('/profile/product-categories', [DistributorProfileController::class, 'updateProductCategories'])->name('profile.product_categories.update');
            Route::put('/profile/delivery', [DistributorProfileController::class, 'updateDelivery'])->name('profile.delivery.update');
            Route::post('/profile/documents', [DistributorProfileController::class, 'storeDocument'])->name('profile.documents.store');
            Route::delete('/profile/documents/{document}', [DistributorProfileController::class, 'deleteDocument'])->name('profile.documents.delete');
        });

        Route::middleware('distributor.cabinet:warehouses')->group(function () {
            Route::get('/warehouses', [DistributorWarehouseController::class, 'index'])->name('warehouses.index');
            Route::get('/warehouses/export', [DistributorProfileController::class, 'exportWarehouses'])->name('warehouses.export');
            Route::post('/warehouses', [DistributorProfileController::class, 'storeWarehouse'])->name('warehouses.store');
            Route::put('/warehouses/{warehouse}', [DistributorProfileController::class, 'updateWarehouse'])->name('warehouses.update');
            Route::delete('/warehouses/{warehouse}', [DistributorProfileController::class, 'deleteWarehouse'])->name('warehouses.delete');
        });

        Route::middleware('distributor.cabinet:products')->group(function () {
            Route::get('/products', [DistributorProductController::class, 'index'])->name('products.index');
        });

        Route::middleware('distributor.cabinet:discounts')->group(function () {
            Route::get('/discounts', [DistributorDiscountSetController::class, 'index'])->name('discounts.index');
            Route::get('/discounts/export', [DistributorDiscountSetController::class, 'export'])->name('discounts.export');
            Route::get('/discounts/products/search', [DistributorDiscountSetController::class, 'searchProducts'])->name('discounts.products.search');
            Route::get('/discounts/clients/search', [DistributorDiscountSetController::class, 'searchClients'])->name('discounts.clients.search');
            Route::get('/discounts/{discountSet}', [DistributorDiscountSetController::class, 'show'])->whereNumber('discountSet')->name('discounts.show');
            Route::get('/discounts/{discountSet}/edit', [DistributorDiscountSetController::class, 'edit'])->whereNumber('discountSet')->name('discounts.edit');
        });

        Route::middleware('distributor.cabinet:discounts.manage')->group(function () {
            Route::get('/discounts/create', [DistributorDiscountSetController::class, 'create'])->name('discounts.create');
            Route::post('/discounts', [DistributorDiscountSetController::class, 'store'])->name('discounts.store');
            Route::put('/discounts/{discountSet}', [DistributorDiscountSetController::class, 'update'])->whereNumber('discountSet')->name('discounts.update');
            Route::post('/discounts/{discountSet}/toggle', [DistributorDiscountSetController::class, 'toggle'])->whereNumber('discountSet')->name('discounts.toggle');
            Route::post('/discounts/{discountSet}/copy', [DistributorDiscountSetController::class, 'copy'])->whereNumber('discountSet')->name('discounts.copy');
            Route::post('/discounts/{discountSet}/preview', [DistributorDiscountSetController::class, 'preview'])->whereNumber('discountSet')->name('discounts.preview');
            Route::post('/discounts/bulk', [DistributorDiscountSetController::class, 'bulk'])->name('discounts.bulk');
            Route::delete('/discounts/{discountSet}', [DistributorDiscountSetController::class, 'destroy'])->whereNumber('discountSet')->name('discounts.destroy');
        });

        Route::middleware('distributor.cabinet:products.import')->group(function () {
            Route::get('/products/import', [DistributorProductController::class, 'importForm'])->name('products.import');
            Route::post('/products/import', [DistributorProductController::class, 'import'])->name('products.import.process');
        });

        Route::middleware('distributor.cabinet:products')->group(function () {
            Route::get('/products/{product}', [DistributorProductController::class, 'show'])->name('products.show');
        });

        Route::middleware('distributor.cabinet:products.manage')->group(function () {
            Route::put('/products/{product}', [DistributorProductController::class, 'update'])->name('products.update');
            Route::post('/products/{product}/publish', [DistributorProductController::class, 'publish'])->name('products.publish');
            Route::post('/products/{product}/hide', [DistributorProductController::class, 'hide'])->name('products.hide');
            Route::post('/products/{product}/archive', [DistributorProductController::class, 'archive'])->name('products.archive');
            Route::post('/products/{product}/documents', [DistributorProductController::class, 'storeDocument'])->name('products.documents.store');
            Route::delete('/products/{product}/documents/{document}', [DistributorProductController::class, 'deleteDocument'])->name('products.documents.delete');
        });

        Route::middleware('distributor.cabinet:products.prices')->group(function () {
            Route::post('/products/{product}/price', [DistributorProductController::class, 'updatePrice'])->name('products.price.update');
            Route::post('/products/{product}/regional-prices', [DistributorProductController::class, 'updateRegionalPrices'])->name('products.regional-prices.update');
        });

        Route::middleware('distributor.cabinet:warehouses')->group(function () {
            Route::post('/products/{product}/stocks', [DistributorProductController::class, 'updateStocks'])->name('products.stocks.update');
        });

        Route::middleware('distributor.cabinet:orders')->group(function () {
            Route::get('/orders', [DistributorOrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/export', [DistributorOrderController::class, 'export'])->name('orders.export');
            Route::get('/orders/{order}', [DistributorOrderController::class, 'show'])->name('orders.show');
            Route::get('/orders/{order}/print', [DistributorOrderController::class, 'print'])->name('orders.print');
            Route::get('/orders/{order}/documents/{document}/download', [DistributorOrderController::class, 'downloadDocument'])->name('orders.documents.download');
            Route::get('/orders/{order}/documents/{document}/preview', [DistributorOrderController::class, 'previewDocument'])->name('orders.documents.preview');
            Route::get('/orders/{order}/messages/attachments/{attachment}/download', [DistributorOrderController::class, 'downloadMessageAttachment'])->name('orders.messages.attachments.download');
            Route::get('/orders/{order}/messages/attachments/{attachment}/preview', [DistributorOrderController::class, 'previewMessageAttachment'])->name('orders.messages.attachments.preview');
            Route::post('/orders/{order}/messages', [DistributorOrderController::class, 'storeMessage'])->name('orders.messages.store');
        });

        Route::middleware('distributor.cabinet:orders.manage')->group(function () {
            Route::post('/orders/{order}/assign-responsible', [DistributorOrderController::class, 'assignResponsible'])->name('orders.assign_responsible');
            Route::post('/orders/{order}/confirm', [DistributorOrderController::class, 'confirm'])->name('orders.confirm');
            Route::post('/orders/{order}/reject', [DistributorOrderController::class, 'reject'])->name('orders.reject');
            Route::post('/orders/{order}/send-for-approval', [DistributorOrderController::class, 'sendForApproval'])->name('orders.send_for_approval');
            Route::post('/orders/{order}/mark-in-work', [DistributorOrderController::class, 'markInWork'])->name('orders.mark_in_work');
            Route::post('/orders/{order}/mark-ready', [DistributorOrderController::class, 'markReady'])->name('orders.mark_ready');
            Route::post('/orders/{order}/mark-shipped', [DistributorOrderController::class, 'markShipped'])->name('orders.mark_shipped');
            Route::post('/orders/{order}/complete', [DistributorOrderController::class, 'complete'])->name('orders.complete');
            Route::post('/orders/{order}/documents', [DistributorOrderController::class, 'storeDocument'])->name('orders.documents.store');
            Route::delete('/orders/{order}/documents/{document}', [DistributorOrderController::class, 'destroyDocument'])->name('orders.documents.destroy');
        });

        Route::middleware('distributor.cabinet:purchases')->group(function () {
            Route::get('/purchases/cart', [DistributorPurchaseCartController::class, 'index'])->name('purchases.cart.index');
            Route::get('/purchases', [DistributorPurchaseOrderController::class, 'index'])->name('purchases.index');
            Route::get('/purchases/{order}', [DistributorPurchaseOrderController::class, 'show'])->name('purchases.show');
            Route::get('/purchases/{order}/print', [DistributorPurchaseOrderController::class, 'print'])->name('purchases.print');
            Route::get('/purchases/{order}/documents/{document}/download', [DistributorPurchaseOrderController::class, 'downloadDocument'])->name('purchases.documents.download');
            Route::get('/purchases/{order}/documents/{document}/preview', [DistributorPurchaseOrderController::class, 'previewDocument'])->name('purchases.documents.preview');
            Route::get('/purchases/{order}/messages/attachments/{attachment}/download', [DistributorPurchaseOrderController::class, 'downloadMessageAttachment'])->name('purchases.messages.attachments.download');
            Route::get('/purchases/{order}/messages/attachments/{attachment}/preview', [DistributorPurchaseOrderController::class, 'previewMessageAttachment'])->name('purchases.messages.attachments.preview');
            Route::post('/purchases/{order}/messages', [DistributorPurchaseOrderController::class, 'storeMessage'])->name('purchases.messages.store');
        });

        Route::middleware('distributor.cabinet:purchases.manage')->group(function () {
            Route::post('/purchases/cart/items', [DistributorPurchaseCartController::class, 'store'])->name('purchases.cart.items.store');
            Route::put('/purchases/cart/items/{cartItem}', [DistributorPurchaseCartController::class, 'update'])->name('purchases.cart.items.update');
            Route::delete('/purchases/cart/items/{cartItem}', [DistributorPurchaseCartController::class, 'destroy'])->name('purchases.cart.items.destroy');
            Route::get('/purchases/checkout/{manufacturer}', [DistributorPurchaseCheckoutController::class, 'create'])->name('purchases.checkout.create');
            Route::post('/purchases/checkout/{manufacturer}', [DistributorPurchaseCheckoutController::class, 'store'])->name('purchases.checkout.store');
            Route::post('/purchases/{order}/submit', [DistributorPurchaseOrderController::class, 'submit'])->name('purchases.submit');
            Route::post('/purchases/{order}/cancel', [DistributorPurchaseOrderController::class, 'cancel'])->name('purchases.cancel');
            Route::post('/purchases/{order}/approve-changes', [DistributorPurchaseOrderController::class, 'approveChanges'])->name('purchases.approve_changes');
            Route::post('/purchases/{order}/reject-changes', [DistributorPurchaseOrderController::class, 'rejectChanges'])->name('purchases.reject_changes');
            Route::post('/purchases/{order}/confirm-receipt', [DistributorPurchaseOrderController::class, 'confirmReceipt'])->name('purchases.confirm_receipt');
            Route::post('/purchases/{order}/reorder', [DistributorPurchaseOrderController::class, 'reorder'])->name('purchases.reorder');
            Route::post('/purchases/{order}/assign-responsible', [DistributorPurchaseOrderController::class, 'assignResponsible'])->name('purchases.assign_responsible');
            Route::post('/purchases/{order}/items', [DistributorPurchaseOrderController::class, 'storeItem'])->name('purchases.items.store');
            Route::put('/purchases/{order}/items/{item}', [DistributorPurchaseOrderController::class, 'updateItem'])->name('purchases.items.update');
            Route::delete('/purchases/{order}/items/{item}', [DistributorPurchaseOrderController::class, 'destroyItem'])->name('purchases.items.destroy');
            Route::post('/purchases/{order}/documents', [DistributorPurchaseOrderController::class, 'storeDocument'])->name('purchases.documents.store');
            Route::delete('/purchases/{order}/documents/{document}', [DistributorPurchaseOrderController::class, 'destroyDocument'])->name('purchases.documents.destroy');
        });
    });

    // Профиль и сотрудники конечной компании
    Route::middleware(['role.selected', 'role:end_company,company_employee'])->prefix('end-company')->name('end_company.')->group(function () {
        Route::middleware('end_company.cabinet:profile')->group(function () {
            Route::get('/profile', [EndCompanyProfileController::class, 'index'])->name('profile');
            Route::put('/profile/general', [EndCompanyProfileController::class, 'updateGeneral'])->name('profile.general.update');
            Route::put('/profile/legal', [EndCompanyProfileController::class, 'updateLegal'])->name('profile.legal.update');
            Route::post('/profile/contacts', [EndCompanyProfileController::class, 'storeContact'])->name('profile.contacts.store');
            Route::put('/profile/contacts/{contact}', [EndCompanyProfileController::class, 'updateContact'])->name('profile.contacts.update');
            Route::delete('/profile/contacts/{contact}', [EndCompanyProfileController::class, 'deleteContact'])->name('profile.contacts.delete');
            Route::post('/profile/delivery-addresses', [EndCompanyProfileController::class, 'storeDeliveryAddress'])->name('profile.delivery_addresses.store');
            Route::put('/profile/delivery-addresses/{address}', [EndCompanyProfileController::class, 'updateDeliveryAddress'])->name('profile.delivery_addresses.update');
            Route::delete('/profile/delivery-addresses/{address}', [EndCompanyProfileController::class, 'deleteDeliveryAddress'])->name('profile.delivery_addresses.delete');
            Route::post('/profile/delivery-addresses/{address}/default', [EndCompanyProfileController::class, 'setDefaultDeliveryAddress'])->name('profile.delivery_addresses.default');
            Route::put('/profile/integration', [EndCompanyProfileController::class, 'updateIntegration'])->name('profile.integration.update');
            Route::post('/profile/documents', [EndCompanyProfileController::class, 'storeDocument'])->name('profile.documents.store');
            Route::delete('/profile/documents/{document}', [EndCompanyProfileController::class, 'deleteDocument'])->name('profile.documents.delete');
        });

        Route::middleware('end_company.cabinet:employees')->group(function () {
            Route::get('/employees', [EndCompanyEmployeeController::class, 'index'])->name('employees.index');
            Route::get('/employees/create', [EndCompanyEmployeeController::class, 'create'])->name('employees.create');
            Route::post('/employees', [EndCompanyEmployeeController::class, 'store'])->name('employees.store');
            Route::get('/employees/{employee}', [EndCompanyEmployeeController::class, 'show'])->whereNumber('employee')->name('employees.show');
            Route::put('/employees/{employee}', [EndCompanyEmployeeController::class, 'update'])->whereNumber('employee')->name('employees.update');
            Route::post('/employees/{employee}/reset-password', [EndCompanyEmployeeController::class, 'resetPassword'])->whereNumber('employee')->name('employees.reset_password');
            Route::post('/employees/{employee}/resend', [EndCompanyEmployeeController::class, 'resendInvitation'])->whereNumber('employee')->name('employees.resend');
            Route::post('/employees/{employee}/block', [EndCompanyEmployeeController::class, 'block'])->whereNumber('employee')->name('employees.block');
            Route::post('/employees/{employee}/unblock', [EndCompanyEmployeeController::class, 'unblock'])->whereNumber('employee')->name('employees.unblock');
            Route::post('/employees/roles', [EndCompanyEmployeeRoleController::class, 'store'])->name('employees.roles.store');
            Route::put('/employees/roles/{role}', [EndCompanyEmployeeRoleController::class, 'update'])->whereNumber('role')->name('employees.roles.update');
            Route::delete('/employees/roles/{role}', [EndCompanyEmployeeRoleController::class, 'destroy'])->whereNumber('role')->name('employees.roles.destroy');
            Route::delete('/employees/{employee}', [EndCompanyEmployeeController::class, 'destroy'])->whereNumber('employee')->name('employees.destroy');
        });
    });

    // Каталог для дистрибьюторов и конечных компаний
    Route::middleware(['role.selected', 'role:distributor,end_company,company_employee', 'buyer.cabinet'])->prefix('buyer')->name('buyer.')->group(function () {
        Route::get('/catalog/products', [BuyerCatalogController::class, 'products'])->name('catalog.products');
        Route::get('/catalog/search/suggest', SearchSuggestController::class)->name('catalog.search.suggest');
        Route::post('/catalog/region', [BuyerCatalogController::class, 'setRegion'])->name('catalog.region');
        Route::get('/catalog/product/{product}', [BuyerCatalogController::class, 'show'])->name('catalog.show');
        Route::get('/catalog/product/{product}/live', ProductLiveController::class)->name('catalog.product.live');
        Route::get('/catalog/{category?}', [BuyerCatalogController::class, 'index'])->name('catalog.index')->where('category', '[a-z0-9\-]+');
    });

    // Корзина, оформление и заказы конечной компании
    Route::middleware(['role.selected', 'role:end_company,company_employee'])->prefix('buyer')->name('buyer.')->group(function () {
        Route::middleware('end_company.cabinet:cart')->group(function () {
            Route::get('/cart', [BuyerCartController::class, 'index'])->name('cart.index');
            Route::get('/cart/live', [BuyerCartController::class, 'live'])->name('cart.live');
            Route::post('/cart/items', [BuyerCartController::class, 'store'])->name('cart.items.store');
            Route::put('/cart/items/{cartItem}', [BuyerCartController::class, 'update'])->name('cart.items.update');
            Route::delete('/cart/items/{cartItem}', [BuyerCartController::class, 'destroy'])->name('cart.items.destroy');

            Route::get('/checkout', [BuyerCheckoutController::class, 'batchCreate'])->name('checkout.batch');
            Route::post('/checkout', [BuyerCheckoutController::class, 'batchStore'])->name('checkout.batch.store');
            Route::get('/checkout/{distributor}', [BuyerCheckoutController::class, 'create'])->name('checkout.create');
            Route::post('/checkout/{distributor}', [BuyerCheckoutController::class, 'store'])->name('checkout.store');
        });

        Route::middleware('end_company.cabinet:orders')->group(function () {
            Route::get('/orders', [BuyerOrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{order}', [BuyerOrderController::class, 'show'])->name('orders.show');
            Route::get('/orders/{order}/documents/{document}/download', [BuyerOrderController::class, 'downloadDocument'])->name('orders.documents.download');
            Route::get('/orders/{order}/documents/{document}/preview', [BuyerOrderController::class, 'previewDocument'])->name('orders.documents.preview');
            Route::get('/orders/{order}/messages/attachments/{attachment}/download', [BuyerOrderController::class, 'downloadMessageAttachment'])->name('orders.messages.attachments.download');
            Route::get('/orders/{order}/messages/attachments/{attachment}/preview', [BuyerOrderController::class, 'previewMessageAttachment'])->name('orders.messages.attachments.preview');
            Route::post('/orders/{order}/messages', [BuyerOrderController::class, 'storeMessage'])->name('orders.messages.store');
        });

        Route::middleware('end_company.cabinet:orders.create')->group(function () {
            Route::post('/orders/{order}/cancel', [BuyerOrderController::class, 'cancel'])->name('orders.cancel');
            Route::post('/orders/{order}/reorder', [BuyerOrderController::class, 'reorder'])->name('orders.reorder');
            Route::post('/orders/{order}/documents', [BuyerOrderController::class, 'storeDocument'])->name('orders.documents.store');
            Route::delete('/orders/{order}/documents/{document}', [BuyerOrderController::class, 'destroyDocument'])->name('orders.documents.destroy');
        });

        Route::middleware('end_company.cabinet:orders.manage')->group(function () {
            Route::post('/orders/{order}/approve-changes', [BuyerOrderController::class, 'approveChanges'])->name('orders.approve_changes');
            Route::post('/orders/{order}/reject-changes', [BuyerOrderController::class, 'rejectChanges'])->name('orders.reject_changes');
            Route::post('/orders/{order}/confirm-receipt', [BuyerOrderController::class, 'confirmReceipt'])->name('orders.confirm_receipt');
        });
    });
});

Route::get('/login', function () {
    return view('auth.login');
})->name('login');

Route::post('/login', [LoginController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('login.store');

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
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])
    ->middleware('throttle:5,1')
    ->name('password.email');

// Сброс пароля по ссылке из письма (маршрут password.reset нужен для Laravel ResetPassword notification)
Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword'])
    ->middleware('throttle:5,1')
    ->name('password.update');

// Регистрация внешней компании по приглашению
Route::get('/invite/company/{token}', [CompanyInvitationController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('company-invitation.show');
Route::post('/invite/company/{token}', [CompanyInvitationController::class, 'accept'])
    ->middleware('throttle:10,1')
    ->name('company-invitation.accept');

Route::get('/invite/employee/{token}', [EmployeeInvitationController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('employee-invitation.show');
Route::post('/invite/employee/{token}', [EmployeeInvitationController::class, 'accept'])
    ->middleware('throttle:10,1')
    ->name('employee-invitation.accept');
