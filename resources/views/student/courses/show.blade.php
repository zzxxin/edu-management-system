@extends('layouts.app')

@section('title', '课程详情')
@section('page-title', '课程详情')

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <h5 class="card-title">课程信息</h5>
        <dl class="row">
            <dt class="col-sm-3">课程名：</dt>
            <dd class="col-sm-9">{{ $course->name }}</dd>

            <dt class="col-sm-3">教师：</dt>
            <dd class="col-sm-9">{{ $course->teacher->name }}</dd>

            <dt class="col-sm-3">年月：</dt>
            <dd class="col-sm-9">{{ $course->year_month }}</dd>

            <dt class="col-sm-3">费用：</dt>
            <dd class="col-sm-9">¥{{ number_format($course->fee, 2) }}</dd>

            <dt class="col-sm-3">加入时间：</dt>
            <dd class="col-sm-9">{{ $course->pivot->created_at->format('Y-m-d H:i:s') }}</dd>
        </dl>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h5 class="card-title">相关账单</h5>
        @if($course->invoices->count() > 0)
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>金额</th>
                            <th>状态</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($course->invoices as $invoice)
                            <tr>
                                <td>{{ $invoice->id }}</td>
                                <td>¥{{ number_format($invoice->amount, 2) }}</td>
                                <td>
                                    @if($invoice->status === 'sent')
                                        <span class="badge bg-info">待支付</span>
                                    @elseif($invoice->status === 'paid')
                                        <span class="badge bg-success">已支付</span>
                                    @else
                                        <span class="badge bg-danger">支付失败</span>
                                    @endif
                                </td>
                                <td>{{ $invoice->created_at->format('Y-m-d H:i') }}</td>
                                <td>
                                    @if($invoice->status === 'sent')
                                        <a href="{{ route('student.invoices.show', $invoice) }}" class="btn btn-sm btn-primary">支付</a>
                                    @else
                                        <a href="{{ route('student.invoices.show', $invoice) }}" class="btn btn-sm btn-info">查看</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted">暂无账单</p>
        @endif
    </div>
</div>

<a href="{{ route('student.courses.index') }}" class="btn btn-secondary mt-3">返回列表</a>
@endsection
