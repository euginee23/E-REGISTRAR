<?php

namespace App\Concerns;

use App\Models\DocumentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait DocumentRequestValidationRules
{
    /**
     * Get the validation rules used to validate document requests.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function documentRequestRules(): array
    {
        return [
            'document_type_id' => $this->documentTypeRules(),
            'other_document_name' => $this->otherDocumentNameRules(),
            'purpose' => $this->purposeRules(),
            'copies' => $this->copiesRules(),
            'attachments' => $this->attachmentsRules(),
            'attachments.*' => $this->attachmentRules(),
        ];
    }

    /**
     * Get the validation rules used to validate the requested document type.
     *
     * Retired types are excluded so a deactivated document cannot be
     * requested by replaying an old form.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function documentTypeRules(): array
    {
        return [
            'required',
            Rule::exists(DocumentType::class, 'id')->where('is_active', true),
        ];
    }

    /**
     * Get the validation rules used to validate free-text document names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function otherDocumentNameRules(): array
    {
        return ['nullable', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate the purpose of a request.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function purposeRules(): array
    {
        return ['required', 'string', 'min:5', 'max:500'];
    }

    /**
     * Get the validation rules used to validate the number of copies.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function copiesRules(): array
    {
        return ['required', 'integer', 'min:1', 'max:10'];
    }

    /**
     * Get the validation rules used to validate the uploaded file set.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function attachmentsRules(): array
    {
        return ['nullable', 'array', 'max:'.config('registrar.attachments.max_files')];
    }

    /**
     * Get the validation rules used to validate a single uploaded file.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function attachmentRules(): array
    {
        /** @var array<int, string> $mimes */
        $mimes = config('registrar.attachments.mimes');

        return [
            'file',
            'mimes:'.implode(',', $mimes),
            'max:'.config('registrar.attachments.max_kb'),
        ];
    }

    /**
     * Get the validation messages shared by the request forms.
     *
     * @return array<string, string>
     */
    protected function documentRequestMessages(): array
    {
        return [
            'document_type_id.required' => __('Please choose the document you need.'),
            'document_type_id.exists' => __('That document is no longer available for request.'),
            'other_document_name.required' => __('Please name the document you need.'),
            'attachments.max' => __('You may attach at most :count files.', [
                'count' => config('registrar.attachments.max_files'),
            ]),
        ];
    }
}
