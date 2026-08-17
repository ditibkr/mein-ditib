/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './index.html',
    './src/**/*.{vue,js,ts,jsx,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        // DITIB Rot als primäre Farbe
        primary: {
          50:  '#fdf2f4',
          100: '#fce7eb',
          200: '#f9cfd7',
          300: '#f4a0b0',
          400: '#ec6b85',
          500: '#e03356',
          600: '#C41E3A',
          700: '#a5182f',
          800: '#8b1629',
          900: '#761426',
        },
        ditib: {
          red:  '#C41E3A',
          dark: '#1a1a2e',
          light: '#f8f9fa',
        },
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
      },
      spacing: {
        sidebar: '16rem',
      },
    },
  },
  plugins: [],
}
