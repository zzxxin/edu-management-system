<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;

/**
 * 教师仪表板控制器
 */
class DashboardController extends Controller
{
    /**
     * 显示教师仪表板
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $teacher = Auth::guard('teacher')->user();

        $stats = [
            'courses_count' => $teacher->courses()->count(),
            'invoices_pending' => Invoice::whereHas('course', function ($query) use ($teacher) {
                $query->where('teacher_id', $teacher->id);
            })->where('status', Invoice::STATUS_PENDING)->count(),
            'invoices_sent' => Invoice::whereHas('course', function ($query) use ($teacher) {
                $query->where('teacher_id', $teacher->id);
            })->where('status', Invoice::STATUS_SENT)->count(),
            'invoices_paid' => Invoice::whereHas('course', function ($query) use ($teacher) {
                $query->where('teacher_id', $teacher->id);
            })->where('status', Invoice::STATUS_PAID)->count(),
        ];

        return view('teacher.dashboard', compact('stats'));
    }
}
