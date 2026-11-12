// Maskiert Sonderzeichen, bevor Nutzertext per innerHTML eingefügt wird (Schutz vor XSS).
function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[c]));
}

// Wird auf jeder Seite eingebunden. Prüft per /api/me.php, ob eine
// gültige Session existiert, und füllt den Platzhalter <span id="nav-auth">
// entsprechend — entweder mit "Anmelden" oder mit Name + "Abmelden".

async function renderNavAuth() {
  const slot = document.getElementById('nav-auth');
  if (!slot) return;

  try {
    const response = await fetch('/api/me.php');

    if (!response.ok) {
      slot.innerHTML = '<a href="/login.html" class="btn btn-outline">→ Anmelden</a>';
      return;
    }

    const user = await response.json();
    slot.innerHTML = `
      span>${escapeHtml(user.name)}</span>
      <button id="logout-btn" class="btn btn-outline">Abmelden</button>
    `;

    document.getElementById('logout-btn').addEventListener('click', async () => {
      await fetch('/api/auth/logout.php', { method: 'POST' });
      window.location.href = '/index.html';
    });
  } catch (e) {
    slot.innerHTML = '<a href="/login.html" class="btn btn-outline">→ Anmelden</a>';
  }
}

renderNavAuth();

// Footer mit Pflichtlinks (Impressum, Datenschutz) auf jeder Seite
(function renderFooter() {
  const footer = document.createElement('footer');
  footer.style.cssText = 'text-align:center; padding:24px 16px; font-size:0.85rem;';
  footer.innerHTML = '<a href="/impressum.html">Impressum</a> · <a href="/datenschutz.html">Datenschutz</a>';
  document.body.appendChild(footer);
})();
