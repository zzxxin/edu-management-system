<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\OmisePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 学生账单管理控制器
 *
 * 学生所参与的课程相关联的账单一览
 * 学生可以针对某个账单，发起支付
 */
class InvoiceController extends Controller
{
    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * @var OmisePaymentService
     */
    protected $paymentService;

    /**
     * 构造函数
     *
     * @param InvoiceService $invoiceService
     * @param OmisePaymentService $paymentService
     */
    public function __construct(InvoiceService $invoiceService, OmisePaymentService $paymentService)
    {
        $this->invoiceService = $invoiceService;
        $this->paymentService = $paymentService;
    }

    /**
     * 显示我的账单列表
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $student = Auth::guard('student')->user();

        // 只显示已发送、已支付或支付失败的账单，不显示待发送（pending）的账单
        $invoices = Invoice::forStudent($student->id)
            ->excludePending()
            ->with(['course.teacher'])
            ->latest()
            ->paginate(15);

        return view('student.invoices.index', compact('invoices'));
    }

    /**
     * 显示账单详情
     *
     * @param  \App\Models\Invoice  $invoice
     * @return \Illuminate\View\View
     */
    public function show(Invoice $invoice)
    {
        $student = Auth::guard('student')->user();

        // 验证账单是否属于该学生
        if (!$student->ownsInvoice($invoice)) {
            abort(403, '无权访问此账单。');
        }

        // 验证账单状态，待发送状态的账单不显示
        if ($invoice->status === Invoice::STATUS_PENDING) {
            abort(404, '账单尚未发送。');
        }

        $invoice->load(['course.teacher']);

        return view('student.invoices.show', compact('invoice'));
    }

    /**
     * 处理支付请求
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Invoice  $invoice
     * @return \Illuminate\Http\RedirectResponse
     */
    public function pay(Request $request, Invoice $invoice)
    {
        $student = Auth::guard('student')->user();

        // 验证账单是否属于该学生
        if (!$student->ownsInvoice($invoice)) {
            abort(403, '无权访问此账单。');
        }

        $request->validate([
            'omise_token' => 'required|string|min:1',
            'currency' => 'nullable|string|in:thb,jpy,sgd,usd',
        ], [
            'omise_token.required' => '支付令牌不能为空。',
            'omise_token.string' => '支付令牌必须是字符串。',
            'omise_token.min' => '支付令牌不能为空。',
            'currency.string' => '货币代码必须是字符串。',
            'currency.in' => '不支持的货币类型，支持的货币：THB、JPY、SGD、USD。',
        ]);

        // 使用数据库事务和锁防止重复支付
        return DB::transaction(function () use ($request, $invoice) {
            // 使用 lockForUpdate 锁定账单记录，防止并发支付
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);

            // 验证账单是否可以支付
            if (!$this->invoiceService->canPay($invoice)) {
                if ($invoice->status === Invoice::STATUS_PAID) {
                    return back()->withErrors(['error' => '该账单已支付，请勿重复支付。'])->withInput();
                }
                if ($invoice->status === Invoice::STATUS_REJECTED) {
                    return back()->withErrors(['error' => '该账单已被拒绝，无法支付。'])->withInput();
                }
                return back()->withErrors(['error' => '只能支付已发送状态的账单。'])->withInput();
            }

            // 创建支付
            $chargeResult = $this->paymentService->createCharge($invoice, $request->omise_token, $request->currency);

            // 更新账单状态
            $this->paymentService->updateInvoiceStatus($invoice, $chargeResult);

            // 重新加载账单以获取最新状态
            $invoice->refresh();

            // 根据结果返回响应
            if ($chargeResult['success'] && ($chargeResult['status'] ?? '') === 'successful') {
                return redirect()->route('student.invoices.index')
                    ->with('success', '支付成功！');
            } else {
                $errorMessage = $chargeResult['error'] ?? '支付失败，请重试。';
                return back()->withErrors(['error' => $errorMessage])->withInput();
            }
        });
    }

    /**
     * 拒绝支付账单（使账单失效）
     *
     * @param  \App\Models\Invoice  $invoice
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reject(Invoice $invoice)
    {
        $student = Auth::guard('student')->user();

        // 验证账单是否属于该学生
        if (!$student->ownsInvoice($invoice)) {
            abort(403, '无权访问此账单。');
        }

        // 验证账单是否可以拒绝
        if (!$this->invoiceService->canReject($invoice)) {
            if ($invoice->status === Invoice::STATUS_PAID) {
                return back()->withErrors(['error' => '该账单已支付，无法拒绝。']);
            }
            if ($invoice->status === Invoice::STATUS_REJECTED) {
                return back()->withErrors(['error' => '该账单已被拒绝。']);
            }
            return back()->withErrors(['error' => '只能拒绝待支付状态的账单。']);
        }

        // 使用服务拒绝账单
        $this->invoiceService->rejectInvoice($invoice);

        return redirect()->route('student.invoices.index')
            ->with('success', '账单已拒绝，账单已失效。');
    }
}
