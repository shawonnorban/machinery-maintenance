<?php

declare(strict_types=1);

use App\Modules\PreBudget\Http\Controllers\Api\BomApiController;
use App\Modules\PreBudget\Http\Controllers\Api\BomColumnApiController;
use App\Modules\PreBudget\Http\Controllers\Api\BudgetApiController;
use App\Modules\PreBudget\Http\Controllers\Api\ColumnApiController;
use App\Modules\PreBudget\Http\Controllers\Api\DashboardApiController;
use App\Modules\PreBudget\Http\Controllers\Api\DraftApiController;
use App\Modules\PreBudget\Http\Controllers\Api\RequisitionApiController;
use Illuminate\Support\Facades\Route;

/*
 * Pre-Budget (AnnotechERP 33/34, from Norban's Knit Woven and Accessories
 * Pre-Budget): the requisition sheet (meta, rows, header filter values,
 * writes), the drafts of Add Requisition, the budgets (Create Budget, costing,
 * stock), the accessories BOMs and the requisition Column Setup. The masters are screen-kit
 * resources under /erp/pb-*.
 */

Route::middleware(['api.auth', 'throttle:api'])->prefix('pre-budget')->name('pre-budget.')->group(function (): void {
    Route::get('/dashboard', DashboardApiController::class)->name('dashboard');
    Route::get('/meta', [RequisitionApiController::class, 'meta'])->name('meta');
    Route::get('/orders', [RequisitionApiController::class, 'orders'])->name('orders');
    Route::post('/orders/pick', [RequisitionApiController::class, 'pickOrder'])->name('orders.pick');
    Route::get('/job-numbers', [RequisitionApiController::class, 'jobNumbers'])->name('job-numbers');
    Route::get('/requisitions', [RequisitionApiController::class, 'index'])->name('requisitions.index');
    Route::get('/requisitions/distinct', [RequisitionApiController::class, 'distinct'])->name('requisitions.distinct');
    Route::post('/requisitions', [RequisitionApiController::class, 'store'])->name('requisitions.store');
    Route::patch('/requisitions', [RequisitionApiController::class, 'update'])->name('requisitions.update');
    Route::post('/requisitions/copy', [RequisitionApiController::class, 'copy'])->name('requisitions.copy');
    Route::delete('/requisitions', [RequisitionApiController::class, 'destroy'])->name('requisitions.destroy');
    Route::post('/requisitions/artwork', [RequisitionApiController::class, 'artwork'])->name('requisitions.artwork');

    Route::get('/drafts', [DraftApiController::class, 'index'])->name('drafts.index');
    Route::post('/drafts', [DraftApiController::class, 'save'])->name('drafts.save');
    Route::get('/drafts/{no}', [DraftApiController::class, 'show'])->whereNumber('no')->name('drafts.show');
    Route::delete('/drafts/{no}', [DraftApiController::class, 'destroy'])->whereNumber('no')->name('drafts.destroy');

    Route::get('/budgets', [BudgetApiController::class, 'index'])->name('budgets.index');
    Route::get('/budgets/candidates', [BudgetApiController::class, 'candidates'])->name('budgets.candidates');
    Route::get('/budgets/work', [BudgetApiController::class, 'work'])->name('budgets.work');
    Route::get('/budgets/finalized', [BudgetApiController::class, 'finalized'])->name('budgets.finalized');
    Route::post('/budgets/{no}/finalize', [BudgetApiController::class, 'finalize'])->name('budgets.finalize');
    Route::post('/budgets/{no}/withdraw', [BudgetApiController::class, 'withdraw'])->name('budgets.withdraw');
    Route::post('/budgets/{no}/decision', [BudgetApiController::class, 'decide'])->name('budgets.decision');
    Route::post('/budgets/{no}/revise', [BudgetApiController::class, 'revise'])->name('budgets.revise');
    Route::get('/budgets/{no}/costing', [BudgetApiController::class, 'costing'])->name('budgets.costing');
    Route::put('/budgets/{no}/costing', [BudgetApiController::class, 'saveCosting'])->name('budgets.costing.save');
    Route::get('/budgets/{no}/stock', [BudgetApiController::class, 'stock'])->name('budgets.stock');
    Route::put('/budgets/{no}/stock', [BudgetApiController::class, 'saveStock'])->name('budgets.stock.save');
    Route::get('/budgets/{no}/overview', [BudgetApiController::class, 'overview'])->name('budgets.overview');
    Route::get('/budgets/{no}/overview/export', [BudgetApiController::class, 'overviewExport'])->name('budgets.overview.export');
    Route::get('/budgets/{no}/declaration', [BudgetApiController::class, 'declaration'])->name('budgets.declaration');
    Route::get('/budgets/{no}/accessories', [BomApiController::class, 'budget'])->name('budgets.accessories');
    Route::post('/budgets/{no}/boms', [BomApiController::class, 'store'])->name('boms.store');
    Route::post('/budgets', [BudgetApiController::class, 'store'])->name('budgets.store');
    Route::get('/budgets/{no}', [BudgetApiController::class, 'show'])->name('budgets.show');
    Route::patch('/budgets/{no}', [BudgetApiController::class, 'update'])->name('budgets.update');
    Route::post('/budgets/{no}/styles', [BudgetApiController::class, 'addStyles'])->name('budgets.styles.add');
    Route::delete('/budgets/{no}/styles', [BudgetApiController::class, 'removeStyles'])->name('budgets.styles.remove');
    Route::delete('/budgets/{no}', [BudgetApiController::class, 'destroy'])->name('budgets.destroy');

    Route::get('/boms/pending', [BomApiController::class, 'pending'])->name('boms.pending');
    Route::get('/boms', [BomApiController::class, 'index'])->name('boms.index');
    Route::get('/boms/{no}', [BomApiController::class, 'show'])->name('boms.show');
    Route::put('/boms/{no}', [BomApiController::class, 'update'])->name('boms.update');
    Route::delete('/boms/{no}', [BomApiController::class, 'destroy'])->name('boms.destroy');

    Route::get('/columns', [ColumnApiController::class, 'index'])->name('columns.index');
    Route::get('/columns/history', [ColumnApiController::class, 'history'])->name('columns.history');
    Route::post('/columns/check', [ColumnApiController::class, 'check'])->name('columns.check');
    Route::post('/columns', [ColumnApiController::class, 'store'])->name('columns.store');
    Route::put('/columns/freeze', [ColumnApiController::class, 'freeze'])->name('columns.freeze');
    Route::patch('/columns/{key}', [ColumnApiController::class, 'update'])->name('columns.update');
    Route::patch('/columns/{key}/visibility', [ColumnApiController::class, 'visibility'])->name('columns.visibility');
    Route::delete('/columns/{key}', [ColumnApiController::class, 'destroy'])->name('columns.destroy');
    Route::post('/groups', [ColumnApiController::class, 'storeGroup'])->name('groups.store');
    Route::patch('/groups/{group}', [ColumnApiController::class, 'updateGroup'])->name('groups.update');

    // Acc Budget Column Setup: the BOM worksheet's columns (the same Column Setup, sheet BOM) and its Unit Cost Rules.
    Route::prefix('bom-columns')->name('bom-columns.')->group(function (): void {
        Route::get('/', [BomColumnApiController::class, 'index'])->name('index');
        Route::get('/history', [BomColumnApiController::class, 'history'])->name('history');
        Route::post('/check', [BomColumnApiController::class, 'check'])->name('check');
        Route::post('/', [BomColumnApiController::class, 'store'])->name('store');
        Route::put('/freeze', [BomColumnApiController::class, 'freeze'])->name('freeze');
        Route::post('/groups', [BomColumnApiController::class, 'storeGroup'])->name('groups.store');
        Route::patch('/groups/{group}', [BomColumnApiController::class, 'updateGroup'])->name('groups.update');
        Route::put('/unit-rules/{uom}', [BomColumnApiController::class, 'saveRule'])->name('rules.save');
        Route::delete('/unit-rules/{uom}', [BomColumnApiController::class, 'deleteRule'])->name('rules.delete');
        Route::patch('/{key}', [BomColumnApiController::class, 'update'])->name('update');
        Route::patch('/{key}/visibility', [BomColumnApiController::class, 'visibility'])->name('visibility');
        Route::delete('/{key}', [BomColumnApiController::class, 'destroy'])->name('destroy');
    });
});
