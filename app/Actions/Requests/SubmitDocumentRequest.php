<?php

namespace App\Actions\Requests;

use App\Actions\Notifications\SendNotification;
use App\Concerns\DocumentRequestValidationRules;
use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Enums\UserRole;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\RequestAttachment;
use App\Models\RequestStatusHistory;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class SubmitDocumentRequest
{
    use DocumentRequestValidationRules;

    public function __construct(private SendNotification $sendNotification) {}

    /**
     * Get the validation rules for submitting a request.
     *
     * The rules live with the action rather than in the form so the form and
     * any future caller validate identically. The free-text document name is
     * only demanded by types that are defined to need one.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(?DocumentType $documentType = null): array
    {
        $rules = $this->documentRequestRules();

        if ($documentType?->requires_custom_name) {
            $rules['other_document_name'] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * Get the validation messages for submitting a request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->documentRequestMessages();
    }

    /**
     * Record a new document request together with its supporting files.
     *
     * The reference number is assigned by the model observer, and the whole
     * write is wrapped in a retrying transaction so two students submitting
     * at the same moment cannot end up sharing one.
     *
     * @param  array<int, TemporaryUploadedFile|UploadedFile>  $attachments
     */
    public function __invoke(
        Student $student,
        DocumentType $documentType,
        string $purpose,
        int $copies = 1,
        ?string $otherDocumentName = null,
        array $attachments = [],
    ): DocumentRequest {
        $documentRequest = DB::transaction(function () use (
            $student,
            $documentType,
            $purpose,
            $copies,
            $otherDocumentName,
            $attachments,
        ): DocumentRequest {
            $documentRequest = DocumentRequest::create([
                'student_id' => $student->id,
                'document_type_id' => $documentType->id,
                'other_document_name' => $documentType->requires_custom_name ? $otherDocumentName : null,
                'purpose' => $purpose,
                'copies' => $copies,
                'status' => RequestStatus::Pending,
            ]);

            $this->storeAttachments($documentRequest, $attachments);

            RequestStatusHistory::create([
                'document_request_id' => $documentRequest->id,
                'from_status' => null,
                'to_status' => RequestStatus::Pending,
                'changed_by_user_id' => $student->user_id,
                'remarks' => null,
            ]);

            return $documentRequest;
        }, attempts: 3);

        // Notify only once the write has committed, so a rollback can never
        // announce a reference number that does not exist.
        $this->notify($documentRequest);

        return $documentRequest;
    }

    /**
     * Move the uploaded files onto the private disk.
     *
     * @param  array<int, TemporaryUploadedFile|UploadedFile>  $attachments
     */
    private function storeAttachments(DocumentRequest $documentRequest, array $attachments): void
    {
        $disk = (string) config('registrar.attachments.disk');

        foreach ($attachments as $file) {
            $path = $file->store('requests/'.$documentRequest->id, $disk);

            RequestAttachment::create([
                'document_request_id' => $documentRequest->id,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size' => $file->getSize(),
            ]);
        }
    }

    /**
     * Acknowledge the submission and alert the registrar's office.
     */
    private function notify(DocumentRequest $documentRequest): void
    {
        ($this->sendNotification)(
            $documentRequest->student->user,
            NotificationType::RequestSubmitted,
            __('Your request for :document was submitted. Track it with reference :reference.', [
                'document' => $documentRequest->display_name,
                'reference' => $documentRequest->reference_no,
            ]),
            route('student.requests.show', $documentRequest),
        );

        ($this->sendNotification)(
            $this->registrarPersonnel(),
            NotificationType::RequestReceived,
            __(':student requested :document (:reference).', [
                'student' => $documentRequest->student->user->name,
                'document' => $documentRequest->display_name,
                'reference' => $documentRequest->reference_no,
            ]),
            null,
        );
    }

    /**
     * Get the accounts that staff the registrar's office.
     *
     * @return Collection<int, User>
     */
    private function registrarPersonnel(): Collection
    {
        return User::query()
            ->whereIn('role', [UserRole::Administrator, UserRole::RegistrarStaff])
            ->get();
    }
}
