<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Teacher\{
    DashboardController as TeacherDashboardController,
    CourseController as TeacherCourseController,
    InvoiceController as TeacherInvoiceController
};
use App\Http\Controllers\Student\{
    DashboardController as StudentDashboardController,
    CourseController as StudentCourseController,
    InvoiceController as StudentInvoiceController
};

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// 首页重定向到登录页
Route::get('/', function () {
    return redirect()->route('login');
});

// 认证路由
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// 教师路由组
Route::prefix('teacher')->name('teacher.')->middleware(['teacher'])->group(function () {
    // 仪表板
    Route::get('/dashboard', [TeacherDashboardController::class, 'index'])->name('dashboard');
    
    // 课程管理（排除删除功能）
    Route::resource('courses', TeacherCourseController::class)->except(['destroy']);
    
    // 账单管理
    Route::get('/invoices', [TeacherInvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/create', [TeacherInvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [TeacherInvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/{invoice}', [TeacherInvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{invoice}/send', [TeacherInvoiceController::class, 'send'])->name('invoices.send');
    Route::post('/invoices/batch-send', [TeacherInvoiceController::class, 'batchSend'])->name('invoices.batch-send');
});

// 学生路由组
Route::prefix('student')->name('student.')->middleware(['student'])->group(function () {
    // 仪表板
    Route::get('/dashboard', [StudentDashboardController::class, 'index'])->name('dashboard');
    
    // 我的课程
    Route::get('/courses', [StudentCourseController::class, 'index'])->name('courses.index');
    Route::get('/courses/{id}', [StudentCourseController::class, 'show'])->name('courses.show');
    
    // 我的账单
    Route::get('/invoices', [StudentInvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [StudentInvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{invoice}/pay', [StudentInvoiceController::class, 'pay'])->name('invoices.pay');
    Route::post('/invoices/{invoice}/reject', [StudentInvoiceController::class, 'reject'])->name('invoices.reject');
});
