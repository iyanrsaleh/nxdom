/**
 * NexaFetch - Simplified Fetch API wrapper for Nexa UI
 * Provides easy-to-use methods for HTTP requests with automatic JSON handling,
 * error handling, loading states, and request/response interceptors
 */
export class NexaFetch {
  constructor(options = {}) {
    this.baseURL = options.baseURL || '';
    this.defaultHeaders = {
      'Content-Type': 'application/json',
      ...options.headers
    };
    this.timeout = options.timeout || 30000;
    this.requestInterceptors = [];
    this.responseInterceptors = [];
    this.loadingCallbacks = [];
    this.errorCallbacks = [];
    this.enableGetDedupe = options.enableGetDedupe !== false;
    this.getCacheTTL = Number.isFinite(options.getCacheTTL) ? options.getCacheTTL : 1500;
    this._inflightGetRequests = new Map();
    this._resolvedGetCache = new Map();
  }

  /**
   * Add request interceptor
   * @param {Function} interceptor - Function to modify request config
   */
  addRequestInterceptor(interceptor) {
    this.requestInterceptors.push(interceptor);
  }

  /**
   * Add response interceptor
   * @param {Function} interceptor - Function to modify response
   */
  addResponseInterceptor(interceptor) {
    this.responseInterceptors.push(interceptor);
  }

  /**
   * Add loading state callback
   * @param {Function} callback - Function called when loading state changes
   */
  onLoading(callback) {
    this.loadingCallbacks.push(callback);
  }

  /**
   * Add error callback
   * @param {Function} callback - Function called when error occurs
   */
  onError(callback) {
    this.errorCallbacks.push(callback);
  }

  /**
   * Trigger loading callbacks
   * @param {boolean} isLoading - Loading state
   */
  _triggerLoading(isLoading) {
    this.loadingCallbacks.forEach(callback => {
      try {
        callback(isLoading);
      } catch (error) {
        console.warn('Loading callback error:', error);
      }
    });
  }

  /**
   * Trigger error callbacks
   * @param {Error} error - Error object
   */
  _triggerError(error) {
    this.errorCallbacks.forEach(callback => {
      try {
        callback(error);
      } catch (err) {
        console.warn('Error callback error:', err);
      }
    });
  }

  /**
   * Build full URL
   * @param {string} url - Endpoint URL
   * @returns {string} Full URL
   */
  _buildURL(url) {
    if (url.startsWith('http://') || url.startsWith('https://')) {
      return url;
    }
    return this.baseURL + url;
  }

  _buildGetCacheKey(url, options = {}) {
    const fullURL = this._buildURL(url);
    const headers = {
      ...this.defaultHeaders,
      ...(options.headers || {}),
    };
    return `${fullURL}::${JSON.stringify(headers)}`;
  }

  _clearRelatedGetCache(url) {
    const fullURL = this._buildURL(url);
    let parsed;
    try {
      parsed = new URL(fullURL);
    } catch {
      this.clearGetCache();
      return;
    }

    const normalizedPath = parsed.pathname.replace(/\/+$/, '');
    for (const key of this._resolvedGetCache.keys()) {
      const [cachedURL] = key.split('::');
      if (!cachedURL) continue;
      try {
        const cachedParsed = new URL(cachedURL);
        const cachedPath = cachedParsed.pathname.replace(/\/+$/, '');
        if (
          cachedParsed.origin === parsed.origin &&
          (cachedPath === normalizedPath ||
            cachedPath.startsWith(`${normalizedPath}/`) ||
            normalizedPath.startsWith(`${cachedPath}/`))
        ) {
          this._resolvedGetCache.delete(key);
        }
      } catch {
        // Ignore malformed cache keys and continue cleanup.
      }
    }
  }

  /**
   * Apply request interceptors
   * @param {Object} config - Request configuration
   * @returns {Object} Modified configuration
   */
  _applyRequestInterceptors(config) {
    return this.requestInterceptors.reduce((acc, interceptor) => {
      return interceptor(acc) || acc;
    }, config);
  }

  /**
   * Apply response interceptors
   * @param {Response} response - Fetch response
   * @returns {Response} Modified response
   */
  _applyResponseInterceptors(response) {
    return this.responseInterceptors.reduce((acc, interceptor) => {
      return interceptor(acc) || acc;
    }, response);
  }

