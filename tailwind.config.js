/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./app/**/*.php', './public/**/*.js'],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#f0f4ff',
          100: '#e0e9ff',
          500: '#4f6ef7',
          600: '#3a56e8',
          700: '#2d44d4',
          900: '#1a2a8a',
        },
        surface: {
          DEFAULT: '#0f1117',
          card: '#1a1d27',
          border: '#2a2d3e',
          hover: '#22253a',
        },
        positive: '#22c55e',
        negative: '#ef4444',
        neutral: '#f59e0b',
      },
      fontFamily: {
        sans: ['DM Sans', 'sans-serif'],
        display: ['Syne', 'sans-serif'],
        mono: ['JetBrains Mono', 'monospace'],
      },
      boxShadow: {
        glow: '0 0 20px rgba(79,110,247,0.15)',
      },
    },
  },
  plugins: [],
};
