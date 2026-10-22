<?php

use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\RepairController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

// Semua halaman sudah wajib login Portal (config/sso.php, protect_all). Level akses:
//   admin    : semuanya (unit, checklist, tindak lanjut)
//   pengawas : melihat semua P2H, menindaklanjuti temuan, mengisi P2H
//   operator : mengisi P2H dan melihat riwayat miliknya sendiri
//   viewer   : melihat semua (tanpa mengubah)
Route::redirect('/', '/p2h')->name('home');

Route::middleware(['portal.role:admin,pengawas,operator,viewer', 'throttle:120,1'])->prefix('p2h')->name('p2h.')->group(function () {
    Route::get('/', [DashboardController::class, 'home'])->name('home');

    // Riwayat
    Route::get('/riwayat', [InspectionController::class, 'index'])->name('index');
    Route::get('/riwayat/ekspor', [InspectionController::class, 'export'])->name('export');
    Route::get('/riwayat/{inspection}', [InspectionController::class, 'show'])->name('show');
    Route::get('/riwayat/{inspection}/cetak', [InspectionController::class, 'print'])->name('print');
    Route::get('/foto/{answer}', [InspectionController::class, 'photo'])->name('photo');

});

// Mengisi P2H: bukan viewer.
Route::middleware(['portal.role:admin,pengawas,operator', 'throttle:60,1'])->prefix('p2h')->name('p2h.')->group(function () {
    Route::get('/isi', [InspectionController::class, 'create'])->name('fill');
    Route::post('/isi', [InspectionController::class, 'store'])->name('store');
});

// Tindak lanjut temuan.
Route::middleware(['portal.role:admin,pengawas', 'throttle:60,1'])->prefix('p2h')->name('p2h.')->group(function () {
    Route::post('/riwayat/{inspection}/tindak-lanjut', [InspectionController::class, 'followUp'])->name('followup');
    Route::post('/riwayat/{inspection}/dikerjakan', [InspectionController::class, 'startFollowUp'])->name('followup.start');
});

// Antrian perbaikan dan laporan: yang melihat semua data.
Route::middleware(['portal.role:admin,pengawas,viewer', 'throttle:60,1'])->prefix('p2h')->name('p2h.')->group(function () {
    Route::get('/perbaikan', [RepairController::class, 'index'])->name('repairs');
    Route::get('/laporan', [ReportController::class, 'index'])->name('report');
    Route::get('/laporan/rekap.csv', [ReportController::class, 'exportRecap'])->name('report.recap');
    Route::get('/laporan/temuan.csv', [ReportController::class, 'exportFindings'])->name('report.findings');
});

// Master unit dan checklist (lihat): operator tidak perlu melihatnya.
Route::middleware(['portal.role:admin,pengawas,viewer', 'throttle:60,1'])->prefix('p2h')->name('p2h.')->group(function () {
    Route::get('/unit', [UnitController::class, 'index'])->name('units');
    Route::get('/checklist', [ChecklistController::class, 'index'])->name('checklist');
});

// Kelola master: hanya administrator.
Route::middleware(['portal.role:admin', 'throttle:60,1'])->prefix('p2h')->name('p2h.')->group(function () {
    Route::get('/unit/impor', [UnitController::class, 'importForm'])->name('units.import');
    Route::get('/unit/impor/contoh.csv', [UnitController::class, 'importTemplate'])->name('units.import.template');
    Route::post('/unit/impor', [UnitController::class, 'import'])->name('units.import.store');
    Route::post('/unit/{unit}/servis', [UnitController::class, 'serviced'])->name('units.serviced');
    Route::get('/unit/baru', [UnitController::class, 'create'])->name('units.create');
    Route::post('/unit', [UnitController::class, 'store'])->name('units.store');
    Route::get('/unit/{unit}/ubah', [UnitController::class, 'edit'])->name('units.edit');
    Route::put('/unit/{unit}', [UnitController::class, 'update'])->name('units.update');
    Route::post('/unit/{unit}/aktif', [UnitController::class, 'toggle'])->name('units.toggle');

    Route::post('/checklist/versi', [ChecklistController::class, 'storeVersion'])->name('checklist.version.store');
    Route::post('/checklist/versi/{version}/aktifkan', [ChecklistController::class, 'activate'])->name('checklist.version.activate');
    Route::post('/checklist/versi/{version}/item', [ChecklistController::class, 'storeItem'])->name('checklist.item.store');
    Route::put('/checklist/item/{item}', [ChecklistController::class, 'updateItem'])->name('checklist.item.update');
    Route::delete('/checklist/item/{item}', [ChecklistController::class, 'destroyItem'])->name('checklist.item.destroy');
});
