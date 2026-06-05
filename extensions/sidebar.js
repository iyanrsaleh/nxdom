export function renderSidebar(container) {
  container.innerHTML = `
    <div class="beranda-packages-view">
      <div class="beranda-packages-header">
        <p class="beranda-sidebar-view__title">My Extension</p>
      </div>

      <div class="beranda-packages-installed__list">
        <button type="button" class="beranda-packages-installed__item" data-action="home">
          <span class="icon icon-folder-src beranda-packages-installed__icon" aria-hidden="true"></span>
          <span class="beranda-packages-installed__meta">
            <span class="beranda-packages-installed__title">Home</span>
            <span class="beranda-packages-installed__desc">Halaman utama extension</span>
          </span>
        </button>

        <button type="button" class="beranda-packages-installed__item" data-action="settings">
          <span class="icon icon-settings beranda-packages-installed__icon" aria-hidden="true"></span>
          <span class="beranda-packages-installed__meta">
            <span class="beranda-packages-installed__title">Settings</span>
            <span class="beranda-packages-installed__desc">Konfigurasi extension</span>
          </span>
        </button>
      </div>

      <p class="beranda-sidebar-view__hint" style="padding: 8px 12px; margin-top: 8px;">
        Edit <code>sidebar.js</code> untuk mengubah menu ini.
      </p>
    </div>
  `;

  container.addEventListener('click', (ev) => {
    const btn = ev.target.closest('[data-action]');
    if (!btn) return;

    container.querySelectorAll('[data-action]').forEach((el) => el.classList.remove('is-active'));
    btn.classList.add('is-active');

    window.dispatchEvent(new CustomEvent('beranda:open-developer-tab', {
      detail: {
        viewId: 'example',
        contentType: btn.dataset.action,
      },
    }));
  });
}
