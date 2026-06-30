<script setup>
import Card from './ui/Card.vue'

const usps = [
  { title: "Pickup & Delivery Service", icon: "🚚", desc: "Layanan antar jemput tersedia untuk area sekitar." },
  { title: "Wangi Tahan Lama", icon: "✨", desc: "Menggunakan pewangi premium yang awet berhari-hari." },
  { title: "Pengerjaan Cepat", icon: "⚡", desc: "Layanan ekspres tersedia untuk kebutuhan mendesak." },
  { title: "Harga Jujur", icon: "🏷️", desc: "Transparan, tanpa biaya tersembunyi. Murah & berkualitas." },
  { title: "Jaminan Aman", icon: "🛡️", desc: "Proses pencucian hati-hati, pakaian Anda aman bersama kami." },
  { title: "Ramah Lingkungan", icon: "🌱", desc: "Menggunakan deterjen yang aman bagi pakaian dan lingkungan." }
]
</script>

<template>
  <section class="usp-section">
    <div class="container">
        <h2 class="section-title">Mengapa Memilih Kami?</h2>
        <div class="grid">
            <Card v-for="(usp, index) in usps" :key="index" :hover="true" class="usp-card">
                <div class="icon">{{ usp.icon }}</div>
                <h3>{{ usp.title }}</h3>
                <p>{{ usp.desc }}</p>
            </Card>
        </div>
    </div>
  </section>
</template>

<style scoped>
.usp-section {
    padding: 80px 0;
    background-color: var(--color-bg-light);
}
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 40px;
}
.section-title {
    text-align: center;
    font-size: 2.2rem;
    color: var(--color-text);
    margin-bottom: 50px;
    font-weight: 700;
}
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
}
.usp-card {
    text-align: center;
    height: 100%;
}
.icon {
    font-size: 3rem;
    margin-bottom: 20px;
    background: #fff;
    width: 80px;
    height: 80px;
    line-height: 80px;
    border-radius: 50%;
    margin-left: auto;
    margin-right: auto;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
}
h3 {
    margin-bottom: 10px;
    color: var(--color-primary);
    font-weight: 600;
}
p {
    color: #666;
    font-size: 0.95rem;
}
</style>
