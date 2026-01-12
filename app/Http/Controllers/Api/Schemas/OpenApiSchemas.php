<?php

namespace App\Http\Controllers\Api\Schemas;

use OpenApi\Attributes as OA;

/**
 * OpenAPI Schema 定义
 */
#[OA\Info(
    version: "1.0.0",
    title: "教务管理系统 API 文档",
    description: "教务管理系统 API 文档\n\n本系统主要提供 Web 界面，API 端点主要用于第三方服务集成（如 Omise Webhook）。\n\n## 认证说明\n\n本系统使用 Laravel Session 认证，Web 界面通过 Blade 模板提供。\nAPI 端点主要用于 Webhook 回调，使用签名验证确保安全性。"
)]
#[OA\Server(
    url: "http://localhost:8000/api",
    description: "本地开发环境"
)]
#[OA\Server(
    url: "https://your-domain.com/api",
    description: "生产环境"
)]
#[OA\SecurityScheme(
    securityScheme: "OmiseWebhookSignature",
    type: "apiKey",
    name: "X-Omise-Signature",
    in: "header",
    description: "Omise Webhook 签名。使用 HMAC-SHA256 算法，密钥为 Omise Secret Key。"
)]
#[OA\Schema(
    schema: "OmiseWebhookEvent",
    required: ["key", "data"],
    properties: [
        new OA\Property(property: "key", type: "string", enum: ["charge.create", "charge.complete"], example: "charge.complete"),
        new OA\Property(property: "data", ref: "#/components/schemas/OmiseCharge")
    ]
)]
#[OA\Schema(
    schema: "OmiseCharge",
    required: ["id", "status"],
    properties: [
        new OA\Property(property: "id", type: "string", description: "Omise Charge ID", example: "chrg_test_1234567890"),
        new OA\Property(property: "status", type: "string", enum: ["pending", "successful", "failed"], example: "successful"),
        new OA\Property(property: "currency", type: "string", enum: ["JPY", "THB", "SGD", "USD"], example: "JPY"),
        new OA\Property(property: "amount", type: "integer", description: "支付金额（最小货币单位）", example: 1000),
        new OA\Property(property: "paid_at", type: "string", format: "date-time", nullable: true, example: "2026-01-11T16:35:32Z"),
        new OA\Property(property: "failure_message", type: "string", nullable: true, example: "Card declined"),
        new OA\Property(
            property: "metadata",
            type: "object",
            required: ["invoice_id"],
            properties: [
                new OA\Property(property: "invoice_id", type: "string", example: "1"),
                new OA\Property(property: "course_id", type: "string", example: "1"),
                new OA\Property(property: "student_id", type: "string", example: "1"),
                new OA\Property(property: "year_month", type: "string", example: "202601")
            ]
        ),
        new OA\Property(
            property: "source",
            type: "object",
            properties: [
                new OA\Property(property: "type", type: "string", enum: ["card", "internet_banking"], example: "card")
            ]
        )
    ]
)]
#[OA\Tag(name: "Webhook", description: "Omise 支付 Webhook 回调")]
class OpenApiSchemas
{
}
