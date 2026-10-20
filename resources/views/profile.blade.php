<x-app-layout>
    <x-slot name="header">
        <h2 class="page-title">
            Profil & keamanan
        </h2>
    </x-slot>

    <div class="legacy-form">
        <div class="space-y-6">
            <div class="panel p-6 sm:p-8">
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information-form />
                </div>
            </div>

            <div class="panel p-6 sm:p-8">
                <div class="max-w-xl">
                    <livewire:profile.update-password-form />
                </div>
            </div>

            <div class="panel p-6 sm:p-8">
                <div class="max-w-xl">
                    <livewire:profile.delete-user-form />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
