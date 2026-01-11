@extends('layouts.app')

@section('title', '教师仪表板')
@section('page-title', '仪表板')

@section('content')
<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title">课程总数</h5>
                <h2 class="card-text">{{ $stats['courses_count'] }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h5 class="card-title">待发送账单</h5>
                <h2 class="card-text">{{ $stats['invoices_pending'] }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h5 class="card-title">已发送账单</h5>
                <h2 class="card-text">{{ $stats['invoices_sent'] }}</h2>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
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
                    <a href="{{ route('teacher.courses.create') }}" class="btn btn-primary">创建课程</a>
                    <a href="{{ route('teacher.invoices.create') }}" class="btn btn-success">创建账单</a>
                    <a href="{{ route('teacher.courses.index') }}" class="btn btn-info">查看所有课程</a>
                    <a href="{{ route('teacher.invoices.index') }}" class="btn btn-warning">查看所有账单</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
