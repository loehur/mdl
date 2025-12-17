<script setup>
const services = [
    { name: "Laundry Kiloan", desc: "Cuci bersih, kering, dan rapi dalam hitungan kilo." },
    { name: "Cuci Setrika", desc: "Layanan lengkap cuci hingga setrika licin." },
    { name: "Setrika Saja", desc: "Hanya butuh jasa setrika? Kami siap membantu." },
    { name: "Laundry Bedcover", desc: "Perawatan khusus untuk selimut dan bedcover besar." },
    { name: "Linen & Hotel Laundry", desc: "Solusi untuk bisnis penginapan dan hotel." },
    { name: "Karpet & Boneka", desc: "Membersihkan debu dan kotoran mendalam." }
]
</script>

<template>
  <section class="services-section">
    <div class="container">
        <h2 class="section-title">Layanan Kami</h2>
        <div class="services-list">
            <div v-for="(service, index) in services" :key="index" class="service-item">
                <div class="check-icon">✓</div>
                <div class="service-info">
                    <h3>{{ service.name }}</h3>
                    <p>{{ service.desc }}</p>
                </div>
            </div>
        </div>
    </div>
  </section>
</template>

<style scoped>
.services-section {
    padding: 100px 0;
    background-color: var(--color-white);
}
.container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 0 20px;
}
.section-title {
    text-align: center;
    margin-bottom: 60px;
    font-size: 2.2rem;
    color: var(--color-primary);
    font-weight: 700;
}
.services-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 40px;
}
.service-item {
    display: flex;
    align-items: flex-start;
}
.check-icon {
    background: var(--color-accent);
    color: #fff;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 20px;
    font-weight: bold;
    flex-shrink: 0;
}
.service-info h3 {
    margin-bottom: 8px;
    font-size: 1.2rem;
    color: #333;
}
.service-info p {
    color: #666;
    font-size: 0.95rem;
}
</style>
