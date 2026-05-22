import { NexaFetch, nexaFetch } from "./NexaFetch.js";
import NexaEncrypt from "./NexaEncrypt.js";
import { Platform } from "react-native";
import Server from "../../../config";
import { NexaDBLite } from "./NexaDBLite.js";

/**
 * NexaStores - React Native API Store Manager
 * Provides unified API methods for React Native applications
 * Compatible with iOS and Android platforms
 * Uses NexaDBLite for session management
 * 
 * @description
 * NexaStores automatically retrieves userid from NexaDBLite session storage
 * and includes it in all API requests. The userid is fetched from:
 * - Store: "userSessions"
 * - Key: "userSession"
 * - Field: "userid"
 * 
 * API Base URL is configured from Server.API_URL in config.js
 * - Default: http://192.168.1.17/dev/api
 * 
 * @example Session Data Structure
 * {
 *   "id": "userSession",
 *   "success": true,
 *   "message": "Login berhasil",
 *   "phone": "081340890255",
 *   "user_id": 1,
 *   "userid": 1,  // <-- This field is used
 *   "user_name": "iandantrik",
 *   "email": "admin@gmail.com",
 *   "status": "admin",
 *   "role": "admin",
 *   "isLoggedIn": true,
 *   ...
 * }
 * 
 * @example Basic Usage
 * import { NexaStores } from './NexaStores';
 * 
 * // Default usage (packages = "packages")
 * const store = NexaStores();
 * 
 * // Custom packages name
 * const store = NexaStores({ packages: "myPackages" });
 * 
 * // The userid will be automatically included from session
 * const result = await store.api("endpoint").method({ data: "value" });
 * 
 * @example Configuration
 * // config.js
 * const hosts = "http://192.168.1.17/dev";
 * const Server = {
 *   API_URL: hosts + "/api",  // Used by NexaStores
 *   FILE_URL: hosts,
 * };
 * 
 * @features
 * - Automatic userid injection from NexaDBLite session
 * - Fallback to NEXA.userId if available (web/electron compatibility)
 * - API endpoint from Server.API_URL (config.js)
 * - Encrypted communication support
 * - Method chaining with Proxy
 * - React Native compatible (iOS & Android)
 * - No dependency on global NEXA object
 */

// Helper function to normalize URL paths and avoid double slashes
function normalizeUrl(...parts) {
  return parts
    .map((part, index) => {
      if (index === 0) {
        // For the first part (base URL), remove trailing slash
        return part.replace(/\/+$/, "");
      } else {
        // For other parts, remove leading and trailing slashes
        return part.replace(/^\/+|\/+$/g, "");
      }
    })
    .join("/");
}

