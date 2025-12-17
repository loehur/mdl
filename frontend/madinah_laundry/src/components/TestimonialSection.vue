<script setup>
import Card from './ui/Card.vue'

const testimonials = [
    { name: "Siti Rahma", role: "Ibu Rumah Tangga", text: "Sangat terbantu dengan Madinah Laundry. Hasil cucian bersih, wangi, dan rapinya juara! Kurirnya juga ramah banget." },
    { name: "Budi Santoso", role: "Karyawan Swasta", text: "Paling suka sama layanan antjarnya. Pulang kerja capek, tinggal WA, baju kotor dijemput, besoknya udah wangi." },
    { name: "Andi Wijaya", role: "Mahasiswa", text: "Harganya pas di kantong mahasiswa. Kualitasnya ga kalah sama laundry hotel. Recommended banget buat anak kos." }
]
</script>

<template>
  <section class="testimonial-section">
    <div class="container">
        <h2 class="section-title">Apa Kata Mereka?</h2>
        <div class="grid">
            <Card v-for="(item, index) in testimonials" :key="index" padding="large" class="testi-card">
                <div class="quote">“</div>
                <p class="text">{{ item.text }}</p>
                <div class="author">
                    <div class="avatar-placeholder">{{ item.name.charAt(0) }}</div>
                    <div class="info">
                        <h4>{{ item.name }}</h4>
                        <span>{{ item.role }}</span>
                    </div>
                </div>
            </Card>
        </div>
    </div>
  </section>
</template>

<style scoped>
.testimonial-section {
    padding: 100px 0;
    background-color: var(--color-white);
}
.container {
    max-width: 1200px;
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
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
}
.testi-card {
    position: relative;
    background: #f9f9f9;
}
.quote {
    font-size: 4rem;
    color: #ffd700;
    font-family: serif;
    position: absolute;
    top: 10px;
    right: 20px;
    opacity: 0.3;
}
.text {
    font-size: 1.05rem;
    color: #555;
    margin-bottom: 30px;
    font-style: italic;
    line-height: 1.6;
}
.author {
    display: flex;
    align-items: center;
    margin-top: auto;
}
.avatar-placeholder {
    width: 50px;
    height: 50px;
    background: var(--color-primary);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
    margin-right: 15px;
}
.info h4 {
    margin: 0;
    color: #333;
    font-size: 1.1rem;
}
.info span {
    font-size: 0.9rem;
    color: #888;
}
</style>
