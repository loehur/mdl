<script setup>
</script>

<template>
  <footer class="footer">
    <div class="container relative">
        <!-- CTA Banner -->
        <div class="cta-banner">
            <div class="cta-inner">
                <div class="cta-text">
                    <h2>Siap Pakaian Anda <span class="text-white">Bersih</span> & <span class="text-white">Wangi</span>?</h2>
                    <p>Jangan biarkan tumpukan cucian mengganggu hari-harimu. Serahkan pada ahlinya!</p>
                </div>
                <div class="cta-action">
                    <a href="https://wa.me/6281170706611" class="wa-btn-premium">
                        <span class="btn-icon">💬</span>
                        Hubungi Kami Sekarang
                    </a>
                </div>
            </div>
            <!-- Decorative circle -->
            <div class="cta-decor"></div>
        </div>
    </div>

    <div class="footer-main">
        <div class="container">
            <div class="footer-grid">
                <!-- Brand Section -->
                <div class="footer-col brand-col">
                    <div class="footer-logo">
                        <div class="logo-circle">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="4" y="2" width="16" height="20" rx="2" stroke="white" stroke-width="2"/>
                                <circle cx="12" cy="14" r="5" stroke="white" stroke-width="2"/>
                            </svg>
                        </div>
                        <h3>MADINAH <span>LAUNDRY</span></h3>
                    </div>
                    <p class="brand-desc">Solusi laundry profesional terpercaya di Pekanbaru. Kami menjaga kualitas pakaian Anda seperti milik kami sendiri.</p>
                    <div class="social-links">
                        <a href="#" class="social-item">IG</a>
                        <a href="#" class="social-item">FB</a>
                        <a href="#" class="social-item">WA</a>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="footer-col">
                    <h4>Kontak Kami</h4>
                    <ul class="footer-links">
                        <li><span class="icon">📍</span> Jl. Pinang No.45A, Pekanbaru</li>
                        <li><span class="icon">📞</span> 0811-7070-6611</li>
                        <li><span class="icon">📧</span> madinahlaundry@nalju.com</li>
                    </ul>
                </div>

                <!-- Operational -->
                <div class="footer-col">
                    <h4>Jam Operasional</h4>
                    <div class="hours-card">
                        <p>Senin - Minggu</p>
                        <strong>07.00 - 21.00</strong>
                        <span class="status-badge">Setiap Hari</span>
                    </div>
                </div>

                <!-- Legalities -->
                <div class="footer-col">
                    <h4>Legalitas Usaha</h4>
                    <div class="legal-box">
                        <p><strong>NIB:</strong> 1912250063232</p>
                        <p><strong>OSS:</strong> Terdaftar Resmi</p>
                        <div class="verified-footer">
                            <span class="check-footer">✓</span> Verified Business
                        </div>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; 2024 Madinah Laundry. All rights reserved.</p>
                <div class="footer-bottom-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                </div>
            </div>
        </div>
    </div>
  </footer>
</template>

<style scoped>
.footer {
    background: #f8fafc;
    padding-top: 80px;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 40px;
}

.relative { position: relative; }

.cta-banner {
    background: linear-gradient(135deg, var(--color-primary) 0%, #15803d 100%);
    border-radius: 30px;
    padding: 60px;
    color: white;
    position: relative;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(22, 163, 74, 0.4);
    z-index: 10;
    margin-bottom: -60px;
}

.cta-inner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 2;
    max-width: 1000px;
    margin: 0 auto;
}

.cta-text h2 {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 15px;
    color: var(--color-secondary);
}

.cta-text p {
    font-size: 1.2rem;
    opacity: 0.9;
}

.wa-btn-premium {
    background: white;
    color: var(--color-primary);
    padding: 18px 36px;
    border-radius: 50px;
    font-weight: 800;
    font-size: 1.1rem;
    display: inline-flex;
    align-items: center;
    gap: 12px;
    transition: all 0.3s ease;
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
}

.wa-btn-premium:hover {
    transform: translateY(-5px) scale(1.05);
    box-shadow: 0 15px 30px rgba(255,255,255,0.2);
}

.cta-decor {
    position: absolute;
    top: -50px;
    right: -50px;
    width: 250px;
    height: 250px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    z-index: 1;
}

.footer-main {
    background: #0f172a; /* Slate 900 */
    color: #94a3b8;
    padding: 130px 0 40px;
}

.footer-grid {
    display: grid;
    grid-template-columns: 1.5fr 1fr 1fr 1.2fr;
    gap: 40px;
    margin-bottom: 80px;
}

.footer-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 25px;
}

.logo-circle {
    background: var(--color-primary);
    padding: 8px;
    border-radius: 10px;
}

.footer-logo h3 {
    color: white;
    font-size: 1.4rem;
    font-weight: 800;
    letter-spacing: 1px;
}

.footer-logo h3 span {
    color: var(--color-primary);
}

.brand-desc {
    line-height: 1.7;
    margin-bottom: 30px;
}

.social-links {
    display: flex;
    gap: 15px;
}

.social-item {
    width: 40px;
    height: 40px;
    background: rgba(255,255,255,0.05);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.8rem;
    font-weight: bold;
    transition: 0.3s;
}

.social-item:hover {
    background: var(--color-primary);
    transform: translateY(-3px);
}

h4 {
    color: white;
    font-size: 1.2rem;
    font-weight: 700;
    margin-bottom: 30px;
    position: relative;
}

h4::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: -10px;
    width: 30px;
    height: 2px;
    background: var(--color-primary);
}

.footer-links {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.footer-links li {
    display: flex;
    align-items: center;
    gap: 10px;
}

.hours-card {
    background: rgba(255,255,255,0.03);
    padding: 20px;
    border-radius: 15px;
    border: 1px solid rgba(255,255,255,0.05);
}

.hours-card p {
    font-size: 0.9rem;
    margin-bottom: 5px;
}

.hours-card strong {
    display: block;
    color: white;
    font-size: 1.3rem;
    margin-bottom: 10px;
}

.status-badge {
    background: var(--color-primary);
    color: white;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
}

.legal-box {
    font-size: 0.9rem;
}

.legal-box p {
    margin-bottom: 10px;
}

.verified-footer {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #4ade80;
    font-weight: 700;
    margin-top: 10px;
    background: rgba(74, 222, 128, 0.1);
    padding: 6px 12px;
    border-radius: 6px;
}

.footer-bottom {
    border-top: 1px solid rgba(255,255,255,0.05);
    padding-top: 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
}

.footer-bottom-links {
    display: flex;
    gap: 30px;
}

.footer-bottom-links a:hover {
    color: white;
}

@media (max-width: 1024px) {
    .footer-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 768px) {
    .cta-banner {
        padding: 40px 25px;
    }
    .cta-inner {
        flex-direction: column;
        text-align: center;
        gap: 30px;
    }
    .cta-text h2 {
        font-size: 1.8rem;
    }
    .footer-grid {
        grid-template-columns: 1fr;
        gap: 40px;
        text-align: center;
    }
    .footer-logo, .social-links, h4::after {
        justify-content: center;
    }
    h4::after {
        left: 50%;
        transform: translateX(-50%);
    }
    .footer-links li {
        justify-content: center;
    }
    .footer-bottom {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
}
</style>
