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

            <dt class="col-sm-3">教师：</dt>
            <dd class="col-sm-9">{{ $invoice->course->teacher->name }}</dd>

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

            <dt class="col-sm-3">创建时间：</dt>
            <dd class="col-sm-9">{{ $invoice->created_at->format('Y-m-d H:i:s') }}</dd>
        </dl>
    </div>
</div>

@if($invoice->status === 'sent')
<div class="card">
    <div class="card-body">
        <h5 class="card-title">支付</h5>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('student.invoices.pay', $invoice) }}" id="paymentForm">
            @csrf

            <!-- Omise支付表单 -->
            <div class="mb-3">
                <label for="card_number" class="form-label">卡号 <span class="text-danger">*</span></label>
                <input type="text"
                       class="form-control"
                       id="card_number"
                       name="card_number"
                       placeholder="4242 4242 4242 4242"
                       autocomplete="cc-number"
                       maxlength="19"
                       required>
                <small class="form-text text-muted">测试卡号：4242 4242 4242 4242</small>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="expiry_month" class="form-label">过期月份 <span class="text-danger">*</span></label>
                    <input type="text"
                           class="form-control"
                           id="expiry_month"
                           name="expiry_month"
                           placeholder="MM"
                           maxlength="2"
                           pattern="[0-9]{2}"
                           autocomplete="cc-exp-month"
                           required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="expiry_year" class="form-label">过期年份 <span class="text-danger">*</span></label>
                    <input type="text"
                           class="form-control"
                           id="expiry_year"
                           name="expiry_year"
                           placeholder="YYYY"
                           maxlength="4"
                           pattern="[0-9]{4}"
                           autocomplete="cc-exp-year"
                           required>
                </div>
            </div>

            <div class="mb-3">
                <label for="cvv" class="form-label">CVV <span class="text-danger">*</span></label>
                <input type="text"
                       class="form-control"
                       id="cvv"
                       name="cvv"
                       placeholder="123"
                       maxlength="4"
                       pattern="[0-9]{3,4}"
                       autocomplete="cc-csc"
                       required>
                <small class="form-text text-muted">卡背面3-4位数字</small>
            </div>

            <div class="mb-3">
                <label for="card_holder_name" class="form-label">持卡人姓名 <span class="text-danger">*</span></label>
                <input type="text"
                       class="form-control"
                       id="card_holder_name"
                       name="card_holder_name"
                       autocomplete="cc-name"
                       required>
            </div>

            <div class="mb-3">
                <label for="currency" class="form-label">支付币种 <span class="text-danger">*</span></label>
                @php
                    $defaultCurrency = config('services.omise.currency', 'jpy');
                    $isTestKey = strpos(config('services.omise.public_key', ''), 'pkey_test_') === 0;
                @endphp
                <select class="form-select @error('currency') is-invalid @enderror"
                        id="currency"
                        name="currency"
                        required>
                    <option value="">请选择币种</option>
                    <option value="jpy" {{ old('currency', $defaultCurrency) === 'jpy' ? 'selected' : '' }}>
                        JPY - 日元 (Japanese Yen){{ $defaultCurrency === 'jpy' ? ' (默认)' : '' }}
                    </option>
                    <option value="thb" {{ old('currency', $defaultCurrency) === 'thb' ? 'selected' : '' }}>
                        THB - 泰铢 (Thai Baht){{ $defaultCurrency === 'thb' ? ' (默认)' : '' }}
                    </option>
                    <option value="sgd" {{ old('currency', $defaultCurrency) === 'sgd' ? 'selected' : '' }}>
                        SGD - 新加坡元 (Singapore Dollar){{ $defaultCurrency === 'sgd' ? ' (默认)' : '' }}
                    </option>
                    <option value="usd" {{ old('currency', $defaultCurrency) === 'usd' ? 'selected' : '' }}>
                        USD - 美元 (US Dollar){{ $defaultCurrency === 'usd' ? ' (默认)' : '' }}
                    </option>
                </select>
                @error('currency')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">
                    @if($isTestKey)
                        <strong class="text-warning">⚠️ 测试账号提示：</strong>测试账号通常只支持默认币种（{{ strtoupper($defaultCurrency) }}）。如果选择其他币种可能失败，建议使用默认币种。
                    @else
                        请选择您要使用的支付币种。注意：不同币种可能需要启用多币种支付功能。
                    @endif
                </small>
            </div>



            <input type="hidden" name="omise_token" id="omise_token">

            <div class="alert alert-info">
                <small>
                    <strong>测试说明：</strong><br>
                    使用测试卡号：4242 4242 4242 4242<br>
                    过期日期：任意未来日期（如 12/2025）<br>
                    CVV：任意3位数字（如 123）
                </small>
            </div>

            <button type="submit" class="btn btn-primary" id="submitBtn">
                <span id="submitBtnText">支付 ¥{{ number_format($invoice->amount, 2) }}</span>
                <span id="submitBtnSpinner" class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
            </button>
        </form>
    </div>
