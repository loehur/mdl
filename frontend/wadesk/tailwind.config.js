/** @type {import('tailwindcss').Config} */
export default {
  content: ["./index.html", "./src/**/*.{vue,js}"],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"DM Sans"', "Segoe UI", "sans-serif"],
        display: ['"Outfit"', "Segoe UI", "sans-serif"],
      },
      colors: {
        ink: {
          950: "rgb(var(--color-ink-950) / <alpha-value>)",
          900: "rgb(var(--color-ink-900) / <alpha-value>)",
          800: "rgb(var(--color-ink-800) / <alpha-value>)",
          700: "rgb(var(--color-ink-700) / <alpha-value>)",
        },
        accent: {
          DEFAULT: "rgb(var(--color-accent) / <alpha-value>)",
          soft: "rgb(var(--color-accent-soft) / <alpha-value>)",
        },
      },
      /* Warna teks terpisah dari bg — bisa dibalik di tema terang tanpa merusak bg-slate-* */
      textColor: {
        slate: {
          100: "rgb(var(--text-slate-100) / <alpha-value>)",
          200: "rgb(var(--text-slate-200) / <alpha-value>)",
          300: "rgb(var(--text-slate-300) / <alpha-value>)",
          400: "rgb(var(--text-slate-400) / <alpha-value>)",
          500: "rgb(var(--text-slate-500) / <alpha-value>)",
          600: "rgb(var(--text-slate-600) / <alpha-value>)",
          700: "rgb(var(--text-slate-700) / <alpha-value>)",
          800: "rgb(var(--text-slate-800) / <alpha-value>)",
        },
        accent: {
          DEFAULT: "rgb(var(--text-accent) / <alpha-value>)",
          soft: "rgb(var(--text-accent-soft) / <alpha-value>)",
        },
        emerald: {
          200: "rgb(var(--text-emerald-200) / <alpha-value>)",
          300: "rgb(var(--text-emerald-300) / <alpha-value>)",
          400: "rgb(var(--text-emerald-400) / <alpha-value>)",
          500: "rgb(var(--text-emerald-500) / <alpha-value>)",
        },
        amber: {
          100: "rgb(var(--text-amber-100) / <alpha-value>)",
          200: "rgb(var(--text-amber-200) / <alpha-value>)",
          300: "rgb(var(--text-amber-300) / <alpha-value>)",
          400: "rgb(var(--text-amber-400) / <alpha-value>)",
          500: "rgb(var(--text-amber-500) / <alpha-value>)",
          800: "rgb(var(--text-amber-800) / <alpha-value>)",
        },
        red: {
          400: "rgb(var(--text-red-400) / <alpha-value>)",
          500: "rgb(var(--text-red-500) / <alpha-value>)",
        },
        rose: {
          300: "rgb(var(--text-rose-300) / <alpha-value>)",
          400: "rgb(var(--text-rose-400) / <alpha-value>)",
        },
        sky: {
          100: "rgb(var(--text-sky-100) / <alpha-value>)",
          200: "rgb(var(--text-sky-200) / <alpha-value>)",
          300: "rgb(var(--text-sky-300) / <alpha-value>)",
          400: "rgb(var(--text-sky-400) / <alpha-value>)",
        },
        violet: {
          300: "rgb(var(--text-violet-300) / <alpha-value>)",
        },
      },
    },
  },
  plugins: [],
};