  /**
   * Create timeout promise
   * @param {number} timeout - Timeout in milliseconds
   * @returns {Promise} Timeout promise
   */
  _createTimeoutPromise(timeout) {
    return new Promise((_, reject) => {
      setTimeout(() => {
        reject(new Error(`Request timeout after ${timeout}ms`));
      }, timeout);
    });
  }

  /**
   * Core fetch method
   * @param {string} url - Request URL
   * @param {Object} options - Fetch options
   * @returns {Promise} Response promise
   */
  async _fetch(url, options = {}) {
    const config = {
      url: this._buildURL(url),
      method: options.method || 'GET',
      headers: {
        ...this.defaultHeaders,
        ...options.headers
      },
      ...options
    };

    // Apply request interceptors
    const finalConfig = this._applyRequestInterceptors(config);

    this._triggerLoading(true);

    try {

      const fetchPromise = fetch(finalConfig.url, {
        method: finalConfig.method,
        headers: finalConfig.headers,
        body: finalConfig.body,
        mode: finalConfig.mode,
        credentials: finalConfig.credentials,
        cache: finalConfig.cache,
        redirect: finalConfig.redirect,
        referrer: finalConfig.referrer,
        referrerPolicy: finalConfig.referrerPolicy,
        integrity: finalConfig.integrity,
        keepalive: finalConfig.keepalive,
        signal: finalConfig.signal
      });

      const timeoutPromise = this._createTimeoutPromise(this.timeout);
      const response = await Promise.race([fetchPromise, timeoutPromise]);

      if (!response.ok) {
        throw new Error(`HTTP Error: ${response.status} ${response.statusText}`);
      }

      // Apply response interceptors
      const finalResponse = this._applyResponseInterceptors(response);

      return finalResponse;
    } catch (error) {
      this._triggerError(error);
      throw error;
    } finally {
      this._triggerLoading(false);
    }
  }

  /**
   * GET request
   * @param {string} url - Request URL
   * @param {Object} options - Request options
   * @returns {Promise} Response data
   */
  async get(url, options = {}) {
    const dedupeEnabled = options.dedupe !== false && this.enableGetDedupe;
    const ttl = Number.isFinite(options.cacheTTL) ? options.cacheTTL : this.getCacheTTL;

    if (!dedupeEnabled) {
      const response = await this._fetch(url, { ...options, method: 'GET' });
      return this._parseResponse(response);
    }

    const key = this._buildGetCacheKey(url, options);
    const now = Date.now();
    const resolvedCache = this._resolvedGetCache.get(key);
    if (resolvedCache && resolvedCache.expiresAt > now) {
      return resolvedCache.data;
    }

    if (this._inflightGetRequests.has(key)) {
      return this._inflightGetRequests.get(key);
    }

    const requestPromise = (async () => {
      const response = await this._fetch(url, { ...options, method: 'GET' });
      const data = await this._parseResponse(response);
      if (ttl > 0) {
        this._resolvedGetCache.set(key, {
          data,
          expiresAt: Date.now() + ttl,
        });
      }
      return data;
    })();

    this._inflightGetRequests.set(key, requestPromise);
    try {
      return await requestPromise;
    } finally {
      this._inflightGetRequests.delete(key);
    }
  }

  /**
   * POST request
   * @param {string} url - Request URL
   * @param {Object} data - Request data
   * @param {Object} options - Request options
   * @returns {Promise} Response data
   */
  async post(url, data = null, options = {}) {
    const config = {
      ...options,
      method: 'POST'
    };

    if (data) {
      config.body = this._prepareBody(data, options.headers);
    }

    const response = await this._fetch(url, config);
    const parsed = await this._parseResponse(response);
    this._clearRelatedGetCache(url);
    return parsed;
  }

  /**
   * PUT request
   * @param {string} url - Request URL
   * @param {Object} data - Request data
   * @param {Object} options - Request options
   * @returns {Promise} Response data
   */
  async put(url, data = null, options = {}) {
    const config = {
      ...options,
      method: 'PUT'
    };

    if (data) {
      config.body = this._prepareBody(data, options.headers);
    }

    const response = await this._fetch(url, config);
    const parsed = await this._parseResponse(response);
    this._clearRelatedGetCache(url);
    return parsed;
  }

  /**
   * PATCH request
   * @param {string} url - Request URL
   * @param {Object} data - Request data
   * @param {Object} options - Request options
   * @returns {Promise} Response data
   */
  async patch(url, data = null, options = {}) {
    const config = {
      ...options,
      method: 'PATCH'
    };

    if (data) {
      config.body = this._prepareBody(data, options.headers);
    }

    const response = await this._fetch(url, config);
    const parsed = await this._parseResponse(response);
    this._clearRelatedGetCache(url);
    return parsed;
  }

