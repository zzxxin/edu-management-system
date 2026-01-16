<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * 账单服务类
 *
 * 封装账单相关的业务逻辑
 */
class InvoiceService
{
    /**
     * 为课程的所有学生创建账单
     *
     * @param Course $course 课程对象
     * @return array{created_count: int, skipped_count: int}
     */
    public function createInvoicesForCourse(Course $course): array
    {
        // 获取该课程的所有学生（SoftDeletes 会自动过滤已删除的学生）
        $studentIds = $course->students()->pluck('students.id')->toArray();

        if (empty($studentIds)) {
            return [
                'created_count' => 0,
                'skipped_count' => 0,
            ];
        }

        $createdCount = 0;
        $skippedCount = 0;

        DB::transaction(function () use ($course, $studentIds, &$createdCount, &$skippedCount) {
            foreach ($studentIds as $studentId) {
                // 检查是否已存在该课程和学生的账单
                $existingInvoice = Invoice::forCourseAndStudent($course->id, $studentId)->first();

                if (!$existingInvoice) {
                    Invoice::create([
                        'course_id' => $course->id,
                        'student_id' => $studentId,
                        'year_month' => $course->year_month,
                        'amount' => $course->fee,
                        'status' => Invoice::STATUS_PENDING,
                    ]);
                    $createdCount++;
                } else {
                    $skippedCount++;
                }
            }
        });

        return [
            'created_count' => $createdCount,
            'skipped_count' => $skippedCount,
        ];
    }

    /**
     * 发送账单（单个）
     *
     * @param Invoice $invoice 账单对象
     * @return bool 是否为重新发送
     */
    public function sendInvoice(Invoice $invoice): bool
    {
        $isResend = $invoice->status === Invoice::STATUS_REJECTED;

        $invoice->update([
            'status' => Invoice::STATUS_SENT,
            'sent_at' => now(),
            'rejected_at' => null, // 重新发送时清空拒绝时间
        ]);

        return $isResend;
    }

    /**
     * 批量发送账单
     *
     * @param array $invoiceIds 账单ID数组
     * @param int $teacherId 教师ID（用于权限验证）
     * @return int 成功发送的数量
     * @throws \InvalidArgumentException
     */
    public function batchSendInvoices(array $invoiceIds, int $teacherId): int
    {
        // 参数校验：确保 invoiceIds 是整数数组
        if (empty($invoiceIds)) {
            throw new \InvalidArgumentException('账单ID数组不能为空。');
        }

        foreach ($invoiceIds as $id) {
            if (!is_numeric($id) || $id <= 0) {
                throw new \InvalidArgumentException('账单ID必须是正整数。');
            }
        }

        // 参数校验：确保 teacherId 是正整数
        if ($teacherId <= 0) {
            throw new \InvalidArgumentException('教师ID必须是正整数。');
        }

        // 验证所有账单的课程是否属于该教师，并且状态为待发送或已拒绝
        $invoices = Invoice::whereIn('id', $invoiceIds)
            ->forTeacher($teacherId)
            ->byStatuses([Invoice::STATUS_PENDING, Invoice::STATUS_REJECTED])
            ->get();

        $count = 0;

        DB::transaction(function () use ($invoices, &$count) {
            foreach ($invoices as $invoice) {
                $invoice->update([
                    'status' => Invoice::STATUS_SENT,
                    'sent_at' => now(),
                    'rejected_at' => null, // 重新发送时清空拒绝时间
                ]);
                $count++;
            }
        });

        return $count;
    }

    /**
     * 拒绝账单
     *
     * @param Invoice $invoice 账单对象
     * @return void
     */
    public function rejectInvoice(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            // 重新加载账单并锁定，确保获取最新状态并防止并发修改
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);

            $invoice->update([
                'status' => Invoice::STATUS_REJECTED,
                'rejected_at' => now(),
            ]);
        });
    }

    /**
     * 验证账单是否可以发送
     *
     * @param Invoice $invoice 账单对象
     * @return bool
     */
    public function canSend(Invoice $invoice): bool
    {
        return in_array($invoice->status, [Invoice::STATUS_PENDING, Invoice::STATUS_REJECTED]);
    }

    /**
     * 验证账单是否可以拒绝
     *
     * @param Invoice $invoice 账单对象
     * @return bool
     */
    public function canReject(Invoice $invoice): bool
    {
        return $invoice->status === Invoice::STATUS_SENT;
    }

    /**
     * 验证账单是否可以支付
     *
     * @param Invoice $invoice 账单对象
     * @return bool
     */
    public function canPay(Invoice $invoice): bool
    {
        return $invoice->status === Invoice::STATUS_SENT;
    }
}
