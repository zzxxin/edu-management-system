<?php

namespace Tests\Unit;

use App\Models\Course;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 课程模型单元测试
 */
class CourseModelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 测试课程关联教师
     */
    public function test_course_belongs_to_teacher(): void
    {
        $teacher = Teacher::factory()->create();
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
        ]);

        $this->assertInstanceOf(Teacher::class, $course->teacher);
        $this->assertEquals($teacher->id, $course->teacher->id);
    }

    /**
     * 测试课程关联学生（多对多）
     */
    public function test_course_belongs_to_many_students(): void
    {
        $teacher = Teacher::factory()->create();
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
        ]);

        $student1 = Student::factory()->create(['teacher_id' => $teacher->id]);
        $student2 = Student::factory()->create(['teacher_id' => $teacher->id]);

        $course->students()->attach([$student1->id, $student2->id]);

        $this->assertCount(2, $course->students);
        $this->assertTrue($course->students->contains($student1));
        $this->assertTrue($course->students->contains($student2));
    }

    /**
     * 测试课程关联账单
     */
    public function test_course_has_many_invoices(): void
    {
        $teacher = Teacher::factory()->create();
        $student = Student::factory()->create(['teacher_id' => $teacher->id]);
        $course = Course::factory()->create([
            'teacher_id' => $teacher->id,
        ]);

        $invoice1 = Invoice::factory()->create([
            'course_id' => $course->id,
            'student_id' => $student->id,
        ]);
        $invoice2 = Invoice::factory()->create([
            'course_id' => $course->id,
            'student_id' => $student->id,
        ]);

        $this->assertCount(2, $course->invoices);
        $this->assertTrue($course->invoices->contains($invoice1));
        $this->assertTrue($course->invoices->contains($invoice2));
    }
}
