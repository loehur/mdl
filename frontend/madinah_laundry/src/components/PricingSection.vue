<script setup>
import Button from './ui/Button.vue'

const packages = [
    { 
        name: "Basic", 
        price: "Rp 6.000", 
        unit: "/kg", 
        features: ["Cuci Kering", "Lipat Rapi", "Wangi Standar", "3 Hari Kerja"],
        recommended: false 
    },
    { 
        name: "Standard", 
        price: "Rp 8.000", 
        unit: "/kg", 
        features: ["Cuci Kering", "Setrika Uap", "Wangi Premium", "2 Hari Kerja"],
        recommended: true 
    },
    { 
        name: "Premium", 
        price: "Rp 12.000", 
        unit: "/kg", 
        features: ["Cuci Kering", "Setrika Uap Detil", "Anti Bakteri", "1 Hari / Express"],
        recommended: false 
    }
]
</script>

<template>
  <section class="pricing-section">
    <div class="container">
      <h2 class="section-title">Paket Harga</h2>
      <div class="pricing-grid">
        <div 
          v-for="(pkg, index) in packages" 
          :key="index" 
          class="pricing-card"
          :class="{ 'recommended': pkg.recommended }"
        >
            <div v-if="pkg.recommended" class="badge">Terlaris</div>
            <h3>{{ pkg.name }}</h3>
            <div class="price">
                <span class="amount">{{ pkg.price }}</span>
                <span class="unit">{{ pkg.unit }}</span>
            </div>
            <ul class="features">
                <li v-for="(feature, fIndex) in pkg.features" :key="fIndex">{{ feature }}</li>
            </ul>
            <div class="action">
                <Button :variant="pkg.recommended ? 'primary' : 'outline'" href="https://wa.me/628117686252">
                    Pilih Paket
                </Button>
            </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.pricing-section {
    padding: 100px 0;
    background-color: var(--color-bg-light);
}
.container {
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 20px;
}
.section-title {
    text-align: center;
    margin-bottom: 60px;
    font-size: 2.2rem;
    color: var(--color-text);
    font-weight: 700;
}
.pricing-grid {
    display: flex;
    justify-content: center;
    gap: 30px;
    flex-wrap: wrap;
    align-items: stretch;
}
.pricing-card {
    background: #fff;
    border-radius: 20px;
    padding: 40px 30px;
    width: 300px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
    position: relative;
    border: 2px solid transparent;
    transition: transform 0.3s;
    display: flex;
    flex-direction: column;
}
.pricing-card:hover {
    transform: translateY(-10px);
}
.pricing-card.recommended {
    border-color: var(--color-primary);
    transform: scale(1.05);
    box-shadow: 0 15px 40px rgba(30, 144, 255, 0.15);
    z-index: 1;
}
.pricing-card.recommended:hover {
    transform: scale(1.05) translateY(-10px);
}
.badge {
    position: absolute;
    top: -15px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--color-accent);
    color: #fff;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: bold;
}
.pricing-card h3 {
    font-size: 1.5rem;
    margin-bottom: 20px;
    color: #333;
}
.price {
    margin-bottom: 30px;
    color: var(--color-primary);
}
.amount {
    font-size: 2.5rem;
    font-weight: 800;
}
.unit {
    font-size: 1rem;
    color: #999;
}
.features {
    margin-bottom: 40px;
    flex-grow: 1;
}
.features li {
    margin-bottom: 15px;
    color: #666;
    border-bottom: 1px dashed #eee;
    padding-bottom: 15px;
}
.features li:last-child {
    border-bottom: none;
}
</style>
