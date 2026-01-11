# 项目封装方法清单

本文档列出了项目中所有封装的业务逻辑方法，按照模块分类。

## 📦 1. Invoice 模型 (`app/Models/Invoice.php`)

### 关系方法 (Relationships)
- `course(): BelongsTo` - 账单所属的课程
- `student(): BelongsTo` - 账单所属的学生
- `payments(): HasMany` - 账单的所有支付记录（一个账单可以有多次支付尝试）
- `successfulPayments(): HasMany` - 账单的成功支付记录

### 状态判断方法 (Status Checkers)
- `isPending(): bool` - 是否为待发送状态
- `isSent(): bool` - 是否为已发送状态（待支付）
- `isPaid(): bool` - 是否为已支付状态
- `isRejected(): bool` - 是否为已拒绝状态

### 状态常量
- `STATUS_PENDING = 'pending'` - 待发送
- `STATUS_SENT = 'sent'` - 已发送（待支付）
- `STATUS_PAID = 'paid'` - 已支付
- `STATUS_FAILED = 'failed'` - 支付失败
- `STATUS_REJECTED = 'rejected'` - 已拒绝（学生拒绝支付）

---

## 💳 2. Payment 模型 (`app/Models/Payment.php`)

### 关系方法 (Relationships)
- `invoice(): BelongsTo` - 支付记录所属的账单

### 状态判断方法 (Status Checkers)
- `isPending(): bool` - 是否为处理中状态
- `isSuccessful(): bool` - 是否为成功状态
- `isFailed(): bool` - 是否为失败状态

### 状态常量
- `STATUS_PENDING = 'pending'` - 处理中
- `STATUS_SUCCESSFUL = 'successful'` - 成功
- `STATUS_FAILED = 'failed'` - 失败

---

## 🔐 3. OmisePaymentService 服务类 (`app/Services/OmisePaymentService.php`)

### 核心支付方法

#### `createCharge(Invoice $invoice, string $token, ?string $currency = null): array`
创建支付（Charge）
- **功能**：调用 Omise API 创建支付请求
- **参数**：
  - `$invoice` - 账单对象
  - `$token` - Omise Token ID（前端生成）
  - `$currency` - 货币代码（可选，默认使用配置值）
- **返回**：支付结果数组，包含 `success`, `charge`, `charge_id`, `status`, `payment_id` 等
- **特性**：
  - 自动处理货币转换（JPY 不乘以 100，其他货币乘以 100）
  - 金额验证（Omise 最小金额要求）
  - 自动创建 Payment 记录
  - 详细的错误处理和用户友好的错误信息

#### `getCharge(string $chargeId)`
获取支付详情
- **功能**：从 Omise API 获取指定 Charge 的详细信息
- **参数**：`$chargeId` - Omise Charge ID
- **返回**：`\OmiseCharge` 对象或 `null`（失败时）

#### `updateInvoiceStatus(Invoice $invoice, array $chargeResult): bool`
处理支付结果并更新账单状态
- **功能**：根据支付结果更新账单状态和支付记录
- **参数**：
  - `$invoice` - 账单对象
  - `$chargeResult` - 支付结果数组
- **返回**：是否成功更新
- **逻辑**：
  - 成功支付：更新账单为 `paid` 状态，记录 `paid_at`
  - 失败支付：保持账单为 `sent` 状态，更新支付记录状态
  - 处理中：保持 `sent` 状态，等待 Webhook 处理

### Webhook 相关方法

#### `verifyWebhookSignature(string $payload, string $signature): bool`
验证 Webhook 签名
- **功能**：验证 Omise Webhook 请求的签名，确保请求来自 Omise
- **参数**：
  - `$payload` - Webhook 请求体（原始 JSON 字符串）
  - `$signature` - Webhook 签名（从 `X-Omise-Signature` 头获取）
- **返回**：是否验证通过
- **安全**：使用 `hash_equals()` 防止时序攻击

