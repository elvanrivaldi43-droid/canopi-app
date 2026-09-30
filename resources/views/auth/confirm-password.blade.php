<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        Data keuangan (gaji, kasbon, slip) dikunci. Masukkan password login kamu untuk melihatnya.
        Setelah benar, data terbuka selama 5 menit lalu terkunci lagi.
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <!-- Password -->
        <div>
            <x-input-label for="password" value="Password" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end mt-4">
            <x-primary-button>
                Buka
            </x-primary-button>
        </div>
    </form>

    <div class="mt-4 text-xs text-gray-500">
        Lupa password? Minta reset ke Owner.
    </div>
</x-guest-layout>
