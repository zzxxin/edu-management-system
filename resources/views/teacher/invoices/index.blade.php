@extends('layouts.app')

@section('title', '账单管理')
@section('page-title', '账单列表')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="{{ route('teacher.invoices.create') }}" class="btn btn-primary">创建账单</a>
    <form method="POST" action="{{ route('teacher.invoices.batch-send') }}" class="d-inline" id="batchSendForm">
        @csrf
        <input type="hidden" name="invoice_ids" id="batchInvoiceIds">
        <button type="submit" class="btn btn-success" id="batchSendBtn" disabled>批量发送</button>
    </form>
</div>

<div class="card">
    <div class="card-body">
        @if($invoices->count() > 0)
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll">
                            </th>
                            <th>ID</th>
                            <th>课程</th>
                            <th>学生</th>
                            <th>金额</th>
                            <th>状态</th>
                            <th>操作时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoices as $invoice)
                            <tr>
                                <td>
                                    @if($invoice->status === 'pending' || $invoice->status === 'rejected')
                                        <input type="checkbox" class="invoice-checkbox" value="{{ $invoice->id }}">
                                    @endif
                                </td>
                                <td>{{ $invoice->id }}</td>
                                <td>{{ $invoice->course->name }}</td>
                                <td>{{ $invoice->student->name }}</td>
                                <td>¥{{ number_format($invoice->amount, 2) }}</td>
                                <td>
                                    @if($invoice->status === 'pending')
                                        <span class="badge bg-warning">待发送</span>
                                    @elseif($invoice->status === 'sent')
                                        <span class="badge bg-info">已发送</span>
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
                                    <a href="{{ route('teacher.invoices.show', $invoice) }}" class="btn btn-sm btn-info">详情</a>
                                    @if($invoice->status === 'pending')
                                        <form action="{{ route('teacher.invoices.send', $invoice) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-success">发送</button>
                                        </form>
                                    @elseif($invoice->status === 'rejected')
                                        <form action="{{ route('teacher.invoices.send', $invoice) }}" method="POST" class="d-inline" onsubmit="return confirm('确定要重新发送此账单吗？');">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-warning">重新发送</button>
                                        </form>
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
            <p class="text-center text-muted">暂无账单，<a href="{{ route('teacher.invoices.create') }}">创建第一个账单</a></p>
        @endif
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.querySelectorAll('.invoice-checkbox');
        const batchSendBtn = document.getElementById('batchSendBtn');
        const batchInvoiceIds = document.getElementById('batchInvoiceIds');

        selectAll.addEventListener('change', function() {
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBatchSendBtn();
        });

        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateBatchSendBtn();
                selectAll.checked = Array.from(checkboxes).every(cb => cb.checked);
            });
        });

        function updateBatchSendBtn() {
            const selected = Array.from(checkboxes).filter(cb => cb.checked).map(cb => cb.value);
            batchSendBtn.disabled = selected.length < 2; // 至少需要选择两个
            batchInvoiceIds.value = JSON.stringify(selected);
        }

        document.getElementById('batchSendForm').addEventListener('submit', function(e) {
            const selected = Array.from(checkboxes).filter(cb => cb.checked).map(cb => cb.value);
            if (selected.length < 2) {
                alert('批量发送至少需要选择两个账单。');
                e.preventDefault();
                return;
            }
            if (!confirm('确定要批量发送选中的 ' + selected.length + ' 个账单吗？')) {
                e.preventDefault();
            }
        });
    });
</script>
@endpush
@endsection
