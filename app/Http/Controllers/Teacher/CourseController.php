<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 教师课程管理控制器
 *
 * 教师可以创建课程，课程能够设定：课程名、年月、费用、学生
 */
class CourseController extends Controller
{
    /**
     * 显示课程列表
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $teacher = Auth::guard('teacher')->user();


        $courses = Course::where('teacher_id', $teacher->id)
            ->withCount('students') // 统计学生数量
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('teacher.courses.index', compact('courses'));
    }

    /**
     * 显示创建课程表单
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $teacher = Auth::guard('teacher')->user();

        // 获取该教师管理的学生列表
        $students = Student::where('teacher_id', $teacher->id)
            ->orderBy('name')
            ->get();

        return view('teacher.courses.create', compact('students'));
    }

    /**
     * 存储新课程
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'year_month' => 'required|string|size:6|regex:/^\d{6}$/',
            'fee' => 'required|numeric|min:0',
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
        ]);

        $teacher = Auth::guard('teacher')->user();

        // 验证学生是否属于该教师
        $validStudentIds = Student::where('teacher_id', $teacher->id)
            ->whereIn('id', $request->student_ids)
            ->pluck('id')
            ->toArray();

        if (count($validStudentIds) !== count($request->student_ids)) {
            return back()->withErrors(['student_ids' => '所选学生中部分不属于您的管理范围。'])->withInput();
        }

        DB::transaction(function () use ($request, $teacher, $validStudentIds) {
            // 创建课程
            $course = Course::create([
                'name' => $request->name,
                'year_month' => $request->year_month,
                'fee' => $request->fee,
                'teacher_id' => $teacher->id,
            ]);

            // 关联学生（使用sync避免重复关联）
            $course->students()->sync($validStudentIds);
        });

        return redirect()->route('teacher.courses.index')
            ->with('success', '课程创建成功！');
    }

    /**
     * 显示课程详情
     *
     * @param  \App\Models\Course  $course
     * @return \Illuminate\View\View
     */
    public function show(Course $course)
    {
        $teacher = Auth::guard('teacher')->user();

        // 验证课程是否属于该教师
        if ($course->teacher_id !== $teacher->id) {
            abort(403, '无权访问此课程。');
        }

        $course->load(['students', 'invoices.student']);

        return view('teacher.courses.show', compact('course'));
    }

    /**
     * 显示编辑课程表单
     *
     * @param  \App\Models\Course  $course
     * @return \Illuminate\View\View
     */
    public function edit(Course $course)
    {
        $teacher = Auth::guard('teacher')->user();

        // 验证课程是否属于该教师
        if ($course->teacher_id !== $teacher->id) {
            abort(403, '无权访问此课程。');
        }

        // 获取该教师管理的学生列表
        $students = Student::where('teacher_id', $teacher->id)
            ->orderBy('name')
            ->get();

        // 加载当前课程的学生关联
        $course->load('students');

        return view('teacher.courses.edit', compact('course', 'students'));
    }

    /**
     * 更新课程
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Course  $course
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Course $course)
    {
        $teacher = Auth::guard('teacher')->user();

        // 验证课程是否属于该教师
        if ($course->teacher_id !== $teacher->id) {
            abort(403, '无权访问此课程。');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'year_month' => 'required|string|size:6|regex:/^\d{6}$/',
            'fee' => 'required|numeric|min:0',
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
        ]);

        // 验证学生是否属于该教师
        $validStudentIds = Student::where('teacher_id', $teacher->id)
            ->whereIn('id', $request->student_ids)
            ->pluck('id')
            ->toArray();

        if (count($validStudentIds) !== count($request->student_ids)) {
            return back()->withErrors(['student_ids' => '所选学生中部分不属于您的管理范围。'])->withInput();
        }

        DB::transaction(function () use ($request, $course, $validStudentIds) {
            // 更新课程
            $course->update([
                'name' => $request->name,
                'year_month' => $request->year_month,
                'fee' => $request->fee,
            ]);

            // 更新学生关联
            $course->students()->sync($validStudentIds);
        });

        return redirect()->route('teacher.courses.index')
            ->with('success', '课程更新成功！');
    }

    /**
     * 删除课程
     *
     * @param  \App\Models\Course  $course
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Course $course)
    {
        $teacher = Auth::guard('teacher')->user();

        // 验证课程是否属于该教师
        if ($course->teacher_id !== $teacher->id) {
            abort(403, '无权访问此课程。');
        }

        $course->delete();

        return redirect()->route('teacher.courses.index')
            ->with('success', '课程删除成功！');
    }
}
