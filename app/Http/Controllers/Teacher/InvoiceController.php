<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 教师账单管理控制器
 *
 * 教师可以创建账单，账单可以执行"发送"，发送后学生在【我的账单】中就能看到待支付的账单
 */
class InvoiceController extends Controller
{
    /**
     * 显示账单列表
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $teacher = Auth::guard('teacher')->user();

        $invoices = Invoice::whereHas('course', function ($query) use ($teacher) {
                $query->where('teacher_id', $teacher->id);
            })
            ->with(['course', 'student']) // 预加载课程和学生关联
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('teacher.invoices.index', compact('invoices'));
    }

    /**
     * 显示账单详情
     *
     * @param  \App\Models\Invoice  $invoice
     * @return \Illuminate\View\View
     */
    public function show(Invoice $invoice)
    {
        $teacher = Auth::guard('teacher')->user();

        // 验证账单的课程是否属于该教师
        if ($invoice->course->teacher_id !== $teacher->id) {
            abort(403, '无权访问此账单。');
        }

        // 预加载关联数据和支付记录
        $invoice->load(['course.teacher', 'student', 'payments' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }]);

        return view('teacher.invoices.show', compact('invoice'));
    }

    /**
     * 显示创建账单表单
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $teacher = Auth::guard('teacher')->user();

        // 获取该教师的课程列表（只显示有学生的课程），并预加载学生关联
        $courses = Course::where('teacher_id', $teacher->id)
            ->has('students') // 只获取有学生的课程
            ->with('students') // 预加载学生关联，用于前端显示
            ->orderBy('created_at', 'desc')
            ->get();

        return view('teacher.invoices.create', compact('courses'));
    }

    /**
     * 存储新账单
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        $teacher = Auth::guard('teacher')->user();

        // 验证课程是否属于该教师
        $course = Course::where('id', $request->course_id)
            ->where('teacher_id', $teacher->id)
            ->with('students') // 预加载学生关联
            ->firstOrFail();

        // 获取该课程的所有学生
        $studentIds = $course->students->pluck('id')->toArray();

        if (empty($studentIds)) {
            return back()->withErrors(['course_id' => '该课程暂无学生，无法创建账单。'])->withInput();
        }

        $createdCount = 0;
        $skippedCount = 0;

        DB::transaction(function () use ($course, $studentIds, &$createdCount, &$skippedCount) {
            // 为课程的所有学生创建账单
            foreach ($studentIds as $studentId) {
                // 检查是否已存在该课程和学生的账单，避免重复创建
                $existingInvoice = Invoice::where('course_id', $course->id)
                    ->where('student_id', $studentId)
                    ->first();

                if (!$existingInvoice) {
                    Invoice::create([
                        'course_id' => $course->id,
                        'student_id' => $studentId,
                        'year_month' => $course->year_month, // 从课程中获取年月
                        'amount' => $course->fee,
                        'status' => Invoice::STATUS_PENDING,
                    ]);
                    $createdCount++;
                } else {
                    $skippedCount++;
                }
            }
        });

        // 根据创建结果返回不同的提示信息
        if ($createdCount > 0 && $skippedCount > 0) {
            return redirect()->route('teacher.invoices.index')
                ->with('success', "成功创建 {$createdCount} 个账单，{$skippedCount} 个账单已存在，已跳过。");
        } elseif ($createdCount > 0) {
            return redirect()->route('teacher.invoices.index')
                ->with('success', "成功创建 {$createdCount} 个账单！");
        } else {
            return redirect()->route('teacher.invoices.index')
                ->with('info', "该课程的所有学生已有账单，无需重复创建。");
        }
    }

    /**
     * 发送账单（将状态改为已发送，学生可以在【我的账单】中看到）
     * 支持待发送状态的账单首次发送，也支持已拒绝状态的账单重新发送
     *
     * @param  \App\Models\Invoice  $invoice
     * @return \Illuminate\Http\RedirectResponse
     */
    public function send(Invoice $invoice)
    {
        $teacher = Auth::guard('teacher')->user();

        // 验证账单的课程是否属于该教师
        if ($invoice->course->teacher_id !== $teacher->id) {
            abort(403, '无权访问此账单。');
        }

        // 允许发送待发送状态的账单，也允许重新发送已拒绝状态的账单
        if ($invoice->status !== Invoice::STATUS_PENDING && $invoice->status !== Invoice::STATUS_REJECTED) {
            return back()->withErrors(['error' => '只能发送待发送状态或已拒绝状态的账单。']);
        }

        // 记录是否为重新发送（在更新状态之前检查）
        $isResend = $invoice->status === Invoice::STATUS_REJECTED;

        $invoice->update([
            'status' => Invoice::STATUS_SENT,
            'sent_at' => now(),
            'rejected_at' => null, // 重新发送时清空拒绝时间
        ]);

        $message = $isResend 
            ? '账单重新发送成功！' 
            : '账单发送成功！';

        return back()->with('success', $message);
    }

    /**
     * 批量发送账单
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function batchSend(Request $request)
    {
        // 处理 invoice_ids：如果是 JSON 字符串，则解码为数组
        $invoiceIds = $request->input('invoice_ids');
        if (is_string($invoiceIds)) {
            $decoded = json_decode($invoiceIds, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $invoiceIds = $decoded;
                // 将解码后的数组合并回请求
                $request->merge(['invoice_ids' => $invoiceIds]);
            } else {
                return back()->withErrors(['error' => '无效的账单ID格式。'])->withInput();
            }
        }

        $request->validate([
            'invoice_ids' => 'required|array|min:2',
            'invoice_ids.*' => 'exists:invoices,id',
        ], [
            'invoice_ids.min' => '批量发送至少需要选择两个账单。',
        ]);

        $teacher = Auth::guard('teacher')->user();

        // 验证所有账单的课程是否属于该教师，并且状态为待发送或已拒绝（可以重新发送）
        $invoices = Invoice::whereIn('id', $invoiceIds)
            ->whereHas('course', function ($query) use ($teacher) {
                $query->where('teacher_id', $teacher->id);
            })
            ->whereIn('status', [Invoice::STATUS_PENDING, Invoice::STATUS_REJECTED])
            ->get();

        if ($invoices->count() !== count($invoiceIds)) {
            return back()->withErrors(['error' => '部分账单无法发送，请检查账单状态和权限。']);
        }

        DB::transaction(function () use ($invoices) {
            foreach ($invoices as $invoice) {
                $invoice->update([
                    'status' => Invoice::STATUS_SENT,
                    'sent_at' => now(),
                    'rejected_at' => null, // 重新发送时清空拒绝时间
                ]);
            }
        });

        return back()->with('success', '批量发送成功！');
    }
}
