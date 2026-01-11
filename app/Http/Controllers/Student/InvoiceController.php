<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
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
     * 显示我的账单列表
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $student = Auth::guard('student')->user();

        // 只显示已发送、已支付或支付失败的账单，不显示待发送（pending）的账单
        $invoices = $student->invoices()
            ->where('status', '!=', Invoice::STATUS_PENDING) // 排除待发送状态的账单
            ->with(['course.teacher']) // 预加载课程和教师关联
            ->orderBy('created_at', 'desc')
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
        if ($invoice->student_id !== $student->id) {
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
        if ($invoice->student_id !== $student->id) {
            abort(403, '无权访问此账单。');
        }

        $request->validate([
            'omise_token' => 'required|string',
            'currency' => 'nullable|string|in:thb,jpy,sgd,usd', // 支持的货币代码
        ]);

        // 使用数据库事务和锁防止重复支付
        return DB::transaction(function () use ($request, $invoice) {
            // 使用 lockForUpdate 锁定账单记录，防止并发支付
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);

            // 再次验证账单状态（防止并发请求）
            if ($invoice->status !== Invoice::STATUS_SENT) {
                if ($invoice->status === Invoice::STATUS_PAID) {
                    return back()->withErrors(['error' => '该账单已支付，请勿重复支付。'])->withInput();
                }
                if ($invoice->status === Invoice::STATUS_REJECTED) {
                    return back()->withErrors(['error' => '该账单已被拒绝，无法支付。'])->withInput();
                }
                return back()->withErrors(['error' => '只能支付已发送状态的账单。'])->withInput();
            }

            // 使用 Omise 支付服务处理支付
            $paymentService = app(\App\Services\OmisePaymentService::class);

            // 创建支付（传递用户选择的币种）
            $chargeResult = $paymentService->createCharge($invoice, $request->omise_token, $request->currency);

            // 更新账单状态
            $paymentService->updateInvoiceStatus($invoice, $chargeResult);

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
        if ($invoice->student_id !== $student->id) {
            abort(403, '无权访问此账单。');
        }

        // 只能拒绝已发送状态的账单
        if ($invoice->status !== Invoice::STATUS_SENT) {
            if ($invoice->status === Invoice::STATUS_PAID) {
                return back()->withErrors(['error' => '该账单已支付，无法拒绝。']);
            }
            if ($invoice->status === Invoice::STATUS_REJECTED) {
                return back()->withErrors(['error' => '该账单已被拒绝。']);
            }
            return back()->withErrors(['error' => '只能拒绝待支付状态的账单。']);
        }

        // 使用数据库事务和锁，防止并发操作
        return DB::transaction(function () use ($invoice) {
            // 重新加载账单并锁定，确保获取最新状态并防止并发修改
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);

            // 再次验证账单状态
            if ($invoice->status !== Invoice::STATUS_SENT) {
                return back()->withErrors(['error' => '账单状态已改变，无法拒绝。']);
            }

            // 更新账单状态为已拒绝，并记录拒绝时间
            $invoice->update([
                'status' => Invoice::STATUS_REJECTED,
                'rejected_at' => now(),
            ]);

            return redirect()->route('student.invoices.index')
                ->with('success', '账单已拒绝，账单已失效。');
        });
    }
}
