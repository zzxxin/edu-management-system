<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Omise Webhook 功能测试
 */
class OmiseWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected Teacher $teacher;
    protected Student $student;
    protected Course $course;
    protected Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试数据
        $this->teacher = Teacher::factory()->create();
        $this->student = Student::factory()->create([
            'teacher_id' => $this->teacher->id,
        ]);
        $this->course = Course::factory()->create([
            'teacher_id' => $this->teacher->id,
            'fee' => 1000.00,
            'year_month' => '202601',
        ]);
        $this->invoice = Invoice::factory()->create([
            'course_id' => $this->course->id,
            'student_id' => $this->student->id,
            'amount' => $this->course->fee,
            'year_month' => $this->course->year_month,
            'status' => Invoice::STATUS_SENT,
        ]);

        // 设置测试配置
        Config::set('services.omise.secret_key', 'skey_test_123456');
        Config::set('services.omise.public_key', 'pkey_test_123456');
        Config::set('services.omise.currency', 'jpy');
    }

    /**
     * 计算 Webhook 签名
     */
    protected function calculateSignature(array $payload): string
    {
        $payloadString = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return hash_hmac('sha256', $payloadString, config('services.omise.secret_key'));
    }

    /**
     * 发送 Webhook 请求
     */
    protected function sendWebhook(array $payload, ?string $signature = null): \Illuminate\Testing\TestResponse
    {
        if ($signature === null) {
            $signature = $this->calculateSignature($payload);
        }

        return $this->postJson('/api/omise/webhook', $payload, [
            'X-Omise-Signature' => $signature,
        ]);
    }

    /**
     * 测试 Webhook 请求缺少签名时返回 401
     */
    public function test_webhook_requires_signature(): void
    {
        $payload = [
            'key' => 'charge.complete',
            'data' => [
                'id' => 'chrg_test_123',
                'status' => 'successful',
                'metadata' => [
                    'invoice_id' => $this->invoice->id,
                ],
            ],
        ];

        $response = $this->postJson('/api/omise/webhook', $payload);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Missing signature']);
    }

    /**
     * 测试 Webhook 请求签名无效时返回 401
     */
    public function test_webhook_invalid_signature(): void
    {
        $payload = [
            'key' => 'charge.complete',
            'data' => [
                'id' => 'chrg_test_123',
                'status' => 'successful',
                'metadata' => [
                    'invoice_id' => $this->invoice->id,
                ],
            ],
        ];

        $response = $this->sendWebhook($payload, 'invalid_signature');

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Invalid signature']);
    }

    /**
     * 测试 Webhook 处理成功支付事件
     */
    public function test_webhook_handles_successful_payment(): void
    {
        $payload = [
            'key' => 'charge.complete',
            'data' => [
                'id' => 'chrg_test_success_123',
                'status' => 'successful',
                'currency' => 'jpy',
                'metadata' => [
                    'invoice_id' => $this->invoice->id,
                ],
                'source' => [
                    'type' => 'card',
                ],
            ],
        ];

        $response = $this->sendWebhook($payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        // 验证账单状态已更新
        $this->invoice->refresh();
        $this->assertEquals(Invoice::STATUS_PAID, $this->invoice->status);
        $this->assertNotNull($this->invoice->paid_at);
        $this->assertEquals('chrg_test_success_123', $this->invoice->omise_charge_id);

        // 验证支付记录已创建
        $payment = Payment::where('omise_charge_id', 'chrg_test_success_123')->first();
        $this->assertNotNull($payment);
        $this->assertEquals($this->invoice->id, $payment->invoice_id);
        $this->assertEquals(Payment::STATUS_SUCCESSFUL, $payment->status);
        $this->assertNotNull($payment->paid_at);
    }

    /**
     * 测试 Webhook 处理失败支付事件
     */
    public function test_webhook_handles_failed_payment(): void
    {
        $payload = [
            'key' => 'charge.complete',
            'data' => [
                'id' => 'chrg_test_failed_123',
                'status' => 'failed',
                'currency' => 'jpy',
                'failure_message' => 'Card declined',
                'metadata' => [
                    'invoice_id' => $this->invoice->id,
                ],
                'source' => [
                    'type' => 'card',
                ],
            ],
        ];

        $response = $this->sendWebhook($payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        // 验证账单状态未更新（保持 sent 状态）
        $this->invoice->refresh();
        $this->assertEquals(Invoice::STATUS_SENT, $this->invoice->status);
        $this->assertNull($this->invoice->paid_at);

        // 验证支付记录已创建
        $payment = Payment::where('omise_charge_id', 'chrg_test_failed_123')->first();
        $this->assertNotNull($payment);
        $this->assertEquals(Payment::STATUS_FAILED, $payment->status);
        $this->assertEquals('Card declined', $payment->error_message);
    }

    /**
     * 测试 Webhook 幂等性（重复处理同一个事件）
     */
    public function test_webhook_idempotency(): void
    {
        $chargeId = 'chrg_test_idempotent_123';

        $payload = [
            'key' => 'charge.complete',
            'data' => [
                'id' => $chargeId,
                'status' => 'successful',
                'currency' => 'jpy',
                'metadata' => [
                    'invoice_id' => $this->invoice->id,
                ],
                'source' => [
                    'type' => 'card',
                ],
            ],
        ];

        // 第一次处理
        $response1 = $this->sendWebhook($payload);
        $response1->assertStatus(200);

        // 验证账单状态已更新
        $this->invoice->refresh();
        $this->assertEquals(Invoice::STATUS_PAID, $this->invoice->status);

        // 第二次处理（幂等性测试）
        $response2 = $this->sendWebhook($payload);
        $response2->assertStatus(200);

        // 验证账单状态仍然是已支付（没有被重复更新）
        $this->invoice->refresh();
        $this->assertEquals(Invoice::STATUS_PAID, $this->invoice->status);

        // 验证只创建了一个支付记录（或更新了现有记录）
        $paymentCount = Payment::where('omise_charge_id', $chargeId)->count();
        $this->assertEquals(1, $paymentCount);
    }

    /**
     * 测试 Webhook 忽略不相关的事件
     */
    public function test_webhook_ignores_unrelated_events(): void
    {
        $payload = [
            'key' => 'customer.create', // 不相关的事件
            'data' => [
                'id' => 'cus_test_123',
            ],
        ];

        $response = $this->sendWebhook($payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ignored']);
    }

    /**
     * 测试 Webhook 处理缺少 invoice_id 的事件
     */
    public function test_webhook_handles_missing_invoice_id(): void
    {
        $payload = [
            'key' => 'charge.complete',
            'data' => [
                'id' => 'chrg_test_no_invoice_123',
                'status' => 'successful',
                'metadata' => [], // 缺少 invoice_id
            ],
        ];

        $response = $this->sendWebhook($payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ignored']);
    }

    /**
     * 测试 Webhook 处理不存在的账单
     */
    public function test_webhook_handles_nonexistent_invoice(): void
    {
        $payload = [
            'key' => 'charge.complete',
            'data' => [
                'id' => 'chrg_test_invalid_invoice_123',
                'status' => 'successful',
                'currency' => 'jpy',
                'metadata' => [
                    'invoice_id' => 99999, // 不存在的账单ID
                ],
                'source' => [
                    'type' => 'card',
                ],
            ],
        ];

        $response = $this->sendWebhook($payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ignored']);
    }

    /**
     * 测试 Webhook 处理 pending 状态的支付
     */
    public function test_webhook_handles_pending_payment(): void
    {
        $payload = [
            'key' => 'charge.create',
            'data' => [
                'id' => 'chrg_test_pending_123',
                'status' => 'pending',
                'currency' => 'jpy',
                'metadata' => [
                    'invoice_id' => $this->invoice->id,
                ],
                'source' => [
                    'type' => 'card',
                ],
            ],
        ];

        $response = $this->sendWebhook($payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        // 验证账单状态未更新（保持 sent 状态）
        $this->invoice->refresh();
        $this->assertEquals(Invoice::STATUS_SENT, $this->invoice->status);

        // 验证支付记录已创建
        $payment = Payment::where('omise_charge_id', 'chrg_test_pending_123')->first();
        $this->assertNotNull($payment);
        $this->assertEquals(Payment::STATUS_PENDING, $payment->status);
    }
}
