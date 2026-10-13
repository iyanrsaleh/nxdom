// --- i18n Dictionary ---
const I18N = {
  id: {
    "meta.title": "NXDOM — Nusantara eXtreme Development Object Model",
    "meta.desc": "Organisasi pengembangan perangkat lunak open source dari Nusantara. Build Open. Develop Extreme. Empower Nusantara.",
    "nav.about": "Tentang",
    "nav.divisions": "Divisi",
    "nav.principles": "Prinsip",
    "nav.ecosystem": "Ekosistem",
    "nav.sponsor": "Dukung Kami",
    "hero.badge": "Organisasi Open Source Nusantara",
    "hero.desc": "Kami adalah komunitas pengembang teknologi terbuka yang inovatif, mandiri, dan berdaya saing global. Berfokus pada pengembangan sistem, arsitektur perangkat lunak, dan teknologi digital fundamental.",
    "hero.cta1": "Lihat Ekosistem",
    "hero.cta2": "Misi Organisasi",
    "vision.title": "Visi Kami",
    "vision.desc": "Menjadi komunitas pengembang teknologi terbuka yang inovatif, mandiri, dan berdaya saing global dari Nusantara.",
    "mission.title": "Misi Utama",
    "mission.1": "Mengembangkan perangkat lunak <em>open source</em> yang dapat dipelajari, digunakan, dan dikembangkan bersama.",
    "mission.2": "Membangun <em>framework</em> dan sistem modular yang kuat, fleksibel, dan dapat diperluas.",
    "mission.3": "Mendorong kolaborasi antara programmer, developer, administrator sistem, dan peneliti.",
    "mission.4": "Mengembangkan standar serta dokumentasi teknis yang terbuka.",
    "mission.5": "Membangun ekosistem teknologi Nusantara yang berkontribusi kepada komunitas global.",
    "div.title": "Struktur &amp; Divisi",
    "div.sub": "Fokus pengembangan dan riset di berbagai lapisan arsitektur teknologi.",
    "div.core": "Fokus pada arsitektur inti dan perancangan model objek fundamental yang menjadi basis sistem.",
    "div.systems": "Membangun sistem operasi, arsitektur server, dan infrastruktur komputasi tingkat rendah.",
    "div.framework": "Menciptakan framework dan pustaka (library) pemrograman untuk mempercepat pengembangan aplikasi.",
    "div.security": "Menangani keamanan aplikasi, audit kode, pencegahan eksploitasi, dan standarisasi kriptografi.",
    "div.community": "Pusat kolaborasi ekosistem, penyusunan dokumentasi, dan program edukasi untuk developer.",
    "div.labs": "Divisi riset dan eksperimen teknologi mutakhir, inkubasi prototipe, dan pengembangan AI.",
    "eco.title": "Ekosistem Proyek",
    "eco.sub": "Produk <em>open source</em> inovatif yang lahir dan dikembangkan dari rahim komunitas kami.",
    "eco.tag1": "Agent CLI",
    "eco.tag2": "Desktop SDK",
    "eco.tag3": "Database",
    "eco.desc1": "AI coding agent langsung di terminal. Memiliki agent Build & Plan, paham kode via LSP, dan mendukung ekstensi MCP dengan multi-provider model.",
    "eco.desc2": "SDK C++ native berbasis Qt 6. Framework aplikasi desktop super ringan tanpa Electron, dibekali jendela Fluent, PTY terminal, dan runtime bridge.",
    "eco.desc3": "Mesin database dokumen berbasis JSON. Cepat, ringan, dan dirancang untuk skalabilitas aplikasi modern tanpa setup yang rumit.",
    "eco.tag4": "IDE Desktop",
    "eco.desc4": "Editor kode desktop di atas NXC SDK. Explorer, pencarian, source control, API client, database, dan terminal agen AI dalam satu jendela, dengan penyorotan sintaks lebih dari 400 bahasa.",
    "eco.tag5": "Framework CLI",
    "eco.desc5": "CLI untuk mengelola seluruh runtime Nxdom — Backend PHP, Desktop Electron, Mobile Expo, Web Express, dan Web statik — lengkap dengan modul siap pakai dan Agent AI bawaan multi-provider.",
    "eco.tag6": "Manajer Paket",
    "eco.desc6": "Package manager Node.js dengan Global Package Store bersama. Satu salinan package (mis. express) dipakai lintas semua project — hemat disk, hemat waktu install, dan tetap kompatibel dengan struktur node_modules standar Node.js.",
    "eco.tag7": "Platform Realtime",
    "eco.desc7": "CLI dan platform WebSocket untuk fitur realtime — chat, panggilan suara/video, AI streaming, tracking, dan notifikasi. Semua modul siap pakai dalam satu gateway ber-token, dengan adapter data fleksibel dan binary standalone.",
    "eco.tag8": "Local Server / Tunnel CLI",
    "eco.desc8": "Jadikan PC Windows/Linux origin server dengan domain HTTPS sendiri via Cloudflare Tunnel. Satu perintah, tanpa port forwarding, tanpa IP publik — zero dependency runtime dan tersedia sebagai single executable.",
    "eco.tag9": "Port Monitor",
    "eco.desc9": "Dasbor interaktif real-time untuk port TCP/UDP: proses pemiliknya dan direktori kerja (Dir) tempat port dibuka. Buka browser, kill, health check, start/stop server, dan API internal (/api/ports) — single binary C++ tanpa dependensi.",
    "eco.tag10": "Database Layer",
    "eco.desc10": "Lapisan lokal di depan Firebase Realtime Database: aplikasi kirim JSON ke localhost, NxfireDB yang meneruskan ke cloud. Cache, antrian offline, realtime WebSocket lokal, dan SDK 5 bahasa — tanpa domain, tanpa biaya.",
    "eco.tag11": "Sinkronisasi Remote CLI",
    "eco.desc11": "Jembatan terminal ke server remote: jalankan nxzilla di folder proyek, berkas remote (SSH/SFTP atau FTP/FTPS) ditarik masuk ke folder kerja. Push/pull dengan deteksi konflik berbasis baseline - TUI keyboard-driven tanpa dependensi.",
    "cat.all": "Semua",
    "cat.agent": "Agent CLI",
    "cat.desktop": "Desktop SDK",
    "cat.ide": "IDE Desktop",
    "cat.database": "Database",
    "cat.framework": "Framework CLI",
    "cat.pkg": "Manajer Paket",
    "cat.realtime": "Platform Realtime",
    "cat.tunnel": "Server &amp; Tunnel",
    "cat.port": "Port Monitor",
    "cat.dblayer": "Database Layer",
    "cat.sync": "Sinkronisasi Remote CLI",
    "pr.title": "Prinsip Open Source",
    "pr.sub": "Pondasi etos kerja yang kami pegang teguh.",
    "pr.1": "Kode, perancangan, dan proses pengembangan kami buka secara transparan.",
    "pr.2": "Kontribusi, ide, dan diskusi dari komunitas sangat dihargai dan menjadi motor penggerak.",
    "pr.3": "Setiap sistem dibangun dari komponen independen yang dapat didaur ulang (reusable).",
    "pr.4": "Keputusan teknis dan aturan tata kelola proyek terdokumentasi dengan baik dan adil.",
    "pr.5": "Mematuhi lisensi terbuka seperti MIT, Apache-2.0, atau GPL sesuai dengan sifat proyek.",
    "foot.link1": "Organisasi GitHub",
    "foot.link2": "Proyek Kami",
    "foot.link3": "Menjadi Sponsor",
    "foot.copy": "&copy; 2026 Nusantara eXtreme Development Object Model. Dirilis di bawah lisensi terbuka.",
    "foot.slogan": "Membangun ekosistem teknologi yang mandiri untuk Indonesia dan dunia."
  },
  en: {
    "meta.title": "NXDOM — Nusantara eXtreme Development Object Model",
    "meta.desc": "Open source software development organization from Nusantara. Build Open. Develop Extreme. Empower Nusantara.",
    "nav.about": "About",
    "nav.divisions": "Divisions",
    "nav.principles": "Principles",
    "nav.ecosystem": "Ecosystem",
    "nav.sponsor": "Sponsor Us",
    "hero.badge": "Nusantara Open Source Organization",
    "hero.desc": "We are a community of open technology developers that are innovative, independent, and globally competitive. Focused on systems development, software architecture, and fundamental digital technologies.",
    "hero.cta1": "View Ecosystem",
    "hero.cta2": "Our Mission",
    "vision.title": "Our Vision",
    "vision.desc": "To become an innovative, independent, and globally competitive open technology development community from Nusantara.",
    "mission.title": "Core Mission",
    "mission.1": "Develop <em>open source</em> software that can be studied, used, and evolved together.",
    "mission.2": "Build robust, flexible, and extensible modular frameworks and systems.",
    "mission.3": "Foster collaboration between programmers, developers, system administrators, and researchers.",
    "mission.4": "Develop open technical standards and documentations.",
    "mission.5": "Build an Indonesian tech ecosystem that contributes back to the global community.",
    "div.title": "Structure &amp; Divisions",
    "div.sub": "Research and development focus across multiple layers of technology architecture.",
    "div.core": "Focuses on core architecture and fundamental object model design that serve as system foundations.",
    "div.systems": "Builds operating systems, server architectures, and low-level computing infrastructure.",
    "div.framework": "Creates programming frameworks and libraries to accelerate application development.",
    "div.security": "Handles application security, code auditing, exploit prevention, and cryptographic standards.",
    "div.community": "Ecosystem collaboration center, documentation drafting, and developer education programs.",
    "div.labs": "Research and experimentation division for cutting-edge tech, prototype incubation, and AI development.",
    "eco.title": "Project Ecosystem",
    "eco.sub": "Innovative <em>open source</em> products born and nurtured from our community.",
    "eco.tag1": "CLI Agent",
    "eco.tag2": "Desktop SDK",
    "eco.tag3": "Database",
    "eco.desc1": "AI coding agent right in your terminal. Features Build & Plan agents, code-aware via LSP, and supports MCP extensions with multi-provider models.",
    "eco.desc2": "Native C++ SDK based on Qt 6. Ultra-lightweight desktop app framework without Electron, equipped with Fluent windows, PTY terminal, and runtime bridge.",
    "eco.desc3": "JSON-based document database engine. Fast, lightweight, and designed for modern application scalability without complex setups.",
    "eco.tag4": "Desktop IDE",
    "eco.desc4": "Desktop code editor built on the NXC SDK. Explorer, search, source control, API client, database, and an AI-agent terminal in one window, with syntax highlighting for 400+ languages.",
    "eco.tag5": "Framework CLI",
    "eco.desc5": "A CLI to manage every Nxdom runtime — PHP Backend, Electron Desktop, Expo Mobile, Express Web, and static Web — complete with ready-to-use modules and a built-in multi-provider AI agent.",
    "eco.tag6": "Package Manager",
    "eco.desc6": "A Node.js package manager backed by a shared Global Package Store. One physical copy of a package (e.g. express) serves every project — saving disk and install time while staying compatible with the standard Node.js node_modules layout.",
    "eco.tag7": "Realtime Platform",
    "eco.desc7": "CLI and WebSocket platform for realtime features — chat, voice/video calls, AI streaming, tracking, and notifications. Every module ready in one token-protected gateway, with flexible data adapters and standalone binaries.",
    "eco.tag8": "Local Server / Tunnel CLI",
    "eco.desc8": "Turn your Windows/Linux PC into an origin server with your own HTTPS domain via Cloudflare Tunnel. One command, no port forwarding, no public IP — zero dependency runtime, available as a single executable.",
    "eco.tag9": "Port Monitor",
    "eco.desc9": "Real-time interactive dashboard for TCP/UDP ports: the owning process and the working directory (Dir) the port was opened from. Open in browser, kill, health check, start/stop servers, and an internal API (/api/ports) — single C++ binary, zero dependencies.",
    "eco.tag10": "Database Layer",
    "eco.desc10": "A local layer in front of Firebase Realtime Database: your app sends JSON to localhost, NxfireDB forwards it to the cloud. Cache, offline queue, local realtime WebSocket, and SDKs in 5 languages — no domain, no cost.",
    "eco.tag11": "Remote Sync CLI",
    "eco.desc11": "A terminal bridge to remote servers: run nxzilla in a project folder and remote files (SSH/SFTP or FTP/FTPS) are pulled into your working directory. Push/pull with baseline conflict detection - keyboard-driven TUI, zero dependencies.",
    "cat.all": "All",
    "cat.agent": "CLI Agent",
    "cat.desktop": "Desktop SDK",
    "cat.ide": "Desktop IDE",
    "cat.database": "Database",
    "cat.framework": "Framework CLI",
    "cat.pkg": "Package Manager",
    "cat.realtime": "Realtime Platform",
    "cat.tunnel": "Server &amp; Tunnel",
    "cat.port": "Port Monitor",
    "cat.dblayer": "Database Layer",
    "cat.sync": "Remote Sync CLI",
    "pr.title": "Open Source Principles",
    "pr.sub": "The work ethic foundation we hold firmly.",
    "pr.1": "Our code, designs, and development processes are transparently open.",
    "pr.2": "Contributions, ideas, and discussions from the community are highly valued and serve as our driving force.",
    "pr.3": "Every system is built from independent, reusable components.",
    "pr.4": "Technical decisions and project governance rules are well-documented and fair.",
    "pr.5": "Adhering to open licenses like MIT, Apache-2.0, or GPL according to the nature of the project.",
    "foot.link1": "GitHub Organization",
    "foot.link2": "Our Projects",
    "foot.link3": "Become a Sponsor",
    "foot.copy": "&copy; 2026 Nusantara eXtreme Development Object Model. Released under open licenses.",
    "foot.slogan": "Building an independent tech ecosystem for Indonesia and the world."
  }
};

