<script setup>
const services = [
    { name: "Laundry Kiloan", desc: "Cuci bersih, kering, dan rapi dalam hitungan kilo.", icon: "🧺" },
    { name: "Cuci Setrika", desc: "Layanan lengkap cuci hingga setrika licin.", icon: "👔" },
    { name: "Setrika Saja", desc: "Hanya butuh jasa setrika? Kami siap membantu.", icon: "💨" },
    { name: "Laundry Bedcover", desc: "Perawatan khusus untuk selimut dan bedcover besar.", icon: "🛌" },
    { name: "Linen & Hotel Laundry", desc: "Solusi untuk bisnis penginapan dan hotel.", icon: "🏨" },
    { name: "Karpet & Boneka", desc: "Membersihkan debu dan kotoran mendalam.", icon: "🧸" }
]
</script>

<template>
  <section class="services-section" id="services">
    <div class="container">
        <div class="section-header">
            <span class="section-badge">Layanan Terbaik</span>
            <h2 class="section-title">Apa Yang Kami <span class="highlight">Tawarkan?</span></h2>
            <p class="section-subtitle">Berbagai pilihan layanan perawatan pakaian yang disesuaikan dengan kebutuhan Anda.</p>
        </div>
        
        <div class="services-grid">
            <div v-for="(service, index) in services" :key="index" class="service-card">
                <div class="service-icon-wrapper">
                    <span class="service-icon">{{ service.icon }}</span>
                </div>
                <div class="service-content">
                    <h3>{{ service.name }}</h3>
                    <p>{{ service.desc }}</p>
                </div>
                <div class="card-arrow">→</div>
            </div>
        </div>
    </div>
  </section>
</template>

<style scoped>
.services-section {
    padding: 120px 0;
    background-color: var(--color-bg-light);
    position: relative;
    overflow: hidden;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 40px;
}

.section-header {
    text-align: center;
    max-width: 700px;
    margin: 0 auto 70px auto;
}

.section-badge {
    background: rgba(22, 163, 74, 0.1);
    color: var(--color-primary);
    padding: 6px 16px;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 700;
    display: inline-block;
    margin-bottom: 20px;
}

.section-title {
    font-size: 3rem;
    color: #1f2937;
    font-weight: 800;
    margin-bottom: 20px;
}

.section-title .highlight {
    color: var(--color-primary);
}

.section-subtitle {
    color: #6b7280;
    font-size: 1.1rem;
    line-height: 1.6;
}

.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 30px;
}

.service-card {
    background: var(--color-white);
    padding: 40px;
    border-radius: 24px;
    display: flex;
    flex-direction: column;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: 1px solid #f3f4f6;
    position: relative;
    cursor: pointer;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
}

.service-card:hover {
    transform: translateY(-10px) scale(1.02);
    box-shadow: 0 20px 40px -10px rgba(22, 163, 74, 0.1);
    border-color: var(--color-primary);
}

.service-icon-wrapper {
    width: 70px;
    height: 70px;
    background: #f0fdf4;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 25px;
    transition: all 0.3s;
}

.service-card:hover .service-icon-wrapper {
    background: var(--color-primary);
    transform: rotate(10deg);
}

.service-icon {
    font-size: 2.2rem;
}

.service-card:hover .service-icon {
    filter: brightness(0) invert(1);
    content: ""; /* If using SVG, but with emoji we just scale */
    transform: scale(1.1);
}

.service-content h3 {
    font-size: 1.5rem;
    color: #111827;
    margin-bottom: 12px;
    font-weight: 700;
}

.service-content p {
    color: #4b5563;
    line-height: 1.6;
    margin-bottom: 0;
}

.card-arrow {
    position: absolute;
    bottom: 30px;
    right: 30px;
    font-size: 1.5rem;
    color: var(--color-primary);
    opacity: 0;
    transition: all 0.3s;
    transform: translateX(-10px);
}

.service-card:hover .card-arrow {
    opacity: 1;
    transform: translateX(0);
}

@media (max-width: 768px) {
    .services-section {
        padding: 80px 0;
    }
    .section-title {
        font-size: 2.2rem;
    }
    .services-grid {
        grid-template-columns: 1fr;
    }
    .service-card {
        padding: 30px;
    }
}
</style>
