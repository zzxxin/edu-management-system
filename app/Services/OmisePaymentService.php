<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Omise 支付服务类
 *
 * 按照 Omise 官方标准封装支付相关功能
 */
class OmisePaymentService
{
    /**
     * 获取 Omise 密钥
     *
     * @return array{secret_key: string, public_key: string}
     * @throws \Exception
     */
    protected function getOmiseKeys(): array
    {
        $secretKey = config('services.omise.secret_key');
        $publicKey = config('services.omise.public_key');

        if (empty($secretKey)) {
            throw new \Exception('Omise Secret Key 未配置');
        }

        return [
            'secret_key' => $secretKey,
            'public_key' => $publicKey,
        ];
    }

    /**
     * 创建支付（Charge）
     *
     * @param Invoice $invoice 账单对象
     * @param string $token Omise Token ID
     * @param string|null $currency 货币代码（可选，如果不提供则使用配置中的默认值）
     * @return array 支付结果
     * @throws \Exception
     */
    public function createCharge(Invoice $invoice, string $token, ?string $currency = null): array
    {
        // 确保 Omise 类已加载（Omise PHP SDK v2.18 使用无命名空间的类）
        // 使用全局命名空间前缀 \ 来引用全局类
        $omisePath = base_path('vendor/omise/omise-php/lib/Omise.php');
        if (!file_exists($omisePath)) {
            throw new \Exception('Omise PHP SDK 文件未找到，请运行 composer install 安装依赖');
        }

        // 始终 require，因为 Omise 类可能还没有被加载
        require_once $omisePath;

        $keys = $this->getOmiseKeys();

        try {
            // 根据业务需求设置货币
            // 如果用户选择了币种，使用用户选择的；否则使用配置中的默认值
            $currency = $currency ? strtolower($currency) : strtolower(config('services.omise.currency', 'jpy')); // 确保货币代码是小写

            // 按照 Omise 官方标准创建 Charge
            // 金额需要转换为最小货币单位
            // 注意：JPY（日元）没有小数单位，不需要乘以 100
            // 其他货币（THB, SGD, USD 等）需要乘以 100 转换为最小单位
            // Omise 要求最小金额为 100 最小单位（即 1.00 THB/SGD/USD 或 100 JPY）
            if ($currency === 'jpy') {
                // 日元没有小数位，直接使用整数金额
                $amount = (int)$invoice->amount;
                $minAmount = 100; // JPY 最小金额为 100 日元
                $minAmountDisplay = '100 JPY';
            } else {
                // 其他货币转换为最小单位（分、分、分等）
                $amount = (int)($invoice->amount * 100);
                $minAmount = 100; // 其他货币最小金额为 100 最小单位 = 1.00 货币单位
                $minAmountDisplay = '1.00 ' . strtoupper($currency);
            }

            // 验证金额是否满足 Omise 最低要求
            if ($amount < $minAmount) {
                $errorMessage = "支付金额不足。Omise 要求最小支付金额为 {$minAmountDisplay}，当前账单金额为 " .
                               number_format($invoice->amount, $currency === 'jpy' ? 0 : 2) . " " . strtoupper($currency);

                Log::warning('Omise Charge Amount Too Small', [
                    'invoice_id' => $invoice->id,
                    'invoice_amount' => $invoice->amount,
                    'currency' => $currency,
                    'converted_amount' => $amount,
                    'min_amount' => $minAmount,
                ]);

                return [
                    'success' => false,
                    'error' => $errorMessage,
                    'error_code' => 'amount_too_small',
                ];
            }

            // Omise PHP SDK v2.18 使用无命名空间的类，直接使用全局命名空间
            $charge = \OmiseCharge::create([
                'amount' => $amount,
                'currency' => $currency,
                'card' => $token,
                'description' => "课程费用支付 - {$invoice->course->name} (账单 #{$invoice->id})",
                'metadata' => [
                    'invoice_id' => $invoice->id,
                    'course_id' => $invoice->course_id,
                    'student_id' => $invoice->student_id,
                    'year_month' => $invoice->year_month,
                ],
            ], $keys['public_key'], $keys['secret_key']);

            // 创建支付记录
            // 注意：$charge 可能是数组或对象，需要处理
            $chargeArray = is_array($charge) ? $charge : (method_exists($charge, 'toArray') ? $charge->toArray() : (array)$charge);

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'omise_charge_id' => $chargeArray['id'] ?? null,
                'amount' => $invoice->amount, // 使用账单金额（元）
                'currency' => $currency,
                'status' => ($chargeArray['status'] ?? 'pending') === 'successful' ? Payment::STATUS_SUCCESSFUL :
                           (($chargeArray['status'] ?? 'pending') === 'failed' ? Payment::STATUS_FAILED : Payment::STATUS_PENDING),
                'payment_method' => $chargeArray['source']['type'] ?? 'card',
                'omise_response' => $chargeArray, // 保存完整响应
                'paid_at' => ($chargeArray['status'] ?? 'pending') === 'successful' ? now() : null,
            ]);

