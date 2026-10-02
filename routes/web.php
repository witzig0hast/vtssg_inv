<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\AssetModelController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ConsumableController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportExportController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\KitTemplateController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StocktakeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:20,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/two-factor', [LoginController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('/two-factor', [LoginController::class, 'verifyChallenge'])->name('two-factor.verify')->middleware('throttle:20,1');

    // Profil, 2FA, API-Tokens
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('edit');
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        Route::put('/password', [ProfileController::class, 'password'])->name('password');
        Route::post('/2fa/start', [ProfileController::class, 'twoFactorStart'])->name('2fa.start');
        Route::post('/2fa/confirm', [ProfileController::class, 'twoFactorConfirm'])->name('2fa.confirm');
        Route::delete('/2fa', [ProfileController::class, 'twoFactorDisable'])->name('2fa.disable');
        Route::post('/tokens', [ProfileController::class, 'tokenCreate'])->name('tokens.create');
        Route::delete('/tokens/{id}', [ProfileController::class, 'tokenDelete'])->name('tokens.delete');
    });

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/search', SearchController::class)->name('search');

    // ── Assets ──────────────────────────────────────────────
    Route::middleware('can:assets.view')->group(function () {
        Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
        Route::get('/assets/export', [ImportExportController::class, 'export'])->name('assets.export')->middleware('can:assets.export');
        Route::get('/models', [AssetModelController::class, 'index'])->name('models.index')->middleware('can:models.view');
    });
    Route::middleware('can:assets.create')->group(function () {
        Route::get('/assets/create', [AssetController::class, 'create'])->name('assets.create');
        Route::post('/assets', [AssetController::class, 'store'])->name('assets.store');
    });
    Route::middleware('can:assets.import')->group(function () {
        Route::get('/assets/import', [ImportExportController::class, 'form'])->name('assets.import');
        Route::get('/assets/import/template', [ImportExportController::class, 'template'])->name('assets.import.template');
        Route::post('/assets/import', [ImportExportController::class, 'import'])->name('assets.import.store')->middleware('throttle:10,1');
    });
    Route::post('/assets/bulk', [AssetController::class, 'bulk'])->name('assets.bulk')->middleware('can:assets.view');
    Route::get('/assets/{asset}', [AssetController::class, 'show'])->name('assets.show')->middleware('can:assets.view');
    Route::middleware('can:assets.update')->group(function () {
        Route::get('/assets/{asset}/edit', [AssetController::class, 'edit'])->name('assets.edit');
        Route::put('/assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
        Route::post('/assets/{asset}/block', [AssetController::class, 'block'])->name('assets.block');
    });
    Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy')->middleware('can:assets.delete');

    Route::middleware('can:assets.checkout')->group(function () {
        Route::get('/assets/{asset}/checkout', [CheckoutController::class, 'form'])->name('assets.checkout');
        Route::post('/assets/{asset}/checkout', [CheckoutController::class, 'store'])->name('assets.checkout.store');
        Route::post('/checkouts/{checkout}/checkin', [CheckoutController::class, 'checkin'])->name('checkouts.checkin');
        Route::get('/checkouts/open', [CheckoutController::class, 'overdue'])->name('checkouts.open');
    });

    Route::middleware('can:assets.label')->group(function () {
        Route::get('/labels', [LabelController::class, 'print'])->name('labels.print');
        Route::get('/assets/{asset}/label', [LabelController::class, 'single'])->name('assets.label');
    });

    // ── Modelle ─────────────────────────────────────────────
    Route::middleware('can:models.manage')->group(function () {
        Route::get('/models/create', [AssetModelController::class, 'create'])->name('models.create');
        Route::post('/models', [AssetModelController::class, 'store'])->name('models.store');
        Route::get('/models/{model}/edit', [AssetModelController::class, 'edit'])->name('models.edit');
        Route::put('/models/{model}', [AssetModelController::class, 'update'])->name('models.update');
        Route::delete('/models/{model}', [AssetModelController::class, 'destroy'])->name('models.destroy');
    });
    Route::get('/models/{model}/image', [AssetModelController::class, 'image'])->name('models.image')->middleware('can:models.view');
    Route::get('/models/{model}', [AssetModelController::class, 'show'])->name('models.show')->middleware('can:models.view');

    // ── Scannen ─────────────────────────────────────────────
    Route::get('/scan', [ScanController::class, 'index'])->name('scan')->middleware('can:assets.view');
    Route::get('/scan/lookup', [ScanController::class, 'lookup'])->name('scan.lookup')->middleware(['can:assets.view', 'throttle:240,1']);
    Route::post('/scan/action', [ScanController::class, 'action'])->name('scan.action')->middleware(['can:assets.checkout', 'throttle:240,1']);

    // ── Stammdaten (generisch) ──────────────────────────────
    Route::prefix('master/{resource}')->name('master.')->where(['resource' => 'categories|manufacturers|suppliers|locations|status-labels|customers|inspection-types'])->group(function () {
        Route::get('/', [MasterDataController::class, 'index'])->name('index');
        Route::get('/create', [MasterDataController::class, 'create'])->name('create');
        Route::post('/', [MasterDataController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [MasterDataController::class, 'edit'])->name('edit')->whereNumber('id');
        Route::put('/{id}', [MasterDataController::class, 'update'])->name('update')->whereNumber('id');
        Route::delete('/{id}', [MasterDataController::class, 'destroy'])->name('destroy')->whereNumber('id');
    });

    // ── Verbrauchsmaterial ─────────────────────────────────
    Route::middleware('can:consumables.manage')->group(function () {
        Route::get('/consumables/create', [ConsumableController::class, 'create'])->name('consumables.create');
        Route::post('/consumables', [ConsumableController::class, 'store'])->name('consumables.store');
        Route::get('/consumables/{consumable}/edit', [ConsumableController::class, 'edit'])->name('consumables.edit');
        Route::put('/consumables/{consumable}', [ConsumableController::class, 'update'])->name('consumables.update');
        Route::delete('/consumables/{consumable}', [ConsumableController::class, 'destroy'])->name('consumables.destroy');
    });
    Route::middleware('can:consumables.view')->group(function () {
        Route::get('/consumables', [ConsumableController::class, 'index'])->name('consumables.index');
        Route::get('/consumables/{consumable}', [ConsumableController::class, 'show'])->name('consumables.show');
    });
    Route::post('/consumables/{consumable}/movement', [ConsumableController::class, 'movement'])->name('consumables.movement')->middleware('can:consumables.manage');

    // ── Projekte ────────────────────────────────────────────
    Route::middleware('can:projects.create')->group(function () {
        Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::post('/projects/{project}/duplicate', [ProjectController::class, 'duplicate'])->name('projects.duplicate');
    });
    Route::middleware('can:projects.view')->group(function () {
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('/projects/calendar', [ProjectController::class, 'calendar'])->name('projects.calendar');
        Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show')->whereNumber('project');
        Route::get('/projects/{project}/document/{type}', [ProjectController::class, 'document'])->name('projects.document');
    });
    Route::middleware('can:projects.update')->group(function () {
        Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
        Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::post('/projects/{project}/status', [ProjectController::class, 'status'])->name('projects.status');
        Route::post('/projects/{project}/items', [ProjectController::class, 'addItem'])->name('projects.items.add');
        Route::put('/projects/{project}/items/{item}', [ProjectController::class, 'updateItem'])->name('projects.items.update');
        Route::delete('/projects/{project}/items/{item}', [ProjectController::class, 'removeItem'])->name('projects.items.remove');
        Route::post('/projects/{project}/template', [ProjectController::class, 'applyTemplate'])->name('projects.template.apply');
        Route::post('/projects/{project}/save-template', [ProjectController::class, 'saveAsTemplate'])->name('projects.template.save');
    });
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy')->middleware('can:projects.delete');
    Route::middleware('can:projects.pack')->group(function () {
        Route::get('/projects/{project}/pack', [ProjectController::class, 'pack'])->name('projects.pack');
        Route::post('/projects/{project}/pack', [ProjectController::class, 'packScan'])->name('projects.pack.scan')->middleware('throttle:240,1');
    });

    // Packlisten-Vorlagen
    Route::middleware('can:projects.update')->prefix('kits')->name('kits.')->group(function () {
        Route::get('/', [KitTemplateController::class, 'index'])->name('index');
        Route::get('/create', [KitTemplateController::class, 'create'])->name('create');
        Route::post('/', [KitTemplateController::class, 'store'])->name('store');
        Route::get('/{kit}/edit', [KitTemplateController::class, 'edit'])->name('edit');
        Route::put('/{kit}', [KitTemplateController::class, 'update'])->name('update');
        Route::delete('/{kit}', [KitTemplateController::class, 'destroy'])->name('destroy');
    });

    // ── Prüfungen & Wartung ─────────────────────────────────
    Route::get('/inspections', [InspectionController::class, 'index'])->name('inspections.index')->middleware('can:inspections.view');
    Route::middleware('can:inspections.record')->group(function () {
        Route::get('/inspections/create', [InspectionController::class, 'create'])->name('inspections.create');
        Route::post('/inspections', [InspectionController::class, 'store'])->name('inspections.store');
    });
    Route::middleware('can:maintenance.report')->group(function () {
        Route::get('/maintenance/create', [MaintenanceController::class, 'create'])->name('maintenance.create');
        Route::post('/maintenance', [MaintenanceController::class, 'store'])->name('maintenance.store');
    });
    Route::middleware('can:maintenance.view')->group(function () {
        Route::get('/maintenance', [MaintenanceController::class, 'index'])->name('maintenance.index');
        Route::get('/maintenance/{ticket}', [MaintenanceController::class, 'show'])->name('maintenance.show')->whereNumber('ticket');
    });
    Route::middleware('can:maintenance.manage')->group(function () {
        Route::put('/maintenance/{ticket}', [MaintenanceController::class, 'update'])->name('maintenance.update');
    });
    Route::post('/maintenance/{ticket}/comments', [MaintenanceController::class, 'comment'])->name('maintenance.comment')->middleware('can:maintenance.report');

    // ── Inventur ────────────────────────────────────────────
    Route::middleware('can:stocktakes.view')->group(function () {
        Route::get('/stocktakes', [StocktakeController::class, 'index'])->name('stocktakes.index');
        Route::get('/stocktakes/{stocktake}', [StocktakeController::class, 'show'])->name('stocktakes.show');
    });
    Route::middleware('can:stocktakes.manage')->group(function () {
        Route::post('/stocktakes', [StocktakeController::class, 'store'])->name('stocktakes.store');
        Route::post('/stocktakes/{stocktake}/scan', [StocktakeController::class, 'scan'])->name('stocktakes.scan')->middleware('throttle:240,1');
        Route::post('/stocktakes/{stocktake}/close', [StocktakeController::class, 'close'])->name('stocktakes.close');
    });

    // ── Dateien ─────────────────────────────────────────────
    Route::post('/attachments/{type}/{id}', [AttachmentController::class, 'store'])->name('attachments.store')->whereNumber('id');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    // ── Berichte / Audit / Admin ────────────────────────────
    Route::get('/reports', [ReportController::class, 'index'])->name('reports')->middleware('can:reports.view');
    Route::get('/audit', [AuditController::class, 'index'])->name('audit.index')->middleware('can:audit.view');
    Route::middleware('can:users.manage')->group(function () {
        Route::resource('users', UserController::class)->except('show');
    });
    Route::middleware('can:settings.manage')->group(function () {
        Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    });
});
