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
                /* 14px html root — keep xs/sm/base readable (not ~10px from default rem scale) */
                xs: ['14px', { lineHeight: '1.35' }],
                sm: ['14px', { lineHeight: '1.4' }],
                base: ['14px', { lineHeight: '1.5' }],
                lg: ['16px', { lineHeight: '1.5' }],
                xl: ['18px', { lineHeight: '1.5' }],
                '2xl': ['20px', { lineHeight: '1.4' }],
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
