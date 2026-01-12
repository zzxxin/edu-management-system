<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Student;
use App\Services\CourseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 教师课程管理控制器
 *
 * 教师可以创建课程，课程能够设定：课程名、年月、费用、学生
 */
class CourseController extends Controller
{
    /**
     * @var CourseService
     */
    protected $courseService;

    /**
     * 构造函数
     *
     * @param CourseService $courseService
     */
    public function __construct(CourseService $courseService)
    {
        $this->courseService = $courseService;
    }

    /**
     * 显示课程列表
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $teacher = Auth::guard('teacher')->user();

        $courses = Course::forTeacher($teacher->id)
            ->withCount('students')
            ->latest()
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
        $students = Student::forTeacher($teacher->id)
            ->orderByName()
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
            'student_ids.*' => 'required|integer|exists:students,id',
        ], [
            'name.required' => '课程名称不能为空。',
            'name.string' => '课程名称必须是字符串。',
            'name.max' => '课程名称长度不能超过255个字符。',
            'year_month.required' => '年月不能为空。',
            'year_month.size' => '年月必须是6位数字。',
            'year_month.regex' => '年月格式不正确，应为6位数字（如：202310）。',
            'fee.required' => '课程费用不能为空。',
            'fee.numeric' => '课程费用必须是数字。',
            'fee.min' => '课程费用不能小于0。',
            'student_ids.required' => '请选择至少一个学生。',
            'student_ids.array' => '学生ID必须是数组。',
            'student_ids.min' => '至少需要选择一个学生。',
            'student_ids.*.required' => '学生ID不能为空。',
            'student_ids.*.integer' => '学生ID必须是整数。',
            'student_ids.*.exists' => '部分学生ID不存在。',
        ]);

        $teacher = Auth::guard('teacher')->user();

        // 验证学生是否属于该教师
        $validStudentIds = $this->courseService->validateStudentIds($request->student_ids, $teacher->id);

        if (count($validStudentIds) !== count($request->student_ids)) {
            return back()->withErrors(['student_ids' => '所选学生中部分不属于您的管理范围。'])->withInput();
        }

        // 使用服务创建课程
        $this->courseService->createCourse($request->only(['name', 'year_month', 'fee']), $teacher->id, $validStudentIds);

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
        if (!$course->belongsToTeacher($teacher->id)) {
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
        if (!$course->belongsToTeacher($teacher->id)) {
            abort(403, '无权访问此课程。');
        }

        // 获取该教师管理的学生列表
        $students = Student::forTeacher($teacher->id)
            ->orderByName()
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
        if (!$course->belongsToTeacher($teacher->id)) {
            abort(403, '无权访问此课程。');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'year_month' => 'required|string|size:6|regex:/^\d{6}$/',
            'fee' => 'required|numeric|min:0',
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'required|integer|exists:students,id',
        ], [
            'name.required' => '课程名称不能为空。',
            'name.string' => '课程名称必须是字符串。',
            'name.max' => '课程名称长度不能超过255个字符。',
            'year_month.required' => '年月不能为空。',
            'year_month.size' => '年月必须是6位数字。',
            'year_month.regex' => '年月格式不正确，应为6位数字（如：202310）。',
            'fee.required' => '课程费用不能为空。',
            'fee.numeric' => '课程费用必须是数字。',
            'fee.min' => '课程费用不能小于0。',
            'student_ids.required' => '请选择至少一个学生。',
            'student_ids.array' => '学生ID必须是数组。',
            'student_ids.min' => '至少需要选择一个学生。',
            'student_ids.*.required' => '学生ID不能为空。',
            'student_ids.*.integer' => '学生ID必须是整数。',
            'student_ids.*.exists' => '部分学生ID不存在。',
        ]);

        // 验证学生是否属于该教师
        $validStudentIds = $this->courseService->validateStudentIds($request->student_ids, $teacher->id);

        if (count($validStudentIds) !== count($request->student_ids)) {
            return back()->withErrors(['student_ids' => '所选学生中部分不属于您的管理范围。'])->withInput();
        }

        // 使用服务更新课程
        $this->courseService->updateCourse($course, $request->only(['name', 'year_month', 'fee']), $validStudentIds);

        return redirect()->route('teacher.courses.index')
            ->with('success', '课程更新成功！');
    }
}
