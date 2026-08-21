<style>
  /* Kas modals — MDL UI Theme (lihat laundry/docs/UI_THEME.md) */
  .op-modal {
    --op-ink: #0f172a;
    --op-muted: #1e293b;
    --op-line: #94a3b8;
    --op-blue: #2563eb;
    --op-blue-deep: #1d4ed8;
    --op-green: #16a34a;
    --op-green-deep: #15803d;
    --op-yellow: #f59e0b;
    --op-yellow-deep: #d97706;
    --op-red: #dc2626;
    --op-red-deep: #b91c1c;
    --op-radius: 0;
    position: fixed;
    inset: 0;
    z-index: 5200;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
  }
  .op-modal.is-open { display: flex; }
  .op-modal.is-static .op-modal__backdrop { cursor: default; }
  body.op-modal-open { overflow: hidden; }
  .op-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15, 23, 42, 0.58);
    backdrop-filter: blur(3px);
    cursor: pointer;
  }
  .op-modal__panel {
    position: relative;
    z-index: 1;
    width: min(440px, 100%);
    max-height: min(94vh, 920px);
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: var(--op-radius);
    box-shadow: 0 24px 48px rgba(15, 23, 42, 0.3);
    overflow: visible;
    animation: kasModalIn .18s ease-out;
  }
  .op-modal__panel--kas {
    width: min(760px, 100%);
  }
  .op-modal__panel--form {
    min-height: min(72vh, 620px);
  }
  .op-modal__panel--kas.op-modal__panel--form {
    min-height: min(68vh, 580px);
  }
  @keyframes kasModalIn {
    from { opacity: 0; transform: translateY(10px) scale(0.98); }
    to { opacity: 1; transform: none; }
  }
  .op-modal__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    color: #fff;
    background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
    flex-shrink: 0;
  }
  .op-modal__head--blue {
    background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
  }
  .op-modal__head--green {
    background: linear-gradient(105deg, #15803d 0%, #16a34a 100%);
  }
  .op-modal__head--yellow {
    background: linear-gradient(105deg, #d97706 0%, #f59e0b 100%);
    color: #111;
  }
  .op-modal__head--red {
    background: linear-gradient(105deg, #b91c1c 0%, #dc2626 100%);
  }
  .op-modal__head h3,
  .op-modal__head h5 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 900;
    letter-spacing: -0.02em;
    font-family: 'fontku', 'Segoe UI', sans-serif;
    text-shadow: 0 1px 0 rgba(0,0,0,.18);
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }
  .op-modal__head--yellow h3,
  .op-modal__head--yellow h5 {
    text-shadow: none;
  }
  .op-modal__head small {
    display: block;
    margin-top: 2px;
    font-size: 0.72rem;
    font-weight: 750;
    opacity: 0.95;
  }
  .op-modal__close {
    width: 34px;
    height: 34px;
    border: 0;
    border-radius: 0;
    background: rgba(255,255,255,.2);
    color: inherit;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
  }
  .op-modal__head--yellow .op-modal__close {
    background: rgba(0,0,0,.12);
    color: #111;
  }
  .op-modal__close:hover { background: rgba(255,255,255,.32); }
  .op-modal__head--yellow .op-modal__close:hover { background: rgba(0,0,0,.18); }
  .op-modal__form-wrap {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
  }
  .op-modal__body {
    padding: 14px 16px;
    overflow-y: auto;
    flex: 1 1 auto;
    background:
      radial-gradient(90% 60% at 0% 0%, rgba(37,99,235,.10), transparent 50%),
      radial-gradient(80% 50% at 100% 0%, rgba(245,158,11,.10), transparent 45%),
      linear-gradient(180deg, #eef4ff 0%, #f4fff8 50%, #fff8eb 100%);
    color: var(--op-ink);
    font-weight: 750;
    font-size: 0.88rem;
  }
  .op-modal__panel--form .op-modal__body {
    overflow: visible;
    padding-bottom: 20px;
  }
  .op-modal__foot {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 8px;
    padding: 12px 16px;
    background: #fff;
    border-top: 1px solid #e2e8f0;
    flex-shrink: 0;
  }
  .op-label {
    display: block;
    margin: 0 0 5px;
    font-size: 0.78rem;
    font-weight: 900;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--op-muted);
  }
  .op-label--danger {
    color: var(--op-red-deep);
  }
  .op-field { margin-bottom: 0; }
  .op-input,
  .op-modal select.op-input:not(.tize):not(.selectized) {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--op-line);
    border-radius: 0 !important;
    background: #fff;
    color: var(--op-ink);
    font-weight: 800;
    font-size: 0.88rem;
    outline: none;
  }
  .op-input:focus,
  .op-modal select.op-input:not(.tize):not(.selectized):focus {
    border-color: var(--op-blue);
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22);
  }
  .op-input--danger {
    border-color: #fca5a5;
    background: linear-gradient(180deg, #fef2f2, #fff);
  }
  .op-input--danger:focus {
    border-color: var(--op-red);
    box-shadow: 0 0 0 2px rgba(220, 38, 38, 0.18);
  }
  .op-modal select.tize,
  .op-modal select.selectized {
    border: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
    padding: 0 !important;
  }
  .op-modal .selectize-control,
  .op-modal .selectize-control.single {
    margin: 0;
    border: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
  }
  .op-modal .selectize-control.single .selectize-input {
    border: 1px solid var(--op-line) !important;
    border-radius: 0 !important;
    padding: 10px 12px !important;
    min-height: 42px;
    box-shadow: none !important;
    font-weight: 800;
    background: #fff !important;
  }
  .op-modal .selectize-control.single .selectize-input.focus {
    border-color: var(--op-blue) !important;
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.22) !important;
  }
  .op-modal .selectize-control.single .selectize-input:after {
    border: 0 !important;
  }
  .op-modal .selectize-dropdown {
    border: 1px solid var(--op-line) !important;
    border-radius: 0 !important;
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
    z-index: 30 !important;
  }
  .op-modal .selectize-dropdown .option {
    font-weight: 700;
    color: var(--op-ink);
  }
  .op-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 12px 14px;
    border: 1px solid transparent;
    border-radius: 0 !important;
    font-size: 0.95rem;
    font-weight: 900;
    cursor: pointer;
    line-height: 1.2;
    font-family: 'fontku', 'Segoe UI', sans-serif;
  }
  .op-btn:disabled { opacity: 0.55; cursor: not-allowed; }
  .op-btn--blue {
    background: linear-gradient(180deg, var(--op-blue), var(--op-blue-deep));
    color: #fff;
  }
  .op-btn--primary {
    background: linear-gradient(180deg, var(--op-green), var(--op-green-deep));
    color: #fff;
  }
  .op-btn--warn {
    background: linear-gradient(180deg, var(--op-yellow), var(--op-yellow-deep));
    color: #111;
  }
  .op-btn--danger {
    background: linear-gradient(180deg, var(--op-red), var(--op-red-deep));
    color: #fff;
  }
  .op-btn--block { width: 100%; }
  .kas-pg-modal-grid {
    display: grid;
    gap: 12px 14px;
    grid-template-columns: 1fr;
  }
  @media (min-width: 576px) {
    .kas-pg-modal-grid {
      grid-template-columns: 1fr 1fr;
    }
    .kas-pg-modal-grid > .kas-pg-span-2 {
      grid-column: 1 / -1;
    }
  }
  .kas-saldo-box {
    padding: 10px 12px;
    border: 1px solid #86efac;
    background: linear-gradient(180deg, #f0fdf4, #fff);
    text-align: center;
    font-size: 1.05rem;
    font-weight: 900;
    color: #15803d;
  }
  .kas-saldo-box.kas-saldo--minus {
    border-color: #fca5a5;
    background: linear-gradient(180deg, #fef2f2, #fff);
    color: #b91c1c;
  }
  .kas-live-amt {
    display: block;
    margin-top: 4px;
    font-size: 0.78rem;
    font-weight: 800;
    color: #15803d;
  }
  .kas-hint-warn {
    margin: 0;
    padding: 8px 10px;
    border: 1px solid #fcd34d;
    background: linear-gradient(180deg, #fffbeb, #fff);
    color: #b45309;
    font-size: 0.78rem;
    font-weight: 750;
    line-height: 1.35;
  }
  .kas-pg-modal-grid .pg-ket-wrap {
    margin-bottom: 0;
  }
</style>
