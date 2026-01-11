@extends('layouts.app')

@section('title', '我的账单')
@section('page-title', '我的账单')

@section('content')
<div class="card">
    <div class="card-body">
        @if($invoices->count() > 0)
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>课程</th>
                            <th>教师</th>
                            <th>金额</th>
                            <th>状态</th>
                            <th>操作时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $invoice)
                            <tr>
                                <td>{{ $invoice->id }}</td>
                                <td>{{ $invoice->course->name }}</td>
                                <td>{{ $invoice->course->teacher->name }}</td>
                                <td>¥{{ number_format($invoice->amount, 2) }}</td>
                            <td>
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
                            </td>
                                <td>
                                    @if($invoice->paid_at)
                                        <span class="text-success">支付：{{ $invoice->paid_at->format('Y-m-d H:i') }}</span>
                                    @elseif($invoice->rejected_at)
                                        <span class="text-secondary">拒绝：{{ $invoice->rejected_at->format('Y-m-d H:i') }}</span>
                                    @elseif($invoice->sent_at)
                                        <span class="text-info">发送：{{ $invoice->sent_at->format('Y-m-d H:i') }}</span>
                                    @else
                                        <span class="text-muted">创建：{{ $invoice->created_at->format('Y-m-d H:i') }}</span>
                                    @endif
                                </td>
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
            <div class="mt-3">
                {{ $invoices->links() }}
            </div>
        @else
            <p class="text-center text-muted">您还没有账单</p>
        @endif
    </div>
</div>
@endsection
