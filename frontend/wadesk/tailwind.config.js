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
          DEFAULT: "#0d9488",
          soft: "#14b8a6",
        },
      },
    },
  },
  plugins: [],
};
