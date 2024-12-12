/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  darkMode: 'class',
  theme: {
    extend: {
      fontFamily: {
        sans: ['Roboto', 'system-ui', 'sans-serif'],
      },
      colors: {
        primary: {
          DEFAULT: '#990047',
          dark: '#800039',
          light: '#b3195f',
        },
        secondary: {
          DEFAULT: '#327a35',
          dark: '#275e29',
          light: '#3d9241',
        },
      },
      backgroundImage: {
        'hero': "linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('https://images.unsplash.com/photo-1551632811-561732d1e306')",
      },
    },
  },
  plugins: [],
}