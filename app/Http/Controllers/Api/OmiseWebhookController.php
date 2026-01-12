<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OmisePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

/**
 * Omise Webhook 控制器
 *
 * 处理 Omise 支付平台的 Webhook 回调
 */
class OmiseWebhookController extends Controller
{
    /**
     * 处理 Omise Webhook 请求
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    #[OA\Post(
        path: "/api/omise/webhook",
        tags: ["Webhook"],
        summary: "Omise Webhook 回调",
        description: "处理 Omise 支付平台的 Webhook 事件。接收支付创建和完成事件，自动更新账单和支付记录状态。支持幂等性处理。",
        security: [["OmiseWebhookSignature" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            description: "Omise Webhook 事件数据",
            content: new OA\JsonContent(
                ref: "#/components/schemas/OmiseWebhookEvent",
                examples: [
                    "charge_create" => new OA\Examples(
                        example: "charge_create",
                        summary: "支付创建事件",
                        value: [
                            "key" => "charge.create",
                            "data" => [
                                "id" => "chrg_test_1234567890",
                                "status" => "pending",
                                "currency" => "JPY",
                                "amount" => 1000,
                                "metadata" => [
                                    "invoice_id" => "1",
                                    "course_id" => "1",
                                    "student_id" => "1",
                                    "year_month" => "202601"
                                ],
                                "source" => ["type" => "card"]
                            ]
                        ]
                    ),
                    "charge_complete_success" => new OA\Examples(
                        example: "charge_complete_success",
                        summary: "支付成功事件",
                        value: [
                            "key" => "charge.complete",
                            "data" => [
                                "id" => "chrg_test_1234567890",
                                "status" => "successful",
                                "currency" => "JPY",
                                "amount" => 1000,
                                "paid_at" => "2026-01-11T16:35:32Z",
                                "metadata" => [
                                    "invoice_id" => "1",
                                    "course_id" => "1",
                                    "student_id" => "1",
                                    "year_month" => "202601"
                                ],
                                "source" => ["type" => "card"]
                            ]
                        ]
                    ),
                    "charge_complete_failed" => new OA\Examples(
                        example: "charge_complete_failed",
                        summary: "支付失败事件",
                        value: [
                            "key" => "charge.complete",
                            "data" => [
                                "id" => "chrg_test_1234567890",
                                "status" => "failed",
                                "currency" => "JPY",
                                "amount" => 1000,
                                "failure_message" => "Card declined",
                                "metadata" => [
                                    "invoice_id" => "1",
                                    "course_id" => "1",
                                    "student_id" => "1",
                                    "year_month" => "202601"
                                ],
                                "source" => ["type" => "card"]
                            ]
                        ]
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "处理成功或忽略的事件",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", enum: ["success", "ignored"], example: "success")
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: "签名验证失败",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "error", type: "string", example: "Invalid signature")
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: "服务器内部错误",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "error", type: "string", example: "Processing failed")
                    ]
                )
            )
        ]
    )]
    public function handle(Request $request)
    {
        try {
            // 获取 Webhook 签名（必须验证，确保请求来自 Omise）
            $signature = $request->header('X-Omise-Signature');
            $payload = $request->getContent();

            // 强制验证签名
            if (empty($signature)) {
                Log::warning('Omise Webhook Missing Signature', [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
                return response()->json(['error' => 'Missing signature'], 401);
            }

            $paymentService = app(OmisePaymentService::class);
            if (!$paymentService->verifyWebhookSignature($payload, $signature)) {
                Log::warning('Invalid Omise Webhook Signature', [
                    'signature' => $signature,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
                return response()->json(['error' => 'Invalid signature'], 401);
            }

            // 解析 Webhook 数据
            $eventData = $request->json()->all();

            Log::info('Omise Webhook Received', [
                'event_key' => $eventData['key'] ?? null,
                'event_data' => $eventData,
            ]);

            // 处理 Webhook 事件
            $paymentService = app(OmisePaymentService::class);
            $result = $paymentService->handleWebhookEvent($eventData);

            if ($result) {
                return response()->json(['status' => 'success'], 200);
            } else {
                return response()->json(['status' => 'ignored'], 200);
            }
        } catch (\Exception $e) {
            Log::error('Omise Webhook Processing Error', [
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }
}
