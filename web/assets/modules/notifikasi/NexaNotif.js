/**
 * No-op: `notifikasi.css` dimuat lewat `nexa.css` (@import) / `<link>` di HTML.
 * Tetap diekspor bila ada kode yang memanggil `ensureNotifStylesheet()`.
 */
export async function ensureNotifStylesheet() {
  if (typeof document === "undefined") return;
}

export class NexaNotif {
  constructor(options = {}) {
    this.container = null;
    this.isVisible = false;
    this.autoHideDelay = options.autoHideDelay || 5000; // Default 5 seconds
    this.timers = new Map(); // Store timers for each notification
    this.voiceEnabled = false; // Voice notification flag
    this.voice = null; // Voice instance
    this.init();
  }

  init() {
    // Get the notification container
    this.container = document.getElementById("notificationContainer");

    if (!this.container) {
      this.container = this.createContainer();
    }

    if (!this.container) {
      // Unable to create container in this environment
      return;
    }

    // Initialize voice notification
    this.initVoice();

    // Bind action handlers
    this.bindActions();
  }

  // Initialize voice notification system
  initVoice() {
    try {
      // Check if voice is enabled in system settings
      this.checkVoiceSettings();

      // Initialize voice instance if available
      if (typeof NXUI !== "undefined" && NXUI.NexaVoice) {
        this.voice = new NXUI.NexaVoice();
      } else {
        console.warn("NXUI.NexaVoice not available");
      }
    } catch (error) {
      console.error("Error initializing voice notification:", error);
    }
  }

  // Check voice settings from system storage
  async checkVoiceSettings() {
    try {
      if (typeof NXUI !== "undefined" && NXUI.ref) {
        const systemData = await NXUI.ref.get("bucketsStore", "system");
        this.voiceEnabled = systemData?.voice || false;
      }
    } catch (error) {
      console.error("Error checking voice settings:", error);
      this.voiceEnabled = false;
    }
  }

  // Enable/disable voice notifications
  setVoiceEnabled(enabled) {
    this.voiceEnabled = enabled;
  }

  // Speak notification content - automatically check IndexedDB
  async speakNotification(title, subtitle) {
    try {
      // Always check current voice settings from IndexedDB
      if (typeof NXUI !== "undefined" && NXUI.ref) {
        const systemData = await NXUI.ref.get("bucketsStore", "system");
        const voiceEnabled = systemData?.voice || false;

        if (voiceEnabled && this.voice) {
          // Use subtitle only for voice output
          const voiceText = `${subtitle}`;
          this.voice.speak(voiceText);
        }
      }
    } catch (error) {
      console.error("Error speaking notification:", error);
    }
  }

  bindActions() {
    // Bind close notification handlers
    const closeButtons = this.container.querySelectorAll(
      ".nx-notif-action-btn.nx-notif-close-btn, .nx-notif-action-btn.nx-notif-ok-btn"
    );
    closeButtons.forEach((btn) => {
      btn.addEventListener("click", (e) => {
        this.closeNotification(e.target);
      });
    });

    // Bind snooze notification handlers
    const snoozeButtons = this.container.querySelectorAll(
      ".nx-notif-action-btn.nx-notif-snooze-btn"
    );
    snoozeButtons.forEach((btn) => {
      btn.addEventListener("click", (e) => {
        this.snoozeNotification(e.target);
      });
    });
  }

  async show(options = {}) {
    if (!this.container) {
      // console.error("Notification container not found");
      return false;
    }

    try {
      // Check if container has any notification cards
      const cards = this.container.querySelectorAll(".nx-notif-card");

      // If no cards exist, create notifications based on options
      if (cards.length === 0) {
        if (options.type) {
          // Create single notification with specified type
          this.createSingleNotification(options);
        } else {
          // Create default notifications
          this.createDefaultNotifications();
        }
      }

      // Remove hidden class and show the container
      this.container.classList.remove("nx-notif-hidden");
      this.container.classList.add("nx-notif-showing");
      this.container.style.display = "flex"; // Force display
      this.isVisible = true;

      return true;
    } catch (error) {
      console.error("Error showing notifications:", error);
      return false;
    }
  }

  hide() {
    if (!this.container || !this.isVisible) {
      return false;
    }

    try {
      // Remove showing class and add hidden class
      this.container.classList.remove("nx-notif-showing");
      this.container.classList.add("nx-notif-hidden");
      this.container.style.display = "none"; // Force hide
      this.isVisible = false;

      return true;
    } catch (error) {
      console.error("Error hiding notifications:", error);
      return false;
    }
  }

