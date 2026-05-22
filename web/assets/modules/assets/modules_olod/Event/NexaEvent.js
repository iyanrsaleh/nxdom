// Flag untuk mencegah duplikasi event listener
let isEventDelegationActive = false;
let registeredElementHandlers = new Set();

export function setHandler(name, data) {
  try {
    let handlerData;

    if (data instanceof NamedNodeMap) {
      // Jika data adalah NamedNodeMap (attributes)
      const attributes = {};
      Array.from(data).forEach((attr) => {
        attributes[attr.name] = attr.value;
      });
      handlerData = {
        timestamp: new Date().toISOString(),
        attributes: attributes,
      };
    } else {
      // Jika data adalah object biasa
      handlerData = {
        ...data,
        timestamp: new Date().toISOString(),
      };
    }

    localStorage.setItem(`handler_${name}`, JSON.stringify(handlerData));
  } catch (error) {
    // Silent error handling
  }
}

// Fungsi untuk mengambil handler data dari localStorage
export function getHandler(name) {
  try {
    const data = localStorage.getItem(`handler_${name}`);
    return data ? JSON.parse(data) : null;
  } catch (error) {
    return null;
  }
}

// Fungsi untuk mendapatkan semua handler secara dinamis
export function getHandlers() {
  return Object.entries(window)
    .filter(
      ([key, value]) =>
        key.endsWith("Click") && typeof value === "function" && key !== "click"
    )
    .reduce((handlers, [key, value]) => {
      handlers[key] = value;
      return handlers;
    }, {});
}

// Fungsi untuk mendaftarkan handler ke window
export function registerHandler(name, handler) {
  if (typeof handler === "function") {
    window[name] = handler;
    // console.log(`Handler "${name}" berhasil didaftarkan`);
  }
}

// Register handler ke window
// registerHandler("addClick", addClick);
// registerHandler("editClick", editClick);
// registerHandler("deleteClick", deleteClick);

// Daftarkan fungsi handler ke window
export function EventHandler() {
  // Mencari semua elemen yang bisa diklik (button dan link)
  const clickableElements = document.querySelectorAll("a, button");
  clickableElements.forEach((element) => {
    // Cek apakah element sudah punya event listener
    const elementKey = element.id || element.tagName + Math.random();
    if (registeredElementHandlers.has(elementKey)) {
      return; // Skip jika sudah ada listener
    }

    // Mark element sebagai sudah ter-register
    registeredElementHandlers.add(elementKey);

    element.addEventListener("click", function (e) {
      e.preventDefault();
      const elementId = this.id;

      // Coba ambil data sebelumnya
      const previousData = getHandler(elementId);

      // Mencari fungsi handler yang sesuai
      const handler = window[elementId];
      if (typeof handler === "function") {
        // Simpan data sebelum menjalankan handler
        setHandler(elementId, e.srcElement.attributes);
        // Jalankan handler
        handler(e.srcElement.attributes);
      }
    });
  });
}

export function NexaEvent() {
  return {
    Handler: function () {
      // Cegah duplikasi event delegation
      if (isEventDelegationActive) {
        return;
      }

      // Modifikasi EventHandler untuk mendukung delegasi event
      const handleClick = (e) => {
        const target = e.target;
        const elementId = target.id;

        // Skip jika bukan element yang perlu di-handle
        if (!elementId) return;

        // Cek apakah ada handler terdaftar
        const handler = window[elementId];
        if (typeof handler === "function") {
          e.preventDefault();

          // Simpan data event
          setHandler(elementId, target.attributes);

          // Jalankan handler dengan data yang lebih lengkap
          handler({
            id: target.getAttribute("key"),
            type: elementId,
            attributes: target.attributes,
            element: target,
          });
        }
      };

      // Gunakan event delegation pada document level
      document.addEventListener("click", handleClick);
      isEventDelegationActive = true;
    },
    register: function (handlers) {
      // Pastikan handlers adalah object
      if (typeof handlers === "object") {
        // Register setiap handler
        Object.entries(handlers).forEach(([name, func]) => {
          registerHandler(name, func);
        });
      }
    },
    getHandler: function (name) {
      return getHandler(name);
    },
    setHandler: function (name, data) {
      return setHandler(name, data);
    },

    // Utility functions untuk debugging dan reset
    reset: function () {
      isEventDelegationActive = false;
      registeredElementHandlers.clear();
    },

    getStatus: function () {
      return {
        delegationActive: isEventDelegationActive,
        registeredElements: registeredElementHandlers.size,
      };
    },
  };
}
