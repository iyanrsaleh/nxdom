<?php
namespace App\Controllers\Admin;
use App\System\NexaController;

/**
 * ExampleController - Contoh Form Handler yang Benar
 */
class ExampleController extends NexaController
{

    public function news($params){
        $news = $this->Storage('news')->get();
        return $news;
    }

    public function newsId($params){
        $news = $this->Storage('news')  
         ->where('slug',$params['id'])
         ->first();
        return $news;
    }


    /**
     * Index - Menampilkan form
     */
    public function index(array $params = []): void
    {
        // $this->dump($params);
//         {
//   "base_url": "http://localhost/motali",
//   "assets": "http://localhost/motali/assets/drive",
//   "page_home": "http://localhost/motali/abdulmaskhuriyanrsalehsaleh",
//   "page_index": "http://localhost/motali/abdulmaskhuriyanrsalehsaleh/example",
//   "projectName": "motali",
//   "current_url": "http://localhost/motali/abdulmaskhuriyanrsalehsaleh/example",
//   "resource_id": null,
//   "res_id": null,
//   "mapping": "",
//   "current_path": "/motali/abdulmaskhuriyanrsalehsaleh/example",
//   "slug": "abdulmaskhuriyanrsalehsaleh",
//   "slug1": "example",
//   "uid": 4569,
//   "uid_user": "",
//   "is_logged_in": true,
//   "method": "index",
//   "username": "abdulmaskhuriyanrsalehsaleh",
//   "page": "example",
//   "request_method": "GET",
//   "form_data": [],
//   "requested_method": "index",
//   "used_fallback": false,
//   "dashboard_context": true
// }
//         // Data untuk select options
        $categoryOptions = [
            ['value' => 'teknologi', 'label' => 'Teknologi'],
            ['value' => 'bisnis', 'label' => 'Bisnis'],
            ['value' => 'pendidikan', 'label' => 'Pendidikan'],
            ['value' => 'kesehatan', 'label' => 'Kesehatan'],
        ];
        
        // Initialize empty values untuk form
        $this->setData([
            'message'           => '',
            'nama'              => '',
            'email'             => '',
            'deskripsi'         => '',
            'kategori'          => '',
            'gender'            => '',
            'hobi'              => '',
            'tanggal'           => '',
            'setuju'            => '',
            'errors_nama'       => '',
            'errors_email'      => '',
            'errors_deskripsi'  => '',
            'errors_kategori'   => '',
            'errors_gender'     => '',
            'errors_hobi'       => '',
            'errors_tanggal'    => '',
            'errors_setuju'     => '',
            'action'            => $params['page_index'].'/add',
        ]);
        
        // Restore form state jika ada error dari submit sebelumnya
        $formState = $this->getState('form_add', []);
        
        // Handle SELECT - set selected berdasarkan formState
        $kategori = $formState['kategori'] ?? '';
        foreach ($categoryOptions as &$option) {
            $option['selected'] = ($option['value'] === $kategori) ? 'selected' : '';
        }
        unset($option); // Break reference
        
        // Assign category options untuk select (dengan selected sudah diset)
        $this->assignBlock('kategori_list', $categoryOptions);
        
        // Assign ke template (akan overwrite setData jika ada error)
        if (!empty($formState)) {
            $this->nexaVars($formState);
            
            // Handle RADIO checked
            $gender = $formState['gender'] ?? '';
            $this->nexaVar('checked_pria', $gender === 'pria' ? 'checked' : '');
            $this->nexaVar('checked_wanita', $gender === 'wanita' ? 'checked' : '');
            
            // Handle CHECKBOX checked
            $hobi = $formState['hobi'] ?? '';
            if (is_string($hobi)) {
                $hobi = explode(',', $hobi);
            }
            $this->nexaVar('checked_membaca', in_array('membaca', (array)$hobi) ? 'checked' : '');
            $this->nexaVar('checked_olahraga', in_array('olahraga', (array)$hobi) ? 'checked' : '');
            $this->nexaVar('checked_musik', in_array('musik', (array)$hobi) ? 'checked' : '');
            $this->nexaVar('checked_traveling', in_array('traveling', (array)$hobi) ? 'checked' : '');
            
            // Handle SINGLE CHECKBOX
            $this->nexaVar('checked_setuju', ($formState['setuju'] ?? '') === 'ya' ? 'checked' : '');
        }
        
        // Clear form state setelah ditampilkan
        $this->clearState('form_add');
    }



    /**
     * Add - Memproses form submission
     */
    public function add(array $params = []): void
    {
        // Handle checkbox array SEBELUM validasi
        // Convert hobi[] array menjadi string
        if (isset($_POST['hobi']) && is_array($_POST['hobi'])) {
            $_POST['hobi'] = implode(',', $_POST['hobi']);
        }
        
        // Setup form dengan validasi untuk berbagai tipe input
        $form = $this->createForm()
            ->fields([
                'nama'       => 'Name|3|Nama minimal 3 karakter',
                'email'      => 'Email|null|Email harus valid',
                'deskripsi'  => 'Name|10|Deskripsi minimal 10 karakter',
                'kategori'   => 'Name|1|Kategori harus dipilih',
                'gender'     => 'Name|1|Gender harus dipilih',
                'hobi'       => 'Name|1|Minimal pilih satu hobi',
                'tanggal'    => 'Name|1|Tanggal harus diisi',
                'setuju'     => 'Name|1|Anda harus menyetujui syarat & ketentuan',
            ])
            ->setSuccess('✅ Berhasil menambahkan data!')
            ->setError('❌ Mohon perbaiki kesalahan berikut!');

        // Proses form
        $result = $form->process();
        
        // Jika SUKSES
        if ($result['success']) {
            // TODO: Simpan ke database
            // $this->useModels('NamaModel', 'create', [$result['data']]);
            
            // DEBUG: Tampilkan data yang akan disimpan
            echo "<h2>✅ Data Berhasil Divalidasi!</h2>";
            echo "<h3>Data yang akan disimpan ke database:</h3>";
            echo "<pre>";
            print_r($result['data']);
            echo "</pre>";
            die("Uncomment redirect untuk menyimpan");
            
            // Prepare response dengan field yang DIBERSIHKAN
            $clearValues = true; // ✅ Kosongkan input setelah sukses
            $templateVars = $form->Response($result, $clearValues);
            
            // Simpan state untuk ditampilkan pesan sukses
            $this->setState('form_add', $templateVars);
            
            // Redirect ke halaman sebelumnya (back)
            $this->redirectBack();
        }
        
        // Jika GAGAL validasi
        else {
            // Prepare response dengan field yang TIDAK DIKOSONGKAN
            $clearValues = false; // ❌ Pertahankan input untuk diperbaiki user
            $templateVars = $form->Response($result, $clearValues);
            
            // Simpan state error
            $this->setState('form_add', $templateVars);
            
            // Redirect kembali ke halaman sebelumnya
            $this->redirectBack();
        }
    }


    public function Fetch(){
         return $this->NexaRender();
     }

   
     public function FetchEvents(array $params = []){
           $this->eventsAccess($params);
     }

     public function FetchControllers(){
        return $this->eventsControllers();
     }


    public function FetchModels(){
         $this->eventsModel();
    }


} 
