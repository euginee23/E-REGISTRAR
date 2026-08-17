@props(['histories'])

@if ($histories->isEmpty())
    <flux:text size="sm" class="text-zinc-500">{{ __('No activity recorded yet.') }}</flux:text>
@else
    <ol class="flex flex-col gap-4">
        @foreach ($histories as $history)
            <li class="flex gap-3">
                <div class="flex flex-col items-center">
                    <span @class([
                        'mt-1 size-2.5 shrink-0 rounded-full',
                        'bg-accent' => $loop->last,
                        'bg-zinc-300' => ! $loop->last,
                    ])></span>

                    @unless ($loop->last)
                        <span class="mt-1 w-px flex-1 bg-zinc-200"></span>
                    @endunless
                </div>

                <div class="flex flex-col gap-0.5 pb-1">
                    <x-status-badge :status="$history->to_status" class="self-start" />

                    <flux:text size="sm" class="text-zinc-500">
                        {{ $history->created_at?->format('M j, Y \a\t g:i A') }}
                        @if ($history->changedBy)
                            &middot; {{ $history->changedBy->name }}
                        @endif
                    </flux:text>

                    @if ($history->remarks)
                        <flux:text size="sm">{{ $history->remarks }}</flux:text>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
@endif
