@extends('layouts.app')

@section('title', '学生仪表板')
@section('page-title', '仪表板')

@section('content')
<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title">我的课程</h5>
                <h2 class="card-text">{{ $stats['courses_count'] }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h5 class="card-title">待支付账单</h5>
                <h2 class="card-text">{{ $stats['invoices_sent'] }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h5 class="card-title">已支付账单</h5>
                <h2 class="card-text">{{ $stats['invoices_paid'] }}</h2>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">快速操作</h5>
                <div class="d-grid gap-2 d-md-flex">
                    <a href="{{ route('student.courses.index') }}" class="btn btn-primary">查看我的课程</a>
                    <a href="{{ route('student.invoices.index') }}" class="btn btn-info">查看我的账单</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