</div>
@endif

@if($invoice->status === 'sent')
<div class="card mb-3">
    <div class="card-body">
        <h5 class="card-title">操作</h5>
        <form method="POST" action="{{ route('student.invoices.reject', $invoice) }}" class="d-inline" id="rejectForm" onsubmit="return confirmReject();">
            @csrf
            <button type="submit" class="btn btn-danger">拒绝支付</button>
        </form>
        <p class="text-muted mt-2 mb-0">
            <small>拒绝支付后，账单将失效，无法再次支付。</small>
        </p>
    </div>
</div>
@endif

<a href="{{ route('student.invoices.index') }}" class="btn btn-secondary mt-3">返回列表</a>

@push('scripts')
<script>
    function confirmReject() {
        return confirm('确定要拒绝支付此账单吗？\n\n拒绝后账单将失效，无法再次支付。');
    }
</script>
@if($invoice->status === 'sent')
<script src="https://cdn.omise.co/omise.js"></script>
<script>
    (function() {
        // 初始化 Omise（按照官方标准）
        Omise.setPublicKey('{{ config("services.omise.public_key") }}');

        const form = document.getElementById('paymentForm');
        const submitBtn = document.getElementById('submitBtn');
        const cardNumberInput = document.getElementById('card_number');
        const expiryMonthInput = document.getElementById('expiry_month');
        const expiryYearInput = document.getElementById('expiry_year');
        const cvvInput = document.getElementById('cvv');

        // 格式化卡号输入（每4位添加空格）
        cardNumberInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formattedValue;
        });

        // 限制月份输入（01-12）
        expiryMonthInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value > 12) value = 12;
            if (value.length === 1 && value > 1) value = '0' + value;
            e.target.value = value;
        });

        // 限制年份输入（4位数字）
        expiryYearInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
        });

        // 限制 CVV 输入（3-4位数字）
        cvvInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
        });

        // 防止重复提交的辅助函数
        function setSubmitButtonLoading(loading) {
            const submitBtnText = document.getElementById('submitBtnText');
            const submitBtnSpinner = document.getElementById('submitBtnSpinner');

            if (loading) {
                submitBtn.disabled = true;
                submitBtnText.textContent = '处理中...';
                submitBtnSpinner.classList.remove('d-none');
                submitBtn.classList.add('disabled');
            } else {
                submitBtn.disabled = false;
                submitBtnText.textContent = '支付 ¥{{ number_format($invoice->amount, 2) }}';
                submitBtnSpinner.classList.add('d-none');
                submitBtn.classList.remove('disabled');
            }
        }

        // 表单提交处理
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // 如果按钮已禁用，说明正在处理中，直接返回
            if (submitBtn.disabled) {
                return false;
            }

            // 获取币种选择（只声明一次）
            const currencySelect = document.getElementById('currency');
            const selectedCurrency = currencySelect.value;
            const defaultCurrency = '{{ config("services.omise.currency", "jpy") }}';
            const publicKey = '{{ config("services.omise.public_key", "") }}';
            const isTestKey = publicKey.indexOf('pkey_test_') === 0;

            // 验证币种是否已选择
            if (!selectedCurrency) {
                alert('请选择支付币种');
                return;
            }

            // 如果是测试账号且选择了非默认币种，给出警告
            if (isTestKey && selectedCurrency !== defaultCurrency) {
                if (!confirm('⚠️ 警告：您使用的是测试账号，通常只支持默认币种（' + defaultCurrency.toUpperCase() + '）。\n\n选择其他币种（' + selectedCurrency.toUpperCase() + '）可能会失败。\n\n是否继续使用 ' + selectedCurrency.toUpperCase() + ' 支付？\n\n建议：使用默认币种 ' + defaultCurrency.toUpperCase() + ' 以确保支付成功。')) {
                    return;
                }
            }

            // 禁用提交按钮，防止重复提交
            setSubmitButtonLoading(true);

            // 验证表单
            if (!validateForm()) {
                setSubmitButtonLoading(false);
                return;
            }

            // 在创建 Omise token 前刷新 CSRF token，避免 419 错误
            const csrfInput = form.querySelector('input[name="_token"]');
            const metaToken = document.querySelector('meta[name="csrf-token"]');

            // 先刷新 CSRF token
            fetch('{{ route("student.invoices.show", $invoice) }}', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                },
                credentials: 'same-origin'
            })
            .then(response => response.text())
            .then(html => {
                // 从响应中提取新的 CSRF token
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newToken = doc.querySelector('meta[name="csrf-token"]');
                if (newToken && csrfInput) {
                    csrfInput.value = newToken.getAttribute('content');
                    // 同时更新 meta 标签
                    if (metaToken) {
                        metaToken.setAttribute('content', newToken.getAttribute('content'));
                    }
                }

                // CSRF token 刷新成功后，创建 Omise token
                return createOmiseToken();
            })
            .catch(error => {
                console.warn('Failed to refresh CSRF token, using existing token:', error);
                // 即使刷新失败也继续，使用现有的 token
                return createOmiseToken();
            })
            .catch(error => {
                // 处理所有错误情况，恢复按钮状态
                console.error('Payment processing error:', error);
                setSubmitButtonLoading(false);
            });

            // 创建 Omise token 的函数（返回 Promise）
            function createOmiseToken() {
                return new Promise(function(resolve, reject) {
                    // 准备卡片数据（按照 Omise 官方标准）
                    const card = {
                        name: document.getElementById('card_holder_name').value.trim(),
                        number: cardNumberInput.value.replace(/\s/g, ''),
                        expiration_month: expiryMonthInput.value.padStart(2, '0'),
                        expiration_year: expiryYearInput.value,
                        security_code: cvvInput.value,
                        currency: selectedCurrency.toUpperCase() // 指定币种（Omise 需要大写的币种代码）
                    };

                    // 使用 Omise.js 创建 Token（按照官方标准，包含币种信息）
                    Omise.createToken('card', card, function(statusCode, response) {
                        if (statusCode === 200) {
                            // Token 创建成功，设置 token 并提交表单
                            const tokenInput = document.getElementById('omise_token');
                            console.log('Omise response:', response);

                            if (!tokenInput) {
                                console.error('omise_token input field not found');
                                alert('支付表单错误：找不到 token 输入框，请刷新页面重试');
                                setSubmitButtonLoading(false);
                                reject(new Error('omise_token input field not found'));
                                return;
                            }

                            if (!response || !response.id) {
                                console.error('Token creation failed: missing response or token ID', {
                                    response: response,
                                    statusCode: statusCode
                                });
                                alert('Token 创建失败：无法获取 token ID，请重试');
                                setSubmitButtonLoading(false);
                                reject(new Error('Token creation failed: missing response or token ID'));
                                return;
                            }

                            // 设置 token 值
                            tokenInput.value = response.id;
                            console.log('Token created and set:', response.id);
                            console.log('Token input value:', tokenInput.value);

                            // 验证 token 是否已设置
                            if (!tokenInput.value || tokenInput.value.trim() === '') {
                                console.error('Token not set correctly');
                                alert('Token 设置失败，请重试');
                                setSubmitButtonLoading(false);
                                reject(new Error('Token not set correctly'));
                                return;
                            }

                            // 确保表单提交前 token 已设置
                            console.log('Submitting form with token:', tokenInput.value);
                            resolve(response.id); // 返回 token ID
                            // 提交表单
                            form.submit();
                        } else {
                            // Token 创建失败，显示错误信息
                            let errorMessage = '支付处理失败';
                            if (response && response.message) {
                                errorMessage += '：' + response.message;
                            } else if (response && response.error) {
                                errorMessage += '：' + response.error;
                            } else {
                                errorMessage += '：未知错误 (状态码: ' + statusCode + ')';
                            }
                            console.error('Omise token creation failed:', statusCode, response);
                            alert(errorMessage);
                            setSubmitButtonLoading(false);
                            reject(new Error(errorMessage));
                        }
                    });
                });
            }
        });

        // 表单验证
        function validateForm() {
            const cardNumber = cardNumberInput.value.replace(/\s/g, '');
            const expiryMonth = expiryMonthInput.value;
            const expiryYear = expiryYearInput.value;
            const cvv = cvvInput.value;
            const cardHolderName = document.getElementById('card_holder_name').value.trim();

            if (cardNumber.length < 13 || cardNumber.length > 19) {
                alert('请输入有效的卡号');
                return false;
            }

            if (!expiryMonth || expiryMonth < 1 || expiryMonth > 12) {
                alert('请输入有效的过期月份（01-12）');
                return false;
            }

            if (!expiryYear || expiryYear.length !== 4) {
                alert('请输入有效的过期年份（4位数字）');
                return false;
            }

            const currentYear = new Date().getFullYear();
            if (parseInt(expiryYear) < currentYear) {
                alert('卡片已过期');
                return false;
            }

            if (cvv.length < 3 || cvv.length > 4) {
                alert('请输入有效的 CVV（3-4位数字）');
                return false;
            }

            if (!cardHolderName) {
                alert('请输入持卡人姓名');
                return false;
            }

            return true;
        }
    })();
</script>
@endif
@endpush
@endsection
