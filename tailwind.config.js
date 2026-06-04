import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
            fontSize: {
                /* 12px html root — default Tailwind sm/xs were ~10px; keep UI readable */
                xs: ['12px', { lineHeight: '1.35' }],
                sm: ['12px', { lineHeight: '1.4' }],
                base: ['12px', { lineHeight: '1.5' }],
                lg: ['14px', { lineHeight: '1.5' }],
                xl: ['16px', { lineHeight: '1.5' }],
                '2xl': ['18px', { lineHeight: '1.4' }],
            },
            height: {
                '18': '4.5rem', // 72px
            },
        },
    },

    plugins: [
        forms,
        typography,
    ],
};
