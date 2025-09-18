<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\LoginRegisterController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [LoginRegisterController::class, 'register']);
Route::post('/login', [LoginRegisterController::class, 'login']);
// Route::match(['GET','POST'],'/payment/callback', [WalletController::class, 'webhook']);
Route::post('/payment/webhook', [WalletController::class, 'verifyPayment']);
Route::get('/payment/callback', [WalletController::class, 'handleCallback']);
Route::get('/payment/verify/{reference}', [WalletController::class, 'verifyPayment']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [LoginRegisterController::class, 'logout']);
    Route::post('/current-period', [SettingController::class, 'updatePeriod']);
    Route::get('/current-period', [SettingController::class, 'getCurrentPeriod']);
    Route::post('/payment', [WalletController::class, 'initiatePayment']);
    Route::post('/reinvest', [WalletController::class, 'reinvest']);
    Route::post('/withdraw', [WalletController::class, 'withdraw'])->middleware('throttle:10,1');
    Route::get('/bank-list', [WalletController::class, 'getBanks']);
    Route::post('/update-bank-details', [ProfileController::class, 'updateBankDetails']);
    Route::get('/contributions', [WalletController::class, 'getContributions']);
    Route::get('/wallet', [WalletController::class, 'getWalletDetails']);
    Route::get('/profit-details', [WalletController::class, 'getProfits']);
    Route::get('/profit-history', [WalletController::class, 'getProfitHistory']);
    Route::get('/profile', [ProfileController::class, 'index']);
    Route::post('/update-personal', [ProfileController::class, 'updatePersonal']);
    Route::post('/change-password', [ProfileController::class, 'changePassword']);
    Route::middleware('role:admin,super_admin')->group(function () {
        Route::post('/update-current-period', [WalletController::class, 'updateCurrentPeriod']);
        Route::post('/approve-member', [AdminController::class, 'approveMember']);
        Route::get('/pending-members', [AdminController::class, 'getPendingMembers']);
        Route::get('/members', [AdminController::class, 'getMembers']);
        Route::get('/member-profile', [AdminController::class, 'getMemberProfile']);
        Route::get('/member-transactions', [AdminController::class, 'getTransactionHistory']);
        Route::post('/profits-distribution', [WalletController::class, 'distributeProfits']);
//        Route::post('/members', [MemberController::class, 'getMembers']);
    });
});

// Route::middleware(['auth:sanctum', 'role:admin'])->get('/test-role', function () {
//     return response()->json(['message' => 'Role middleware passed']);
// });