#### `handleWebhookEvent(array $eventData): bool`
处理 Webhook 事件（支持幂等性）
- **功能**：处理 Omise Webhook 事件，更新账单和支付记录状态
- **参数**：`$eventData` - Webhook 事件数据数组
- **返回**：是否处理成功
- **幂等性保证**：
  1. 使用 `omise_charge_id` 作为唯一标识，避免重复处理
  2. 账单状态更新前检查是否已经是已支付状态
  3. 使用数据库事务和行锁（`lockForUpdate()`）确保数据一致性
- **处理的事件类型**：
  - `charge.create` - 支付创建
  - `charge.complete` - 支付完成
- **逻辑**：
  - 成功支付：更新账单为 `paid`，创建/更新 Payment 记录
  - 失败支付：创建/更新 Payment 记录（状态为 `failed`），账单保持 `sent` 状态
  - 自动处理重复 Webhook 调用（幂等性）

### 内部方法

#### `getOmiseKeys(): array` (protected)
获取 Omise 密钥
- **功能**：从配置中获取 Omise API 密钥
- **返回**：包含 `secret_key` 和 `public_key` 的数组
- **异常**：如果 Secret Key 未配置，抛出 `\Exception`

---

## 📚 4. Course 模型 (`app/Models/Course.php`)

### 关系方法 (Relationships)
- `teacher(): BelongsTo` - 课程所属的教师
- `students(): BelongsToMany` - 课程的学生（多对多关系）
- `invoices(): HasMany` - 课程的账单列表

---

## 👨‍🎓 5. Student 模型 (`app/Models/Student.php`)

### 关系方法 (Relationships)
- `teacher(): BelongsTo` - 学生所属的教师
- `courses(): BelongsToMany` - 学生参与的课程（多对多关系）
- `invoices(): HasMany` - 学生的账单列表

---

## 👨‍🏫 6. Teacher 模型 (`app/Models/Teacher.php`)

### 关系方法 (Relationships)
- `students(): HasMany` - 教师的学生列表
- `courses(): HasMany` - 教师的课程列表

---

## 🎯 封装原则

### 1. 业务逻辑封装
- ✅ **模型方法**：状态判断、关系查询等业务逻辑封装在模型中
- ✅ **服务类**：复杂的第三方 API 调用和业务逻辑封装在 Service 类中
- ✅ **避免重复**：不在 Controller 中重复实现业务逻辑

### 2. 幂等性保证
- ✅ **支付处理**：使用数据库事务和行锁防止并发问题
- ✅ **Webhook 处理**：使用 `omise_charge_id` 作为唯一标识，支持重复调用
- ✅ **状态检查**：更新前检查当前状态，避免重复操作

### 3. 错误处理
- ✅ **友好提示**：将技术错误转换为用户友好的错误信息
- ✅ **日志记录**：所有关键操作都记录日志，便于调试和审计
- ✅ **异常捕获**：所有外部 API 调用都有异常处理

### 4. 数据一致性
- ✅ **事务处理**：关键操作使用数据库事务
- ✅ **行锁机制**：使用 `lockForUpdate()` 防止并发修改
- ✅ **状态验证**：操作前验证数据状态，确保业务规则

---

## 📝 使用示例

### Invoice 模型方法使用
```php
// 检查账单状态
if ($invoice->isPaid()) {
    // 账单已支付
}

// 获取账单的支付记录
$payments = $invoice->payments;

// 获取成功的支付记录
$successfulPayments = $invoice->successfulPayments;
```

### OmisePaymentService 使用
```php
// 创建支付
$paymentService = app(OmisePaymentService::class);
$result = $paymentService->createCharge($invoice, $omiseToken, 'jpy');

if ($result['success']) {
    // 支付成功
    $paymentService->updateInvoiceStatus($invoice, $result);
}

// 处理 Webhook
$isValid = $paymentService->verifyWebhookSignature($payload, $signature);
if ($isValid) {
    $paymentService->handleWebhookEvent($eventData);
}
```

---

## 🔄 更新记录

- **2026-01-11**: 添加 `Invoice::isRejected()` 方法
- **2026-01-11**: 添加 `Invoice::rejected_at` 字段支持
- **2026-01-11**: 完善 Webhook 幂等性处理
- **2026-01-11**: 添加 Payment 模型状态判断方法
