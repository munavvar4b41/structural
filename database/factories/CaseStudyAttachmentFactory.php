<?php

namespace Database\Factories;

use App\Enums\CaseStudyAttachmentType;
use App\Models\CaseStudy;
use App\Models\CaseStudyAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaseStudyAttachment>
 */
class CaseStudyAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'case_study_id' => CaseStudy::factory(),
            'path' => 'case-studies/'.fake()->uuid().'.pdf',
            'original_name' => fake()->word().'.pdf',
            'mime' => 'application/pdf',
            'type' => CaseStudyAttachmentType::Document,
            'sort_order' => 0,
        ];
    }

    public function image(): static
    {
        return $this->state(fn (): array => [
            'path' => 'case-studies/'.fake()->uuid().'.png',
            'original_name' => fake()->word().'.png',
            'mime' => 'image/png',
            'type' => CaseStudyAttachmentType::Image,
        ]);
    }
}
