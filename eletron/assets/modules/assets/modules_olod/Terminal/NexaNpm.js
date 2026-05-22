
export class NexaNpm {
  constructor() {
    this.spinnerInterval = null;
    this.textInterval = null;
    this.spinner = "|/-\\";
  }

  async init() {
    try {
      await NXUI.NexaStylesheet.Dom(['npm.css']);
      
      setTimeout(async () => {
        try {
          this.initializeSpinner();
          this.initializeTexts();
        } catch (error) {
          console.error("❌ Error initializing NexaNpm:", error);
        }
      }, 100);
    } catch (error) {
      console.error("❌ Error loading NexaNpm stylesheet:", error);
    }
  }

  initializeSpinner() {
    const spinnerDiv = document.getElementById("nexa-npm-spinner");
    if (!spinnerDiv) return;

    if (this.spinnerInterval) {
      clearInterval(this.spinnerInterval);
    }

    this.spinnerInterval = setInterval(() => {
      const random = Math.floor(Math.random() * (5 - 0 + 1)) + 0;
      spinnerDiv.innerHTML = this.spinner.charAt(random);
    }, 100);
  }

  initializeTexts() {
    const verb = document.getElementById("nexa-npm-verb");
    const mapToRegistry = document.getElementById("nexa-npm-mapToRegistry");
    const uri = document.getElementById("nexa-npm-uri");

    if (!verb || !mapToRegistry || !uri) return;

    verb.innerHTML = "verb";
    mapToRegistry.innerHTML = "mapToRegistry";

    if (this.textInterval) {
      clearInterval(this.textInterval);
    }

    this.textInterval = setInterval(() => {
      const vs = ["verb", "sill"];
      const random = Math.floor(Math.random() * (1 - 0 + 1)) + 0;

      verb.innerHTML = vs[random];

      if (verb.innerHTML === "sill") {
        verb.style.background = "#fff";
        verb.style.color = "#000";

        mapToRegistry.innerHTML = "mapToRegistry";
        uri.innerHTML = "C:\\Users\\Nexa\\AppData\\Roaming";
      } else {
        verb.style.background = "#000";
        verb.style.color = "blue";

        mapToRegistry.innerHTML = "afterAdd";
        uri.innerHTML = "uri https://nexaui.net/registry";
      }
    }, 1000);
  }

  render() {
    return `
  <div class="nexa-npm-progress">
  <div class="nexa-npm-loader-container"><span id="nexa-npm-loader"></span><span id="nexa-npm-loader-points">[ . . . . . . . . . . . . . . . . . . . . ]</span></div>
  <span id="nexa-npm-spinner"></span>
  <span>fetchMetadata:</span>
  <span id="nexa-npm-verb">verb</span>
  <span id="nexa-npm-mapToRegistry">mapToRegistry</span>
  <span id="nexa-npm-uri">uri https://nexaui.net/registry/</span>
</div>

    `;
  }

  destroy() {
    if (this.spinnerInterval) {
      clearInterval(this.spinnerInterval);
      this.spinnerInterval = null;
    }
    if (this.textInterval) {
      clearInterval(this.textInterval);
      this.textInterval = null;
    }
  }
}

