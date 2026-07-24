<section>
    <header>
        <h2 class="text-base sm:text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-xs sm:text-sm text-gray-500">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-4 sm:mt-6 space-y-4 sm:space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full text-xs sm:text-sm" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2 text-xs sm:text-sm" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full text-xs sm:text-sm" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2 text-xs sm:text-sm" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-xs sm:text-sm mt-2 text-gray-600">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-xs sm:text-sm text-indigo-600 hover:text-indigo-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-xs sm:text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div>
            <x-input-label for="phone" :value="__('Phone')" />
            <x-text-input id="phone" name="phone" type="tel" class="mt-1 block w-full text-xs sm:text-sm" :value="old('phone', $user->phone)" autocomplete="tel" placeholder="+971501234567" />
            <x-input-error class="mt-2 text-xs sm:text-sm" :messages="$errors->get('phone')" />
            @if(($user->role ?? null) === 'client')
                <p class="mt-1.5 text-xs text-gray-500">
                    Or manage it on the dedicated page:
                    <a href="{{ route('client.phone.edit') }}" class="font-medium text-indigo-600 hover:text-indigo-800">Update phone number</a>
                </p>
            @endif
        </div>

        <div class="flex items-center gap-3 sm:gap-4">
            <x-primary-button class="text-xs sm:text-sm px-3 sm:px-4 py-1.5 sm:py-2">{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-xs sm:text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
