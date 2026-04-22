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
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    // Safelist dynamic palette classes used for group color-coding on the
    // assessment submissions page. Each blade file picks a color from this
    // palette at render time, so these utilities must be generated even
    // though the raw class string is never written literally in any file.
    safelist: [
        {
            pattern: /^(bg|text|border|border-l)-(indigo|emerald|amber|rose|sky|violet|teal|fuchsia|cyan|lime|orange|pink)-(100|300|400|500|700|900)$/,
            variants: ['dark', 'hover', 'dark:hover'],
        },
        {
            pattern: /^(bg|text)-(indigo|emerald|amber|rose|sky|violet|teal|fuchsia|cyan|lime|orange|pink)-900\/30$/,
            variants: ['dark'],
        },
    ],

    plugins: [forms, typography],
};
