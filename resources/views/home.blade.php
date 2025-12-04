<x-app-layout>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-tr from-green-50 to-green-100 dark:from-gray-900 dark:to-gray-800 px-4">
        <div class="bg-white dark:bg-gray-900 shadow-lg rounded-lg max-w-lg w-full p-10 text-center transform transition duration-500 ease-in-out hover:scale-[1.02]">
            <div class="flex justify-center mb-6">
                <!-- Plant icon for theme -->
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 2a10 10 0 0110 10c0 5.523-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12l2 2 4-4" />
                </svg>
            </div>

            <h1 class="text-4xl font-extrabold text-gray-900 dark:text-gray-100 mb-4 animate-fadeIn">
                Welcome to Tandil Backend
            </h1>

            <p class="text-green-700 dark:text-green-300 mb-6 leading-relaxed text-lg animate-fadeIn delay-100">
                A comprehensive platform for managing plant maintenance services across homes and farms.
            </p>

            <p class="text-gray-600 dark:text-gray-300 mb-8 leading-relaxed animate-fadeIn delay-200">
                Manage roles, subscriptions, visits, complaints, and reporting with ease. Your gateway to seamless plant care service management.
            </p>

            <a href="{{ route('profile.edit') }}" class="inline-block bg-green-600 hover:bg-green-700 text-white font-semibold px-6 py-3 rounded-lg shadow-md transition duration-300 animate-fadeIn delay-300">
                Manage Profile
            </a>
        </div>
    </div>

    <style>
        @keyframes fadeIn {
            from {opacity: 0; transform: translateY(20px);}
            to {opacity: 1; transform: translateY(0);}
        }
        .animate-fadeIn {
            animation: fadeIn 0.6s ease forwards;
        }
        .animate-fadeIn.delay-100 {
            animation-delay: 0.1s;
        }
        .animate-fadeIn.delay-200 {
            animation-delay: 0.2s;
        }
        .animate-fadeIn.delay-300 {
            animation-delay: 0.3s;
        }
    </style>
</x-app-layout>
