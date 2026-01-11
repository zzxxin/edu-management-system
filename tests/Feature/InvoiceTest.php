<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

/**
 * 账单功能测试
 */
class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected Teacher $teacher;
    protected Student $student;
    protected Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试教师
        $this->teacher = Teacher::factory()->create([
            'email' => 'teacher@test.com',
            'password' => bcrypt('password'),
        ]);

        // 创建测试学生
        $this->student = Student::factory()->create([
            'email' => 'student@test.com',
            'password' => bcrypt('password'),
            'teacher_id' => $this->teacher->id,
        ]);

        // 创建测试课程
        $this->course = Course::factory()->create([
            'teacher_id' => $this->teacher->id,
            'fee' => 200.00,
        ]);
        $this->course->students()->attach($this->student->id);
    }

    /**
     * 测试教师可以创建账单
     */
    public function test_teacher_can_create_invoice(): void
    {
        $invoiceData = [
            'course_id' => $this->course->id,
            'student_ids' => [$this->student->id],
        ];

        // 登录教师并创建账单
        $response = $this->actingAs($this->teacher, 'teacher')
            ->post(route('teacher.invoices.store'), $invoiceData);

        $response->assertRedirect(route('teacher.invoices.index'));
        $this->assertDatabaseHas('invoices', [
            'course_id' => $this->course->id,
            'student_id' => $this->student->id,
            'amount' => $this->course->fee,
            'status' => Invoice::STATUS_PENDING,
        ]);
    }

    /**
     * 测试教师可以发送账单
     */
    public function test_teacher_can_send_invoice(): void
    {
        // 创建账单
        $invoice = Invoice::factory()->create([
            'course_id' => $this->course->id,
            'student_id' => $this->student->id,
            'amount' => $this->course->fee,
            'status' => Invoice::STATUS_PENDING,
        ]);

        // 登录教师并发送账单
        $response = $this->actingAs($this->teacher, 'teacher')
            ->post(route('teacher.invoices.send', $invoice));

        $response->assertRedirect();
        $invoice->refresh();
        $this->assertEquals(Invoice::STATUS_SENT, $invoice->status);
        $this->assertNotNull($invoice->sent_at);
    }

    /**
     * 测试学生可以查看自己的账单
     */
    public function test_student_can_view_own_invoices(): void
    {
        // 创建账单
        $invoice = Invoice::factory()->create([
            'course_id' => $this->course->id,
            'student_id' => $this->student->id,
            'amount' => $this->course->fee,
            'status' => Invoice::STATUS_SENT,
        ]);

        // 登录学生并查看账单列表
        $response = $this->actingAs($this->student, 'student')
            ->get(route('student.invoices.index'));

        $response->assertStatus(200);
        $response->assertSee(number_format($invoice->amount, 2));
    }

    /**
     * 测试学生只能查看自己的账单
     */
    public function test_student_cannot_view_other_invoices(): void
    {
        // 创建另一个学生
        $otherStudent = Student::factory()->create([
            'teacher_id' => $this->teacher->id,
        ]);

        // 创建账单（属于另一个学生）
        $invoice = Invoice::factory()->create([
            'course_id' => $this->course->id,
            'student_id' => $otherStudent->id,
            'amount' => $this->course->fee,
            'status' => Invoice::STATUS_SENT,
        ]);

        // 登录学生并尝试查看不属于自己的账单
        $response = $this->actingAs($this->student, 'student')
            ->get(route('student.invoices.show', $invoice));

        $response->assertStatus(403);
    }

    /**
     * 测试账单列表
     */
    public function test_invoice_list_avoids_n_plus_one_query(): void
    {
        // 创建多个账单
        $invoices = Invoice::factory()->count(5)->create([
            'course_id' => $this->course->id,
            'student_id' => $this->student->id,
        ]);

        // 使用查询监听器检测查询次数
        \DB::enableQueryLog();

        // 登录教师并查看账单列表（应该使用with预加载）
        $this->actingAs($this->teacher, 'teacher')
            ->get(route('teacher.invoices.index'));

        $queries = \DB::getQueryLog();

        $queryCount = count($queries);
        $this->assertLessThan(20, $queryCount, '检测到N+1查询问题');
    }
}
