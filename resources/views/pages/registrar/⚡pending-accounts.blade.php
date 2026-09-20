<?php

use App\Actions\Users\ApproveStudentAccount;
use App\Actions\Users\RejectStudentAccount;
use App\Models\User;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Pending accounts')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public ?int $rejectingId = null;

    public string $reason = '';

    /**
     * Reset paging whenever the search narrows the result set.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Get the accounts waiting on review, longest waiting first.
     *
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function pendingAccounts(): LengthAwarePaginator
    {
        return User::query()
            ->pending()
            ->with(['student', 'registryEntry'])
            ->when($this->search !== '', fn ($query) => $query->where(function ($query): void {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%')
                    ->orWhereHas('student', fn ($student) => $student->where('student_number', 'like', '%'.$this->search.'%'));
            }))
            ->oldest()
            ->paginate(15);
    }

    /**
     * Let a pending account into the system.
     */
    public function approve(int $userId, ApproveStudentAccount $approveStudentAccount): void
    {
        $user = User::query()->findOrFail($userId);

        Gate::authorize('approve', $user);

        $approveStudentAccount($user, Auth::user());

        unset($this->pendingAccounts);

        Flux::toast(variant: 'success', text: __(':name can now sign in.', ['name' => $user->name]));
    }

    /**
     * Open the modal asking why the account is being turned away.
     */
    public function startRejection(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        Gate::authorize('reject', $user);

        $this->rejectingId = $user->id;
        $this->reason = '';
        $this->resetValidation();

        Flux::modal('reject-account')->show();
    }

    /**
     * Turn away a pending account.
     */
    public function reject(RejectStudentAccount $rejectStudentAccount): void
    {
        $user = User::query()->findOrFail($this->rejectingId);

        Gate::authorize('reject', $user);

        $validated = $this->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $rejectStudentAccount($user, Auth::user(), $validated['reason']);

        $this->reset('rejectingId', 'reason');
        unset($this->pendingAccounts);

        Flux::modal('reject-account')->close();
        Flux::toast(variant: 'success', text: __('The account was turned away and the student has been emailed.'));
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-page-heading
        :heading="__('Pending accounts')"
        :subheading="__('New registrations matched the student registry and are waiting for a person to confirm them.')"
    />

    <flux:input
        wire:model.live.debounce.300ms="search"
        :label="__('Search')"
        :placeholder="__('Name, email, or student number')"
        class="max-w-xs"
        data-test="pending-search"
    />

    <flux:table :paginate="$this->pendingAccounts">
        <flux:table.columns>
            <flux:table.column>{{ __('Registered') }}</flux:table.column>
            <flux:table.column>{{ __('Submitted details') }}</flux:table.column>
            <flux:table.column>{{ __('Registry record') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->pendingAccounts as $account)
                <flux:table.row wire:key="pending-{{ $account->id }}">
                    <flux:table.cell class="whitespace-nowrap">
                        {{ $account->created_at?->diffForHumans() }}
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex flex-col">
                            <flux:text size="sm" class="font-medium">{{ $account->name }}</flux:text>
                            <flux:text size="sm" class="text-zinc-400">{{ $account->email }}</flux:text>
                            <flux:text size="sm" class="font-mono text-xs">
                                {{ $account->student?->student_number ?? __('No student profile') }}
                            </flux:text>
                            @if ($account->student !== null)
                                <flux:text size="sm" class="text-zinc-400">{{ $account->student->course }}</flux:text>
                            @endif
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if ($account->registryEntry !== null)
                            <div class="flex flex-col" data-test="registry-record">
                                <flux:text size="sm" class="font-medium">{{ $account->registryEntry->name }}</flux:text>
                                <flux:text size="sm" class="text-zinc-400">{{ $account->registryEntry->course }}</flux:text>
                            </div>
                        @else
                            <flux:badge color="amber" size="sm" data-test="no-registry-record">
                                {{ __('No registry entry') }}
                            </flux:badge>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell>
                        <div class="flex justify-end gap-1">
                            <flux:button
                                wire:click="approve({{ $account->id }})"
                                size="xs"
                                variant="primary"
                                data-test="approve-account"
                            >
                                {{ __('Approve') }}
                            </flux:button>

                            <flux:button
                                wire:click="startRejection({{ $account->id }})"
                                size="xs"
                                variant="subtle"
                                data-test="reject-account"
                            >
                                {{ __('Turn away') }}
                            </flux:button>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">
                        <flux:text class="text-zinc-400" data-test="no-pending-accounts">
                            {{ __('No accounts are waiting for review.') }}
                        </flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="reject-account" class="min-w-[26rem]">
        <form wire:submit="reject" class="flex flex-col gap-6">
            <div class="flex flex-col gap-2">
                <flux:heading size="lg">{{ __('Turn away this account') }}</flux:heading>
                <flux:text>
                    {{ __('The reason is emailed to the student, and their student number is released so they can register again once it is sorted out.') }}
                </flux:text>
            </div>

            <flux:textarea
                wire:model="reason"
                :label="__('Reason')"
                rows="3"
                required
                data-test="rejection-reason-input"
            />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="danger" data-test="confirm-rejection">
                    {{ __('Turn away') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
