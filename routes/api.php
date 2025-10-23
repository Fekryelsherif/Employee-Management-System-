<?php

use App\Http\Controllers\Api\AreaManager\BranchManagerController;
use App\Http\Controllers\Api\AreaManager\RegionStatsController;
use App\Http\Controllers\Api\AreaManager\RegionTargetController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
//use App\Http\Controllers\Api\Employee\ChallengeRespondController;
use App\Http\Controllers\Api\BranchManager\BranchDashboardController;
use App\Http\Controllers\Api\BranchManager\ChallengeController;
use App\Http\Controllers\Api\BranchManager\EmployeeController;
use App\Http\Controllers\Api\BranchManager\TargetController;
use App\Http\Controllers\Api\ChallengeProgressController;
use App\Http\Controllers\Api\Employee\ChallengeRespondController;
use App\Http\Controllers\Api\Employee\ClientController;
use App\Http\Controllers\Api\Employee\EmployeeDashboardController;
use App\Http\Controllers\Api\Employee\EmployeeTargetController;
use App\Http\Controllers\Api\Employee\LoanController;
use App\Http\Controllers\Api\Employee\NoteController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\Owner\OwnerDashboardController;
use App\Http\Controllers\Api\Owner\RegionController;
use App\Http\Controllers\Api\Owner\RegionManagerController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SalesController;
use App\Http\Controllers\Api\ServiceCommissionController;
use App\Http\Controllers\Api\TargetRecreateController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;













//use App\Http\controllers\Api\Employee\challengeRespondController;












/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

//_____________________________________***_____________________________________
// Routes for Employee




//_____________________________________***_____________________________________
//Authenticated Routes for Employee

