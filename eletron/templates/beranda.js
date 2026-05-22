// Export function untuk route 'home'
export async function beranda(page, route) {
  route.register(page, async (routeName, container, routeMeta = {
    title: "Beranda | App",
    description: "Halaman beranda.",
  }, style, nav = {}) => {
    route.routeMetaByRoute.set(page, routeMeta);
      container.innerHTML = `
        <div class="main-splash">
          <img
            class="main-splash__icon"
            src="/assets/images/logo.png"
            alt=""
          />
          <div class="main-splash__headlines">
            <h1 class="main-splash__welcome">Nxdom Framework</h1>
          </div>
          <p class="main-splash__lead">
         Nusantara eXtreme Development Object Model
          </p>
        </div>
        <p class="site-header__hint">Contoh tautan:</p>
          <nav class="site-header__nav" aria-label="Navigasi utama">
            <ul class="site-header__links">
              <li><a href="/beranda" id="nav-home">Beranda</a></li>
              <li><a href="/about" id="nav-about">About</a></li>
              <li><a href="/blog" id="nav-blog">Blog</a></li>
            </ul>
          </nav>
        `;
  });
}
