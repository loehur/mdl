<div id="dlv-root" class="px-1 mt-2">
  <style>
    #dlv-root {
      --dlv-ink: #0f172a;
      --dlv-muted: #1e293b;
      --dlv-line: #cbd5e1;
      --dlv-blue: #2563eb;
      --dlv-blue-deep: #1d4ed8;
      --dlv-yellow: #f59e0b;
      --dlv-yellow-deep: #d97706;
      --dlv-radius: 0;
      --dlv-border: 1px;
      width: 100%;
      margin: 0 0 24px;
      font-family: 'fontku', 'Segoe UI', sans-serif;
      color: var(--dlv-ink);
    }
    #dlv-root,
    #dlv-root .dlv-panel,
    #dlv-root .dlv-icon {
      border-radius: 0 !important;
    }
    #dlv-root .dlv-panel {
      min-width: 0;
      height: 100%;
      display: flex;
      flex-direction: column;
      background: #fff;
      border: var(--dlv-border) solid var(--dlv-line);
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    }
    #dlv-root .dlv-panel--customer {
      background: linear-gradient(180deg, #eff6ff, #fff);
      border-color: #93c5fd;
    }
    #dlv-root .dlv-panel--cabang {
      background: linear-gradient(180deg, #fffbeb, #fff);
      border-color: #fcd34d;
    }
    #dlv-root .dlv-head {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px 14px;
      color: #fff;
      font-weight: 900;
      letter-spacing: -0.02em;
      text-shadow: 0 1px 0 rgba(0, 0, 0, 0.18);
    }
    #dlv-root .dlv-panel--customer .dlv-head {
      background: linear-gradient(105deg, #1d4ed8 0%, #2563eb 100%);
    }
    #dlv-root .dlv-panel--cabang .dlv-head {
      background: linear-gradient(105deg, #d97706 0%, #f59e0b 100%);
    }
    #dlv-root .dlv-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 30px;
      height: 30px;
      flex-shrink: 0;
      background: rgba(255, 255, 255, 0.22);
      border: 1px solid rgba(255, 255, 255, 0.35);
      color: #fff;
      font-size: 0.9rem;
    }
    #dlv-root .dlv-head h2 {
      margin: 0;
      font-size: 0.95rem;
      font-weight: 900;
      line-height: 1.2;
    }
    #dlv-root .dlv-head small {
      display: block;
      margin-top: 2px;
      font-size: 0.72rem;
      font-weight: 700;
      opacity: 0.92;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    #dlv-root .dlv-body {
      flex: 1;
      padding: 14px;
      min-height: 220px;
    }
    #dlv-root .dlv-empty {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 8px;
      min-height: 180px;
      padding: 18px 12px;
      border: 1px dashed #94a3b8;
      background: rgba(255, 255, 255, 0.72);
      text-align: center;
    }
    #dlv-root .dlv-empty i {
      font-size: 1.75rem;
      color: var(--dlv-muted);
    }
    #dlv-root .dlv-panel--customer .dlv-empty i {
      color: var(--dlv-blue);
    }
    #dlv-root .dlv-panel--cabang .dlv-empty i {
      color: var(--dlv-yellow-deep);
    }
    #dlv-root .dlv-empty strong {
      font-size: 0.88rem;
      font-weight: 900;
      color: var(--dlv-ink);
    }
    #dlv-root .dlv-empty span {
      font-size: 0.78rem;
      font-weight: 750;
      color: var(--dlv-muted);
      max-width: 260px;
    }
  </style>

  <div class="row g-2">
    <div class="col-12 col-md-6">
      <section class="dlv-panel dlv-panel--customer" aria-label="Delivery Customer">
        <header class="dlv-head">
          <span class="dlv-icon" aria-hidden="true"><i class="fas fa-user"></i></span>
          <div>
            <h2>Customer</h2>
            <small>Order delivery pelanggan</small>
          </div>
        </header>
        <div class="dlv-body">
          <div class="dlv-empty">
            <i class="fas fa-motorcycle" aria-hidden="true"></i>
            <strong>Belum ada order delivery</strong>
            <span>Order delivery customer akan tampil di sini.</span>
          </div>
        </div>
      </section>
    </div>

    <div class="col-12 col-md-6">
      <section class="dlv-panel dlv-panel--cabang" aria-label="Delivery Cabang">
        <header class="dlv-head">
          <span class="dlv-icon" aria-hidden="true"><i class="fas fa-store"></i></span>
          <div>
            <h2>Cabang</h2>
            <small>Order delivery antar cabang</small>
          </div>
        </header>
        <div class="dlv-body">
          <div class="dlv-empty">
            <i class="fas fa-truck" aria-hidden="true"></i>
            <strong>Belum ada order delivery</strong>
            <span>Order delivery cabang akan tampil di sini.</span>
          </div>
        </div>
      </section>
    </div>
  </div>
</div>
