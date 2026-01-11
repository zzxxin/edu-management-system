@extends('layouts.app')

@section('title', '我的课程')
@section('page-title', '我的课程')

@section('content')
<div class="card">
    <div class="card-body">
        @if($courses->count() > 0)
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>课程名</th>
                            <th>教师</th>
                            <th>年月</th>
                            <th>费用</th>
                            <th>加入时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($courses as $course)
                            <tr>
                                <td>{{ $course->id }}</td>
                                <td>{{ $course->name }}</td>
                                <td>{{ $course->teacher->name }}</td>
                                <td>{{ $course->year_month }}</td>
                                <td>¥{{ number_format($course->fee, 2) }}</td>
                                <td>{{ $course->pivot->created_at->format('Y-m-d H:i') }}</td>
                                <td>
                                    <a href="{{ route('student.courses.show', $course->id) }}" class="btn btn-sm btn-info">查看详情</a>
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
            <p class="text-center text-muted">您还没有加入任何课程</p>
        @endif
    </div>
</div>
@endsection
