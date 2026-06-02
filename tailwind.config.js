import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Plus Jakarta Sans', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                gold: {
                    DEFAULT: '#C9A84C',
                    light: '#E8C96D',
                    dark: '#A8872E',
                    bg: '#FDF8EC',
                },
                dark: {
                    DEFAULT: '#0F1117',
                    2: '#161B27',
                    3: '#1E2535',
                },
            },
        },
    },
    plugins: [forms],
};