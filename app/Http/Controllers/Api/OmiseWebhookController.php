<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OmisePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
