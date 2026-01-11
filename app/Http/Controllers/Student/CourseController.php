<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * 学生课程管理控制器
 *
 * 学生在课程一览页面，可查看自己的课程信息
 */
class CourseController extends Controller
{
    /**
     * 显示我的课程列表
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $student = Auth::guard('student')->user();


        $courses = $student->courses()
            ->with(['teacher']) // 预加载教师关联
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('student.courses.index', compact('courses'));
    }

    /**
     * 显示课程详情
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        $student = Auth::guard('student')->user();

        // 验证课程是否属于该学生
        $course = $student->courses()
            ->where('courses.id', $id)
            ->with(['teacher', 'invoices' => function ($query) use ($student) {
                // 只加载该学生的账单，且过滤掉未发送（pending）状态的账单
                $query->where('student_id', $student->id)
                      ->where('status', '!=', \App\Models\Invoice::STATUS_PENDING);
            }])
            ->firstOrFail();

        return view('student.courses.show', compact('course'));
    }
}
