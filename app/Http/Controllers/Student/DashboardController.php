<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * 学生仪表板控制器
 */
class DashboardController extends Controller
{
    /**
     * 显示学生仪表板
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $student = Auth::guard('student')->user();

        $stats = [
            'courses_count' => $student->courses()->count(),
            'invoices_sent' => $student->invoices()->where('status', 'sent')->count(),
            'invoices_paid' => $student->invoices()->where('status', 'paid')->count(),
        ];

        return view('student.dashboard', compact('stats'));
    }
}
