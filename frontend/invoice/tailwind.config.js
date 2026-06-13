export default {
  content: ["./index.html", "./src/**/*.{vue,js,ts}"],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Barlow"', "system-ui", "sans-serif"],
        display: ['"Barlow Semi Condensed"', '"Barlow"', "system-ui", "sans-serif"],
      },
      colors: {
        ink: {
          DEFAULT: "#f5f3f9",
          50: "#ffffff",
          100: "#ede9f5",
          200: "#d8d0e8",
        },
        ledger: {
          DEFAULT: "#7c5cbf",
          dim: "#5b3fa0",
          glow: "#9b7dd4",
        },
        credit: {
          DEFAULT: "#059669",
          dim: "#047857",
          light: "#d1fae5",
        },
        debit: {
          DEFAULT: "#e11d48",
          dim: "#be123c",
          light: "#ffe4e6",
        },
        mist: "#6b7280",
        pearl: "#1f2937",
      },
      boxShadow: {
        glow: "0 8px 40px rgba(124, 92, 191, 0.18)",
        panel: "0 8px 32px rgba(31, 41, 55, 0.08)",
        card: "0 1px 3px rgba(31, 41, 55, 0.06), 0 8px 24px rgba(31, 41, 55, 0.04)",
      },
      animation: {
        float: "float 8s ease-in-out infinite",
        shimmer: "shimmer 2.5s linear infinite",
        "fade-up": "fadeUp 0.5s ease-out both",
      },
      keyframes: {
        float: {
          "0%, 100%": { transform: "translateY(0px)" },
          "50%": { transform: "translateY(-12px)" },
        },
        shimmer: {
          "0%": { backgroundPosition: "200% center" },
          "100%": { backgroundPosition: "-200% center" },
        },
        fadeUp: {
          "0%": { opacity: "0", transform: "translateY(16px)" },
          "100%": { opacity: "1", transform: "translateY(0)" },
        },
      },
    },
  },
  plugins: [],
};
