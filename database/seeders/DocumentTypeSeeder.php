<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Seed the academic documents the registrar's office issues.
     *
     * This is reference data rather than demo data, so it is seeded in every
     * environment and matched on the slug to stay idempotent.
     */
    public function run(): void
    {
        $types = [
            [
                'slug' => 'form-137',
                'name' => 'Form 137',
                'description' => "A student's permanent academic record from basic education.",
                'processing_days' => 5,
                'requires_custom_name' => false,
            ],
            [
                'slug' => 'transcript-of-records',
                'name' => 'Transcript of Records',
                'description' => "An official record of a student's academic performance and completed courses.",
                'processing_days' => 7,
                'requires_custom_name' => false,
            ],
            [
                'slug' => 'certificate-of-enrollment',
                'name' => 'Certificate of Enrollment',
                'description' => 'Confirms that a student is currently enrolled in the institution.',
                'processing_days' => 2,
                'requires_custom_name' => false,
            ],
            [
                'slug' => 'good-moral-certificate',
                'name' => 'Good Moral Certificate',
                'description' => "Attests to a student's good conduct while enrolled in the institution.",
                'processing_days' => 3,
                'requires_custom_name' => false,
            ],
            [
                'slug' => 'other-academic-document',
                'name' => 'Other Academic Document',
                'description' => 'Any other academic record; the requester supplies the document name.',
                'processing_days' => 5,
                'requires_custom_name' => true,
            ],
        ];

        foreach ($types as $type) {
            DocumentType::query()->updateOrCreate(
                ['slug' => $type['slug']],
                $type + ['is_active' => true],
            );
        }
    }
}
