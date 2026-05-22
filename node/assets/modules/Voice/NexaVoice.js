/**
 * NexaVoice - Text-to-Speech Voice Class
 * Provides text-to-speech functionality with various voice options and configurations
 */
class NexaVoice {
  constructor(options = {}) {
    this.synthesis = window.speechSynthesis;
    this.voices = [];
    this.currentVoice = null;

    // Default settings - always normal/default
    this.settings = {
      rate: 1.0, // Normal speed - tidak bisa diubah
      pitch: 1.0, // Normal pitch - tidak bisa diubah
      volume: 1.0, // Normal volume - tidak bisa diubah
      lang: "id-ID", // Indonesian language - default
    };

    this.isPlaying = false;
    this.isPaused = false;
    this.userInteracted = false;

    // For better pause/resume functionality
    this.currentText = "";
    this.currentUtterance = null;
    
    // Fallback to ResponsiveVoice if Indonesian voice not available
    this.useResponsiveVoice = options.useResponsiveVoice !== false; // Default true
    this.responsiveVoiceKey = options.responsiveVoiceKey || "16D1IGr5";
    this.hasIndonesianVoice = false; // Will be set after checking voices

    // Initialize voices when available
    this.initVoices();

    // Setup user interaction detection
    this.setupUserInteraction();
  }

  /**
   * Setup user interaction detection
   */
  setupUserInteraction() {
    const events = ["click", "touchstart", "keydown"];
    const markInteraction = () => {
      this.userInteracted = true;
      events.forEach((event) => {
        document.removeEventListener(event, markInteraction);
      });
    };

    events.forEach((event) => {
      document.addEventListener(event, markInteraction, { once: true });
    });
  }

  /**
   * Initialize available voices
   */
  initVoices() {
    // Wait for voices to be loaded
    if (this.synthesis.getVoices().length === 0) {
      this.synthesis.addEventListener("voiceschanged", () => {
        this.loadVoices();
        // Log available voices for debugging
        this.logAvailableVoices();
      }, { once: true });
    } else {
      this.loadVoices();
      // Log available voices for debugging
      this.logAvailableVoices();
    }
  }
  
  /**
   * Log available voices for debugging (especially Indonesian voices)
   */
  logAvailableVoices() {
    const indonesianVoices = this.voices.filter((voice) => 
      voice.lang.toLowerCase().includes('id') ||
      voice.name.toLowerCase().includes('indonesia') ||
      voice.name.toLowerCase().includes('indonesian')
    );
    
    this.hasIndonesianVoice = indonesianVoices.length > 0;
    
    // Silent mode - only log if Indonesian voice not found and ResponsiveVoice not available
    if (!this.hasIndonesianVoice && this.useResponsiveVoice && typeof window.responsiveVoice === 'undefined') {
      console.warn('⚠️ NexaVoice: No Indonesian voices found and ResponsiveVoice not loaded');
    }
  }

  /**
   * Load available voices
   */
  loadVoices() {
    this.voices = this.synthesis.getVoices();

    // Set default voice based on language - prioritize Indonesian voices
    const targetLang = this.settings.lang || "id-ID";
    
    // Try to find exact match first (id-ID)
    let foundVoice = this.voices.find((voice) => 
      voice.lang.toLowerCase() === targetLang.toLowerCase()
    );
    
    // If not found, try to find voice that includes language code (id)
    if (!foundVoice) {
      const langCode = targetLang.split('-')[0]; // Extract 'id' from 'id-ID'
      foundVoice = this.voices.find((voice) => 
        voice.lang.toLowerCase().startsWith(langCode.toLowerCase())
      );
    }
    
    // If still not found, try to find any voice with 'indonesia' in name
    if (!foundVoice) {
      foundVoice = this.voices.find((voice) => 
        voice.name.toLowerCase().includes('indonesia') || 
        voice.name.toLowerCase().includes('indonesian')
      );
    }
    
    // Fallback to first available voice if still not found
    this.currentVoice = foundVoice || this.voices[0];
  }

