@extends('layouts.app')

@section('title', '创建课程')
@section('page-title', '创建课程')

@section('content')
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ route('teacher.courses.store') }}">
            @csrf

            <div class="mb-3">
                <label for="name" class="form-label">课程名 <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                       id="name" name="name" value="{{ old('name') }}" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="year_month" class="form-label">年月 <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('year_month') is-invalid @enderror" 
                       id="year_month" name="year_month" value="{{ old('year_month') }}" 
                       placeholder="格式：202310" maxlength="6" required>
                @error('year_month')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">格式：YYYYMM，例如：202310</small>
            </div>

            <div class="mb-3">
                <label for="fee" class="form-label">费用 <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" class="form-control @error('fee') is-invalid @enderror" 
                       id="fee" name="fee" value="{{ old('fee') }}" required>
                @error('fee')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">选择学生 <span class="text-danger">*</span></label>
                @error('student_ids')
                    <div class="text-danger mb-2">{{ $message }}</div>
                @enderror
                @foreach($students as $student)
                    <div class="form-check">
                        <input class="form-check-input @error('student_ids') is-invalid @enderror" 
                               type="checkbox" name="student_ids[]" 
                               value="{{ $student->id }}" id="student_{{ $student->id }}"
                               {{ in_array($student->id, old('student_ids', [])) ? 'checked' : '' }}>
                        <label class="form-check-label" for="student_{{ $student->id }}">
                            {{ $student->name }} ({{ $student->email }})
                        </label>
                    </div>
                @endforeach
                @if($students->count() === 0)
                    <p class="text-muted">暂无学生，请在后台管理系统中先创建学生。</p>
                @endif
            </div>

            <div class="d-flex justify-content-between">
                <a href="{{ route('teacher.courses.index') }}" class="btn btn-secondary">取消</a>
                <button type="submit" class="btn btn-primary">创建</button>
            </div>
        </form>
    </div>
</div>
@endsection