  closeNotification(button) {
    if (!button) {
      return;
    }

    const notificationCard = button.closest(".nx-notif-card");
    if (!notificationCard) {
      return;
    }

    try {
      // Clear any existing timer for this notification
      const timerId = this.timers.get(notificationCard);
      if (timerId) {
        clearTimeout(timerId);
        this.timers.delete(notificationCard);
      }

      // Add close animation
      notificationCard.style.transition =
        "opacity 0.2s ease, transform 0.2s ease";
      notificationCard.style.opacity = "0";
      notificationCard.style.transform = "translateX(30px)";

      // Remove card after animation
      setTimeout(() => {
        notificationCard.remove();

        // Check if all notifications are closed
        const remainingCards =
          this.container.querySelectorAll(".nx-notif-card");
        if (remainingCards.length === 0) {
          this.hide();
        }
      }, 200);
    } catch (error) {
      console.error("Error closing notification:", error);
    }
  }

  // Method to close notification directly (for notifications without action buttons)
  closeNotificationDirect(notificationCard) {
    if (!notificationCard) return;

    try {
      // Clear any existing timer for this notification
      const timerId = this.timers.get(notificationCard);
      if (timerId) {
        clearTimeout(timerId);
        this.timers.delete(notificationCard);
      }

      // Add close animation
      notificationCard.style.transition =
        "opacity 0.2s ease, transform 0.2s ease";
      notificationCard.style.opacity = "0";
      notificationCard.style.transform = "translateX(30px)";

      // Remove card after animation
      setTimeout(() => {
        notificationCard.remove();

        // Check if all notifications are closed
        const remainingCards =
          this.container.querySelectorAll(".nx-notif-card");
        if (remainingCards.length === 0) {
          this.hide();
        }
      }, 200);
    } catch (error) {
      console.error("Error closing notification directly:", error);
    }
  }

  snoozeNotification(button) {
    const notificationCard = button.closest(".nx-notif-card");
    if (!notificationCard) return;

    try {
      // Update notification content to show snoozed state
      const titleElement = notificationCard.querySelector(".nx-notif-title");
      const subtitleElement =
        notificationCard.querySelector(".nx-notif-subtitle");

      if (titleElement && subtitleElement) {
        const originalTitle = titleElement.textContent;
        const originalSubtitle = subtitleElement.textContent;

        titleElement.textContent = `${originalTitle} (Snoozed)`;
        subtitleElement.textContent = "Will remind you again in 5 minutes";

        // Change snooze button to unsnooze
        button.textContent = "Unsnooze";
        button.classList.remove("nx-notif-snooze-btn");
        button.classList.add("nx-notif-ok-btn");

        // Update click handler
        button.onclick = () => this.unsnoozeNotification(button);

        // Auto-unsnooze after 5 seconds (for demo purposes)
        setTimeout(() => {
          this.unsnoozeNotification(button);
        }, 5000);
      }
    } catch (error) {
      console.error("Error snoozing notification:", error);
    }
  }

  unsnoozeNotification(button) {
    const notificationCard = button.closest(".nx-notif-card");
    if (!notificationCard) return;

    try {
      // Restore original content
      const titleElement = notificationCard.querySelector(".nx-notif-title");
      const subtitleElement =
        notificationCard.querySelector(".nx-notif-subtitle");

      if (titleElement && subtitleElement) {
        // Remove "(Snoozed)" from title
        titleElement.textContent = titleElement.textContent.replace(
          " (Snoozed)",
          ""
        );

        // Restore original subtitle based on notification type
        if (titleElement.textContent === "Coffee Break") {
          subtitleElement.textContent = "In 5 minutes";
        } else if (titleElement.textContent === "Cannot Send Mail") {
          subtitleElement.textContent =
            "The message was rejected by the server because it is too large.";
        } else if (titleElement.textContent === "Yippee") {
          subtitleElement.textContent = "Nothing bad happened.";
        }

        // Restore snooze button
        button.textContent = "Snooze";
        button.classList.remove("nx-notif-ok-btn");
        button.classList.add("nx-notif-snooze-btn");

        // Restore click handler
        button.onclick = () => this.snoozeNotification(button);
      }
    } catch (error) {
      console.error("Error unsnoozing notification:", error);
    }
  }