Route::prefix('employee')->group(function () {
        Route::post('/register', [AuthController::class, 'registerEmployee']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/logout', [AuthController::class, 'logout'])->middleware(['auth:sanctum', 'employee']);
        // Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        // Route::post('/reset-password', [AuthController::class, 'resetPassword']);
 });


 //_____________________________________***_____________________________________
 //  Clients Routes

 Route::prefix('employee')->middleware('auth:sanctum')->group(function () {
    Route::get('clients', [ClientController::class, 'index']);
    Route::post('clients', [ClientController::class, 'store']);
    Route::get('clients/{id}', [ClientController::class, 'show']);
    Route::put('clients/{id}', [ClientController::class, 'update']);
    Route::delete('clients/{id}', [ClientController::class, 'destroy']);
});




//_____________________________________***_____________________________________
// Routes of Clients & Services

 Route::prefix('employee')->middleware('auth:sanctum')->group(function () {
    Route::post('/storeClientsServices', [ClientController::class, 'storeClientService']);        // إضافة
    Route::get('/ClientsServices', [ClientController::class, 'indexOfClientService']);          // عرض
    Route::get('clients', [ClientController::class, 'indexClients']);
    Route::get('services', [ClientController::class, 'indexServices']);
    Route::put('client/{clientId}', [ClientController::class, 'updateClientServices']);
    Route::put('service/{serviceId}', [ClientController::class, 'updateServiceClients']);
    Route::delete('{clientId}/{serviceId}', [ClientController::class, 'detachServiceFromClient']);
});





//_____________________________________***_____________________________________
// Routes of Employee Profile

Route::prefix('employee')->middleware('auth:sanctum')->group(function () {

     Route::get('/profile', [ClientController::class, 'profile']);
    Route::put('/profile', [ClientController::class, 'updateProfile']);
    Route::delete('/profile', [ClientController::class, 'deleteProfile']);

});






//_____________________________________***_____________________________________
// Routes of Notes & Loans & Notifications

Route::middleware('auth:sanctum')->group(function () {
    // Notes
    Route::post('notes', [NoteController::class, 'store']);
    Route::get('notes', [NoteController::class, 'index']);
    Route::put('notes/{id}/review', [NoteController::class, 'markReviewed']);

    // Loans
    Route::post('loans', [LoanController::class, 'store']);
    Route::get('loans', [LoanController::class, 'index']);
    Route::put('loans/{id}/approve', [LoanController::class, 'approve']);
    Route::put('loans/{id}/reject', [LoanController::class, 'reject']);

    // Notifications
    Route::get('notifications', [NotificationController::class, 'all']);
    Route::get('notifications/unread', [NotificationController::class, 'unread']);
    Route::put('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::get('notifications/countofUnread', [NotificationController::class, 'countofUnread']);

});





//___________________________________***_____________________________________

// Sales exports & actions
Route::prefix('employee')->middleware('auth:sanctum')->group(function () {
    Route::post('sales', [SalesController::class,'store']); // تسجيل بيع
    Route::get('sales/export/csv', [SalesController::class,'exportCsv']); // export CSV (employee or BM)
    Route::get('sales/export/pdf', [SalesController::class,'exportPdf']); // export PDF
});



// Employee Dashboard
Route::middleware('auth:sanctum')->prefix('employee')->group(function () {
    Route::get('dashboard/overview', [EmployeeDashboardController::class, 'employeeOverview']);
    Route::get('dashboard/performance', [EmployeeDashboardController::class, 'employeePerformanceDetails']);
    Route::get('dashboard/services', [EmployeeDashboardController::class, 'employeeSoldServices']);
    Route::get('dashboard/sales', [EmployeeDashboardController::class, 'employeeSalesDetails']);
    Route::get('dashboard/export/csv', [EmployeeDashboardController::class, 'exportEmployeeDashboardCsv']);
    Route::get('dashboard/export/pdf', [EmployeeDashboardController::class, 'exportEmployeeDashboardpdf']);


});




//_____________________________________***_____________________________________
//_____________________________________***_____________________________________


Route::middleware('auth:sanctum')->prefix('branch-manager')->group(function(){
    // Branch manager targets
    Route::get('/targets', [TargetController::class,'index']);
    Route::post('/targets', [TargetController::class,'store']);
    Route::get('/targets/{id}', [TargetController::class,'show']);
    Route::put('/targets/{id}', [TargetController::class,'update']);
    Route::delete('/targets/{id}', [TargetController::class,'destroy']);
    // Route::get('/targets/{id}/export/pdf', [TargetController::class,'exportPdf']);
    // Route::get('/targets/{id}/export/csv', [TargetController::class,'exportCsv']);
});

Route::middleware('auth:sanctum')->prefix('employee')->group(function () {
    Route::get('/targets/recreate', [TargetRecreateController::class, 'index']);
    Route::get('/targets/recreate/{id}', [TargetRecreateController::class, 'show']);
    Route::post('/targets/recreate', [TargetRecreateController::class, 'store']);
    Route::put('/targets/recreate/{id}', [TargetRecreateController::class, 'update']);
    Route::delete('/targets/recreate/{id}', [TargetRecreateController::class, 'destroy']);
});
 // Branch manager Callenges & Participants & Detach
Route::middleware(['auth:sanctum'])->prefix('branch-manager')->group(function () {
    Route::get('challenges', [ChallengeController::class, 'index']);              // عرض كل التحديات
    Route::post('challenges', [ChallengeController::class, 'store']);             // إنشاء تحدي جديد
    Route::get('challenges/{id}', [ChallengeController::class, 'show']);          // عرض تحدي معين
    Route::put('challenges/{id}', [ChallengeController::class, 'update']);        // تعديل تحدي
    Route::delete('challenges/{id}', [ChallengeController::class, 'destroy']);    // حذف تحدي

    // إدارة المشاركين
    Route::post('challenges/{id}/participants', [ChallengeController::class, 'addParticipants']); // إضافة مشاركين
    Route::delete('challenges/{id}/participants/{employeeId}', [ChallengeController::class, 'removeParticipant']); // حذف مشارك
});

// 🔹 كل العمولات
Route::middleware('auth:sanctum')->prefix('branch-manager')->group(function () {
    Route::get('/service-commissions', [ServiceCommissionController::class, 'index']);
    Route::get('/service-commissions/{id}', [ServiceCommissionController::class, 'show']);
    Route::post('/service-commissions', [ServiceCommissionController::class, 'store']);
    Route::put('/service-commissions/{id}', [ServiceCommissionController::class, 'update']);
    Route::delete('/service-commissions/{id}', [ServiceCommissionController::class, 'destroy']);

});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('sales', [SalesController::class, 'index']);
    Route::post('sales', [SalesController::class, 'store']);
    Route::get('sales/{id}', [SalesController::class, 'show']);
    Route::put('sales/{id}', [SalesController::class, 'update']);
    Route::delete('sales/{id}', [SalesController::class, 'destroy']);
    Route::get('sales/export/csv', [SalesController::class, 'exportCsv']);
    Route::get('sales/export/pdf', [SalesController::class, 'exportPdf']);
});






//_____________________________________***_____________________________________
//_____________________________________***_____________________________________
//_____________________________________***_____________________________________

