<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('User accounts')] class extends Component {
    use PasswordValidationRules, ProfileValidationRules, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $role = '';

    public ?int $editingId = null;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $newRole = UserRole::RegistrarStaff->value;
    public string $newStatus = UserStatus::Active->value;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    /**
     * Reset paging when a filter narrows the list.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Get the accounts on this page.
     *
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->with('student')
            ->when($this->role !== '', fn ($query) => $query->where('role', $this->role))
            ->when($this->search !== '', fn ($query) => $query->where(function ($query): void {
                $query->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%');
            }))
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(15);
    }

    /**
     * Open the modal ready to create a staff account.
     */
    public function createUser(): void
    {
        Gate::authorize('create', User::class);

        $this->reset('editingId', 'name', 'email', 'password', 'password_confirmation');
        $this->newRole = UserRole::RegistrarStaff->value;
        $this->newStatus = UserStatus::Active->value;
        $this->resetValidation();

        Flux::modal('user-form')->show();
    }

    /**
     * Open the modal to edit an existing account.
     */
    public function editUser(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        Gate::authorize('update', $user);

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->password_confirmation = '';
        $this->newRole = $user->role->value;
        $this->newStatus = $user->status->value;
        $this->resetValidation();

        Flux::modal('user-form')->show();
    }

    /**
     * Create or update the account.
     */
    public function saveUser(): void
    {
        $user = $this->editingId === null ? null : User::query()->findOrFail($this->editingId);

        Gate::authorize($user === null ? 'create' : 'update', $user ?? User::class);

        $validated = $this->validate([
            'name' => $this->nameRules(),
            'email' => $this->emailRules($this->editingId),
            'newRole' => ['required', Rule::enum(UserRole::class)],
            'newStatus' => ['required', Rule::enum(UserStatus::class)],
            'password' => $user === null ? $this->passwordRules() : $this->optionalPasswordRules(),
        ]);

        $role = UserRole::from($validated['newRole']);
        $status = UserStatus::from($validated['newStatus']);

        if ($user === null) {
            $created = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => $role,
                'status' => $status,
            ]);

            // The administrator vouched for the address, so the account skips
            // email verification. Set outside the mass assignment, which
            // deliberately does not expose email_verified_at.
            $created->forceFill(['email_verified_at' => now()])->save();
        } else {
            // Role and status changes are guarded separately so an
            // administrator cannot lock themselves - or the system - out.
            if ($role !== $user->role) {
                Gate::authorize('changeRole', $user);
                $user->role = $role;
            }

            if ($status !== $user->status) {
                Gate::authorize('changeStatus', $user);
                $user->status = $status;
            }

            $user->name = $validated['name'];
            $user->email = $validated['email'];

            if ($validated['password'] !== '') {
                $user->password = $validated['password'];
            }

            $user->save();
        }

        unset($this->users);

        Flux::modal('user-form')->close();
        Flux::toast(variant: 'success', text: $user === null
            ? __('Account created.')
            : __('Account updated.'));
    }

    /**
     * Suspend or reactivate an account.
     */
    public function toggleStatus(int $userId): void
    {
        $user = User::query()->findOrFail($userId);

        Gate::authorize('changeStatus', $user);

        $user->status = $user->status === UserStatus::Active
            ? UserStatus::Suspended
            : UserStatus::Active;

        $user->save();

        unset($this->users);

        Flux::toast(variant: 'success', text: __('Account is now :status.', [
            'status' => $user->status->label(),
        ]));
    }

    /**
     * Get the roles an administrator may assign.
     *
     * @return array<int, UserRole>
     */
    #[Computed]
    public function assignableRoles(): array
    {
        return UserRole::assignable();
    }
}; ?>

<div class="flex w-full flex-col gap-6">
    <x-page-heading
        :heading="__('User accounts')"
        :subheading="__('Students register themselves. Staff and administrator accounts are created here.')"
    >
        <flux:button wire:click="createUser" variant="primary" size="sm" icon="plus" data-test="create-user">
            {{ __('New account') }}
        </flux:button>
    </x-page-heading>

    <div class="flex flex-wrap items-end gap-3">
        <flux:input
            wire:model.live.debounce.300ms="search"
            :label="__('Search')"
            :placeholder="__('Name or email')"
            icon="magnifying-glass"
            class="max-w-xs"
            data-test="search-input"
        />

        <flux:select wire:model.live="role" :label="__('Role')" class="max-w-52" data-test="role-filter">
            <flux:select.option value="">{{ __('All roles') }}</flux:select.option>
            @foreach (App\Enums\UserRole::cases() as $option)
                <flux:select.option :value="$option->value" wire:key="role-{{ $option->value }}">
                    {{ $option->label() }}
                </flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:table :paginate="$this->users">
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Role') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->users as $user)
                <flux:table.row wire:key="user-{{ $user->id }}">
                    <flux:table.cell>
                        <div class="flex flex-col">
                            <flux:text size="sm">{{ $user->name }}</flux:text>
                            @if ($user->student?->student_number)
                                <flux:text size="sm" class="text-zinc-400">{{ $user->student->student_number }}</flux:text>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell><x-status-badge :status="$user->role" /></flux:table.cell>
                    <flux:table.cell><x-status-badge :status="$user->status" /></flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-1">
                            @can('update', $user)
                                <flux:button
                                    wire:click="editUser({{ $user->id }})"
                                    size="xs"
                                    variant="ghost"
                                    data-test="edit-user"
                                >
                                    {{ __('Edit') }}
                                </flux:button>
                            @endcan

                            @can('changeStatus', $user)
                                <flux:button
                                    wire:click="toggleStatus({{ $user->id }})"
                                    size="xs"
                                    variant="subtle"
                                    data-test="toggle-status"
                                >
                                    {{ $user->status === App\Enums\UserStatus::Active ? __('Suspend') : __('Reactivate') }}
                                </flux:button>
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal name="user-form" class="min-w-[26rem]">
        <form wire:submit="saveUser" class="flex flex-col gap-6">
            <div class="flex flex-col gap-2">
                <flux:heading size="lg">
                    {{ $editingId === null ? __('New account') : __('Edit account') }}
                </flux:heading>
                <flux:text>
                    {{ $editingId === null
                        ? __('The account is active and verified straight away.')
                        : __('Leave the password blank to keep the current one.') }}
                </flux:text>
            </div>

            <flux:input wire:model="name" :label="__('Name')" required data-test="user-name-input" />
            <flux:input wire:model="email" :label="__('Email')" type="email" required data-test="user-email-input" />

            <flux:select wire:model="newRole" :label="__('Role')" required data-test="user-role-select">
                @foreach ($this->assignableRoles as $option)
                    <flux:select.option :value="$option->value" wire:key="assign-{{ $option->value }}">
                        {{ $option->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="newStatus" :label="__('Status')" required data-test="user-status-select">
                @foreach (App\Enums\UserStatus::cases() as $option)
                    <flux:select.option :value="$option->value" wire:key="status-{{ $option->value }}">
                        {{ $option->label() }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:input
                wire:model="password"
                :label="$editingId === null ? __('Password') : __('New password')"
                :description="$editingId === null ? null : __('Leave blank to keep the current password.')"
                type="password"
                :required="$editingId === null"
                viewable
                data-test="user-password-input"
            />

            <flux:input
                wire:model="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                :required="$editingId === null"
                viewable
                data-test="user-password-confirmation-input"
            />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button type="submit" variant="primary" data-test="save-user">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
