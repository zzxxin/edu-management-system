@extends('layouts.app')

@section('title', '账单详情')
@section('page-title', '账单详情')

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <h5 class="card-title">账单信息</h5>
        <dl class="row">
            <dt class="col-sm-3">账单ID：</dt>
            <dd class="col-sm-9">{{ $invoice->id }}</dd>

            <dt class="col-sm-3">课程：</dt>
            <dd class="col-sm-9">{{ $invoice->course->name }}</dd>

            <dt class="col-sm-3">学生：</dt>
            <dd class="col-sm-9">{{ $invoice->student->name }} ({{ $invoice->student->email }})</dd>

            <dt class="col-sm-3">年月：</dt>
            <dd class="col-sm-9">{{ $invoice->year_month }}</dd>

            <dt class="col-sm-3">金额：</dt>
            <dd class="col-sm-9">¥{{ number_format($invoice->amount, 2) }}</dd>

            <dt class="col-sm-3">状态：</dt>
            <dd class="col-sm-9">
                @if($invoice->status === 'pending')
                    <span class="badge bg-warning">待发送</span>
                @elseif($invoice->status === 'sent')
                    <span class="badge bg-info">待支付</span>
                @elseif($invoice->status === 'paid')
                    <span class="badge bg-success">已支付</span>
                @elseif($invoice->status === 'rejected')
                    <span class="badge bg-secondary">已拒绝</span>
                @else
                    <span class="badge bg-danger">支付失败</span>
                @endif
            </dd>

            @if($invoice->sent_at)
                <dt class="col-sm-3">发送时间：</dt>
                <dd class="col-sm-9">{{ $invoice->sent_at->format('Y-m-d H:i:s') }}</dd>
            @endif

            @if($invoice->paid_at)
                <dt class="col-sm-3">支付时间：</dt>
                <dd class="col-sm-9">{{ $invoice->paid_at->format('Y-m-d H:i:s') }}</dd>
            @endif

            @if($invoice->status === 'rejected')
                <dt class="col-sm-3">拒绝时间：</dt>
                <dd class="col-sm-9">
                    @if($invoice->rejected_at)
                        {{ $invoice->rejected_at->format('Y-m-d H:i:s') }}
                    @else
                        <span class="text-muted">未记录（旧数据）</span>
                    @endif
                </dd>
            @endif

            @if($invoice->omise_charge_id)
                <dt class="col-sm-3">Omise 支付ID：</dt>
                <dd class="col-sm-9"><code>{{ $invoice->omise_charge_id }}</code></dd>
            @endif

            <dt class="col-sm-3">创建时间：</dt>
            <dd class="col-sm-9">{{ $invoice->created_at->format('Y-m-d H:i:s') }}</dd>

            <dt class="col-sm-3">更新时间：</dt>
            <dd class="col-sm-9">{{ $invoice->updated_at->format('Y-m-d H:i:s') }}</dd>
        </dl>
    </div>
</div>

@if($invoice->payments->count() > 0)
<div class="card mb-3">
    <div class="card-body">
        <h5 class="card-title">支付记录</h5>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>支付ID</th>
                        <th>Omise Charge ID</th>
                        <th>金额</th>
                        <th>币种</th>
                        <th>状态</th>
                        <th>支付方式</th>
                        <th>支付时间</th>
                        <th>错误信息</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->payments as $payment)
                    <tr>
                        <td>#{{ $payment->id }}</td>
                        <td>
                            @if($payment->omise_charge_id)
                                <code>{{ $payment->omise_charge_id }}</code>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>{{ number_format($payment->amount, 2) }}</td>
                        <td><span class="badge bg-secondary">{{ strtoupper($payment->currency) }}</span></td>
                        <td>
                            @if($payment->status === 'successful')
                                <span class="badge bg-success">成功</span>
                            @elseif($payment->status === 'failed')
                                <span class="badge bg-danger">失败</span>
                            @else
                                <span class="badge bg-warning">处理中</span>
                            @endif
                        </td>
                        <td>{{ $payment->payment_method ?? '-' }}</td>
                        <td>
                            @if($payment->paid_at)
                                {{ $payment->paid_at->format('Y-m-d H:i:s') }}
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if($payment->error_message)
                                <small class="text-danger">{{ $payment->error_message }}</small>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <h5 class="card-title">操作</h5>
        @if($invoice->status === 'pending')
            <form method="POST" action="{{ route('teacher.invoices.send', $invoice) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-primary">发送账单</button>
            </form>
        @elseif($invoice->status === 'rejected')
            <form method="POST" action="{{ route('teacher.invoices.send', $invoice) }}" class="d-inline" onsubmit="return confirm('确定要重新发送此账单吗？\n\n重新发送后，学生将再次收到支付通知。');">
                @csrf
                <button type="submit" class="btn btn-warning">重新发送账单</button>
            </form>
        @endif
        <a href="{{ route('teacher.invoices.index') }}" class="btn btn-secondary">返回列表</a>
    </div>
</div>
@endsection
