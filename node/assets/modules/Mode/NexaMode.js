
export class NexaMode {
  constructor() {
    // Properties
    this.body = document.body;
    this.themeToggle = document.getElementById("themeToggle");
    // Periksa apakah themeToggle ada sebelum melanjutkan
    if (!this.themeToggle) {
      return;
    }

    this.themeIcon = this.themeToggle.querySelector("i.material-symbols-outlined") || 
                     this.themeToggle.querySelector("i") ||
                     this.themeToggle.querySelector(".material-symbols-outlined");
    this.menuToggle = document.getElementById("menuToggle");
    this.sidebar = document.querySelector(".sidebar-grid");
    this.prefersDarkScheme = window.matchMedia("(prefers-color-scheme: dark)");

    // Initialize hanya jika themeToggle ada
    this.init();
  }

  init() {
    this.setupEventListeners();
    this.loadThemePreference();
  }

  setupEventListeners() {
    // Toggle theme event
    this.themeToggle.addEventListener("click", (e) => {
      e.preventDefault();
      e.stopPropagation();
      this.body.classList.toggle("dark-mode-grid");
      
      // Pastikan icon element masih valid sebelum update
      if (!this.themeIcon || !this.themeIcon.parentElement) {
        this.themeIcon = this.themeToggle.querySelector("i.material-symbols-outlined") || 
                         this.themeToggle.querySelector("i");
      }
      
      this.updateThemeIcon();
      this.saveThemePreference();
    });

    // System theme change event
    this.prefersDarkScheme.addEventListener("change", (e) => {
      if (localStorage.getItem("darkMode") === null) {
        if (e.matches) {
          this.body.classList.add("dark-mode-grid");
        } else {
          this.body.classList.remove("dark-mode-grid");
        }
        this.updateThemeIcon();
      }
    });
  }

  updateThemeIcon() {
    // Update icon berdasarkan dark mode status
    if (!this.themeIcon) {
      // Coba cari lagi jika tidak ditemukan
      this.themeIcon = this.themeToggle.querySelector("i.material-symbols-outlined") || 
                       this.themeToggle.querySelector("i") ||
                       this.themeToggle.querySelector(".material-symbols-outlined");
    }
    
    if (this.themeIcon) {
      const isDarkMode = this.body.classList.contains("dark-mode-grid");
      const newIconName = isDarkMode ? "light_mode" : "dark_mode";
      
      // Gunakan innerHTML untuk Material Symbols Outlined
      this.themeIcon.innerHTML = newIconName;
      this.themeIcon.textContent = newIconName;
      
      // Set attribute
      if (isDarkMode) {
        this.themeIcon.setAttribute("title", "Switch to Light Mode");
      } else {
        this.themeIcon.setAttribute("title", "Switch to Dark Mode");
      }
      
      // Force reflow untuk memastikan icon ter-update
      void this.themeIcon.offsetWidth;
    }
  }

  saveThemePreference() {
    const isDarkMode = this.body.classList.contains("dark-mode-grid");
    onCookie("darkmode", isDarkMode || "");
    localStorage.setItem("darkMode", isDarkMode);
  }

  loadThemePreference() {
    const savedTheme = localStorage.getItem("darkMode");

    if (savedTheme !== null) {
      // Use saved preference
      if (savedTheme === "true") {
        this.body.classList.add("dark-mode-grid");
      }
    } else {
      // Use system preference
      if (this.prefersDarkScheme.matches) {
        this.body.classList.add("dark-mode-grid");
      }
    }

    this.updateThemeIcon();
  }
}
export function onCookie(name, value) {
  // Membuat cookie dengan waktu kedaluwarsa sesi
  document.cookie = `${encodeURIComponent(name)}=${encodeURIComponent(
    value
  )}; path=/`;
}