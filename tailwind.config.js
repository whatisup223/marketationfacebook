/** @type {import('tailwindcss').Config} */
module.exports = {
    content: ["./**/*.{php,html,js}"],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'sans-serif'],
            },
            colors: {
                // You can extend colors here if needed to match what CDN was doing or project specific
            }
        },
    },
    plugins: [],
}
