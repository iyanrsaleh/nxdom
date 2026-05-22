export const standarMenu = [
   {
		id: "createPackage",
		appName: "Create Package",
		icons: "add",
		status: "development",
		deskripsi: "Menu langsung tanpa sub-folder"
	},
	{
		id: "dashboard",
		appName: "Dashboard",
		icons: "dashboard",
		status: "development",
		deskripsi: "Ringkasan aplikasi",
		children: [
			{
				type: "operation-group",
				label: "Menu Dashboard",
				icon: "dashboard_customize",
				open: true,
				count: 3,
				children: [
					{ type: "operation-leaf", label: "Overview", icon: "grid_view", actionName: "settings", actionId: "dashboard" },
					{ type: "operation-leaf", label: "Statistik", icon: "query_stats", actionName: "settings", actionId: "dashboard-stats" },
					{ type: "operation-leaf", label: "AaaaaaaaaStatistik", icon: "query_stats", actionName: "settings", actionId: "dashboard-stats-2" }
				]
			}
		]
	},
	{
		id: "laporan",
		appName: "Laporan",
		icons: "description",
		status: "development",
		deskripsi: "Daftar laporan standar",
		children: [
			{
				type: "operation-group",
				label: "Jenis Laporan",
				icon: "topic",
				open: true,
				count:false,
				children: [
					{ type: "operation-leaf", label: "Harian", icon: "today", actionName: "settings", actionId: "laporan-harian" },
					{ type: "operation-leaf", label: "Bulanan", icon: "calendar_month", actionName: "settings", actionId: "laporan-bulanan" },
					{ type: "operation-leaf", label: "Tahunan", icon: "date_range", actionName: "settings", actionId: "laporan-tahunan" }
				]
			}
		]
	},
	{
		id: "pengaturan",
		appName: "Pengaturan",
		icons: "settings",
		status: "development",
		deskripsi: "Konfigurasi aplikasi",
		children: [
			{
				type: "operation-group",
				label: "Konfigurasi",
				icon: "tune",
				open: true,
				count: 2,
				children: [
					{ type: "operation-leaf", label: "Pengguna", icon: "manage_accounts", actionName: "settings", actionId: "pengaturan-pengguna" },
					{ type: "operation-leaf", label: "Role Akses", icon: "admin_panel_settings", actionName: "settings", actionId: "pengaturan-role" }
				]
			},
			{ type: "operation-leaf", label: "Tentang Sistem", icon: "info", actionName: "settings", actionId: "pengaturan-tentang" }
		]
	},
	{
		id: "bantuan",
		appName: "Bantuan",
		icons: "help",
		status: "development",
		deskripsi: "Menu langsung tanpa sub-folder"
	}
];
