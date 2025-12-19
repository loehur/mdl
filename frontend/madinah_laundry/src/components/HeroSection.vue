<script setup>
import Button from './ui/Button.vue'
</script>

<template>
  <section class="hero-section">


    <div class="container">
      <nav class="navbar">
        <div class="logo-container">
            <div class="logo-box">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="4" y="2" width="16" height="20" rx="2" stroke="currentColor" stroke-width="2"/>
                    <circle cx="12" cy="14" r="5" stroke="currentColor" stroke-width="2"/>
                    <path d="M9 6h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>
            <span class="logo-text">MADINAH LAUNDRY</span>
        </div>
      </nav>
      
      <div class="hero-content">
        <div class="text-content">
          <div class="trust-badge">
              <span class="badge-icon">⭐</span>
              Layanan Laundry No. 1 di Pekanbaru
          </div>
          <h1 class="hero-title">Pakaian <span class="highlight">Bersih</span> & <span class="highlight-yellow">Wangi</span> Tanpa Ribet</h1>
          <p class="hero-subtitle">
            Kami menghargai waktu Anda. Biarkan kami yang mengurus tumpukan cucian dengan standar kebersihan tertinggi dan wangi yang tahan lama.
          </p>
          <div class="cta-group">
            <Button variant="white" href="https://wa.me/6281170706611" class="btn-glow">
              Order via WhatsApp
            </Button>
            <div class="stats">
                <div class="stat-item">
                    <strong>1000+</strong>
                    <span>Pelanggan</span>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-item">
                    <strong>4.9/5</strong>
                    <span>Rating</span>
                </div>
            </div>
          </div>
          
          <div class="benefits-grid">
              <div class="benefit-item">
                  <span class="benefit-icon">🚚</span>
                  <span>Pickup & Delivery</span>
              </div>
              <div class="benefit-item">
                  <span class="benefit-icon">⚡</span>
                  <span>Express 24 Jam</span>
              </div>
              <div class="benefit-item">
                  <span class="benefit-icon">🌿</span>
                  <span>Eco-Friendly Detergent</span>
              </div>
          </div>
        </div>

        <div class="visual-content">
            <div class="laundry-machine-container">
                <div class="machine-glow"></div>
                <div class="laundry-machine-illustration">
                    <div class="machine-top"></div>
                    <div class="machine-body">
                        <div class="branding">MADINAH</div>
                        <div class="door-frame">
                            <div class="door-handle"></div>
                            <div class="door-glass">
                                <div class="washing-cycle">
                                    <div class="water-level"></div>
                                    <div class="clothes-tumble">
                                        <div class="cloth c1"></div>
                                        <div class="cloth c2"></div>
                                        <div class="cloth c3"></div>
                                    </div>
                                    <div class="bubbles">
                                        <div class="bubble b1"></div>
                                        <div class="bubble b2"></div>
                                        <div class="bubble b3"></div>
                                        <div class="bubble b4"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="controls-panel">
                            <div class="dial"></div>
                            <div class="digital-display">
                                <span class="timer">00:24</span>
                            </div>
                            <div class="buttons">
                                <span></span><span></span>
                            </div>
                        </div>
                    </div>
                    <div class="machine-shadow"></div>
                </div>
            </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.hero-section {
  background: linear-gradient(135deg, var(--color-primary) 0%, #065f46 100%);
  color: var(--color-white);
  padding-bottom: 120px;
  overflow: hidden;
  position: relative;
  min-height: 90vh;
  display: flex;
  flex-direction: column;
}



.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
  position: relative;
  z-index: 2;
}

.navbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 25px 0;
}

.logo-container {
  display: flex;
  align-items: center;
  gap: 12px;
}
.logo-box {
    background: var(--color-secondary);
    color: #333;
    padding: 8px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 15px rgba(250, 204, 21, 0.4);
}
.logo-text {
  font-size: 1.4rem;
  font-weight: 800;
  letter-spacing: 1px;
}

.hero-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 60px;
  gap: 60px;
}

.text-content {
  flex: 1.2;
}

