<?php

namespace Database\Factories;

use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
class CourseFactory extends Factory
{
    /**
     * 定义模型的默认状态
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->year();
        $month = str_pad(fake()->numberBetween(1, 12), 2, '0', STR_PAD_LEFT);
        
        return [
            'name' => fake()->words(2, true) . '课程',
            'year_month' => $year . $month,
            'fee' => fake()->randomFloat(2, 100, 1000),
            'teacher_id' => Teacher::factory(),
        ];
    }
}
