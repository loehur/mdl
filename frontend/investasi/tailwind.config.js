export default {
  content: ["./index.html", "./src/**/*.{vue,js,ts}"],
  theme: {
    extend: {
      fontFamily: {
        display: ['"Instrument Serif"', "Georgia", "serif"],
        sans: ['"Outfit"', "system-ui", "sans-serif"],
        mono: ['"JetBrains Mono"', "monospace"],
      },
      colors: {
        ink: {
          DEFAULT: "#0a0f18",
          50: "#111827",
          100: "#1a2332",
          200: "#243044",
        },
        ledger: {
          DEFAULT: "#5b9bd5",
          dim: "#3d7ab8",
          glow: "#93c5fd",
        },
        credit: {
          DEFAULT: "#5eead4",
          dim: "#2dd4bf",
          bg: "#14b8a6",
        },
        debit: {
          DEFAULT: "#fca5a5",
          dim: "#f87171",
        },
        mist: "#8b95a8",
        pearl: "#eef2f7",
      },
      boxShadow: {
        glow: "0 0 60px rgba(91, 155, 213, 0.18)",
        panel: "0 24px 80px rgba(0,0,0,0.45)",
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