            // 记录支付请求日志
            Log::info('Omise Charge Created', [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment->id,
                'charge_id' => $chargeArray['id'] ?? null,
                'amount' => $amount,
                'currency' => $currency,
                'status' => $chargeArray['status'] ?? 'pending',
            ]);

            return [
                'success' => true,
                'charge' => $chargeArray,
                'charge_id' => $chargeArray['id'] ?? null,
                'status' => $chargeArray['status'] ?? 'pending',
                'payment_id' => $payment->id,
            ];
        } catch (\Exception $e) {
            // 解析错误信息，提供更友好的错误提示
            $errorMessage = $e->getMessage();
            $userFriendlyMessage = $errorMessage;

            // 记录完整的错误信息用于调试
            $currencyUsed = $currency ?? strtolower(config('services.omise.currency', 'jpy'));
            $defaultCurrency = strtolower(config('services.omise.currency', 'jpy'));
            $publicKey = config('services.omise.public_key', '');
            $isTestKey = strpos($publicKey, 'pkey_test_') === 0;

            Log::error('Omise Charge Creation Exception Details', [
                'invoice_id' => $invoice->id,
                'currency_used' => $currencyUsed,
                'default_currency' => $defaultCurrency,
                'is_test_key' => $isTestKey,
                'error_message' => $errorMessage,
                'error_code' => $e->getCode(),
                'error_class' => get_class($e),
            ]);

            // 检查是否是货币转换错误（更精确的判断，避免误判）
            // 检查 "multi_currency" 或 "multi-currency" 错误（Omise API 返回的错误）
            $isMultiCurrencyError = stripos($errorMessage, 'multi_currency') !== false ||
                                   stripos($errorMessage, 'multi-currency') !== false ||
                                   stripos($errorMessage, 'currency conversion') !== false;

            // 检查是否是货币不支持错误
            $isCurrencyNotSupported = (stripos($errorMessage, 'currency') !== false &&
                                      (stripos($errorMessage, 'not supported') !== false ||
                                       stripos($errorMessage, 'unsupported') !== false ||
                                       stripos($errorMessage, 'invalid') !== false));

            if ($isMultiCurrencyError || $isCurrencyNotSupported) {
                // 如果是 multi_currency 错误，说明账户未启用多币种功能
                if ($isMultiCurrencyError) {
                    $userFriendlyMessage = "支付失败：Omise 账户未启用多币种支付功能。\n\n" .
                                         "错误信息：Failed multi_currency\n\n" .
                                         "问题说明：\n" .
                                         "即使使用默认币种（" . strtoupper($currencyUsed) . "），Omise 账户也需要启用多币种功能才能正常处理支付。\n\n" .
                                         "解决方案：\n" .
                                         "1. 登录 Omise 控制台：https://dashboard.omise.co/\n" .
                                         "2. 进入 Settings（设置）页面\n" .
                                         "3. 查找 Multi-Currency（多币种）或 Currency（货币）设置\n" .
                                         "4. 启用多币种功能（可能需要接受服务条款）\n" .
                                         "5. 如果找不到设置选项，请联系 Omise 支持：support@omise.co\n\n" .
                                         "注意：某些测试账号可能需要联系 Omise 支持手动启用多币种功能。";
                } elseif ($currencyUsed === $defaultCurrency) {
                    // 如果使用的是默认币种但仍然报错，可能是账户配置问题
                    $userFriendlyMessage = "支付失败：Omise 账户配置可能有问题。\n\n" .
                                         "错误信息：" . $errorMessage . "\n\n" .
                                         "可能的原因：\n" .
                                         "1. Omise 账户未激活或未通过验证\n" .
                                         "2. API 密钥配置错误（请检查 .env 文件中的 OMISE_PUBLIC_KEY 和 OMISE_SECRET_KEY）\n" .
                                         "3. 账户状态异常或需要完成验证流程\n" .
                                         "4. 测试账号可能需要额外的设置\n\n" .
                                         "建议：\n" .
                                         "1. 登录 Omise 控制台（https://dashboard.omise.co/）检查账户状态\n" .
                                         "2. 确认 API 密钥是否正确（测试环境使用 pkey_test_ 和 skey_test_ 开头的密钥）\n" .
                                         "3. 检查账户是否已完成所有必要的验证步骤\n" .
                                         "4. 联系 Omise 支持：support@omise.co";
                } else {
                    // 使用了非默认币种
                    if ($isTestKey) {
                        $userFriendlyMessage = "货币转换失败。测试账号通常只支持默认币种（" . strtoupper($defaultCurrency) . "）。\n\n您选择了 " . strtoupper($currencyUsed) . "，但测试账号可能不支持此币种。\n\n建议：请使用默认币种 " . strtoupper($defaultCurrency) . " 重新支付。\n\n如需使用其他币种，请联系 Omise 支持启用多币种功能：support@omise.co";
                    } else {
                        $userFriendlyMessage = '货币转换失败。请检查：1) Omise 账户是否已启用多币种支付功能；2) 货币代码是否正确（应为小写，如 thb, jpy, sgd, usd）；3) 请联系 Omise 支持启用多币种功能：support@omise.co';
                    }
                }
            } else {
                // 不是货币转换错误，显示原始错误信息，但提供更友好的格式
                $userFriendlyMessage = "支付失败：\n\n" . $errorMessage . "\n\n" .
                                     "如果问题持续存在，请：\n" .
                                     "1. 检查 Omise 控制台中的账户状态\n" .
                                     "2. 确认 API 密钥配置正确\n" .
                                     "3. 联系 Omise 支持：support@omise.co";
            }

            // 检查是否是金额不足错误
            if (stripos($errorMessage, 'amount must be greater than or equal to') !== false ||
                stripos($errorMessage, 'amount must be at least') !== false) {
                $currencyUsed = $currency ?? strtolower(config('services.omise.currency', 'jpy'));
                if ($currencyUsed === 'jpy') {
                    $userFriendlyMessage = '支付金额不足。Omise 要求最小支付金额为 100 JPY（日元）。当前账单金额可能低于此限制，请联系管理员。';
                } else {
                    $userFriendlyMessage = '支付金额不足。Omise 要求最小支付金额为 1.00 ' . strtoupper($currencyUsed) . '。当前账单金额可能低于此限制，请联系管理员。';
                }
            }

            // 创建失败的支付记录
            try {
                Payment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => $invoice->amount,
                    'currency' => $currency ?? strtolower(config('services.omise.currency', 'jpy')),
                    'status' => Payment::STATUS_FAILED,
                    'error_message' => $errorMessage,
                ]);
            } catch (\Exception $paymentException) {
                // 如果创建支付记录失败，只记录日志
                Log::error('Failed to create payment record', [
                    'invoice_id' => $invoice->id,
                    'error' => $paymentException->getMessage(),
                ]);
            }

            // 记录错误日志
            Log::error('Omise Charge Creation Failed', [
                'invoice_id' => $invoice->id,
                'currency' => $currency ?? 'unknown',
                'amount' => $amount ?? null,
                'error_code' => $e->getCode(),
                'error_message' => $errorMessage,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $userFriendlyMessage,
                'error_code' => $e->getCode(),
                'original_error' => $errorMessage,
            ];
        }
    }

    /**
     * 获取支付详情
     *
     * @param string $chargeId Omise Charge ID
     * @return \OmiseCharge|null
     */
    public function getCharge(string $chargeId)
    {
        // 确保 Omise 类已加载（Omise PHP SDK v2.18 使用无命名空间的类）
        $omisePath = base_path('vendor/omise/omise-php/lib/Omise.php');
        if (!file_exists($omisePath)) {
            throw new \Exception('Omise PHP SDK 文件未找到，请运行 composer install 安装依赖');
        }

        // 始终 require，因为 Omise 类可能还没有被加载
        require_once $omisePath;

        $keys = $this->getOmiseKeys();

        try {
            // Omise PHP SDK v2.18 使用无命名空间的类，直接使用全局命名空间
            return \OmiseCharge::retrieve($chargeId, $keys['public_key'], $keys['secret_key']);
        } catch (\Exception $e) {
            Log::error('Get Charge Failed', [
                'charge_id' => $chargeId,
                'error_message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 处理支付结果并更新账单状态
     *
     * @param Invoice $invoice 账单对象
     * @param array $chargeResult 支付结果
     * @return bool 是否成功更新
     */
    public function updateInvoiceStatus(Invoice $invoice, array $chargeResult): bool
    {
        if (!$chargeResult['success']) {
            // 支付失败，账单状态保持 sent（可以再次尝试支付）
            // 支付记录已经在 createCharge 中创建
            return false;
        }

        $charge = $chargeResult['charge'];
        $chargeArray = is_array($charge) ? $charge : (method_exists($charge, 'toArray') ? $charge->toArray() : (array)$charge);
        $status = $chargeArray['status'] ?? 'pending';
        $paymentId = $chargeResult['payment_id'] ?? null;

        // 根据 Omise 支付状态更新账单
        // Omise 支付状态：pending, successful, failed
        if ($status === 'successful') {
            // 只有成功的支付才更新账单状态
            $invoice->update([
                'status' => Invoice::STATUS_PAID,
                'paid_at' => now(),
                'omise_charge_id' => $chargeArray['id'] ?? null, // 保留最后一个成功的支付ID
            ]);

            // 更新支付记录状态（如果存在）
            if ($paymentId) {
                Payment::where('id', $paymentId)->update([
                    'status' => Payment::STATUS_SUCCESSFUL,
                    'paid_at' => now(),
                ]);
            }

            return true;
        } elseif ($status === 'failed') {
            // 支付失败，但账单状态保持 sent（可以再次尝试支付）
            // 更新支付记录状态
            if ($paymentId) {
                Payment::where('id', $paymentId)->update([
                    'status' => Payment::STATUS_FAILED,
                    'error_message' => $chargeArray['failure_message'] ?? '支付失败',
                ]);
            }
            return false;
        } else {
            // pending 状态，保持 sent 状态，等待后续处理（通过 Webhook）
            return false;
        }
    }

    /**
     * 验证 Webhook 签名
     *
     * @param string $payload Webhook 请求体
     * @param string $signature Webhook 签名
     * @return bool 是否验证通过
     */
    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $secretKey = config('services.omise.secret_key');
        $expectedSignature = hash_hmac('sha256', $payload, $secretKey);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * 处理 Webhook 事件（支持幂等性）
     *
     * 幂等性保证：
     * 1. 使用 omise_charge_id 作为唯一标识，避免重复处理
     * 2. 账单状态更新前检查是否已经是已支付状态
     * 3. 使用数据库事务确保数据一致性
     *
     * @param array $eventData Webhook 事件数据
     * @return bool 是否处理成功
     */
    public function handleWebhookEvent(array $eventData): bool
    {
        try {
            $eventType = $eventData['key'] ?? null;
            $chargeData = $eventData['data'] ?? null;

            if ($eventType !== 'charge.create' && $eventType !== 'charge.complete') {
                Log::info('Ignoring Omise Webhook Event', ['event_type' => $eventType]);
                return false;
            }

            if (!$chargeData || !isset($chargeData['id'])) {
                Log::warning('Invalid Omise Webhook Data', ['event_data' => $eventData]);
                return false;
            }

            $chargeId = $chargeData['id'];
            $chargeStatus = $chargeData['status'] ?? null;
            $metadata = $chargeData['metadata'] ?? [];

            // 从 metadata 中获取 invoice_id
            $invoiceId = $metadata['invoice_id'] ?? null;

            if (!$invoiceId) {
                Log::warning('Invoice ID not found in Omise Charge Metadata', [
                    'charge_id' => $chargeId,
                ]);
                return false;
            }

            // 使用数据库事务确保数据一致性
            return DB::transaction(function () use ($chargeId, $chargeStatus, $chargeData, $invoiceId) {
                // 重新加载账单（在事务中确保获取最新状态）
                $invoice = Invoice::lockForUpdate()->find($invoiceId);
                if (!$invoice) {
                    Log::warning('Invoice not found', ['invoice_id' => $invoiceId]);
                    return false;
                }

                // 幂等性处理：查找或创建支付记录（使用 omise_charge_id 作为唯一标识）
                $payment = Payment::where('omise_charge_id', $chargeId)->first();
                if (!$payment) {
                    // 如果支付记录不存在，创建新记录
                    $payment = Payment::create([
                        'invoice_id' => $invoiceId,
                        'omise_charge_id' => $chargeId,
                        'amount' => $invoice->amount,
                        'currency' => strtolower($chargeData['currency'] ?? config('services.omise.currency', 'jpy')),
                        'status' => $chargeStatus === 'successful' ? Payment::STATUS_SUCCESSFUL :
                                   ($chargeStatus === 'failed' ? Payment::STATUS_FAILED : Payment::STATUS_PENDING),
                        'payment_method' => $chargeData['source']['type'] ?? 'unknown',
                        'omise_response' => $chargeData,
                        'error_message' => $chargeData['failure_message'] ?? null,
                        'paid_at' => $chargeStatus === 'successful' ? now() : null,
                    ]);
                } else {
                    // 幂等性：支付记录已存在，只更新状态（避免重复处理）
                    // 如果状态已经相同，可以跳过更新（可选优化）
                    if ($payment->status !== ($chargeStatus === 'successful' ? Payment::STATUS_SUCCESSFUL :
                                             ($chargeStatus === 'failed' ? Payment::STATUS_FAILED : Payment::STATUS_PENDING))) {
                        $payment->update([
                            'status' => $chargeStatus === 'successful' ? Payment::STATUS_SUCCESSFUL :
                                       ($chargeStatus === 'failed' ? Payment::STATUS_FAILED : Payment::STATUS_PENDING),
                            'omise_response' => $chargeData,
                            'paid_at' => $chargeStatus === 'successful' ? now() : null,
                        ]);
                    }
                }

                // 幂等性处理：更新账单状态（只有成功的支付才更新，且只更新一次）
                if ($chargeStatus === 'successful') {
                    // 检查账单是否已经是已支付状态（幂等性保证）
                    if (!$invoice->isPaid()) {
                        $invoice->update([
                            'status' => Invoice::STATUS_PAID,
                            'paid_at' => now(),
                            'omise_charge_id' => $chargeId,
                        ]);
                    } else {
                        // 账单已经是已支付状态，记录日志但不更新（幂等性保证）
                        Log::info('Omise Webhook: Invoice already paid, skipping update', [
                            'invoice_id' => $invoiceId,
                            'charge_id' => $chargeId,
                        ]);
                    }
                }
                // 注意：失败的支付不改变账单状态，账单保持 sent 状态，可以再次尝试支付

                Log::info('Omise Webhook Processed', [
                    'invoice_id' => $invoiceId,
                    'charge_id' => $chargeId,
                    'status' => $chargeStatus,
                    'payment_id' => $payment->id,
                ]);

                return true;
            });
        } catch (\Exception $e) {
            Log::error('Webhook Processing Error', [
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
                'event_data' => $eventData,
            ]);
            return false;
        }
    }
}
