  </div><!-- /console-content -->
</div><!-- /console-main -->

<script>
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('collapsed');
}

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
