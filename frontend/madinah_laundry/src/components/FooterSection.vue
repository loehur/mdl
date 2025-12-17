<script setup>
</script>

<template>
  <footer class="footer">
    <div class="cta-banner">
        <h2>Siap Pakaian Anda Bersih Hari Ini?</h2>
        <p>Jangan biarkan tumpukan cucian mengganggu hari-harimu. Serahkan pada Madinah Laundry!</p>
        <a href="https://wa.me/628117686252" class="wa-btn">
            Hubungi Kami Sekarang
        </a>
    </div>
    <div class="footer-content">
        <div class="container">
            <div class="footer-grid">
                <div class="brand">
                    <div class="footer-logo">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="4" y="2" width="16" height="20" rx="2" stroke="var(--color-primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="12" cy="14" r="5" stroke="var(--color-primary)" stroke-width="2"/>
                            <path d="M9 6h6" stroke="var(--color-primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M12 11.5a2.5 2.5 0 0 1 2.5 2.5" stroke="var(--color-primary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <h3>MADINAH LAUNDRY</h3>
                    </div>
                    <p>Pakaian bersih, wangi, dan rapi.</p>
                    <p>Jalan Pinang, No. 45A Kota Pekanbaru</p>
                </div>
                <div class="contact">
                    <h4>Hubungi Kami</h4>
                    <p>📍 Area Layanan: Seluruh Kota Pekanbaru</p>
                    <p>📞 WhatsApp: 0811-7686-252</p>
                </div>
                <div class="hours">
                    <h4>Jam Operasional</h4>
                    <p>Senin - Sabtu: 08.00 - 20.00</p>
                    <p>Minggu: 09.00 - 17.00</p>
                </div>
            </div>
            <div class="copyright">
                &copy; 2024 Madinah Laundry. All rights reserved.
            </div>
        </div>
    </div>
  </footer>
</template>

<style scoped>
.footer {
    position: relative;
    padding-top: 100px; /* Space for banner overlap */
    background: #f8f8f8;
}
.cta-banner {
    background: linear-gradient(135deg, var(--color-primary), #4facfe);
    color: #fff;
    text-align: center;
    padding: 50px 20px;
    border-radius: 20px;
    max-width: 900px;
    margin: 0 auto;
    position: relative;
    top: -50px;
    box-shadow: 0 20px 50px rgba(30, 144, 255, 0.3);
}
.cta-banner h2 {
    font-size: 2rem;
    margin-bottom: 15px;
}
.cta-banner p {
    font-size: 1.1rem;
    margin-bottom: 30px;
    opacity: 0.9;
}
.wa-btn {
    display: inline-block;
    background: #fff;
    color: var(--color-primary);
    padding: 15px 30px;
    border-radius: 50px;
    font-weight: bold;
    font-size: 1.1rem;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
.wa-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}
.footer-content {
    background: #2b3a42;
    color: #fff;
    padding: 80px 0 30px;
    margin-top: -100px; /* Pull up to hide space behind banner */
    padding-top: 140px; /* Push content down below banner */
}
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}
.footer-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 40px;
    margin-bottom: 50px;
}
.footer-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}
.footer-logo h3 {
    margin: 0;
    font-size: 1.5rem;
    color: var(--color-primary);
}
.brand p {
    color: #aaa;
    line-height: 1.6;
}
h4 {
    font-size: 1.2rem;
    margin-bottom: 20px;
    border-bottom: 2px solid var(--color-primary);
    display: inline-block;
    padding-bottom: 5px;
}
.contact p, .hours p {
    margin-bottom: 10px;
    color: #ddd;
}
.copyright {
    text-align: center;
    color: #777;
    border-top: 1px solid #444;
    padding-top: 30px;
    font-size: 0.9rem;
}
</style>
