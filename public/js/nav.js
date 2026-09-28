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
      <span>${user.name}</span>
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
