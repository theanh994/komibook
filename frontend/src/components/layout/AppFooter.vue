<template>
  <footer class="app-footer">
    <div class="footer-wave">
      <svg viewBox="0 0 1440 120" preserveAspectRatio="none">
        <path d="M0,40 C360,100 720,0 1080,60 C1260,90 1380,40 1440,50 L1440,120 L0,120 Z" fill="currentColor" />
      </svg>
    </div>

    <div class="footer-content">
      <div class="footer-grid">
        
        <!-- Column 1: Về chúng tôi -->
        <div class="footer-col">
          <div class="footer-brand">
            <div class="footer-logo">
              <i class="pi pi-book"></i>
            </div>
            <div>
              <span class="brand-name">Komi<span class="brand-accent">Book</span></span>
              <span class="brand-tagline">Thư viện của bạn</span>
            </div>
          </div>
          <p class="footer-desc">
            KomiBook là nền tảng thương mại sách đa gian hàng, kết nối độc giả với hàng ngàn cuốn sách giấy và E-book chất lượng từ các nhà bán uy tín.
          </p>
          <div class="footer-stats">
            <div class="stat-item">
              <i class="pi pi-book"></i>
              <span>10,000+ sách</span>
            </div>
            <div class="stat-item">
              <i class="pi pi-shop"></i>
              <span>500+ gian hàng</span>
            </div>
          </div>
        </div>

        <!-- Column 2: Hỗ trợ khách hàng -->
        <div class="footer-col">
          <h4 class="footer-heading">Hỗ trợ khách hàng</h4>
          <ul class="footer-links">
            <li><a href="#"><i class="pi pi-question-circle"></i> Trung tâm trợ giúp</a></li>
            <li><a href="#"><i class="pi pi-truck"></i> Theo dõi đơn hàng</a></li>
            <li><a href="#"><i class="pi pi-replay"></i> Đổi trả & Hoàn tiền</a></li>
            <li><a href="#"><i class="pi pi-headphones"></i> Liên hệ hỗ trợ</a></li>
            <li><a href="#"><i class="pi pi-comments"></i> FAQ - Câu hỏi thường gặp</a></li>
          </ul>
        </div>

        <!-- Column 3: Chính sách -->
        <div class="footer-col">
          <h4 class="footer-heading">Chính sách</h4>
          <ul class="footer-links">
            <li><a href="#"><i class="pi pi-shield"></i> Chính sách bảo mật</a></li>
            <li><a href="#"><i class="pi pi-file"></i> Điều khoản sử dụng</a></li>
            <li><a href="#"><i class="pi pi-credit-card"></i> Chính sách thanh toán</a></li>
            <li><a href="#"><i class="pi pi-send"></i> Chính sách vận chuyển</a></li>
            <li><a href="#"><i class="pi pi-verified"></i> Chính sách bảo hành</a></li>
          </ul>
        </div>

        <!-- Column 4: Kết nối -->
        <div class="footer-col">
          <h4 class="footer-heading">Kết nối với chúng tôi</h4>
          <p class="connect-desc">Theo dõi KomiBook để nhận tin tức và khuyến mãi mới nhất!</p>
          <div class="social-links">
            <a href="#" class="social-btn social-facebook" aria-label="Facebook">
              <i class="pi pi-facebook"></i>
            </a>
            <a href="#" class="social-btn social-twitter" aria-label="Twitter">
              <i class="pi pi-twitter"></i>
            </a>
            <a href="#" class="social-btn social-instagram" aria-label="Instagram">
              <i class="pi pi-instagram"></i>
            </a>
            <a href="#" class="social-btn social-youtube" aria-label="YouTube">
              <i class="pi pi-youtube"></i>
            </a>
          </div>
          <div class="contact-info">
            <div class="contact-item">
              <i class="pi pi-envelope"></i>
              <span>support@komibook.vn</span>
            </div>
            <div class="contact-item">
              <i class="pi pi-phone"></i>
              <span>1900 xxxx xx</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom bar -->
      <div class="footer-bottom">
        <p class="copyright">&copy; {{ currentYear }} KomiBook. Tất cả quyền được bảo lưu.</p>
        <div class="payment-methods">
          <span class="payment-label">Phương thức thanh toán:</span>
          <div class="payment-icons">
            <span class="payment-icon">💳 VISA</span>
            <span class="payment-icon">💳 MasterCard</span>
            <span class="payment-icon">🏦 ATM</span>
            <span class="payment-icon">💰 COD</span>
          </div>
        </div>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { computed } from 'vue'

