<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 支付记录模型单元测试
 */
class PaymentModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试支付记录关联账单
     */
    public function test_payment_belongs_to_invoice(): void
    {
        $teacher = Teacher::factory()->create();
        $student = Student::factory()->create(['teacher_id' => $teacher->id]);
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
        ]);

        $invoice = Invoice::factory()->create([
            'course_id' => $course->id,
            'student_id' => $student->id,
        ]);

        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
        ]);

        $this->assertInstanceOf(Invoice::class, $payment->invoice);
        $this->assertEquals($invoice->id, $payment->invoice->id);
    }

    /**
     * 测试支付记录状态判断方法
     */
    public function test_payment_status_methods(): void
    {
        $teacher = Teacher::factory()->create();
        $student = Student::factory()->create(['teacher_id' => $teacher->id]);
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
        ]);

        $invoice = Invoice::factory()->create([
            'course_id' => $course->id,
            'student_id' => $student->id,
        ]);

        // 测试处理中状态
        $pendingPayment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'status' => Payment::STATUS_PENDING,
        ]);
        $this->assertTrue($pendingPayment->isPending());
        $this->assertFalse($pendingPayment->isSuccessful());
        $this->assertFalse($pendingPayment->isFailed());

        // 测试成功状态
        $successfulPayment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'status' => Payment::STATUS_SUCCESSFUL,
        ]);
        $this->assertFalse($successfulPayment->isPending());
        $this->assertTrue($successfulPayment->isSuccessful());
        $this->assertFalse($successfulPayment->isFailed());

        // 测试失败状态
        $failedPayment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'status' => Payment::STATUS_FAILED,
        ]);
        $this->assertFalse($failedPayment->isPending());
        $this->assertFalse($failedPayment->isSuccessful());
        $this->assertTrue($failedPayment->isFailed());
    }

    /**
     * 测试支付记录的属性类型转换
     */
    public function test_payment_casts(): void
    {
        $teacher = Teacher::factory()->create();
        $student = Student::factory()->create(['teacher_id' => $teacher->id]);
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
        ]);

        $invoice = Invoice::factory()->create([
            'course_id' => $course->id,
            'student_id' => $student->id,
        ]);

        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'amount' => '100.50',
            'omise_response' => ['id' => 'test_id', 'status' => 'successful'],
            'paid_at' => now(),
        ]);

        $this->assertIsFloat($payment->amount);
        $this->assertEquals(100.50, $payment->amount);
        $this->assertIsArray($payment->omise_response);
        $this->assertEquals('test_id', $payment->omise_response['id']);
        $this->assertInstanceOf(\Carbon\Carbon::class, $payment->paid_at);
    }
}
