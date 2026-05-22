// ES6 Module untuk Click Sound dengan NexaUI Toggle
class NexaClick {
  constructor() {
    // Load setting dari localStorage
    this.isEnabled = this.loadSetting();
    this.setupToggle();
    // Track elements yang sudah diberi sound
    this.soundEnabledElements = new WeakSet();
  }

  // Method untuk load setting dari localStorage
  loadSetting() {
    const saved = localStorage.getItem("clickSoundEnabled");
    if (saved === "disabled") {
      return false;
    }
    return true; // default: enabled
  }

  // Method untuk save setting ke localStorage
  saveSetting(enabled) {
    if (enabled) {
      localStorage.setItem("clickSoundEnabled", "enabled");
    } else {
      localStorage.setItem("clickSoundEnabled", "disabled");
    }
    this.isEnabled = enabled;
    this.updateSoundLabel();
  }

  // Method untuk update label dan icon sesuai status
  updateSoundLabel() {
    const label = document.getElementById("soundLabel");
    if (label) {
      if (this.isEnabled) {
        label.innerHTML =
          '<span class="material-symbols-outlined nx-icon-xs">volume_up</span> Enable Click Sound';
      } else {
        label.innerHTML =
          '<span class="material-symbols-outlined nx-icon-xs">volume_off</span> Disable Click Sound';
      }
    }
  }

  // Method untuk setup NexaUI toggle control
  setupToggle() {
    const toggle = document.getElementById("soundToggle");
    if (toggle) {
      // Set initial state
      toggle.checked = this.isEnabled;
      this.updateSoundLabel();

      // Add event listener
      toggle.addEventListener("change", (e) => {
        this.saveSetting(e.target.checked);
      });
    }
  }

  // Method untuk memutar audio (hanya jika enabled)
  async playAudio() {
    if (!this.isEnabled) {
      return;
    }

    try {
      const audio = new Audio(`${NEXA.url}/assets/modules/Click/click.mp3`);
      audio.volume = 0.5;
      audio.currentTime = 0;
      await audio.play();
    } catch (error) {
      // Silent fail - no console log
    }
  }

  // Method untuk handle click dengan event delegation
  handleClick = (event) => {
    // Hanya play sound jika elemen target adalah button, link, atau clickable
    const target = event.target.closest('button, a, [onclick], .btn, .clickable');
    if (target) {
      this.playAudio();
    }
  };

  // Method untuk toggle sound programmatically
  toggleSound() {
    this.saveSetting(!this.isEnabled);
    const toggle = document.getElementById("soundToggle");
    if (toggle) {
      toggle.checked = this.isEnabled;
    }
    return this.isEnabled;
  }

  // Method untuk enable sound
  enableSound() {
    this.saveSetting(true);
    const toggle = document.getElementById("soundToggle");
    if (toggle) toggle.checked = true;
  }

  // Method untuk disable sound
  disableSound() {
    this.saveSetting(false);
    const toggle = document.getElementById("soundToggle");
    if (toggle) toggle.checked = false;
  }

  // Method untuk menambahkan sound ke element tertentu
  addSoundToElement(element) {
    if (!element || this.soundEnabledElements.has(element)) {
      return false; // Return false jika element sudah diberi sound
    }

    element.addEventListener("click", this.handleClick);
    this.soundEnabledElements.add(element);
    return true; // Return true jika berhasil menambahkan sound
  }

  // Method untuk menambahkan sound ke container dan semua child elements
  addSoundToContainer(container) {
    if (!container) return 0;

    const buttons = [...container.querySelectorAll("button")];
    const links = [...container.querySelectorAll("a")];
    const clickableElements = [
      ...container.querySelectorAll("[onclick], .btn, .clickable"),
    ];

    // Gabungkan semua elemen yang bisa diklik
    const allClickableElements = [
      ...new Set([...buttons, ...links, ...clickableElements]),
    ];

    let addedCount = 0;
    allClickableElements.forEach((element) => {
      if (this.addSoundToElement(element)) {
        addedCount++;
      }
    });

    return addedCount;
  }

  // Method untuk initialize event listeners
  init() {
    // Cegah double initialization
    if (this.isInitialized) {
      return;
    }
    this.isInitialized = true;

    // Gunakan event delegation di document.body untuk menghindari double sound
    // Ini lebih efisien dan mencegah double listener
    document.addEventListener("click", this.handleClick, true);

    // Setup MutationObserver untuk detect dynamic content (optional, untuk tracking saja)
    this.setupMutationObserver();
  }

  // Method untuk setup MutationObserver (detect dynamic content)
  // Catatan: Dengan event delegation, MutationObserver tidak diperlukan untuk menambahkan listener
  // Tapi tetap bisa digunakan untuk tracking/logging jika diperlukan
  setupMutationObserver() {
    // Observer untuk mendeteksi penambahan elemen baru (optional, untuk tracking saja)
    // Dengan event delegation, tidak perlu menambahkan listener manual
    // MutationObserver hanya untuk monitoring jika diperlukan
    if (this.observer) {
      return; // Observer sudah ada, jangan buat lagi
    }
    
    this.observer = new MutationObserver((mutations) => {
      // Event delegation sudah menangani semua click, jadi tidak perlu menambahkan listener lagi
      // Observer ini hanya untuk tracking/logging jika diperlukan di masa depan
    });

    // Observe seluruh document untuk perubahan
    this.observer.observe(document.body, {
      childList: true,
      subtree: true,
    });
  }

  // Method untuk manual re-initialize (untuk use case khusus)
  reinitialize(container = document.body) {
    return this.addSoundToContainer(container);
  }

  // Method untuk mendapatkan statistik
  getStats() {
    const buttons = document.querySelectorAll("button").length;
    const links = document.querySelectorAll("a").length;
    const clickableElements = document.querySelectorAll(
      "[onclick], .btn, .clickable"
    ).length;

    return {
      totalButtons: buttons,
      totalLinks: links,
      totalClickableElements: clickableElements,
      soundEnabled: this.isEnabled,
    };
  }
}

// ES6 Export
export { NexaClick };
export default NexaClick;
