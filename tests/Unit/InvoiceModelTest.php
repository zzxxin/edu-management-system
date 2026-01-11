<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 账单模型单元测试
 */
class InvoiceModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试账单关联课程
     */
    public function test_invoice_belongs_to_course(): void
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

        $this->assertInstanceOf(Course::class, $invoice->course);
        $this->assertEquals($course->id, $invoice->course->id);
    }

    /**
     * 测试账单关联学生
     */
    public function test_invoice_belongs_to_student(): void
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

        $this->assertInstanceOf(Student::class, $invoice->student);
        $this->assertEquals($student->id, $invoice->student->id);
    }

    /**
     * 测试账单状态判断方法
     */
    public function test_invoice_status_methods(): void
    {
        $teacher = Teacher::factory()->create();
        $student = Student::factory()->create(['teacher_id' => $teacher->id]);
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
        ]);

        // 测试待发送状态
        $invoicePending = Invoice::factory()->create([
            'course_id' => $course->id,
            'student_id' => $student->id,
            'status' => Invoice::STATUS_PENDING,
        ]);
        $this->assertTrue($invoicePending->isPending());
        $this->assertFalse($invoicePending->isSent());
        $this->assertFalse($invoicePending->isPaid());

        // 测试已发送状态
        $invoiceSent = Invoice::factory()->create([
            'course_id' => $course->id,
            'student_id' => $student->id,
            'status' => Invoice::STATUS_SENT,
        ]);
        $this->assertFalse($invoiceSent->isPending());
        $this->assertTrue($invoiceSent->isSent());
        $this->assertFalse($invoiceSent->isPaid());

        // 测试已支付状态
        $invoicePaid = Invoice::factory()->create([
            'course_id' => $course->id,
            'student_id' => $student->id,
            'status' => Invoice::STATUS_PAID,
        ]);
        $this->assertFalse($invoicePaid->isPending());
        $this->assertFalse($invoicePaid->isSent());
        $this->assertTrue($invoicePaid->isPaid());
    }
}
