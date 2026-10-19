{{-- Minimal view so the eager-loaded history (and Debugbar) can be checked. FE: restyle freely. --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $session->judul ?? __('Chat Session') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @foreach ($session->chatMessages as $message)
                <div class="bg-white shadow-sm sm:rounded-lg p-6 text-gray-900">
                    <p class="text-sm font-semibold uppercase text-gray-500">{{ $message->role }}</p>
                    <p class="mt-1">{{ $message->isi }}</p>

                    @foreach ($message->toolCalls as $toolCall)
                        <p class="mt-2 text-sm text-gray-600">
                            {{ $toolCall->tool->nama }} &middot; {{ $toolCall->status }}
                            @if ($toolCall->loggable)
                                &middot; {{ class_basename($toolCall->loggable) }} #{{ $toolCall->loggable->id }}
                            @endif
                        </p>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
