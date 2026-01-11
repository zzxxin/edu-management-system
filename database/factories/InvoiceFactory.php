<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Invoice;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * 定义模型的默认状态
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $course = Course::factory()->create();
        
        return [
            'course_id' => $course->id,
            'student_id' => Student::factory()->create(['teacher_id' => $course->teacher_id]),
            'year_month' => $course->year_month, // 从课程中获取年月
            'amount' => $course->fee,
            'status' => Invoice::STATUS_PENDING,
            'sent_at' => null,
            'paid_at' => null,
            'omise_charge_id' => null,
        ];
    }

    /**
     * 已发送状态的账单
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Invoice::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    /**
     * 已支付状态的账单
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Invoice::STATUS_PAID,
            'sent_at' => now()->subDays(1),
            'paid_at' => now(),
            'omise_charge_id' => 'chrg_test_' . fake()->uuid(),
        ]);
    }
}
