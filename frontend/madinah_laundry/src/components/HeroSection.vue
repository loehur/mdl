<script setup>
import Button from './ui/Button.vue'
</script>

<template>
  <section class="hero-section">
    <div class="container">
      <nav class="navbar">
        <div class="logo-container">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="logo-icon">
                <rect x="4" y="2" width="16" height="20" rx="2" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="12" cy="14" r="5" stroke="white" stroke-width="2"/>
                <path d="M9 6h6" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M12 11.5a2.5 2.5 0 0 1 2.5 2.5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span class="logo-text">MADINAH LAUNDRY</span>
        </div>
        <div class="nav-cta">
             <!-- Placeholder for nav items if needed -->
        </div>
      </nav>
      
      <div class="hero-content">
        <div class="text-content">
          <h1 class="hero-title">Laundry Bersih, Cepat & Terpercaya</h1>
          <p class="hero-subtitle">
            Solusi pakaian bersih tanpa ribet. Kami merawat pakaian Anda dengan penuh perhatian, 
            sehingga Anda bisa merasa tenang dan nyaman.
          </p>
          <div class="cta-group">
            <Button variant="white" href="https://wa.me/628117686252">
              Order via WhatsApp
            </Button>
          </div>
        </div>
        <div class="visual-content">
            <!-- Illustrated visualization using CSS/HTML -->
            <div class="laundry-machine-illustration">
                <div class="machine-body">
                    <div class="door-frame">
                        <div class="door-glass">
                            <div class="water-level">
                                <div class="bubble b1"></div>
                                <div class="bubble b2"></div>
                                <div class="bubble b3"></div>
                            </div>
                            <div class="clothes"></div>
                        </div>
                    </div>
                    <div class="controls">
                        <div class="dial"></div>
                        <div class="display">00:45</div>
                    </div>
                </div>
            </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.hero-section {
  background: linear-gradient(135deg, var(--color-primary), #4facfe);
  color: var(--color-white);
  padding-bottom: 80px;
  overflow: hidden;
  position: relative;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 20px;
}

.navbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 30px 0;
}

.logo-container {
  display: flex;
  align-items: center;
  gap: 10px;
}

.logo-text {
  font-size: 1.5rem;
  font-weight: 800;
  letter-spacing: 1px;
}

.logo-icon {
    width: 32px;
    height: 32px;
}

.hero-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 40px;
  flex-wrap: wrap;
}

.text-content {
  flex: 1;
  min-width: 300px;
  padding-right: 40px;
}

.hero-title {
  font-size: 3.5rem;
  font-weight: 800;
  line-height: 1.2;
  margin-bottom: 20px;
}

.hero-subtitle {
  font-size: 1.125rem;
  margin-bottom: 40px;
  opacity: 0.9;
  max-width: 500px;
}

.visual-content {
  flex: 1;
  min-width: 300px;
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 40px 0;
}

/* Abstract Washing Machine Illustration */
.laundry-machine-illustration {
    position: relative;
}
.machine-body {
    width: 280px;
    height: 340px;
    background: #f0f0f0;
    border-radius: 30px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.2);
    position: relative;
    padding: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.door-frame {
    width: 220px;
    height: 220px;
    border-radius: 50%;
    background: #e0e0e0;
    display: flex;
    justify-content: center;
    align-items: center;
    border: 5px solid #d0d0d0;
    margin-top: 20px;
}
.door-glass {
    width: 180px;
    height: 180px;
    border-radius: 50%;
    background: #2b3a42;
    position: relative;
    overflow: hidden;
    border: 8px solid rgba(255,255,255,0.2);
}
.water-level {
    position: absolute;
    bottom: 0;
    width: 100%;
    height: 40%;
    background: rgba(30, 144, 255, 0.6);
    animation: wave 3s infinite ease-in-out;
}
.controls {
    display: flex;
    width: 100%;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding: 0 20px;
}
.display {
    background: #333;
    color: #4CAF50;
    padding: 5px 10px;
    border-radius: 5px;
    font-family: monospace;
    font-size: 14px;
}
.dial {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #ccc;
    border: 2px solid #bbb;
}

@keyframes wave {
    0%, 100% { height: 40%; }
    50% { height: 45%; }
}

@media (max-width: 768px) {
  .hero-title {
    font-size: 2.5rem;
  }
  .hero-content {
    flex-direction: column-reverse;
    text-align: center;
  }
  .text-content {
    padding-right: 0;
    margin-top: 40px;
  }
  .hero-subtitle {
     margin: 0 auto 30px auto;
  }
}
</style>