const currentYear = computed(() => new Date().getFullYear())
</script>

<style scoped>
.app-footer {
  position: relative;
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
  color: #cbd5e1;
  margin-top: auto;
}

.footer-wave {
  position: relative;
  top: -1px;
  color: #f8fafc;
  line-height: 0;
}
.footer-wave svg {
  width: 100%;
  height: 60px;
}

.footer-content {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 24px 32px;
}

.footer-grid {
  display: grid;
  grid-template-columns: 1.5fr 1fr 1fr 1.2fr;
  gap: 40px;
  padding-bottom: 40px;
  border-bottom: 1px solid rgba(148, 163, 184, 0.15);
}

/* ─── Brand Column ─── */
.footer-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}
.footer-logo {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 20px;
  box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
}
.brand-name {
  display: block;
  font-size: 20px;
  font-weight: 800;
  color: #f1f5f9;
  letter-spacing: -0.02em;
}
.brand-accent {
  color: #818cf8;
}
.brand-tagline {
  display: block;
  font-size: 10px;
  text-transform: uppercase;
  letter-spacing: 0.15em;
  color: #64748b;
  font-weight: 600;
}
.footer-desc {
  font-size: 13px;
  line-height: 1.7;
  color: #94a3b8;
  margin: 0 0 16px;
}
.footer-stats {
  display: flex;
  gap: 16px;
}
.stat-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  color: #818cf8;
  background: rgba(99, 102, 241, 0.1);
  padding: 6px 12px;
  border-radius: 8px;
  border: 1px solid rgba(99, 102, 241, 0.15);
}
.stat-item i {
  font-size: 12px;
}

/* ─── Column Headings & Links ─── */
.footer-heading {
  font-size: 14px;
  font-weight: 700;
  color: #f1f5f9;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin: 0 0 20px;
  position: relative;
  padding-bottom: 10px;
}
.footer-heading::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  width: 32px;
  height: 3px;
  background: linear-gradient(90deg, #6366f1, #8b5cf6);
  border-radius: 2px;
}
.footer-links {
  list-style: none;
  padding: 0;
  margin: 0;
}
.footer-links li {
  margin-bottom: 10px;
}
.footer-links a {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #94a3b8;
  text-decoration: none;
  transition: all 0.2s ease;
  padding: 4px 0;
}
.footer-links a i {
  font-size: 12px;
  color: #64748b;
  transition: color 0.2s ease;
}
.footer-links a:hover {
  color: #e2e8f0;
  transform: translateX(4px);
}
.footer-links a:hover i {
  color: #818cf8;
}

/* ─── Social / Connect ─── */
.connect-desc {
  font-size: 13px;
  color: #94a3b8;
  margin: 0 0 16px;
  line-height: 1.6;
}
.social-links {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
}
.social-btn {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  color: white;
  text-decoration: none;
  transition: all 0.3s ease;
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.social-btn:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 20px rgba(0,0,0,0.3);
}
.social-facebook { background: linear-gradient(135deg, #1877f2, #0d65d9); }
.social-twitter { background: linear-gradient(135deg, #1da1f2, #0c85d0); }
.social-instagram { background: linear-gradient(135deg, #e1306c, #c13584, #833ab4); }
.social-youtube { background: linear-gradient(135deg, #ff0000, #cc0000); }

.contact-info {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.contact-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #94a3b8;
}
.contact-item i {
  font-size: 12px;
  color: #818cf8;
}

/* ─── Bottom Bar ─── */
.footer-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-top: 24px;
  gap: 16px;
  flex-wrap: wrap;
}
.copyright {
  font-size: 12px;
  color: #64748b;
  margin: 0;
}
.payment-methods {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.payment-label {
  font-size: 11px;
  color: #64748b;
  font-weight: 500;
}
.payment-icons {
  display: flex;
  gap: 6px;
}
.payment-icon {
  font-size: 11px;
  color: #94a3b8;
  background: rgba(255,255,255,0.05);
  padding: 4px 8px;
  border-radius: 6px;
  border: 1px solid rgba(255,255,255,0.08);
}

/* ─── Responsive ─── */
@media (max-width: 1024px) {
  .footer-grid {
    grid-template-columns: 1fr 1fr;
    gap: 32px;
  }
}
@media (max-width: 640px) {
  .footer-grid {
    grid-template-columns: 1fr;
    gap: 28px;
  }
  .footer-bottom {
    flex-direction: column;
    text-align: center;
  }
  .payment-methods {
    justify-content: center;
  }
  .footer-stats {
    flex-wrap: wrap;
  }
}
</style>