function setLang(lang) {
  if (!I18N[lang]) lang = "id";
  document.documentElement.lang = lang;
  localStorage.setItem("nxdom-lang", lang);

  document.querySelectorAll("[data-lang-btn]").forEach(btn => {
    btn.classList.toggle("active", btn.dataset.langBtn === lang);
  });

  const dict = I18N[lang];

  document.querySelectorAll("[data-i18n]").forEach(el => {
    const key = el.getAttribute("data-i18n");
    if (dict[key]) el.innerHTML = dict[key];
  });

  document.querySelectorAll("[data-i18n-attr]").forEach(el => {
    const rules = el.getAttribute("data-i18n-attr").split(",");
    rules.forEach(rule => {
      const parts = rule.split("|");
      if (parts.length === 2 && dict[parts[1]]) {
        el.setAttribute(parts[0], dict[parts[1]]);
      }
    });
  });
  
  if (dict["meta.title"]) document.title = dict["meta.title"];
}

// Scroll Animations & Dynamic Navbar
function handleScroll() {
  const navbar = document.querySelector('.navbar');
  if (navbar) {
    if (window.scrollY > 50) {
      navbar.classList.add('scrolled');
    } else {
      navbar.classList.remove('scrolled');
    }
  }

  const reveals = document.querySelectorAll('.reveal');
  for (let i = 0; i < reveals.length; i++) {
    const windowHeight = window.innerHeight;
    const elementTop = reveals[i].getBoundingClientRect().top;
    const elementVisible = 100;

    if (elementTop < windowHeight - elementVisible) {
      reveals[i].classList.add('active');
    }
  }
}

