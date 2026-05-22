/** true jalan di jendela Electron (bukan browser biasa). */
function nexaRunsInElectron() {
    return typeof navigator !== 'undefined' && /Electron\//.test(navigator.userAgent || '');
}

export class NexaGeolocation {
    constructor(options = {}) {
        // Default options untuk Geolocation API
        // Di Electron, enableHighAccuracy:true memicu Network Location Provider Google → sering HTTP 403 & code 2.
        // ipFallback: perkiraan koordinat dari layanan IP publik jika navigator gagal (tanpa GOOGLE_API_KEY).
        this.defaultOptions = {
            enableHighAccuracy: !nexaRunsInElectron(),
            ipFallback: nexaRunsInElectron(),
            timeout: 10000,
            maximumAge: 300000,
        };
        
        // Merge dengan options yang diberikan user
        this.options = { ...this.defaultOptions, ...options };
        
        // Status tracking
        this.isWatching = false;
        this.watchId = null;
        this.lastPosition = null;
        
        // Event callbacks
        this.onSuccess = null;
        this.onError = null;
        this.onUpdate = null;
        
        // Check browser support
        this.isSupported = this.checkSupport();
    }
    
    /**
     * Cek apakah browser mendukung Geolocation API
     */
    checkSupport() {
        if (!navigator.geolocation) {
            console.error('Geolocation API tidak didukung oleh browser ini');
            return false;
        }
        return true;
    }

    /**
     * Posisi kasar dari IP (tanpa GOOGLE_API_KEY) — untuk Electron saat network location Google 403.
     * @returns {Promise<object>}
     */
    async tryIpGeolocationFallback() {
        const endpoints = [
            async () => {
                const r = await fetch('https://ipapi.co/json/', { credentials: 'omit' });
                if (!r.ok) throw new Error('ipapi');
                const j = await r.json();
                if (j.error || j.latitude == null || j.longitude == null) throw new Error('ipapi');
                return { lat: j.latitude, lng: j.longitude };
            },
            async () => {
                const r = await fetch(
                    'http://ip-api.com/json/?fields=status,message,lat,lon',
                    { credentials: 'omit' }
                );
                if (!r.ok) throw new Error('ip-api');
                const j = await r.json();
                if (j.status !== 'success' || j.lat == null || j.lon == null) throw new Error('ip-api');
                return { lat: j.lat, lng: j.lon };
            },
        ];
        let lastErr;
        for (const fn of endpoints) {
            try {
                const { lat, lng } = await fn();
                if (typeof lat !== 'number' || typeof lng !== 'number' || Number.isNaN(lat + lng)) continue;
                return this.formatApproximatePosition(lat, lng, 50000, 'ip');
            } catch (e) {
                lastErr = e;
            }
        }
        throw lastErr || new Error('IP geolocation failed');
    }

    /**
     * Sama bentuknya dengan formatPosition; akurasi besar (perkiraan kota/wilayah).
     */
    formatApproximatePosition(latitude, longitude, accuracyMeters, source) {
        return {
            latitude,
            longitude,
            accuracy: accuracyMeters,
            altitude: null,
            altitudeAccuracy: null,
            heading: null,
            speed: null,
            timestamp: Date.now(),
            datetime: new Date(),
            coordinates: `${latitude}, ${longitude}`,
            googleMapsUrl: `https://www.google.com/maps?q=${latitude},${longitude}`,
            distanceTo: (lat, lng) => this.calculateDistance(latitude, longitude, lat, lng),
            raw: { source, approximate: true },
        };
    }
    
