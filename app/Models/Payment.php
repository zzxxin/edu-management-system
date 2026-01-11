<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 支付记录模型
 * 
 * 支付记录(Payment): 记录所有支付尝试，一个账单可以有多次支付尝试
 * 只有成功的支付才会更新账单状态为已支付
 */
class Payment extends Model
{
    use HasFactory;

    /**
     * 支付状态常量
     */
    const STATUS_PENDING = 'pending';      // 处理中
    const STATUS_SUCCESSFUL = 'successful'; // 成功
    const STATUS_FAILED = 'failed';         // 失败

    /**
     * 可批量赋值的属性
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'invoice_id',      // 账单ID
        'omise_charge_id', // Omise支付ID
        'amount',          // 金额
        'currency',       // 货币代码
        'status',          // 状态: pending, successful, failed
        'payment_method',  // 支付方式
        'omise_response', // Omise API 响应（JSON）
        'error_message',   // 错误信息
        'paid_at',         // 支付完成时间
    ];

    /**
     * 属性类型转换
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'omise_response' => 'array', // JSON 自动转换为数组
        'paid_at' => 'datetime',
    ];

    /**
     * 支付记录所属的账单
     *
     * @return BelongsTo
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * 是否为处理中状态
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * 是否为成功状态
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESSFUL;
    }

    /**
     * 是否为失败状态
     *
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
