/**
 * Tailwind CSS configuration for the JPC Portal.
 *
 * Strategy: the entire default Tailwind palette is intentionally PRESERVED so the
 * components already written with utility classes (slate-900, indigo-950,
 * emerald-500, ...) keep rendering exactly as authored. The official JPC Portal
 * theme — Royal Blue + Soft Mint Green + Pure White — is layered on top as an
 * additive `jpc` colour group, a `mint` surface group, semantic status colours
 * and a few extra theme extensions. Nothing existing is renamed or overridden;
 * legacy neutral surfaces are retinted centrally in `src/index.css`.
 */

/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        jpc: {
          // Royal Blue ramp — mirrors the official palette in src/index.css.
          // jpc-600 = Royal Blue    (#1E4ED8) → primary buttons
          // jpc-700 = Deep Royal Blue (#1E3A8A) → hover / sidebar
          // jpc-500 = Bright Blue   (#2563EB) → focus rings / accents
          50: '#eef4ff',
          100: '#eaf1ff',
          200: '#c7d9fe',
          300: '#93b4fd',
          400: '#5b87f9',
          500: '#2563eb',
          600: '#1e4ed8',
          700: '#1e3a8a',
          800: '#1b2f6b',
          900: '#16255c',
          950: '#0f1a44',
        },
        // Crisp neutral & blue-slate surfaces — mirrors --color-bg* / --color-mint
        mint: {
          50: '#f8fafc',
          100: '#f1f5f9',
          200: '#eff6ff',
          300: '#e2e8f0',
        },
        // Semantic status colours — mirrors --color-ok / --color-error / --color-warning
        ok: '#059669',
        'ok-soft': '#d1fae5',
        error: '#dc2626',
        'error-soft': '#fee2e2',
        warning: '#f59e0b',
        'warning-soft': '#fef3c7',
        info: '#2563eb',
        'info-soft': '#e0f2fe',
        surface: '#ffffff',
        canvas: '#f8fafc',
        line: '#e2e8f0',
      },
      fontFamily: {
        // The hand-written design system in src/index.css already declares this stack
        // as --font; reuse it so Tailwind and CSS output stay typographically identical.
        sans: [
          'Segoe UI',
          'system-ui',
          '-apple-system',
          'Helvetica Neue',
          'Arial',
          'sans-serif',
        ],
        mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Consolas', 'monospace'],
      },
      borderRadius: {
        jpc: '10px',
        'jpc-lg': '14px',
      },
      boxShadow: {
        jpc: '0 1px 3px rgba(15, 23, 42, 0.08), 0 1px 2px rgba(15, 23, 42, 0.04)',
        'jpc-lg': '0 8px 24px rgba(15, 23, 42, 0.12)',
      },
      backgroundImage: {
        // Official brand gradient — reserved for banners and dashboard highlights.
        jpc: 'linear-gradient(135deg, #1e3a8a, #2563eb)',
      },
      maxWidth: {
        content: '1440px',
      },
      keyframes: {
        'fade-in': {
          from: { opacity: '0', transform: 'translateY(4px)' },
          to: { opacity: '1', transform: 'translateY(0)' },
        },
      },
      animation: {
        'fade-in': 'fade-in 0.18s ease-out',
      },
    },
  },
  plugins: [],
}