export function NexaStores(options = {}) {
  const nexaEncrypt = new NexaEncrypt("nexa-default-secret-key-2025");
  const baseURL = Server.API_URL + "/user";
  const CURL = Server.API_URL;
  const baseAPI = Server.API_URL;
  const modelsURL = baseAPI + "/outside";
  
  // React Native compatible: tidak bergantung pada NEXA global object
  const packagesName = options.packages || "packages";
  const settext = packagesName + "Controllers";
  let CapitalControllers = settext.charAt(0).toUpperCase() + settext.slice(1);

  /**
   * Get userid from NexaDBLite session storage
   * @returns {Promise<number|null>} User ID or null if not found
   */
  const getUserId = async () => {
    try {
      const storedUserData = await NexaDBLite.get("userSessions", "userSession");
      if (storedUserData && storedUserData.userid) {
        return storedUserData.userid;
      }
      // Fallback: cek global NEXA jika ada (untuk web/electron compatibility)
      if (typeof NEXA !== 'undefined' && NEXA.userId) {
        return NEXA.userId;
      }
      return null;
    } catch (error) {
      if (__DEV__) {
        console.error("Error getting userid from session:", error);
      }
      // Fallback: cek global NEXA jika ada
      if (typeof NEXA !== 'undefined' && NEXA.userId) {
        return NEXA.userId;
      }
      return null;
    }
  };

  // ✅ Helper: Hapus double slash kecuali setelah "http(s):"
  const cleanURL = (url) => url.replace(/([^:]\/)\/+/g, "$1");

  // ✅ Helper: Gabung URL dengan aman
  const joinURL = (...parts) => cleanURL(parts.join("/"));

  // ✅ Helper: Cek apakah userid valid (tidak false/null/undefined)
  const hasValidUserId = (userid) => {
    return userid !== null && userid !== undefined && userid !== false;
  };

  // ✅ Helper: Tambahkan userid ke data (hanya jika userid valid)
  const addUserId = async (data) => {
    const userid = await getUserId();
    
    if (!hasValidUserId(userid)) {
      return data;
    }
    if (data === null || data === undefined) {
      return { userid };
    }
    // React Native: Check for FormData compatibility
    if (typeof data === 'object' && !Array.isArray(data)) {
      // Skip FormData objects (React Native uses different FormData implementation)
      if (data.constructor && data.constructor.name === 'FormData') {
        return data;
      }
      return { ...data, userid };
    }
    return data;
  };

  return {
    /**
     * Basic API method with method chaining support
     * React Native compatible - works on iOS and Android
     * @param {string} row - API endpoint path
     * @param {Object} options - Request options
     * @returns {Promise|Proxy} API response or chainable proxy
     */
    url: function (row, options = {}) {
      if (
        typeof options === "undefined" ||
        (typeof options === "object" && Object.keys(options).length === 0)
      ) {
        return new Proxy(
          {},
          {
            get: function (target, method) {
              return async function (data) {
                try {
                  const url = joinURL(CURL, row, method);
                  const result = await nexaFetch.post(url, await addUserId(data || {}));
                  return result;
                } catch (error) {
                  if (__DEV__) {
                    console.error("API Error:", error);
                  }
                  throw error;
                }
              };
            },
          }
        );
      }

      return (async function () {
        try {
          const url = joinURL(CURL, row);
          const data = await nexaFetch.post(url, await addUserId(options));
          return data;
        } catch (error) {
          if (__DEV__) {
            console.error("API Error:", error);
          }
          throw error;
        }
      })();
    },

    /**
     * API method with method chaining support (baseAPI)
     * React Native compatible - works on iOS and Android
     * @param {string} row - API endpoint path
     * @param {Object} options - Request options
     * @returns {Promise|Proxy} API response or chainable proxy
     */
    api: function (row, options = {}) {
      if (
        typeof options === "undefined" ||
        (typeof options === "object" && Object.keys(options).length === 0)
      ) {
        return new Proxy(
          {},
          {
            get: function (target, method) {
              return async function (data) {
                try {
                  const url = joinURL(baseAPI, row, method);
                  const result = await nexaFetch.post(url, await addUserId(data || {}));
                  return result;
                } catch (error) {
                  if (__DEV__) {
                    console.error("API Error:", error);
                  }
                  throw error;
                }
              };
            },
          }
        );
      }

      return (async function () {
        try {
          const url = joinURL(baseAPI, row);
          const data = await nexaFetch.post(url, await addUserId(options));
          return data;
        } catch (error) {
          if (__DEV__) {
            console.error("API Error:", error);
          }
          throw error;
        }
      })();
    },

    /**
     * GET request method
     * React Native compatible - works on iOS and Android
     * @param {string} row - API endpoint path
     * @param {Object} options - Request options
     * @returns {Promise} API response
     */
    get: async function (row, options = {}) {
      try {
        const url = joinURL(baseURL, row);
        const data = await nexaFetch.get(url, await addUserId(options));
        return data;
      } catch (error) {
        if (__DEV__) {
          console.error("GET Error:", error);
        }
        throw error;
      }
    },

    /**
     * POST request method
     * React Native compatible - works on iOS and Android
     * @param {string} row - API endpoint path
     * @param {Object} endpoints - Request data
     * @param {Object} options - Request options
     * @returns {Promise} API response
     */
    post: async function (row, endpoints, options = {}) {
      try {
        const url = joinURL(baseURL, row);
        const data = await nexaFetch.post(baseURL, await addUserId(endpoints), await addUserId(options));
        return data;
      } catch (error) {
        if (__DEV__) {
          console.error("POST Error:", error);
        }
        throw error;
      }
    },
    /**
     * Dynamic package controller method
     * React Native compatible - works on iOS and Android
     * Supports encrypted communication with backend controllers
     * @param {string} row - Method name
     * @param {Object} endpoints - Request data
     * @param {Object} options - Request options
     * @returns {Promise|Proxy} API response or chainable proxy
     */
    [packagesName]: function (row, endpoints, options = {}) {
      // If row is not provided, return a proxy object for method chaining
      if (row === undefined) {
        return new Proxy(
          {},
          {
            get: function (target, method) {
              return async function (data) {
                try {
                  // Tambahkan userid ke data sebelum di-encrypt
                  const userid = await getUserId();
                  const dataWithUserId = hasValidUserId(userid) 
                    ? { ...(data || {}), userid: userid }
                    : (data || {});
                  
                  const dataKey = {
                    controllers: CapitalControllers,
                    method: method,
                    params: await nexaEncrypt.encryptJson(dataWithUserId),
                  };
                  const url = cleanURL(baseAPI + "/outside");
            
                  const result = await nexaFetch.post(
                    url,
                    dataKey,
                    options
                  );
                  return result;
                } catch (error) {
                  if (__DEV__) {
                    console.error("POST Error:", error);
                  }
                  throw error;
                }
              };
            },
          }
        );
      }

      // Original functionality for object-based calls
      return (async function () {
        try {
          // Tambahkan userid ke endpoints sebelum di-encrypt
          const userid = await getUserId();
          const endpointsWithUserId = hasValidUserId(userid) 
            ? { ...(endpoints || {}), userid: userid }
            : (endpoints || {});
          
          const dataKey = {
            controllers: CapitalControllers,
            method: row,
            params: await nexaEncrypt.encryptJson(endpointsWithUserId),
          };
          const data = await nexaFetch.post(
            baseURL,
            dataKey,
            options
          );
          return data;
        } catch (error) {
          if (__DEV__) {
            console.error("POST Error:", error);
          }
          throw error;
        }
      })();
    },

    /**
     * Dynamic package controller - supports any package
     * React Native compatible - works on iOS and Android
     * Supports encrypted communication with backend controllers
     * @param {string} packageName - Package name
     * @returns {Proxy} Chainable proxy for package methods
     */
    package: function (packageName) {
      const packageControllers = packageName + "Controllers";
      const lowerbaseURL = packageName.toLowerCase();
      const fitBaseURL = Server.API_URL;
      const capitalizedControllers =
        packageControllers.charAt(0).toUpperCase() +
        packageControllers.slice(1);
   
      // Return a proxy object that can handle method calls directly
      return new Proxy(
        {},
        {
          get: function (target, method) {
            return async function (data) {
              try {
                // Tambahkan userid ke data sebelum di-encrypt
                const userid = await getUserId();
                const dataWithUserId = hasValidUserId(userid) 
                  ? { ...(data || {}), userid: userid }
                  : (data || {});
                 
                const dataKey = {
                  controllers: capitalizedControllers,
                  method: method,
                  params: await nexaEncrypt.encryptJson(dataWithUserId),
                };
                const url = cleanURL(fitBaseURL + "/outside");
                if (__DEV__) {
                  console.log("[NexaStores] package() URL:", url, "method:", method, "controller:", capitalizedControllers);
                }
                const result = await nexaFetch.post(
                  url,
                  dataKey,
                  {}
                );
                return result;
              } catch (error) {
                if (__DEV__) {
                  console.error("POST Error:", error);
                }
                throw error;
              }
            };
          },
        }
      );
    },

    /**
     * Models method - interact with backend models
     * React Native compatible - works on iOS and Android
     * Supports encrypted communication with backend models
     * @param {string} row - Model name
     * @param {Object} endpoints - Request data
     * @param {Object} options - Request options
     * @returns {Promise|Proxy} API response or chainable proxy
     */
    models: function (row, endpoints, options = {}) {
      // Jika endpoints tidak diberikan, return proxy
      if (endpoints === undefined) {
        return new Proxy(
          {},
          {
            get: function (target, method) {
              return async function (...params) {
                try {
                  // Tambahkan userid ke params sebelum di-encrypt
                  const userid = await getUserId();
                  let paramsWithUserId = params;
                  if (hasValidUserId(userid)) {
                    if (params.length > 0 && typeof params[0] === 'object' && !Array.isArray(params[0])) {
                      // Jika params[0] adalah object, tambahkan userid ke dalamnya
                      paramsWithUserId = [{ ...params[0], userid: userid }, ...params.slice(1)];
                    } else {
                      // Jika tidak, tambahkan userid sebagai object terakhir
                      paramsWithUserId = [...params, { userid: userid }];
                    }
                  }
                  
                  const dataKey = {
                    model: row,
                    method: method,
                    params: await nexaEncrypt.encryptJson(paramsWithUserId),
                  };
                  const data = await nexaFetch.post(cleanURL(modelsURL), dataKey, options);
                  return data;
                } catch (error) {
                  if (__DEV__) {
                    console.error("POST Error:", error);
                  }
                  throw error;
                }
              };
            },
          }
        );
      }

      // Mode pemanggilan langsung (tanpa Proxy)
      return (async function () {
        try {
          // Tambahkan userid ke endpoints.params sebelum di-encrypt
          const userid = await getUserId();
          const paramsWithUserId = hasValidUserId(userid) 
            ? { ...(endpoints.params || {}), userid: userid }
            : (endpoints.params || {});
          
          const dataKey = {
            model: row,
            method: endpoints.method,
            ...endpoints,
            params: await nexaEncrypt.encryptJson(paramsWithUserId),
          };

          const data = await nexaFetch.post(cleanURL(modelsURL), dataKey, options);
          return data;
        } catch (error) {
          if (__DEV__) {
            console.error("POST Error:", error);
          }
          throw error;
        }
      })();
    },


    /**
     * Events method - handle event-based communication
     * React Native compatible - works on iOS and Android
     * Supports encrypted communication with backend events
     * @param {string} row - Event path
     * @param {Object} endpoints - Request data
     * @param {Object} options - Request options
     * @returns {Promise|Proxy} API response or chainable proxy
     */
    events: function (row, endpoints, options = {}) {
      const controllers = cleanURL(packagesName + "/FetchEvents");

      // If endpoints is not provided, return a proxy object for method chaining
      if (endpoints === undefined) {
        return new Proxy(
          {},
          {
            get: function (target, method) {
              return async function (data) {
                try {
                  // Tambahkan userid ke data sebelum di-encrypt
                  const userid = await getUserId();
                  const dataWithUserId = hasValidUserId(userid) 
                    ? { ...(data || {}), userid: userid }
                    : (data || {});
                  
                  const dataKey = {
                    path: convertToPath(
                      row + method.charAt(0).toUpperCase() + method.slice(1)
                    ),
                    data: await nexaEncrypt.encryptJson(dataWithUserId),
                  };

                  const result = await nexaFetch.post(
                    controllers,
                    dataKey,
                    options
                  );
                  return result;
                } catch (error) {
                  if (__DEV__) {
                    console.error("POST Error:", error);
                  }
                  throw error;
                }
              };
            },
          }
        );
      }

      // Original functionality for object-based calls
      return (async function () {
        try {
          // Tambahkan userid ke endpoints sebelum di-encrypt
          const userid = await getUserId();
          const endpointsWithUserId = hasValidUserId(userid) 
            ? { ...(endpoints || {}), userid: userid }
            : (endpoints || {});
          
          const dataKey = {
            path: convertToPath(row),
            data: await nexaEncrypt.encryptJson(endpointsWithUserId),
          };
          const data = await nexaFetch.post(controllers, dataKey, options);
          return data;
        } catch (error) {
          if (__DEV__) {
            console.error("POST Error:", error);
          }
          throw error;
        }
      })();
    },
    /**
     * PUT request method
     * React Native compatible - works on iOS and Android
     * @param {string} row - API endpoint path
     * @param {Object} data - Request data
     * @param {Object} options - Request options
     * @returns {Promise} API response
     */
    put: async function (row, data, options = {}) {
      try {
        const url = joinURL(baseURL, row);
        const result = await nexaFetch.put(url, await addUserId(data), await addUserId(options));
        return result;
      } catch (error) {
        if (__DEV__) {
          console.error("PUT Error:", error);
        }
        throw error;
      }
    },

    /**
     * DELETE request method
     * React Native compatible - works on iOS and Android
     * @param {string} row - API endpoint path
     * @param {Object} options - Request options
     * @returns {Promise} API response
     */
    delete: async function (row, options = {}) {
      try {
        const url = joinURL(baseURL, row);
        const result = await nexaFetch.delete(url, await addUserId(options));
        return result;
      } catch (error) {
        if (__DEV__) {
          console.error("DELETE Error:", error);
        }
        throw error;
      }
    },

    /**
     * Helper function - returns controller configuration
     * React Native compatible - works on iOS and Android
     * @returns {Object} Controller configuration
     */
    helper: function () {
      // React Native compatible: tidak bergantung pada NEXA global
      if (typeof NEXA !== 'undefined' && NEXA.controllers) {
        return NEXA.controllers;
      }
      return {
        packages: packagesName,
        base_url: Server.API_URL,
      };
    },
  };
}

/**
 * Convert camelCase string to path format
 * React Native compatible helper function
 * @param {string} str - CamelCase string
 * @returns {string} Path formatted string
 * @example convertToPath("UserLogin") => "User/login"
 */
function convertToPath(str) {
  const match = str.match(/^([A-Z][a-z]+)([A-Z].*)$/);
  if (!match) return str;
  return `${match[1]}/${match[2].charAt(0).toLowerCase()}${match[2].slice(1)}`;
}

// Export for React Native
export default NexaStores;