// Routes for Branch Manager
Route::prefix('branch-manager')->group(function () {
        Route::post('/register', [AuthController::class, 'registerBranchManager']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/logout', [AuthController::class, 'logout'])->middleware(['auth:sanctum', 'branch_manager']);
        // Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        // Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});



//_____________________________________***_____________________________________

// Routes for managing Employees by Branch Manager
Route::prefix('branch-manager')->middleware('auth:sanctum')->group(function () {
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::get('/employees/{id}', [EmployeeController::class, 'show']);
    Route::put('/employees/{id}', [EmployeeController::class, 'update']);
    Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
});



Route::middleware('auth:sanctum')->prefix('branch-manager')->group(function () {
    Route::get('/dashboard/summary', [BranchDashboardController::class, 'branchSummary']);
    Route::get('/dashboard/employees', [BranchDashboardController::class, 'branchEmployeesDetails']);
    Route::get('/dashboard/sales', [BranchDashboardController::class, 'branchEmployeesSales']);
    Route::get('/dashboard/export/csv', [BranchDashboardController::class, 'exportBranchDashboardCsv']);
    Route::get('/dashboard/export/pdf', [BranchDashboardController::class, 'exportBranchDashboardpdf']);
});




//_____________________________________***_____________________________________
// Routes for managing Targets by Branch Manager
// Route::prefix('branch-manager')->middleware('auth:sanctum')->group(function(){
//     Route::get('targets',[TargetController::class,'index']);
//     Route::post('targets',[TargetController::class,'store']);
//     Route::get('targets/{id}',[TargetController::class,'show']);
//     Route::put('targets/{id}',[TargetController::class,'update']);
//     Route::delete('targets/{id}',[TargetController::class,'destroy']);
// });






//_____________________________________***_____________________________________

// Routes for Employee to view their Targets & record Sales
// Route::prefix('employee')->middleware('auth:sanctum')->group(function(){
//     //Route::get('targets',[EmployeeTargetController::class,'index']);
//     Route::get('targets/{id}',[EmployeeTargetController::class,'show']);
//     // sales (employee records sale)


// });







//_____________________________________***_____________________________________

Route::prefix('branch-manager')->middleware('auth:sanctum')->group(function () {
    Route::get('challenges', [challengeController::class, 'index']);
    Route::post('challenges', [challengeController::class, 'store']);
    Route::get('challenges/{id}', [challengeController::class, 'show']);
    Route::put('challenges/{id}', [challengeController::class, 'update']);
    Route::delete('challenges/{id}', [challengeController::class, 'destroy']);
    Route::post('challenges/{id}/participants', [challengeController::class, 'addParticipants']);
    Route::delete('challenges/{id}/participants/{employeeId}', [challengeController::class, 'removeParticipant']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('employee/challenges', [ChallengeRespondController::class, 'myChallenges']);
    Route::post('employee/challenges/{challengeId}/respond', [ChallengeRespondController::class, 'respond']);
    Route::post('employee/challenges/{challengeId}/complete', [ChallengeRespondController::class, 'markCompleted']);

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('notifications/{id}/unread', [NotificationController::class, 'markUnread']);
    Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
});





//_____________________________________***_____________________________________
//_____________________________________***_____________________________________
//_____________________________________***_____________________________________

// Routes for Area Manager

Route::prefix('area-manager')->group(function () {
        Route::post('/register', [AuthController::class, 'registerRegionManager']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/logout', [AuthController::class, 'logout'])->middleware(['auth:sanctum', 'area_manager']);
        // Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        // Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('branch-managers', [BranchManagerController::class, 'store']);
    Route::get('branch-managers', [BranchManagerController::class, 'index']);
    Route::get('branch-managers/{id}', [BranchManagerController::class, 'show']);
    Route::put('branch-managers/{id}', [BranchManagerController::class, 'update']);
    Route::delete('branch-managers/{id}', [BranchManagerController::class, 'destroy']);
});



// Routes for Area Manager to manage Region Targets
Route::middleware('auth:sanctum')->prefix('area-manager')->group(function () {
    Route::get('/region-targets', [RegionTargetController::class, 'index']); // List all
    Route::get('/region-targets/{id}', [RegionTargetController::class, 'show']); // Show single
    Route::post('/region-targets', [RegionTargetController::class, 'store']); // Create
    Route::put('/region-targets/{id}', [RegionTargetController::class, 'update']); // Update
    Route::delete('/region-targets/{id}', [RegionTargetController::class, 'destroy']); // Delete
});




// Routes for Area Manager to manage Region Statistics

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('region/stats/branches', [RegionStatsController::class, 'branchesSummary']);
    Route::get('region/stats/branch/{branchId}/employees', [RegionStatsController::class, 'branchEmployees']);
    Route::get('region/stats/branch/{branchId}/sales', [RegionStatsController::class, 'branchSales']);
    Route::get('region/stats/export/csv', [RegionStatsController::class, 'exportRegionDashboardCsv']);
    Route::get('region/stats/export/pdf', [RegionStatsController::class, 'exportRegionDashboardPdf']);
});





Route::middleware(['auth:sanctum'])->group(function () {

    // ===== Branches Summary =====
    Route::get('branches-summary/export/csv', [RegionStatsController::class, 'exportBranchesSummaryCsv']);
    Route::get('branches-summary/export/pdf', [RegionStatsController::class, 'exportBranchesSummaryPdf']);

    // ===== Branch Employees =====
    Route::get('branch/{branchId}/employees/export/csv', [RegionStatsController::class, 'exportBranchEmployeesCsv']);
    Route::get('branch/{branchId}/employees/export/pdf', [RegionStatsController::class, 'exportBranchEmployeesPdf']);

    // ===== Branch Sales =====
    Route::get('branch/{branchId}/sales/export/csv', [RegionStatsController::class, 'exportBranchSalesCsv']);
    Route::get('branch/{branchId}/sales/export/pdf', [RegionStatsController::class, 'exportBranchSalesPdf']);
});


Route::middleware('auth:sanctum')->group(function () {
    Route::get('/branches', [BranchController::class, 'index']);
    Route::post('/branches', [BranchController::class, 'store']);
    Route::get('/branches/{id}', [BranchController::class, 'show']);
    Route::put('branches/{id}', [BranchController::class, 'update']);
    Route::delete('branches/{id}', [BranchController::class, 'destroy']);
});

//_____________________________________***_____________________________________
//_____________________________________***_____________________________________
//_____________________________________***_____________________________________
// Routes for Owner

Route::prefix('owner')->group(function () {
        Route::post('/register', [AuthController::class, 'registerOwner']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/logout', [AuthController::class, 'logout'])->middleware(['auth:sanctum', 'owner']);
        // Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
        // Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

Route::middleware(['auth:sanctum', 'owner'])->prefix('owner')->group(function () {
    Route::get('region-managers', [RegionManagerController::class, 'index']);
    Route::post('region-managers', [RegionManagerController::class, 'store']);
    Route::get('region-managers/{id}', [RegionManagerController::class, 'show']);
    Route::put('region-managers/{id}', [RegionManagerController::class, 'update']);
    Route::delete('region-managers/{id}', [RegionManagerController::class, 'destroy']);
});



Route::middleware(['auth:sanctum'])->prefix('owner')->group(function () {

    //  Branches Summary JSON
    Route::get('branches-summary', [OwnerDashboardController::class, 'allBranchesSummaryForOwner']);

    //  Branch Employees JSON
    Route::get('branch-employees', [OwnerDashboardController::class, 'allBranchEmployeesForOwner']);

    //  Branch Sales JSON
    Route::get('branch-sales', [OwnerDashboardController::class, 'allBranchSalesForOwner']);
});


Route::middleware(['auth:sanctum'])->prefix('owner')->group(function () {

    // Branches Summary
    Route::get('branches-summary/csv', [OwnerDashboardController::class, 'exportOwnerDashboardCsv']);
    Route::get('branches-summary/pdf', [OwnerDashboardController::class, 'exportOwnerDashboardPdf']);


});


Route::prefix('owner')->middleware('auth:sanctum')->group(function () {
    Route::get('regions', [RegionController::class, 'index']);
    Route::post('regions', [RegionController::class, 'store']);
    Route::get('regions/{id}', [RegionController::class, 'show']);
    Route::put('regions/{id}', [RegionController::class, 'update']);
    Route::delete('regions/{id}', [RegionController::class, 'destroy']);
});
//_____________________________________***_____________________________________
//_____________________________________***_____________________________________
//_____________________________________***_____________________________________

//Routes for Challenge Progress

Route::prefix('branch-manager')->middleware('auth:sanctum')->group(function () {
    Route::get('challenges/{id}/progress', [ChallengeProgressController::class, 'managerProgress']);
    Route::get('challenges/overview', [ChallengeProgressController::class, 'managerOverview']);
});

Route::prefix('employee')->middleware('auth:sanctum')->group(function () {
    Route::get('challenges/{id}/progress', [ChallengeProgressController::class, 'employeeProgress']);
    Route::get('challenges/active', [ChallengeProgressController::class, 'employeeActiveChallenges']);
});



Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::delete('/profile', [ProfileController::class, 'destroy']);
});



// use Illuminate\Support\Facades\Artisan;

// Route::get('/run-seeder', function () {
//     Artisan::call('migrate', ['--force' => true]);
//     Artisan::call('db:seed', ['--force' => true]);
//     return response()->json(['message' => 'Seeder executed successfully!']);
// });