.trust-badge {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    padding: 8px 16px;
    border-radius: 50px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 24px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.hero-title {
  font-size: 4rem;
  font-weight: 800;
  line-height: 1.1;
  margin-bottom: 24px;
}
.highlight { color: var(--color-secondary); }
.highlight-yellow { color: #fde047; }

.hero-subtitle {
  font-size: 1.25rem;
  margin-bottom: 40px;
  opacity: 0.9;
  line-height: 1.6;
}

.cta-group {
    display: flex;
    align-items: center;
    gap: 30px;
    margin-bottom: 50px;
}

.btn-glow {
    box-shadow: 0 0 20px rgba(255, 255, 255, 0.3);
    transition: all 0.3s;
}
.btn-glow:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(255, 255, 255, 0.4);
}

.stats {
    display: flex;
    align-items: center;
    gap: 20px;
}
.stat-item {
    display: flex;
    flex-direction: column;
}
.stat-item strong { font-size: 1.3rem; }
.stat-item span { font-size: 0.8rem; opacity: 0.7; }
.stat-divider {
    width: 1px;
    height: 30px;
    background: rgba(255, 255, 255, 0.2);
}

.benefits-grid {
    display: flex;
    gap: 24px;
    flex-wrap: wrap;
}
.benefit-item {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(255, 255, 255, 0.05);
    padding: 10px 16px;
    border-radius: 12px;
    font-size: 0.95rem;
}

.visual-content {
  flex: 1;
  display: flex;
  justify-content: center;
  position: relative;
}

/* Enhanced Washing Machine */
.laundry-machine-container {
    position: relative;
}
.machine-glow {
    position: absolute;
    width: 120%;
    height: 120%;
    background: var(--color-secondary);
    filter: blur(60px);
    opacity: 0.2;
    top: -10%;
    left: -10%;
}

.laundry-machine-illustration {
    position: relative;
    width: 320px;
    z-index: 1;
}
.machine-top {
    height: 15px;
    background: #e5e7eb;
    border-radius: 10px 10px 0 0;
    margin: 0 10px;
}
.machine-body {
    background: #f9fafb;
    border-radius: 20px;
    padding: 30px 20px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    border: 1px solid #e5e7eb;
}
.branding {
    text-align: center;
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.7rem;
    color: #9ca3af;
    letter-spacing: 4px;
    margin-bottom: 20px;
}
.door-frame {
    width: 220px;
    height: 220px;
    background: #d1d5db;
    border-radius: 50%;
    margin: 0 auto;
    position: relative;
    padding: 12px;
    box-shadow: inset 0 4px 10px rgba(0,0,0,0.1);
}
.door-handle {
    position: absolute;
    right: 15px;
    top: 50%;
    width: 10px;
    height: 40px;
    background: #9ca3af;
    border-radius: 10px;
    transform: translateY(-50%);
    z-index: 5;
}
.door-glass {
    width: 100%;
    height: 100%;
    background: #1f2937;
    border-radius: 50%;
    overflow: hidden;
    position: relative;
    border: 6px solid rgba(255,255,255,0.1);
}
.water-level {
    position: absolute;
    bottom: -10px;
    left: -10px;
    width: 120%;
    height: 50%;
    background: rgba(16, 185, 129, 0.4);
    filter: blur(5px);
    animation: wave 4s infinite ease-in-out;
}
.clothes-tumble {
    width: 100%;
    height: 100%;
    position: relative;
    animation: spin 3s infinite linear;
}
.cloth {
    position: absolute;
    width: 60px;
    height: 60px;
    border-radius: 15px;
    opacity: 0.8;
}
.c1 { background: #3b82f6; top: 20%; left: 30%; transform: rotate(45deg); }
.c2 { background: #ef4444; bottom: 20%; right: 30%; transform: rotate(-15deg); }
.c3 { background: #facc15; top: 40%; right: 20%; transform: rotate(110deg); }

.bubbles .bubble {
    position: absolute;
    background: rgba(255, 255, 255, 0.4);
    border-radius: 50%;
    animation: bubbleUp 2s infinite ease-out;
}
.b1 { width: 15px; height: 15px; left: 20%; bottom: 10%; animation-delay: 0s; }
.b2 { width: 10px; height: 10px; left: 40%; bottom: 15%; animation-delay: 0.5s; }
.b3 { width: 20px; height: 20px; right: 30%; bottom: 5%; animation-delay: 0.2s; }

.controls-panel {
    margin-top: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0 10px;
}
.dial {
    width: 40px;
    height: 40px;
    background: #d1d5db;
    border-radius: 50%;
    border: 3px solid #9ca3af;
}
.digital-display {
    background: #111827;
    padding: 8px 15px;
    border-radius: 8px;
    border: 2px solid #374151;
}
.timer {
    color: #10b981;
    font-family: 'JetBrains Mono', monospace;
    font-weight: bold;
}
.buttons span {
    display: inline-block;
    width: 12px;
    height: 12px;
    background: #9ca3af;
    border-radius: 50%;
    margin-left: 8px;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
@keyframes wave {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-10px); }
}
@keyframes bubbleUp {
    0% { transform: translateY(0) scale(1); opacity: 0.4; }
    100% { transform: translateY(-100px) scale(0); opacity: 0; }
}

@media (max-width: 992px) {
    .hero-title { font-size: 3rem; }
    .hero-content { gap: 40px; }
}

@media (max-width: 768px) {
  .hero-section {
    padding-bottom: 60px;
    min-height: auto;
  }
  .hero-title {
    font-size: 2.2rem;
    margin-bottom: 16px;
  }
  .hero-subtitle {
    font-size: 1rem;
    margin-bottom: 30px;
  }
  .hero-content {
    flex-direction: column;
    text-align: center;
    margin-top: 20px;
    gap: 30px;
  }
  .text-content {
    padding-right: 0;
  }
  .cta-group {
      flex-direction: column;
      gap: 20px;
      margin-bottom: 30px;
  }
  .stats {
      justify-content: center;
  }
  .benefits-grid {
      justify-content: center;
      gap: 12px;
  }
  .benefit-item {
      padding: 8px 12px;
      font-size: 0.85rem;
  }
  .laundry-machine-illustration {
      width: 240px;
  }
  .door-frame {
      width: 160px;
      height: 160px;
  }
}
</style>