  // Method to add new notification dynamically
  addNotification(
    title,
    subtitle,
    type = "info",
    actions = ["close"],
    autoHide = true,
    icon = null
  ) {
    if (!this.container) return false;

    try {
      const notificationCard = document.createElement("div");
      notificationCard.className = "nx-notif-card";

      // Set icon based on custom icon or type using Material Icons
      let iconName = "";
      let iconColor = "#333";

      // First determine the color based on type
      switch (type) {
        case "success":
          iconColor = "#28a745";
          break;
        case "warning":
          iconColor = "#ff8c00";
          break;
        case "error":
          iconColor = "#dc3545";
          break;
        default: // info
          iconColor = "#007bff";
      }

      // Then determine the icon name
      if (icon) {
        // Use custom icon name but keep the type-appropriate color
        iconName = icon;
      } else {
        // Use default icon based on type
        switch (type) {
          case "success":
            iconName = "check_circle";
            break;
          case "warning":
            iconName = "warning";
            break;
          case "error":
            iconName = "error";
            break;
          default: // info
            iconName = "info";
        }
      }

      notificationCard.innerHTML = `
        <div class="nx-notif-icon">
          <span class="material-symbols-outlined" style="color: ${iconColor};">${iconName}</span>
        </div>
        <div class="nx-notif-content">
          <div class="nx-notif-title">${title}</div>
          <div class="nx-notif-subtitle">${subtitle}</div>
        </div>
        ${
          actions.length > 0
            ? `
        <div class="nx-notif-actions">
          ${
            actions.includes("close")
              ? '<button class="nx-notif-action-btn nx-notif-close-btn" onclick="window.nexaNotif.closeNotification(this)">Close</button>'
              : ""
          }
          ${
            actions.includes("snooze")
              ? '<button class="nx-notif-action-btn nx-notif-snooze-btn" onclick="window.nexaNotif.snoozeNotification(this)">Snooze</button>'
              : ""
          }
          ${
            actions.includes("ok")
              ? '<button class="nx-notif-action-btn nx-notif-ok-btn" onclick="window.nexaNotif.closeNotification(this)">OK</button>'
              : ""
          }
        </div>
        `
            : ""
        }
      `;

      // Add to container
      this.container.appendChild(notificationCard);

      // Speak notification if voice is enabled (async)
      this.speakNotification(title, subtitle);

      // Animate in
      notificationCard.style.opacity = "0";
      notificationCard.style.transform = "translateX(-30px)";

      setTimeout(() => {
        notificationCard.style.transition =
          "opacity 0.3s ease, transform 0.3s ease";
        notificationCard.style.opacity = "1";
        notificationCard.style.transform = "translateX(0)";
      }, 100);

      // Auto-hide timer
      if (autoHide) {
        const timerId = setTimeout(() => {
          // If no action buttons, close directly by removing the card
          if (actions.length === 0) {
            this.closeNotificationDirect(notificationCard);
          } else {
            const actionBtn = notificationCard.querySelector(
              ".nx-notif-action-btn"
            );
            if (actionBtn) {
              this.closeNotification(actionBtn);
            } else {
              // Fallback: close directly if no action button found
              this.closeNotificationDirect(notificationCard);
            }
          }
        }, this.autoHideDelay);

        // Store timer ID for potential cancellation
        this.timers.set(notificationCard, timerId);

        // Add hover pause functionality
        notificationCard.addEventListener("mouseenter", () => {
          clearTimeout(timerId);
        });

        notificationCard.addEventListener("mouseleave", () => {
          const newTimerId = setTimeout(() => {
            // If no action buttons, close directly by removing the card
            if (actions.length === 0) {
              this.closeNotificationDirect(notificationCard);
            } else {
              const actionBtn = notificationCard.querySelector(
                ".nx-notif-action-btn"
              );
              if (actionBtn) {
                this.closeNotification(actionBtn);
              } else {
                // Fallback: close directly if no action button found
                this.closeNotificationDirect(notificationCard);
              }
            }
          }, this.autoHideDelay);
          this.timers.set(notificationCard, newTimerId);
        });
      }

      return true;
    } catch (error) {
      console.error("Error adding notification:", error);
      return false;
    }
  }

  // Method to clear all notifications
  clearAll() {
    if (!this.container) return false;

    try {
      // Clear all timers first
      this.timers.forEach((timerId) => clearTimeout(timerId));
      this.timers.clear();

      const cards = this.container.querySelectorAll(".nx-notif-card");
      cards.forEach((card) => card.remove());
      this.hide();
      return true;
    } catch (error) {
      console.error("Error clearing notifications:", error);
      return false;
    }
  }

