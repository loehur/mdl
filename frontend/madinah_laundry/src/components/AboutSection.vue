<script setup>
</script>

<template>
  <section class="about-section">
    <div class="container">
      <div class="about-content">
        <div class="text-block">
          <h2 class="section-title">Tentang Madinah Laundry</h2>
          <p class="about-desc">
            Madinah Laundry berdiri dari keinginan untuk memberikan layanan pencucian pakaian yang tidak hanya bersih,
            tetapi juga aman dan terpercaya bagi setiap pelanggan. Kami memahami kesibukan Anda, 
            itulah mengapa kami hadir sebagai solusi laundry modern yang berorientasi pada kualitas dan kepuasan pelanggan.
          </p>
          <div class="vision-box">
            <h3>Visi Kami</h3>
            <p>Menjadi laundry terpercaya, berkualitas, dan pilihan utama keluarga.</p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.about-section {
  padding: 100px 0;
  background-color: var(--color-white);
}

.container {
  max-width: 900px;
  margin: 0 auto;
  padding: 0 40px;
  text-align: center;
}

.section-title {
  font-size: 2.5rem;
  color: var(--color-primary);
  margin-bottom: 24px;
  font-weight: 700;
}

.about-desc {
  font-size: 1.1rem;
  color: #555;
  margin-bottom: 40px;
  line-height: 1.8;
}

.vision-box {
  background-color: var(--color-bg-light);
  padding: 30px;
  border-radius: 20px;
  border: 1px solid #e0eaff;
  display: inline-block;
  max-width: 600px;
}

.vision-box h3 {
  color: var(--color-primary);
  margin-bottom: 10px;
  font-size: 1.3rem;
}

.vision-box p {
  color: #444;
  font-weight: 500;
  font-style: italic;
}
</style>
