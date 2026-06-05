/**
 * Template Developer Extension
 *
 * Cukup export default — framework yang handle registrasi.
 * Tidak perlu import registerDeveloperView.
 *
 * Dynamic import dengan cache-busting otomatis aktif saat Development Mode
 * (nx-dev-watch-path tersimpan di localStorage). Di luar Dev Mode, modul
 * diambil dari cache browser seperti biasa — tidak ada overhead tambahan.
 */

export default {
  id: 'example',           // harus sama dengan viewId di package.json
  label: 'My Extension',   // nama di tab header
  description: 'v1.0.0 · Deskripsi singkat extension Anda',
  icon: 'folder-src',      // icon di tab panel (tanpa prefix 'icon-')
  iconType: 'file',        // 'file' atau 'material'

  async render(container) {
    const bust = localStorage.getItem('nx-dev-watch-path') ? '?v=' + Date.now() : '';
    const { renderSidebar } = await import('./sidebar.js' + bust);
    renderSidebar(container);
  },

  async renderTab(container, config) {
    const bust = localStorage.getItem('nx-dev-watch-path') ? '?v=' + Date.now() : '';
    const { renderPanel } = await import('./panel.js' + bust);
    renderPanel(container, config);
  },
};
