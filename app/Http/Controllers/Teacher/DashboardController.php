<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
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
            'invoices_pending' => $teacher->getInvoiceCountByStatus(\App\Models\Invoice::STATUS_PENDING),
            'invoices_sent' => $teacher->getInvoiceCountByStatus(\App\Models\Invoice::STATUS_SENT),
            'invoices_paid' => $teacher->getInvoiceCountByStatus(\App\Models\Invoice::STATUS_PAID),
        ];

        return view('teacher.dashboard', compact('stats'));
    }
}