// Ecosystem category filter
function applyEcoFilter(cat) {
  const cards = document.querySelectorAll('.project-grid .project-card');
  cards.forEach(card => {
    const show = cat === 'all' || card.dataset.cat === cat;
    card.classList.toggle('eco-hidden', !show);
    if (show) card.classList.add('active');
  });
  document.querySelectorAll('.eco-chip').forEach(chip => {
    chip.classList.toggle('active', chip.dataset.cat === cat);
  });
}

// Initialization on DOM Content Loaded
document.addEventListener("DOMContentLoaded", () => {
  // Initialize Lucide Icons
  if (typeof lucide !== "undefined") {
    lucide.createIcons();
  }

  // Setup Language Listeners
  document.querySelectorAll("[data-lang-btn]").forEach(btn => {
    btn.addEventListener("click", () => setLang(btn.dataset.langBtn));
  });

  // Load Saved Language
  const savedLang = localStorage.getItem("nxdom-lang");
  if (savedLang) {
    setLang(savedLang);
  } else {
    const browserLang = navigator.language.slice(0, 2).toLowerCase();
    setLang(browserLang === "id" ? "id" : "en");
  }

  // Ecosystem category filter
  document.querySelectorAll('.eco-chip').forEach(chip => {
    chip.addEventListener('click', () => applyEcoFilter(chip.dataset.cat));
  });
  document.querySelectorAll('[data-cat-link]').forEach(link => {
    link.addEventListener('click', () => applyEcoFilter(link.dataset.catLink));
  });

  // Bind and trigger scroll handler
  window.addEventListener('scroll', handleScroll);
  handleScroll();
});