    /**
     * Mendapatkan posisi saat ini (sekali)
     * @param {Object} customOptions - Opsi khusus untuk request ini
     * @returns {Promise} Promise yang resolve dengan data posisi
     */
    getCurrentPosition(customOptions = {}) {
        return new Promise((resolve, reject) => {
            const options = { ...this.options, ...customOptions };
            const useIpFallback = options.ipFallback !== false && nexaRunsInElectron();

            const fail = (errorData) => {
                if (this.onError) this.onError(errorData);
                reject(errorData);
            };

            if (!this.isSupported) {
                if (useIpFallback) {
                    this.tryIpGeolocationFallback()
                        .then((data) => {
                            this.lastPosition = data;
                            if (this.onSuccess) this.onSuccess(data);
                            resolve(data);
                        })
                        .catch(() => {
                            fail(this.createError('NOT_SUPPORTED', 'Geolocation API tidak didukung'));
                        });
                    return;
                }
                reject(this.createError('NOT_SUPPORTED', 'Geolocation API tidak didukung'));
                return;
            }
            
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const positionData = this.formatPosition(position);
                    this.lastPosition = positionData;
                    
                    if (this.onSuccess) {
                        this.onSuccess(positionData);
                    }
                    
                    resolve(positionData);
                },
                async (error) => {
                    const unavailable = error && error.code === 2;
                    if (useIpFallback && unavailable) {
                        try {
                            console.info(
                                '[NexaGeolocation] navigator.geolocation gagal (sering 403 Google di Electron) — memakai perkiraan lokasi dari IP.'
                            );
                            const data = await this.tryIpGeolocationFallback();
                            this.lastPosition = data;
                            if (this.onSuccess) this.onSuccess(data);
                            resolve(data);
                            return;
                        } catch {
                            /* lanjut ke error asli */
                        }
                    }
                    const errorData = this.handleError(error);
                    fail(errorData);
                },
                options
            );
        });
    }
    
    /**
     * Memantau perubahan posisi secara real-time
     * @param {Object} customOptions - Opsi khusus untuk watching
     * @returns {Number} Watch ID untuk menghentikan tracking
     */
    watchPosition(customOptions = {}) {
        if (!this.isSupported) {
            console.error('Geolocation API tidak didukung');
            return null;
        }
        
        if (this.isWatching) {
            console.warn('Position watching sudah aktif');
            return this.watchId;
        }
        
        const options = { ...this.options, ...customOptions };
        
        this.watchId = navigator.geolocation.watchPosition(
            (position) => {
                const positionData = this.formatPosition(position);
                this.lastPosition = positionData;
                
                // Trigger callback untuk update
                if (this.onUpdate) {
                    this.onUpdate(positionData);
                }
                
                // Trigger callback success juga
                if (this.onSuccess) {
                    this.onSuccess(positionData);
                }
            },
            (error) => {
                const errorData = this.handleError(error);
                
                if (this.onError) {
                    this.onError(errorData);
                }
            },
            options
        );
        
        this.isWatching = true;
        return this.watchId;
    }
    
    /**
     * Menghentikan pemantauan posisi
     */
    stopWatching() {
        if (this.watchId !== null) {
            navigator.geolocation.clearWatch(this.watchId);
            this.watchId = null;
            this.isWatching = false;
        }
    }
    
    /**
     * Format data posisi menjadi object yang mudah digunakan
     * @param {GeolocationPosition} position - Raw position dari browser
     * @returns {Object} Formatted position data
     */
    formatPosition(position) {
        const coords = position.coords;
        
        return {
            // Koordinat utama
            latitude: coords.latitude,
            longitude: coords.longitude,
            accuracy: coords.accuracy,
            
            // Data tambahan (mungkin null pada beberapa device)
            altitude: coords.altitude,
            altitudeAccuracy: coords.altitudeAccuracy,
            heading: coords.heading,
            speed: coords.speed,
            
            // Timestamp
            timestamp: position.timestamp,
            datetime: new Date(position.timestamp),
            
            // Formatted strings
            coordinates: `${coords.latitude}, ${coords.longitude}`,
            googleMapsUrl: `https://www.google.com/maps?q=${coords.latitude},${coords.longitude}`,
            
            // Utility methods
            distanceTo: (lat, lng) => this.calculateDistance(coords.latitude, coords.longitude, lat, lng),
            
            // Raw data (jika diperlukan)
            raw: position
        };
    }
    
    /**
     * Menangani error dari Geolocation API
     * @param {GeolocationPositionError} error - Error dari browser
     * @returns {Object} Formatted error data
     */
    handleError(error) {
        let errorInfo = {
            code: error.code,
            message: error.message,
            timestamp: Date.now(),
            datetime: new Date()
        };
        
        // Pesan error yang lebih user-friendly
        switch (error.code) {
            case error.PERMISSION_DENIED:
                errorInfo.type = 'PERMISSION_DENIED';
                errorInfo.userMessage = 'Akses lokasi ditolak oleh user';
                errorInfo.suggestion = 'Mohon izinkan akses lokasi di browser';
                break;
                
            case error.POSITION_UNAVAILABLE:
                errorInfo.type = 'POSITION_UNAVAILABLE';
                errorInfo.userMessage = 'Informasi lokasi tidak tersedia';
                errorInfo.suggestion = nexaRunsInElectron()
                    ? 'Tanpa GOOGLE_API_KEY, navigator.geolocation sering 403. getCurrentPosition otomatis mencoba perkiraan lokasi dari IP (ipFallback, default true). Set ipFallback:false untuk menonaktifkan, atau pasang GOOGLE_API_KEY di .env.'
                    : 'Pastikan GPS aktif atau koneksi internet stabil';
                break;
                
            case error.TIMEOUT:
                errorInfo.type = 'TIMEOUT';
                errorInfo.userMessage = 'Permintaan lokasi timeout';
                errorInfo.suggestion = 'Coba lagi atau tingkatkan timeout';
                break;
                
            default:
                errorInfo.type = 'UNKNOWN_ERROR';
                errorInfo.userMessage = 'Error tidak dikenal';
                errorInfo.suggestion = 'Silakan coba lagi';
        }
        
        if (
            nexaRunsInElectron() &&
            error.code === error.POSITION_UNAVAILABLE &&
            String(error.message || '').toLowerCase().includes('google')
        ) {
            console.warn(
                '[NexaGeolocation] Google Network Location menolak permintaan (403) di Electron — gunakan enableHighAccuracy:false (default kelas ini).'
            );
        }
        console.error('Geolocation Error:', errorInfo);
        return errorInfo;
    }
    
    /**
     * Membuat custom error
     */
    createError(type, message) {
        return {
            type: type,
            message: message,
            userMessage: message,
            timestamp: Date.now(),
            datetime: new Date()
        };
    }
    
    /**
     * Menghitung jarak antara dua koordinat (dalam meter)
     * Menggunakan Haversine formula
     */
    calculateDistance(lat1, lng1, lat2, lng2) {
        const R = 6371e3; // Radius bumi dalam meter
        const φ1 = lat1 * Math.PI/180;
        const φ2 = lat2 * Math.PI/180;
        const Δφ = (lat2-lat1) * Math.PI/180;
        const Δλ = (lng2-lng1) * Math.PI/180;
        
        const a = Math.sin(Δφ/2) * Math.sin(Δφ/2) +
                Math.cos(φ1) * Math.cos(φ2) *
                Math.sin(Δλ/2) * Math.sin(Δλ/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        
        return R * c; // Jarak dalam meter
    }
    
    /**
     * Set callback untuk success
     */
    onPositionSuccess(callback) {
        this.onSuccess = callback;
        return this;
    }
    
    /**
     * Set callback untuk error
     */
    onPositionError(callback) {
        this.onError = callback;
        return this;
    }
    
    /**
     * Set callback untuk update (saat watching)
     */
    onPositionUpdate(callback) {
        this.onUpdate = callback;
        return this;
    }
    
    /**
     * Get status informasi
     */
    getStatus() {
        return {
            isSupported: this.isSupported,
            isWatching: this.isWatching,
            watchId: this.watchId,
            lastPosition: this.lastPosition,
            options: this.options
        };
    }
    
    /**
     * Update options
     */
    setOptions(newOptions) {
        this.options = { ...this.options, ...newOptions };
        return this;
    }
    
    /**
     * Reset ke default options
     */
    resetOptions() {
        this.options = { ...this.defaultOptions };
        return this;
    }
}