  /**
   * Speak the given text
   * @param {string} text - Text to speak
   * @param {object} options - Optional settings override
   */
  speak(text, options = {}) {
    if (!text) {
      console.warn("NexaVoice: No text provided to speak");
      return;
    }

    // Check if user has interacted with the page
    if (!this.userInteracted) {
      console.warn(
        "NexaVoice: User interaction required before speech synthesis"
      );
      console.log("💡 Click anywhere on the page first to enable speech");
      return;
    }

    // Stop any current speech
    this.stop();

    // Store current text for pause/resume functionality
    this.currentText = text;

    // Check if we should use ResponsiveVoice as fallback
    const targetLang = options.lang || this.settings.lang;
    const isIndonesian = targetLang.toLowerCase().includes('id') || targetLang.toLowerCase() === 'id-id';
    
    // Use ResponsiveVoice if Indonesian voice not available and ResponsiveVoice is enabled
    if (isIndonesian && !this.hasIndonesianVoice && this.useResponsiveVoice && typeof window.responsiveVoice !== 'undefined') {
      try {
        window.responsiveVoice.speak(text, "Indonesian Female", {
          pitch: options.pitch || this.settings.pitch,
          rate: options.rate || this.settings.rate,
          volume: options.volume || this.settings.volume,
          onstart: () => {
            this.isPlaying = true;
            this.isPaused = false;
            this.onStart && this.onStart();
          },
          onend: () => {
            this.isPlaying = false;
            this.isPaused = false;
            this.onEnd && this.onEnd();
          },
          onerror: (error) => {
            this.isPlaying = false;
            this.isPaused = false;
            console.error('❌ ResponsiveVoice Error:', error);
            this.onError && this.onError(error);
          }
        });
        return;
      } catch (error) {
        console.error('❌ ResponsiveVoice failed, falling back to native TTS:', error);
        // Fall through to native TTS
      }
    }

    const utterance = new SpeechSynthesisUtterance(text);
    this.currentUtterance = utterance;

    // Apply language first (important for voice selection)
    utterance.lang = targetLang;
    
    // Reload voices to ensure we have the correct voice for the language
    // This is important because voices might not be loaded when initVoices() was called
    if (this.voices.length === 0 || !this.currentVoice) {
      this.loadVoices();
    } else {
      // Re-check if we have a better voice for the current language
      const langCode = targetLang.split('-')[0];
      const betterVoice = this.voices.find((voice) => 
        voice.lang.toLowerCase() === targetLang.toLowerCase() ||
        voice.lang.toLowerCase().startsWith(langCode.toLowerCase())
      );
      if (betterVoice) {
        this.currentVoice = betterVoice;
      }
    }

    // Apply settings
    utterance.voice = this.currentVoice;
    utterance.rate = options.rate || this.settings.rate;
    utterance.pitch = options.pitch || this.settings.pitch;
    utterance.volume = options.volume || this.settings.volume;

    // Event listeners
    utterance.onstart = () => {
      this.isPlaying = true;
      this.isPaused = false;
      this.onStart && this.onStart();
    };

    utterance.onend = () => {
      this.isPlaying = false;
      this.isPaused = false;
      this.onEnd && this.onEnd();
    };

    utterance.onerror = (event) => {
      this.isPlaying = false;
      this.isPaused = false;

      // Handle different error types
      if (event.error === "not-allowed") {
        console.error("❌ Speech not allowed - user interaction required");
      } else if (event.error !== "interrupted") {
        console.error("❌ NexaVoice Error:", event.error);
      }

      this.onError && this.onError(event);
    };

    utterance.onpause = () => {
      this.isPaused = true;
      this.onPause && this.onPause();
    };

    utterance.onresume = () => {
      this.isPaused = false;
      this.onResume && this.onResume();
    };

    // Speak the text
    this.synthesis.speak(utterance);
    return utterance;
  }

  /**
   * Pause current speech
   */
  pause() {
    if (this.isPlaying && !this.isPaused) {
      try {
        this.synthesis.pause();
        this.isPaused = true;
      } catch (error) {
        // Fallback: stop and restart later
        this.stop();
      }
    }
  }

  /**
   * Resume paused speech
   */
  resume() {
    if (this.isPaused) {
      try {
        if (this.synthesis.paused) {
          this.synthesis.resume();
          this.isPaused = false;
        } else {
          // Fallback: restart from beginning if resume failed
          this.speak(this.currentText);
        }
      } catch (error) {
        this.speak(this.currentText);
      }
    }
  }

