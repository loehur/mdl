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
          950: "#0b1220",
          900: "#111827",
          800: "#1e293b",
          700: "#334155",
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
