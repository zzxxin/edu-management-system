@extends('layouts.app')

@section('title', '创建账单')
@section('page-title', '创建账单')

@section('content')
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('teacher.invoices.store') }}">
            @csrf

            <div class="mb-3">
                <label for="course_id" class="form-label">选择课程 <span class="text-danger">*</span></label>
                <select class="form-select @error('course_id') is-invalid @enderror" 
                        id="course_id" name="course_id" required>
                    <option value="">请选择课程</option>
                    @foreach($courses as $course)
                        <option value="{{ $course->id }}" {{ old('course_id') == $course->id ? 'selected' : '' }}>
                            {{ $course->name }} ({{ $course->year_month }}) - ¥{{ number_format($course->fee, 2) }}
                        </option>
                    @endforeach
                </select>
                @error('course_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">课程学生列表</label>
                <div id="students-container">
                    <p class="text-muted">请先选择课程</p>
                </div>
            </div>

            <div class="mb-3">
                <div class="alert alert-info">
                    <small>选择课程后，系统将自动为该课程的所有学生创建账单。</small>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="{{ route('teacher.invoices.index') }}" class="btn btn-secondary">取消</a>
                <button type="submit" class="btn btn-primary">创建</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const courseSelect = document.getElementById('course_id');
        const studentsContainer = document.getElementById('students-container');
        
        // 课程和学生数据
        const courses = @json($courses->mapWithKeys(function($course) {
            return [$course->id => $course->students->map(function($student) {
                return ['id' => $student->id, 'name' => $student->name, 'email' => $student->email];
            })];
        }));

        courseSelect.addEventListener('change', function() {
            const courseId = this.value;
            studentsContainer.innerHTML = '';

            if (courseId && courses[courseId]) {
                const students = courses[courseId];
                if (students.length > 0) {
                    // 创建学生列表表格
                    let html = '<div class="table-responsive"><table class="table table-sm table-bordered">';
                    html += '<thead><tr><th>ID</th><th>姓名</th><th>邮箱</th></tr></thead>';
                    html += '<tbody>';
                    
                    students.forEach(function(student) {
                        html += `<tr>
                            <td>${student.id}</td>
                            <td>${student.name}</td>
                            <td>${student.email}</td>
                        </tr>`;
                    });
                    
                    html += '</tbody></table></div>';
                    html += `<p class="text-muted mt-2">共 <strong>${students.length}</strong> 名学生</p>`;
                    studentsContainer.innerHTML = html;
                } else {
                    studentsContainer.innerHTML = '<p class="text-muted">此课程暂无学生</p>';
                }
            } else {
                studentsContainer.innerHTML = '<p class="text-muted">请先选择课程</p>';
            }
        });

        // 如果已有选中的课程，触发change事件
        if (courseSelect.value) {
            courseSelect.dispatchEvent(new Event('change'));
        }
    });
</script>
@endpush
@endsection