  // Method to set auto-hide delay
  setAutoHideDelay(delay) {
    this.autoHideDelay = delay;
  }

  // Method to update voice settings from system
  async updateVoiceSettings() {
    await this.checkVoiceSettings();
  }

  // Method to test voice notification - automatically check IndexedDB
  async testVoiceNotification() {
    try {
      // Always check current voice settings from IndexedDB
      if (typeof NXUI !== "undefined" && NXUI.ref) {
        const systemData = await NXUI.ref.get("bucketsStore", "system");
        const voiceEnabled = systemData?.voice || false;

        if (voiceEnabled && this.voice) {
          this.speakNotification("Test Voice", "Sumber tes dari subtitle");
        } else {
          // Voice notification is disabled or not available
        }
      }
    } catch (error) {
      console.error("Error testing voice notification:", error);
    }
  }

  // Method to handle voice settings change from system
  onVoiceSettingsChange(enabled) {
    this.setVoiceEnabled(enabled);
    if (enabled && this.voice) {
      this.speakNotification(
        "Voice Notification",
        "Voice notification telah diaktifkan"
      );
    }
  }

  // Method to add notification with custom auto-hide
  addNotificationWithDelay(
    title,
    subtitle,
    type = "info",
    actions = ["close"],
    delay = null,
    icon = null
  ) {
    const originalDelay = this.autoHideDelay;
    if (delay !== null) {
      this.autoHideDelay = delay;
    }

    const result = this.addNotification(
      title,
      subtitle,
      type,
      actions,
      true,
      icon
    );

    // Restore original delay
    this.autoHideDelay = originalDelay;

    return result;
  }

  // Method to create single notification based on type
  createSingleNotification(options) {
    if (!this.container) return false;

    try {
      const type = options.type || "info";
      const title = options.title || this.getDefaultTitle(type);
      const subtitle = options.subtitle || this.getDefaultSubtitle(type);
      const showActions = options.actions !== false; // Default true unless explicitly false
      const actions = showActions
        ? options.actions || this.getDefaultActions(type)
        : [];
      const autoHide = options.autoHide !== false; // Default true unless explicitly false
      const icon = options.icon || null; // Custom icon

      // Check voice settings before creating notification
      this.checkVoiceSettings();

      this.addNotification(title, subtitle, type, actions, autoHide, icon);

      return true;
    } catch (error) {
      console.error("Error creating single notification:", error);
      return false;
    }
  }

  // Method to get default title based on type
  getDefaultTitle(type) {
    const titles = {
      success: "Success!",
      warning: "Warning",
      error: "Error",
      info: "Information",
    };
    return titles[type] || "Notification";
  }

  // Method to get default subtitle based on type
  getDefaultSubtitle(type) {
    const subtitles = {
      success: "Operation completed successfully.",
      warning: "Please check your input.",
      error: "Something went wrong.",
      info: "Here's some information for you.",
    };
    return subtitles[type] || "This is a notification.";
  }

  // Method to get default actions based on type
  getDefaultActions(type) {
    const actions = {
      success: ["ok"],
      warning: ["close"],
      error: ["close"],
      info: ["close", "snooze"],
    };
    return actions[type] || ["close"];
  }

  // Method to create default notifications
  createDefaultNotifications() {
    if (!this.container) return false;

    try {
      // Create Coffee Break notification
      this.addNotification("Coffee Break", "In 5 minutes", "info", [
        "close",
        "snooze",
      ]);

      // Create Cannot Send Mail notification
      this.addNotification(
        "Cannot Send Mail",
        "The message was rejected by the server because it is too large.",
        "warning",
        ["ok"]
      );

      // Create Yippee notification
      this.addNotification("Yippee", "Nothing bad happened.", "success", [
        "ok",
      ]);

      return true;
    } catch (error) {
      console.error("Error creating default notifications:", error);
      return false;
    }
  }

  createContainer() {
    if (typeof document === "undefined") {
      return null;
    }

    try {
      const container = document.createElement("div");
      container.id = "notificationContainer";
      container.className = "nx-notif-container nx-notif-hidden";
      container.setAttribute("role", "region");
      container.setAttribute("aria-live", "polite");
      container.style.display = "none";

      const target =
        document.querySelector("#nexa_app") ||
        document.body ||
        document.documentElement;
      target.appendChild(container);
      return container;
    } catch (error) {
      console.error("Failed to create notification container:", error);
      return null;
    }
  }
}

// Make NexaNotif available globally for HTML onclick handlers
window.nexaNotif = null;
