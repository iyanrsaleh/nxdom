      export async  function NotifNetwork() {
        const connection = new NXUI.NexaNetwork();
        // connection.onStatusData = (tableData) => {
        //   console.log(tableData);
        // };
       const Notif = new NXUI.Notifikasi({ autoHideDelay: 5000 });

      // Flag untuk mencegah notifikasi saat inisialisasi
      let isInitialized = false;
      
      // Hubungkan notifikasi dengan perubahan jaringan
      connection.onStatusChange = (isOnline, info, isRealChange = false) => {
        if (!isInitialized || !isRealChange) return;
        
        if (isOnline) {
          Notif.show({
            type: "success",
            title: "Jaringan Terhubung",
            subtitle: `Koneksi ${info.type} tersedia - ${info.speed}`,
            actions: false
          });
        } else {
          Notif.show({
            type: "error", 
            title: "Jaringan Terputus",
            subtitle: "Tidak ada koneksi internet",
            actions: true
          });
        }
      };

      let lastNotifiedQuality = null;
      
      connection.onQualityChange = (quality, stability, isRealChange = false) => {
        if (!isInitialized || !isRealChange) return;
        
        if (lastNotifiedQuality === quality) return;
        
        if (quality === 'Buruk' || quality === 'Sangat Buruk') {
          Notif.show({
            type: "warning",
            title: "Kualitas Jaringan Menurun", 
            subtitle: `Stabilitas: ${stability} - Kualitas: ${quality}`,
            actions: false
          });
          lastNotifiedQuality = quality;
        } else if (quality === 'Baik' && stability === 'Stabil' && lastNotifiedQuality !== 'Baik') {
          if (lastNotifiedQuality === 'Buruk' || lastNotifiedQuality === 'Sangat Buruk') {
            Notif.show({
              type: "info",
              title: "Jaringan Kembali Stabil",
              subtitle: "Koneksi sudah membaik",
              actions: false
            });
          }
          lastNotifiedQuality = quality;
        }
      };

      // Inisialisasi selesai setelah 2 detik
      setTimeout(() => {
        isInitialized = true;
      }, 2000);
  }
