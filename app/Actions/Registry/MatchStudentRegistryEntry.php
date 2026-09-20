<?php

namespace App\Actions\Registry;

use App\Exceptions\StudentRegistryMismatchException;
use App\Models\StudentRegistryEntry;
use Illuminate\Support\Str;

class MatchStudentRegistryEntry
{
    /**
     * Find the unclaimed roster entry a registration is entitled to claim.
     *
     * This is what stops one student opening an account against another's
     * number: the number must exist on the roster, the name must match the
     * record it belongs to, and no account may hold it yet.
     *
     * Pass $lock when the caller is inside the transaction that will claim the
     * entry, so two simultaneous registrations cannot both pass this check.
     *
     * @throws StudentRegistryMismatchException
     */
    public function __invoke(string $studentNumber, string $name, bool $lock = false): StudentRegistryEntry
    {
        $entry = StudentRegistryEntry::query()
            ->where('student_number', $this->normaliseNumber($studentNumber))
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->first();

        if ($entry === null) {
            throw StudentRegistryMismatchException::unknownNumber();
        }

        if (! $this->namesMatch($entry->name, $name)) {
            throw StudentRegistryMismatchException::nameMismatch();
        }

        if ($entry->isClaimed()) {
            throw StudentRegistryMismatchException::alreadyClaimed();
        }

        return $entry;
    }

    /**
     * Reduce a student number to the form the roster stores.
     */
    public function normaliseNumber(string $studentNumber): string
    {
        return Str::upper(trim($studentNumber));
    }

    /**
     * Determine whether a submitted name matches the one on the roster.
     *
     * Names are compared as sets of words, so word order, punctuation, casing
     * and a roster written "Surname, First" all still match. A submitted name
     * may carry extra words the roster omits - a middle name or a suffix - but
     * every word the roster holds must be present, so "Juan Dela Cruz" never
     * passes for a roster entry reading "Maria Dela Cruz".
     */
    private function namesMatch(string $registryName, string $submittedName): bool
    {
        $registryTokens = $this->tokenise($registryName);
        $submittedTokens = $this->tokenise($submittedName);

        if ($registryTokens === [] || $submittedTokens === []) {
            return false;
        }

        return array_diff($registryTokens, $submittedTokens) === [];
    }

    /**
     * Break a name into its comparable words.
     *
     * @return array<int, string>
     */
    private function tokenise(string $name): array
    {
        $normalised = Str::lower(Str::ascii($name));
        $normalised = Str::squish((string) preg_replace('/[^a-z]+/', ' ', $normalised));

        if ($normalised === '') {
            return [];
        }

        $tokens = array_unique(explode(' ', $normalised));
        sort($tokens);

        return $tokens;
    }
}
