<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 教师账单管理控制器
 *
 * 教师可以创建账单，账单可以执行"发送"，发送后学生在【我的账单】中就能看到待支付的账单
 */
class InvoiceController extends Controller
{
    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * 构造函数
     *
     * @param InvoiceService $invoiceService
     */
    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * 显示账单列表
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $teacher = Auth::guard('teacher')->user();

        $invoices = Invoice::forTeacher($teacher->id)
            ->withRelations()
            ->latest()
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
        if (!$invoice->course->belongsToTeacher($teacher->id)) {
            abort(403, '无权访问此账单。');
        }

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
        $courses = Course::forTeacher($teacher->id)
            ->withStudents()
            ->withStudentsRelation()
            ->latest()
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
        $course = Course::forTeacher($teacher->id)
            ->withStudentsRelation()
            ->findOrFail($request->course_id);

        // 使用服务创建账单
        $result = $this->invoiceService->createInvoicesForCourse($course);

        // 根据创建结果返回不同的提示信息
        if ($result['created_count'] > 0 && $result['skipped_count'] > 0) {
            return redirect()->route('teacher.invoices.index')
                ->with('success', "成功创建 {$result['created_count']} 个账单，{$result['skipped_count']} 个账单已存在，已跳过。");
        } elseif ($result['created_count'] > 0) {
            return redirect()->route('teacher.invoices.index')
                ->with('success', "成功创建 {$result['created_count']} 个账单！");
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
        if (!$invoice->course->belongsToTeacher($teacher->id)) {
            abort(403, '无权访问此账单。');
        }

        // 验证账单是否可以发送
        if (!$this->invoiceService->canSend($invoice)) {
            return back()->withErrors(['error' => '只能发送待发送状态或已拒绝状态的账单。']);
        }

        // 使用服务发送账单
        $isResend = $this->invoiceService->sendInvoice($invoice);

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
                $request->merge(['invoice_ids' => $invoiceIds]);
            } else {
                return back()->withErrors(['error' => '无效的账单ID格式。'])->withInput();
            }
        }

        $request->validate([
            'invoice_ids' => 'required|array|min:2',
            'invoice_ids.*' => 'required|integer|exists:invoices,id',
        ], [
            'invoice_ids.required' => '请选择要发送的账单。',
            'invoice_ids.min' => '批量发送至少需要选择两个账单。',
            'invoice_ids.*.required' => '账单ID不能为空。',
            'invoice_ids.*.integer' => '账单ID必须是整数。',
            'invoice_ids.*.exists' => '部分账单ID不存在。',
        ]);

        $teacher = Auth::guard('teacher')->user();

        // 使用服务批量发送账单
        $sentCount = $this->invoiceService->batchSendInvoices($invoiceIds, $teacher->id);

        if ($sentCount !== count($invoiceIds)) {
            return back()->withErrors(['error' => '部分账单无法发送，请检查账单状态和权限。']);
        }

        return back()->with('success', '批量发送成功！');
    }
}
