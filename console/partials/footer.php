  </div><!-- /console-content -->
</div><!-- /console-main -->

<!-- Mobile overlay backdrop -->
<div class="sidebar-backdrop" id="sidebar-backdrop" onclick="closeSidebar()"></div>

<script>
const sidebar  = document.getElementById('sidebar');
const backdrop = document.getElementById('sidebar-backdrop');

function isMobile() { return window.innerWidth < 768; }

function toggleSidebar() {
  if (isMobile()) {
    sidebar.classList.toggle('open');
    backdrop.classList.toggle('active');
  } else {
    sidebar.classList.toggle('collapsed');
  }
}

function closeSidebar() {
  sidebar.classList.remove('open');
  backdrop.classList.remove('active');
}

// Close on resize to desktop
window.addEventListener('resize', () => {
  if (!isMobile()) {
    closeSidebar();
    sidebar.classList.remove('collapsed');
  }
});

// Swipe to close on mobile
let touchStartX = 0;
document.addEventListener('touchstart', e => { touchStartX = e.changedTouches[0].clientX; }, { passive: true });
document.addEventListener('touchend', e => {
  if (isMobile() && sidebar.classList.contains('open')) {
    const dx = e.changedTouches[0].clientX - touchStartX;
    if (dx < -60) closeSidebar();
  }
}, { passive: true });

// Update topbar clock
function updateClock() {
  const now = new Date();
  const t = now.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit', second:'2-digit' });
  const d = now.toLocaleDateString('id-ID', { weekday:'short', day:'numeric', month:'short' });
  const el = document.getElementById('topbar-time');
  if (el) el.textContent = `${d}, ${t}`;
}
updateClock();
setInterval(updateClock, 1000);
</script>
</body>
</html>
