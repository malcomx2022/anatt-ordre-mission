/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './index.html',
    './src/**/*.{vue,js,ts,jsx,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        anatt: {
          50: '#EDFAF2',
          100: '#C5F0D5',
          200: '#86DFA6',
          300: '#4ACD7A',
          400: '#27B35D',
          500: '#1D9E4F',
          600: '#1A8C48',
          700: '#14713A',
          800: '#0F5C2E',
          900: '#0A3D1F',
          950: '#072E17',
        },
        gold: {
          50: '#FBF3E0',
          100: '#F5E4BA',
          200: '#EBCF8A',
          300: '#E0BD6A',
          400: '#D4A843',
          500: '#B8891A',
          600: '#8B6914',
          700: '#6B5010',
        },
        benin: {
          red: '#CE1126',
          green: '#1D9E4F',
          yellow: '#D4A843',
        },
        danger: {
          50: '#FEF2F2',
          500: '#EF3340',
          600: '#DC2626',
        },
      },
      fontFamily: {
        display: ['Sora', 'sans-serif'],
        body: ['DM Sans', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
