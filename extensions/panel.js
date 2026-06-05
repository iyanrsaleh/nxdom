export function renderPanel(container, config) {
  const contentType = config?.contentType || 'home';

  switch (contentType) {
    case 'settings':
      renderSettings(container);
      break;
    default:
      renderHome(container);
      break;
  }
}

function renderHome(container) {
  container.innerHTML = `
    <div style="padding: 24px; max-width: 800px; margin: 0 auto;">
      <h2 style="margin: 0 0 8px 0; font-size: 22px; font-weight: 600;">My Extension</h2>
      <p style="margin: 0 0 24px 0; color: var(--beranda-dv-text-muted, #999);">
        Selamat datang di extension Anda. Edit <code>panel.js</code> untuk mengubah konten ini.
      </p>

      <div style="padding: 16px; background: var(--beranda-dv-bg, #1e1e1e); border: 1px solid var(--beranda-dv-header-border, #2d2d30); border-radius: 6px;">
        <p style="margin: 0; font-size: 13px; color: var(--beranda-dv-text-muted, #999);">
          Render apapun di sini: form, tabel, chart, editor, atau konten lainnya.
        </p>
      </div>
    </div>
  `;
}

function renderSettings(container) {
  container.innerHTML = `
    <div style="padding: 24px; max-width: 800px; margin: 0 auto;">
      <h2 style="margin: 0 0 8px 0; font-size: 22px; font-weight: 600;">Settings</h2>
      <p style="margin: 0 0 24px 0; color: var(--beranda-dv-text-muted, #999);">
        Konfigurasi extension Anda.
      </p>

      <div style="padding: 16px; background: var(--beranda-dv-bg, #1e1e1e); border: 1px solid var(--beranda-dv-header-border, #2d2d30); border-radius: 6px;">
        <p style="margin: 0; font-size: 13px; color: var(--beranda-dv-text-muted, #999);">
          Tambahkan form settings di sini. Gunakan <code>setDeveloperData</code> dan
          <code>getDeveloperData</code> dari <code>../extensions.js</code> untuk menyimpan data.
        </p>
      </div>
    </div>
  `;
}
