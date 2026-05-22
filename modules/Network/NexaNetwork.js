export class NexaNetwork {
    constructor() {
        this.online = navigator.onLine;
        this.history = [];
        this.lastCheck = null;
        this.isInitialized = false;
        this.init();
        this.startMonitoring();
        
        // Set initialized setelah 3 detik
        setTimeout(() => {
            this.isInitialized = true;
        }, 3000);
    }

    init() {
        window.addEventListener('online', () => {
            const wasOffline = !this.online;
            this.online = true;
            if (this.onStatusChange && this.isInitialized && wasOffline) {
                this.onStatusChange(true, this.getConnectionInfo(), true);
            }
        });
        
        window.addEventListener('offline', () => {
            const wasOnline = this.online;
            this.online = false;
            if (this.onStatusChange && this.isInitialized && wasOnline) {
                this.onStatusChange(false, this.getConnectionInfo(), true);
            }
        });
    }

    startMonitoring() {
        // Check setiap 10 detik
        setInterval(() => {
            this.checkNetworkChanges();
        }, 10000);
        
        // Check pertama kali
        setTimeout(() => {
            this.checkNetworkChanges();
        }, 1000);
    }

    checkNetworkChanges() {
        const current = this.getConnectionInfo();
        
        if (this.lastCheck) {
            const changes = this.compareConnections(this.lastCheck, current);
        }
        
        // Simpan ke history
        this.history.push({
            timestamp: new Date(),
            ...current
        });
        
        // Batasi history hanya 50 entri terakhir
        if (this.history.length > 50) {
            this.history.shift();
        }
        
        this.lastCheck = current;
        
        // Analisis stabilitas
        const stability = this.analyzeStability();

        // Trigger quality change callback jika ada perubahan kualitas
        if (this.lastCheck && this.onQualityChange && this.isInitialized) {
            if (this.lastQuality !== stability.quality || this.lastStabilityStatus !== stability.status) {
                this.onQualityChange(stability.quality, stability.status, true);
                this.lastQuality = stability.quality;
                this.lastStabilityStatus = stability.status;
            }
        }

        // Trigger status data callback untuk tabel
        if (this.onStatusData && this.isInitialized) {
            const tableData = [
                { label: 'Status', value: current.online ? 'Online' : 'Offline' },
                { label: 'Jenis Koneksi', value: current.type },
                { label: 'Kecepatan', value: current.speed },
                { label: 'Downlink', value: current.downlink },
                { label: 'RTT', value: current.rtt },
                { label: 'Stabilitas', value: stability.status },
                { label: 'Kualitas', value: stability.quality },
                { label: 'Waktu Check', value: new Date().toLocaleTimeString() }
            ];
            this.onStatusData(tableData);
        }
    }

    compareConnections(old, current) {
        const changes = [];
        
        if (old.online !== current.online) {
            changes.push(`Status: ${old.online ? 'Online' : 'Offline'} → ${current.online ? 'Online' : 'Offline'}`);
        }
        
        if (old.type !== current.type) {
            changes.push(`Koneksi: ${old.type} → ${current.type}`);
        }
        
        if (old.speed !== current.speed) {
            changes.push(`Kecepatan: ${old.speed} → ${current.speed}`);
        }
        
        if (old.dataSaver !== current.dataSaver) {
            changes.push(`Data Saver: ${old.dataSaver} → ${current.dataSaver}`);
        }
        
        return {
            hasChanges: changes.length > 0,
            message: changes.join(', ')
        };
    }

    analyzeStability() {
        if (this.history.length < 3) {
            return { status: 'Menganalisis...', quality: 'Unknown' };
        }
        
        const recent = this.history.slice(-6); // 6 check terakhir (1 menit)
        const onlineCount = recent.filter(h => h.online).length;
        const typeChanges = new Set(recent.map(h => h.type)).size;
        
        let status, quality;
        
        if (onlineCount === recent.length && typeChanges === 1) {
            status = 'Stabil';
            quality = 'Baik';
        } else if (onlineCount >= recent.length * 0.8 && typeChanges <= 2) {
            status = 'Cukup Stabil';
            quality = 'Sedang';
        } else if (onlineCount >= recent.length * 0.5) {
            status = 'Tidak Stabil';
            quality = 'Buruk';
        } else {
            status = 'Sangat Buruk';
            quality = 'Sangat Buruk';
        }
        
        return { status, quality };
    }

    status() {
        return this.online;
    }

    getConnectionInfo() {
        if ('connection' in navigator) {
            const conn = navigator.connection;
            return {
                online: this.online,
                type: conn.effectiveType || 'unknown', // 4g, 3g, 2g, slow-2g
                speed: this.getSpeedEstimate(conn.effectiveType),
                downlink: conn.downlink ? `${conn.downlink} Mbps` : 'unknown',
                rtt: conn.rtt ? `${conn.rtt} ms` : 'unknown',
                saveData: conn.saveData || false, // apakah data dibatasi
                dataSaver: conn.saveData ? 'Aktif' : 'Tidak Aktif'
            };
        }
        
        return {
            online: this.online,
            type: 'unknown',
            speed: 'unknown',
            downlink: 'unknown',
            rtt: 'unknown',
            saveData: false,
            dataSaver: 'Tidak Tersedia'
        };
    }

    getSpeedEstimate(effectiveType) {
        const speeds = {
            'slow-2g': '< 50 Kbps',
            '2g': '50-70 Kbps',
            '3g': '700 Kbps - 1.5 Mbps',
            '4g': '> 1.5 Mbps'
        };
        return speeds[effectiveType] || 'unknown';
    }

    getDetailedInfo() {
        const info = this.getConnectionInfo();
        const stability = this.analyzeStability();
        return { ...info, stability };
    }

    getHistory() {
        return this.history;
    }

    getStabilityReport() {
        if (this.history.length < 2) {
            return 'Belum cukup data untuk analisis';
        }
        
        const total = this.history.length;
        const online = this.history.filter(h => h.online).length;
        const uptime = Math.round((online / total) * 100);
        
        const types = [...new Set(this.history.map(h => h.type))];
        const stability = this.analyzeStability();
        
        return {
            total,
            uptime: `${uptime}%`,
            types,
            stability: stability.status,
            quality: stability.quality
        };
    }
}