  /**
   * Stop current speech
   */
  stop() {
    // Stop native TTS
    this.synthesis.cancel();
    
    // Stop ResponsiveVoice if available
    if (typeof window.responsiveVoice !== 'undefined') {
      window.responsiveVoice.cancel();
    }
    
    this.isPlaying = false;
    this.isPaused = false;
    this.currentUtterance = null;
  }

  /**
   * Set voice by name or index
   * @param {string|number} voice - Voice name or index
   */
  setVoice(voice) {
    if (typeof voice === "number") {
      this.currentVoice = this.voices[voice];
    } else if (typeof voice === "string") {
      this.currentVoice = this.voices.find((v) =>
        v.name.toLowerCase().includes(voice.toLowerCase())
      );
    }
  }

  /**
   * Get available voices
   * @returns {Array} Available voices
   */
  getVoices() {
    return this.voices;
  }

  /**
   * Set speech rate - DISABLED (always normal)
   */
  setRate(rate) {
    console.log("⚠️ Rate setting disabled - always using normal speed (1.0)");
    // this.settings.rate = 1.0; // Always normal
  }

  /**
   * Set speech pitch - DISABLED (always normal)
   */
  setPitch(pitch) {
    console.log("⚠️ Pitch setting disabled - always using normal pitch (1.0)");
    // this.settings.pitch = 1.0; // Always normal
  }

  /**
   * Set speech volume - DISABLED (always normal)
   */
  setVolume(volume) {
    console.log(
      "⚠️ Volume setting disabled - always using normal volume (1.0)"
    );
    // this.settings.volume = 1.0; // Always normal
  }

  /**
   * Set language
   * @param {string} lang - Language code (e.g., 'en-US', 'id-ID')
   */
  setLanguage(lang) {
    this.settings.lang = lang;
    this.loadVoices(); // Reload to find voice for new language
  }

  /**
   * Get current settings
   * @returns {object} Current settings
   */
  getSettings() {
    return { ...this.settings };
  }

  /**
   * Check if speech synthesis is supported
   * @returns {boolean} Support status
   */
  isSupported() {
    return "speechSynthesis" in window;
  }

  /**
   * Get current status
   * @returns {object} Current status
   */
  getStatus() {
    return {
      isPlaying: this.isPlaying,
      isPaused: this.isPaused,
      isSupported: this.isSupported(),
      currentVoice: this.currentVoice ? this.currentVoice.name : null,
      currentText: this.currentText,
      synthesisPaused: this.synthesis.paused,
      synthesisSpeaking: this.synthesis.speaking,
    };
  }

  /**
   * Speak text with emotion - DISABLED (always normal voice)
   * @param {string} text - Text to speak
   * @param {string} emotion - Ignored, always normal
   */
  speakWithEmotion(text, emotion = "normal") {
    console.log("⚠️ Emotion settings disabled - using normal voice");
    this.speak(text); // Always use normal settings
  }

  /**
   * Read HTML element content
   * @param {string|HTMLElement} element - Element selector or element
   */
  readElement(element) {
    let el;
    if (typeof element === "string") {
      el = document.querySelector(element);
    } else {
      el = element;
    }

    if (el) {
      const text = el.textContent || el.innerText;
      this.speak(text);
    }
  }

  /**
   * Auto-read page content with specified selectors
   * @param {Array} selectors - Array of CSS selectors to read
   */
  autoRead(selectors = ["h1", "h2", "p"]) {
    let content = "";
    selectors.forEach((selector) => {
      const elements = document.querySelectorAll(selector);
      elements.forEach((el) => {
        content += el.textContent + ". ";
      });
    });

    if (content) {
      this.speak(content);
    }
  }

  // Event handlers (can be overridden)
  onStart() {}
  onEnd() {}
  onError() {}
  onPause() {}
  onResume() {}
}

// Export for use
export { NexaVoice };
export default NexaVoice;

// Also support CommonJS and global window for compatibility
if (typeof module !== "undefined" && module.exports) {
  module.exports = NexaVoice;
} else if (typeof window !== "undefined") {
  window.NexaVoice = NexaVoice;
}
