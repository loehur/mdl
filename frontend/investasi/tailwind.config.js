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
          DEFAULT: "#f0f4f9",
          50: "#ffffff",
          100: "#e8eef5",
          200: "#d1dbe8",
        },
        ledger: {
          DEFAULT: "#4a8fd4",
          dim: "#2f6fad",
          glow: "#6baee8",
        },
        credit: {
          DEFAULT: "#0d9488",
          dim: "#0f766e",
          light: "#ccfbf1",
        },
        debit: {
          DEFAULT: "#dc2626",
          dim: "#b91c1c",
          light: "#fee2e2",
        },
        mist: "#64748b",
        pearl: "#1e293b",
      },
      boxShadow: {
        glow: "0 8px 40px rgba(74, 143, 212, 0.15)",
        panel: "0 8px 32px rgba(15, 23, 42, 0.08)",
        card: "0 1px 3px rgba(15, 23, 42, 0.06), 0 8px 24px rgba(15, 23, 42, 0.04)",
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
