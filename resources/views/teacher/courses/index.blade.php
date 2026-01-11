@extends('layouts.app')

@section('title', '课程管理')
@section('page-title', '课程列表')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="{{ route('teacher.courses.create') }}" class="btn btn-primary">创建课程</a>
</div>

<div class="card">
    <div class="card-body">
        @if($courses->count() > 0)
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>课程名</th>
                            <th>年月</th>
                            <th>费用</th>
                            <th>学生数量</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($courses as $course)
                            <tr>
                                <td>{{ $course->id }}</td>
                                <td>{{ $course->name }}</td>
                                <td>{{ $course->year_month }}</td>
                                <td>¥{{ number_format($course->fee, 2) }}</td>
                                <td>{{ $course->students_count }}</td>
                                <td>{{ $course->created_at->format('Y-m-d H:i') }}</td>
                                <td>
                                    <a href="{{ route('teacher.courses.show', $course) }}" class="btn btn-sm btn-info">查看</a>
                                    <a href="{{ route('teacher.courses.edit', $course) }}" class="btn btn-sm btn-warning">编辑</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                {{ $courses->links() }}
            </div>
        @else
            <p class="text-center text-muted">暂无课程，<a href="{{ route('teacher.courses.create') }}">创建第一个课程</a></p>
        @endif
    </div>
</div>
@endsection
