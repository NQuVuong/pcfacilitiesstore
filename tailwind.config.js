// tailwind.config.js
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './templates/**/*.twig',
    './assets/**/*.{js,ts}',
  ],
  theme: {
    extend: {},
  },
  plugins: [
    require('daisyui'),
    // require('@tailwindcss/forms'), // nếu cần
  ],
  daisyui: {
    themes: ['light'],
    logs: false,
  },
};