  /**
   * DELETE request
   * @param {string} url - Request URL
   * @param {Object} options - Request options
   * @returns {Promise} Response data
   */
  async delete(url, options = {}) {
    const response = await this._fetch(url, { ...options, method: 'DELETE' });
    const parsed = await this._parseResponse(response);
    this._clearRelatedGetCache(url);
    return parsed;
  }

  /**
   * Upload file
   * @param {string} url - Upload URL
   * @param {FormData|File} data - File data
   * @param {Object} options - Request options
   * @returns {Promise} Response data
   */
  async upload(url, data, options = {}) {
    const config = {
      ...options,
      method: 'POST',
      headers: {
        // Remove Content-Type for FormData to let browser set it with boundary
        ...options.headers
      }
    };

    // Remove Content-Type for file uploads
    if (data instanceof FormData || data instanceof File) {
      delete config.headers['Content-Type'];
    }

    if (data instanceof File) {
      const formData = new FormData();
      formData.append('file', data);
      config.body = formData;
    } else {
      config.body = data;
    }

    const response = await this._fetch(url, config);
    return this._parseResponse(response);
  }

  /**
   * Prepare request body
   * @param {*} data - Request data
   * @param {Object} headers - Request headers
   * @returns {string|FormData} Prepared body
   */
  _prepareBody(data, headers = {}) {
    if (data instanceof FormData || data instanceof File || data instanceof Blob) {
      return data;
    }

    if (typeof data === 'string') {
      return data;
    }

    // Check if content type is explicitly set to form data
    const contentType = headers['Content-Type'] || headers['content-type'];
    if (contentType && contentType.includes('application/x-www-form-urlencoded')) {
      return new URLSearchParams(data).toString();
    }

    // Default to JSON
    return JSON.stringify(data);
  }

  /**
   * Parse response based on content type
   * @param {Response} response - Fetch response
   * @returns {Promise} Parsed response data
   */
  async _parseResponse(response) {
    const contentType = response.headers.get('content-type');

    if (!contentType) {
      return response.text();
    }

    if (contentType.includes('application/json')) {
      return response.json();
    }

    if (contentType.includes('text/')) {
      return response.text();
    }

    if (contentType.includes('application/octet-stream') || 
        contentType.includes('image/') || 
        contentType.includes('video/') || 
        contentType.includes('audio/')) {
      return response.blob();
    }

    return response.text();
  }

  /**
   * Create a new instance with different configuration
   * @param {Object} options - Configuration options
   * @returns {NexaFetch} New instance
   */
  create(options = {}) {
    return new NexaFetch({
      baseURL: this.baseURL,
      headers: this.defaultHeaders,
      timeout: this.timeout,
      ...options
    });
  }

  /**
   * Set default headers
   * @param {Object} headers - Headers to set
   */
  setHeaders(headers) {
    this.defaultHeaders = { ...this.defaultHeaders, ...headers };
  }

  /**
   * Set authorization header
   * @param {string} token - Authorization token
   * @param {string} type - Token type (Bearer, Basic, etc.)
   */
  setAuth(token, type = 'Bearer') {
    this.setHeaders({
      'Authorization': `${type} ${token}`
    });
  }

  /**
   * Remove authorization header
   */
  removeAuth() {
    delete this.defaultHeaders['Authorization'];
  }

  /**
   * Set base URL
   * @param {string} baseURL - Base URL
   */
  setBaseURL(baseURL) {
    this.baseURL = baseURL;
  }

  /**
   * Set timeout
   * @param {number} timeout - Timeout in milliseconds
   */
  setTimeout(timeout) {
    this.timeout = timeout;
  }

  /**
   * Clear resolved GET cache entries.
   * @param {string} url - Optional URL to clear specific cache key.
   */
  clearGetCache(url = null) {
    if (!url) {
      this._resolvedGetCache.clear();
      return;
    }
    const fullURL = this._buildURL(url);
    for (const key of this._resolvedGetCache.keys()) {
      if (key.startsWith(`${fullURL}::`)) {
        this._resolvedGetCache.delete(key);
      }
    }
  }
}

// Create default instance
export const nexaFetch = new NexaFetch();

// Export default instance
export default nexaFetch;