/*
=== CONTOH PENGGUNAAN LENGKAP ===

// 1. BASIC USAGE - Mendapatkan posisi sekali
const position = new NexaGeolocation();

// Menggunakan Promise
position.getCurrentPosition()
    .then(data => {
        console.log('Latitude:', data.latitude);
        console.log('Longitude:', data.longitude);
        console.log('Akurasi:', data.accuracy, 'meter');
        console.log('Google Maps URL:', data.googleMapsUrl);
    })
    .catch(error => {
        console.error('Error:', error.userMessage);
        console.log('Saran:', error.suggestion);
    });

// 2. DENGAN CUSTOM OPTIONS
const highAccuracyPosition = new NexaGeolocation({
    enableHighAccuracy: true,
    timeout: 15000,
    maximumAge: 0  // Selalu ambil posisi fresh
});

// 3. MENGGUNAKAN CALLBACKS
const callbackPosition = new NexaGeolocation();

callbackPosition
    .onPositionSuccess(data => {
        console.log('Posisi berhasil didapat:', data.coordinates);
    })
    .onPositionError(error => {
        console.error('Gagal mendapat posisi:', error.userMessage);
    });

// Ambil posisi dengan callback
callbackPosition.getCurrentPosition();

// 4. WATCHING POSITION (Real-time tracking)
const tracker = new NexaGeolocation({
    enableHighAccuracy: true,
    timeout: 5000,
    maximumAge: 1000
});

tracker
    .onPositionUpdate(data => {
        console.log('Posisi update:', data.coordinates);
        console.log('Kecepatan:', data.speed, 'm/s');
        console.log('Arah:', data.heading, 'derajat');
    })
    .onPositionError(error => {
        console.error('Tracking error:', error.userMessage);
    });

// Mulai tracking
const watchId = tracker.watchPosition();

// Hentikan tracking setelah 30 detik
setTimeout(() => {
    tracker.stopWatching();
    console.log('Tracking dihentikan');
}, 30000);

// 5. MENGHITUNG JARAK
position.getCurrentPosition().then(currentPos => {
    const jakartaLat = -6.2088;
    const jakartaLng = 106.8456;
    
    const distance = currentPos.distanceTo(jakartaLat, jakartaLng);
    console.log('Jarak ke Jakarta:', Math.round(distance/1000), 'km');
});

// 6. ADVANCED USAGE - Dengan error handling lengkap
async function getLocationWithRetry(maxRetries = 3) {
    const position = new NexaGeolocation({
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 60000
    });
    
    for (let i = 0; i < maxRetries; i++) {
        try {
            const data = await position.getCurrentPosition();
            console.log('Posisi berhasil didapat pada percobaan ke-', i + 1);
            return data;
        } catch (error) {
            console.log(`Percobaan ke-${i + 1} gagal:`, error.userMessage);
            
            if (error.type === 'PERMISSION_DENIED') {
                throw new Error('User menolak akses lokasi');
            }
            
            if (i === maxRetries - 1) {
                throw new Error('Gagal mendapat lokasi setelah ' + maxRetries + ' percobaan');
            }
            
            // Wait sebelum retry
            await new Promise(resolve => setTimeout(resolve, 2000));
        }
    }
}

// 7. MONITORING STATUS
const monitor = new NexaGeolocation();

setInterval(() => {
    const status = monitor.getStatus();
    console.log('Status:', {
        supported: status.isSupported,
        watching: status.isWatching,
        lastUpdate: status.lastPosition?.datetime
    });
}, 5000);

// 8. DYNAMIC OPTIONS
const dynamicPosition = new NexaGeolocation();

// Untuk mobile - akurasi tinggi
if (window.innerWidth < 768) {
    dynamicPosition.setOptions({
        enableHighAccuracy: true,
        timeout: 15000
    });
} else {
    // Untuk desktop - akurasi normal
    dynamicPosition.setOptions({
        enableHighAccuracy: false,
        timeout: 5000
    });
}

// 9. GEOFENCING EXAMPLE
const geofence = new NexaGeolocation();
const centerLat = -6.2088;  // Jakarta
const centerLng = 106.8456;
const radius = 1000; // 1km

geofence.onPositionUpdate(data => {
    const distance = data.distanceTo(centerLat, centerLng);
    
    if (distance <= radius) {
        console.log('Dalam area geofence');
    } else {
        console.log('Diluar area geofence, jarak:', Math.round(distance), 'meter');
    }
});

geofence.watchPosition();

// 10. EXPORT DATA
position.getCurrentPosition().then(data => {
    // Export ke berbagai format
    const exportData = {
        timestamp: data.timestamp,
        coordinates: data.coordinates,
        accuracy: data.accuracy,
        googleMapsUrl: data.googleMapsUrl,
        
        // Untuk database
        lat: data.latitude,
        lng: data.longitude,
        
        // Untuk API
        location: {
            type: "Point",
            coordinates: [data.longitude, data.latitude]  // GeoJSON format
        }
    };
    
    console.log('Export data:', exportData);
});

=== ERROR CODES YANG MUNGKIN TERJADI ===

1. PERMISSION_DENIED - User menolak akses lokasi
2. POSITION_UNAVAILABLE - GPS/lokasi tidak tersedia
3. TIMEOUT - Request timeout
4. NOT_SUPPORTED - Browser tidak mendukung Geolocation API

=== TIPS PENGGUNAAN ===

1. Selalu cek browser support sebelum menggunakan
2. Gunakan enableHighAccuracy: true untuk GPS yang lebih akurat
3. Set timeout yang reasonable (5-15 detik)
4. Gunakan maximumAge untuk cache posisi
5. Selalu handle error dengan baik
6. Untuk tracking real-time, jangan lupa stopWatching()
7. Test di HTTPS karena beberapa browser memerlukan secure context

*/