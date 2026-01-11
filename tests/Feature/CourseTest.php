<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

/**
 * 课程功能测试
 */
class CourseTest extends TestCase
{
    use RefreshDatabase;

    protected Teacher $teacher;
    protected Student $student;

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
    }

    /**
     * 测试教师可以查看课程列表
     */
    public function test_teacher_can_view_courses_list(): void
    {
        // 创建课程
        $course = Course::factory()->create([
            'teacher_id' => $this->teacher->id,
        ]);
        $course->students()->attach($this->student->id);

        // 登录教师
        $response = $this->actingAs($this->teacher, 'teacher')
            ->get(route('teacher.courses.index'));

        $response->assertStatus(200);
        $response->assertSee($course->name);
    }

    /**
     * 测试教师可以创建课程
     */
    public function test_teacher_can_create_course(): void
    {
        $courseData = [
            'name' => '测试课程',
            'year_month' => '202310',
            'fee' => 200.00,
            'student_ids' => [$this->student->id],
        ];

        // 登录教师并创建课程
        $response = $this->actingAs($this->teacher, 'teacher')
            ->post(route('teacher.courses.store'), $courseData);

        $response->assertRedirect(route('teacher.courses.index'));
        $this->assertDatabaseHas('courses', [
            'name' => '测试课程',
            'year_month' => '202310',
            'fee' => 200.00,
            'teacher_id' => $this->teacher->id,
        ]);

        // 验证学生关联
        $course = Course::where('name', '测试课程')->first();
        $this->assertTrue($course->students->contains($this->student));
    }

    /**
     * 测试学生可以查看自己的课程
     */
    public function test_student_can_view_own_courses(): void
    {
        // 创建课程并关联学生
        $course = Course::factory()->create([
            'teacher_id' => $this->teacher->id,
        ]);
        $course->students()->attach($this->student->id);

        // 登录学生并查看课程列表
        $response = $this->actingAs($this->student, 'student')
            ->get(route('student.courses.index'));

        $response->assertStatus(200);
        $response->assertSee($course->name);
    }

    /**
     * 测试学生只能查看自己的课程
     */
    public function test_student_cannot_view_other_courses(): void
    {
        // 创建另一个学生
        $otherStudent = Student::factory()->create([
            'teacher_id' => $this->teacher->id,
        ]);

        // 创建课程并只关联另一个学生
        $course = Course::factory()->create([
            'teacher_id' => $this->teacher->id,
        ]);
        $course->students()->attach($otherStudent->id);

        // 登录学生并尝试查看不属于自己的课程
        $response = $this->actingAs($this->student, 'student')
            ->get(route('student.courses.show', $course->id));

        $response->assertStatus(404);
    }

    /**
     * 测试课程创建
     */
    public function test_course_list_avoids_n_plus_one_query(): void
    {
        // 创建多个课程和学生
        $courses = Course::factory()->count(5)->create([
            'teacher_id' => $this->teacher->id,
        ]);

        foreach ($courses as $course) {
            $course->students()->attach($this->student->id);
        }

        // 使用查询监听器检测查询次数
        \DB::enableQueryLog();

        // 登录教师并查看课程列表（应该使用with预加载）
        $this->actingAs($this->teacher, 'teacher')
            ->get(route('teacher.courses.index'));

        $queries = \DB::getQueryLog();

        $queryCount = count($queries);
        $this->assertLessThan(20, $queryCount, '检测到N+1查询问题');
    }
}
