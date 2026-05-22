<?php

namespace App\Controllers\Admin\Example;

use App\System\NexaController;

class JsonProjectController extends NexaController
{
    /**
     * Tampilkan form proyek
     */
    public function index()
    {
        $this->render('dashboard/planning/form/proyek');
    }

    /**
     * Proses form proyek dengan JSON support
     * Menggunakan method bawaan NexaController
     */
    public function store()
    {
        // Cek apakah POST request
        if (!$this->isPost()) {
            $this->jsResponse([
                'success' => false,
                'message' => 'Method tidak diizinkan'
            ]);
            return;
        }

        try {
            // Ambil data request (otomatis support JSON dan form-data)
            $requestData = $this->getRequestData();
            
            // Atau ambil manual per field
            $projectData = [
                'name' => $this->input('name'),
                'description' => $this->input('description'),
                'manager_id' => $this->input('manager_id'),
                'status' => $this->input('status'),
                'priority' => $this->input('priority'),
                'start_date' => $this->input('start_date'),
                'end_date' => $this->input('end_date'),
                'budget' => $this->input('budget'),
                'progress' => $this->input('progress', 0),
                'color' => $this->input('color', '#007bff'),
                'estimated_hours' => $this->input('estimated_hours'),
                'auto_notifications' => $this->input('auto_notifications', false),
                'time_tracking' => $this->input('time_tracking', false),
                'public_visibility' => $this->input('public_visibility', false),
                'milestone_name' => $this->input('milestone_name', []),
                'milestone_date' => $this->input('milestone_date', [])
            ];

            // Simulasi simpan ke database
            $projectId = rand(1000, 9999);
            
            // Log data untuk debugging
            error_log("JSON Project saved: " . json_encode($projectData));

            // Kirim response sukses menggunakan jsData
            $this->jsData([
                'success' => true,
                'message' => 'Proyek berhasil disimpan via JSON!',
                'project_id' => $projectId,
                'project_name' => $projectData['name'],
                'created_at' => date('Y-m-d H:i:s'),
                'request_type' => $this->isAjax() ? 'AJAX/JSON' : 'Normal'
            ]);

        } catch (\Exception $e) {
            // Kirim response error
            $this->jsData([
                'success' => false,
                'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test endpoint untuk melihat data yang diterima
     */
    public function test()
    {
        if (!$this->isPost()) {
            $this->jsData([
                'success' => false,
                'message' => 'Method harus POST'
            ]);
            return;
        }

        // Debug semua data yang diterima
        $debugData = [
            'success' => true,
            'message' => 'Data berhasil diterima',
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'tidak ada',
            'is_ajax' => $this->isAjax(),
            'post_data' => $_POST,
            'all_inputs' => $this->inputs(),
            'request_data' => $this->getRequestData(),
            'raw_input' => file_get_contents('php://input')
        ];

        $this->jsData($debugData);
    }
} 