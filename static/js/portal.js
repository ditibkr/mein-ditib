/**
 * SpendenPortal – Gemeinsames JavaScript
 * Alle Seiten-übergreifenden Funktionen
 */

// Sidebar-Zustand persistent speichern
document.addEventListener('DOMContentLoaded', () => {
  if (localStorage.getItem('sb_collapsed') === '1') {
    document.getElementById('sb')?.classList.add('collapsed');
    document.getElementById('mn')?.classList.add('collapsed');
  }
});
