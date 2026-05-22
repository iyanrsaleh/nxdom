import * as Speech from "expo-speech";

class ExpoSpeech {
  constructor() {
    this.isSpeaking = false;
  }

  // Check if speech is available
  async isSpeechAvailable() {
    try {
      const available = await Speech.isAvailableAsync();
      return available;
    } catch (error) {
      console.error("Error checking speech availability:", error);
      return false;
    }
  }

  // Speak text
  async speak(text, options = {}) {
    try {
      const defaultOptions = {
        language: "id-ID",
        pitch: 1.0,
        rate: 0.9,
        volume: 1.0,
      };

      const speechOptions = { ...defaultOptions, ...options };

      if (this.isSpeaking) {
        await this.stop();
      }

      this.isSpeaking = true;
      await Speech.speak(text, {
        ...speechOptions,
        onDone: () => {
          this.isSpeaking = false;
          if (options.onDone) options.onDone();
        },
        onError: (error) => {
          this.isSpeaking = false;
          console.error("Speech error:", error);
          if (options.onError) options.onError(error);
        },
      });
    } catch (error) {
      this.isSpeaking = false;
      console.error("Error speaking:", error);
      throw error;
    }
  }

  // Stop speaking
  async stop() {
    try {
      await Speech.stop();
      this.isSpeaking = false;
    } catch (error) {
      console.error("Error stopping speech:", error);
      throw error;
    }
  }

  // Get available voices
  async getAvailableVoices() {
    try {
      const voices = await Speech.getAvailableVoicesAsync();
      return voices;
    } catch (error) {
      console.error("Error getting available voices:", error);
      throw error;
    }
  }
}

// Export a singleton instance
export default new ExpoSpeech();
