<?php
$footerLogo = function_exists('app_url') ? app_url('2.png') : '../2.png';
?>
            <style>
              /* Footer fallback: self-contained so every residence page stays intact. */
              #app-footer{display:block;width:100%;margin-top:0;position:relative;z-index:4;background:#f6faf9;border-top:1px solid #dce9e7;color:#172d39;font-family:inherit}
              #app-footer .app-footer-inner{width:min(1180px,100%);box-sizing:border-box;margin:0 auto;padding:42px 32px 22px}
              #app-footer .app-footer-grid{display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:42px;padding-bottom:28px}
              #app-footer .app-footer-logo{display:inline-flex;align-items:center;gap:10px;color:#102d39;font-size:22px;font-weight:800;text-decoration:none}
              #app-footer .app-footer-logo img{display:block;width:42px!important;height:42px!important;max-width:42px!important;max-height:42px!important;object-fit:contain!important}
              #app-footer .app-footer-brand p{max-width:410px;margin:14px 0 0;color:#607783;font-size:13px;line-height:1.7}
              #app-footer .app-footer-col h3{margin:4px 0 15px;color:#82919a;font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
              #app-footer .app-footer-links{margin:0;padding:0;list-style:none;display:grid;gap:10px}
              #app-footer .app-footer-links a{color:#284753;font-size:13px;font-weight:600;text-decoration:none}
              #app-footer .app-footer-links a:hover{color:#008b78}
              #app-footer .app-footer-bottom{display:flex;align-items:center;justify-content:space-between;gap:16px;padding-top:18px;border-top:1px solid #dce9e7;color:#7a8c95;font-size:12px}
              #app-footer .app-footer-bottom a{color:#526d76;text-decoration:none}
              @media(max-width:700px){#app-footer .app-footer-inner{padding:30px 20px 20px}#app-footer .app-footer-grid{grid-template-columns:1fr 1fr;gap:26px}#app-footer .app-footer-brand{grid-column:1/-1}#app-footer .app-footer-bottom{align-items:flex-start;flex-direction:column}}
            </style>
            <footer class="app-footer" id="app-footer">
              <div class="app-footer-inner">
                <div class="app-footer-grid">
                  <div class="app-footer-brand">
                    <a href="#" class="app-footer-logo" onclick="goHome(); return false;">
                      <img src="<?= htmlspecialchars($footerLogo, ENT_QUOTES, 'UTF-8') ?>" alt="">
                      <span>AddToMar</span>
                    </a>
                    <p>Order medicines online from trusted pharmacy partners near you. Search, reserve with GCash, and pick up when ready.</p>
                  </div>
                  <div class="app-footer-col">
                    <h3>Quick Links</h3>
                    <ul class="app-footer-links">
                      <li><a href="#" onclick="go('dashboard'); return false;">Home</a></li>
                      <li><a href="#" onclick="go('pharmacies'); return false;">Pharmacies</a></li>
                      <li><a href="#" onclick="go('locator'); return false;">Locator</a></li>
                      <li><a href="#" onclick="go('orders'); return false;">Orders</a></li>
                      <li><a href="#" onclick="go('profile'); return false;">Profile</a></li>
                    </ul>
                  </div>
                  <div class="app-footer-col">
                    <h3>Contact</h3>
                    <ul class="app-footer-links">
                      <li><a href="mailto:support@addtomar.com">support@addtomar.com</a></li>
                    </ul>
                  </div>
                </div>
                <div class="app-footer-bottom">
                  <span>&copy; 2026 AddToMar. All rights reserved.</span>
                  <a href="#" onclick="return false;">Privacy Policy</a>
                </div>
              </div>
            </footer>
