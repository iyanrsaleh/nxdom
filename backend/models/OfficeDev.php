<?php
declare(strict_types=1);

namespace App\Models;

use App\Models\Office\Analysis;
use App\Models\Office\CreateTabel;
use App\Models\Office\Ekstrak;
use App\Models\Office\Importing;
use App\Models\Office\Delete as RowDelete;
use App\Models\Office\Insert as RowInsert;
use App\Models\Office\JoinOprasi;
use App\Models\Office\JoinTabel;
use App\Models\Office\MergeTabel;
use App\Models\Office\Populate;
use App\Models\Office\SingleTabel;
use App\Models\Office\Update as RowUpdate;
use App\Models\Office\TabelData;
use App\Models\Office\TabelView;
use App\Models\Office\User;
use App\System\NexaModel;
use InvalidArgumentException;


/**
 * Model fasad Office — operasi penyimpanan, tabel dinamis, sharing, navigasi UI, dan DDL serba guna.
 *
 * Kebanyakan helper memakai `Storage()` / NexaLite lewat NexaModel. Blok utama di file ini secara berurutan:
 *
 * - User & wilayah — pengguna login, wilayah (`flag`), profil ringkas
 * - Alias & meta — nama tabel dari Metadata, listing menu, kolom/variabel
 * - CRUD dinamis (+ unggah/impor spreadsheet) — baris pada tabel berindeks
 * - Nexa Office — view/create snapshot persisten (`nexa_office`), apps, REST `production`
 * - Sharing & navigasi — berbagi rekaman ke user lain; route + `menu_config.json`
 * - Agrupasi & pencarian — `groupBy` terbatas, search handler, Populate
 * - Analisis — `Analysis`, `JoinTabel`, agregasi `SingleTabel`
 * - Cache — invalidasi Phpfastcache (file) untuk query memo
 * - DDL & bucket — `TabelData`, view, merge/create tabel gabungan (`Buckets`)
 *
 * Nama method publik banyak mempertahankan ejaan pendek seperti `tablesRet*` agar konsisten dengan API/controller yang ada.
 */
class Office extends NexaModel
{
    // =====================================================================
    // User & wilayah
    // =====================================================================

    /** Baris lengkap pengguna aktif dari storage `user` (by userid()). */
    public function byUser(): array {
        $result=$this->Storage('user') 
            ->select([
                'id', 
                'nama', 
                'email', 
                'password',
                'status',
                'telepon',
                'alamat',
                'gender',
                'token',
                'jabatan',
                'instansi',
                'expired'
            ])
            ->where('id', $this->userid())
            ->first();
        return $result ?? [];
    }

    /** Semua baris wilayah (`wilayah`). */
    public function flag(): array {
        $result=$this->Storage('wilayah') 
            ->select(['*'])
            ->get();
        return $result ?? [];
    }

    /** PATCH profil pengguna aktif (`userid`). `userid` di payload hilangkan — dipakai hanya di WHERE. */
    public function upUser(array $data): array {
           // `userid` hanya untuk WHERE; hilangkan dari payload selain nama kolom tabel ini
           unset($data['userid']);
    
           $result = $this->Storage('user')
               ->where('id', $this->userid())
               ->update($data);
           return ['success' => $result];
    }

    /** Semua baris dengan `id` pada tabel storage bernama `$table`, urutan id DESC. */
    public function find(string $table, int $id) {
       return $this->Storage($table) 
         ->where('id', $id)
         ->orderBy("id", "DESC")
         ->get();
    }

    // =====================================================================
    // Metadata tabel — alias nama dari baris controllers (categori Metadata)
    // =====================================================================

    /** Cache statis pemetaan index tabel → alias/slug (array kosong jika tidak ada metadata). */
    private static $tableAliases = null;

    /** Memuat satu kali pemetaan index→alias dari `controllers` Metadata. */
    private function getTableAliases(): array {
        if (self::$tableAliases === null) {
            $metadata = $this->Storage('controllers')
                ->select(['data'])
                ->where('categori', 'Metadata')->first();
            
            if ($metadata && isset($metadata['data']) && is_array($metadata['data'])) {
                $data = $metadata['data'];
                self::$tableAliases = array_combine(
                    array_column($data, 'index'),
                    array_column($data, 'alis')
                );
            } else {
                self::$tableAliases = [];
            }
        }
        
        return self::$tableAliases;
    }

    // =====================================================================
    // Ringkasan tabel dari metadata dan API penampil
    // =====================================================================

    /** Avatar + nama/email untuk satu user (`avaratid` di `$data`). */
    public function avatar(array $data): array{
        $result=$this->Storage('user') 
            ->select("nama,avatar,email")
            ->where('id', $data['avaratid'])
            ->first();
        return $result ?? [];
    }

    /** Struktur baris Metadata: `store` adalah array kolom deskriptor atau `[]`. */
    public function tablesMeta() {
       $metadata = $this->Storage('controllers')
        ->select(['data as store'])
        ->where('categori', 'Metadata')->first();
        if (!$metadata) {
            return ['store' => []];
        }
        if (!isset($metadata['store']) || !is_array($metadata['store'])) {
            $metadata['store'] = [];
        }
        
        return $metadata;
    }

    /** Daftar nama tabel fisik (delegasi ke `showTables`). */
    public function tablesShow() {
        $tables = $this->showTables();
        return $tables;
    }

    /** Menu struktur untuk UI dari alias metadata. */
    public function tablesRet() {
        return $this->generateTableMenu($this->getTableAliases());
    }

    /** Detail per tabel (delegasi ke `showTablesRetInfo`). */
    public function tablesInfo() {
        return $this->showTablesRetInfo($this->getTableAliases());
    }

    /** Daftar nama variabel/kolom pendek untuk pasangan `(index,key)`. */
    public function tabelVariables($key,$name) {
        
        return $this->showVariablesList([$key => $name]);
    }

    /** Mapping kolom ke tipe (delegasi ke `getVariablesType`). */
    public function tabelVariablesType($key,$name) {
        
        return $this->getVariablesType([$key => $name]);
    }    
    /** Baris dari `showTablesRetData` untuk satu pasangan index→nama; urut default `id` DESC. */
    public function tablesRetData($key,$name,$limit=10, $columns=[], $ordering='id',$orderDirection='DESC') {
        return $this->showTablesRetData([$key => $name],$limit, false, 'array', $columns,$ordering,$orderDirection);
    }

    /** Query longgar pada tabel fisik `showTables()[ $key ]` dengan filter `where` opsional. */
    public function tablesLax($key, array $params = []) {
        $allTables = $this->showTables();
        $tableName = $allTables[$key];
        
        $query = $this->Storage($tableName);
        
        // Handle where conditions if provided
        if (isset($params['where']) && is_array($params['where'])) {
            foreach ($params['where'] as $field => $value) {
                $query = $query->where($field, $value);
            }
        }
        
        $users = $query->get();
        return $users;
    }

    // =====================================================================
    // CRUD baris dinamis (STANDAR: Insert/Update/Delete; kolom bersih —
    // jalur IMPOR/UNGGAH di luar kelas tersebut sebelum facade)
    // =====================================================================

    /** Insert baris STANDAR — {@see Insert}; argumen `$fieldConfig` tidak diproses oleh kelas itu (pemishan jalur atas). */
    public function setRetInsert($key, $name, $columns = [], $fieldConfig = null) {
        return RowInsert::run($this, $key, $name, $columns, $fieldConfig);
    }

    /** Update STANDAR — {@see Update}; `$fieldConfig` ditahan untuk API, tidak dipakai oleh kelas itu. */
    public function setRetUpdate($key, $name, $columns = [], $id = null, $fieldConfig = null) {
        return RowUpdate::run($this, $key, $name, $columns, $id, $fieldConfig);
    }

    /** Agregasi/count & grup lewat `showTablesRetGroup`. */
    public function tablesRetCount($key,$name, $columns) {
        return $this->showTablesRetGroup([$key => $name],$columns);
    }

    /** Hapus satu baris STANDAR — {@see Delete} → `tablesRetDelete` + invalidasi cache mutasi. */
    public function setRettDelete($key, $name, $id) {
        return RowDelete::run($this, $key, $name, $id);
    }

    /** Satu atau banyak baris oleh `tablesRetFind`; `$id` boleh null. */
    public function setRetFind($key, $name,$id=null) {
        return $this->tablesRetFind([$key => $name],$id);
    }

    // =====================================================================
    // Penyimpanan pendukung `nexa_office`: view, create, share, apps, production REST
    // =====================================================================

   public function backedTabelView(array $data): array{
         $setData=[
           'user_id'=>1,
           'status'=>'tabelView',
           'data_type'=>'tabelView',
           'data_value'=>$data['data'],
         ];
             $setAtFind= $this->Storage('nexa_office')
            ->where('data_type', 'tabelView')
            ->first();
            if ($setAtFind && isset($setAtFind['id'])) {
                 $this->Storage('nexa_office')
                    ->where('data_type','tabelView')
                    ->update($setData);
            } else {
               $this->Storage('nexa_office')->upsert($setData);
            }

       return [
           'success' =>true,
           'timestamp' => date('Y-m-d H:i:s')
       ];
    }

   public function getTabelViewss(array $data): array{

       return [
           'success' =>true,
           'response' =>$data,
           'timestamp' => date('Y-m-d H:i:s')
       ];
   }



   public function getTabelView(array $data): array{
         $setAtFind= $this->Storage('nexa_office')
         ->select(['data_value'])
        ->where('data_type', 'tabelView')
        ->first();
       return [
           'success' =>true,
           'response' =>$setAtFind['data_value'],
           'timestamp' => date('Y-m-d H:i:s')
       ];
   }




   public function backedCreateTabel(array $data): array{
         $setData=[
           'user_id'=>1,
           'status'=>'createTabel',
           'data_type'=>'createTabel',
           'data_value'=>$data['data'],
         ];
             $setAtFind= $this->Storage('nexa_office')
            ->where('data_type', 'createTabel')
            ->first();
            if ($setAtFind && isset($setAtFind['id'])) {
                 $this->Storage('nexa_office')
                    ->where('data_type','createTabel')
                    ->update($setData);
            } else {
               $this->Storage('nexa_office')->upsert($setData);
            }

       return [
           'success' =>true,
           'timestamp' => date('Y-m-d H:i:s')
       ];
    }



   public function getCreateTabel(array $data): array{
         $setAtFind= $this->Storage('nexa_office')
         ->select(['data_value'])
        ->where('data_type', 'createTabel')
        ->first();
       return [
           'success' =>true,
           'response' =>$setAtFind['data_value'],
           'timestamp' => date('Y-m-d H:i:s')
       ];
   }
   public function shareWithuser(array $data): array{
       $setData=[
         'user_id'=>$data['users'],
         'to_id'=>$data['tousers'],
         'status'=>'share',
         'data_type'=>'share',
         'data_value'=>$data,
       ];
       $result = $this->Storage('nexa_office')->upsert($setData);
       return [
           'success' =>true,
           'timestamp' => date('Y-m-d H:i:s')
       ];
    }
   public function setApps(array $data): array{
         $setData=[
           'user_id'=>$this->userid(),
           'status'=>'Apps',
           'title'=>$data['appname'] ?? null,
           'description'=>$data['description'] ?? null,
           'version'=>$data['version'] ?? '1.0.0',
           'data_type'=>'Apps' ?? null,
           'data_value'=>$data ?? null,
         ];
             $setAtFind= $this->Storage('nexa_office')
            ->where('data_type', 'Apps')
            ->first();
            if ($setAtFind && isset($setAtFind['id'])) {
                 $this->Storage('nexa_office')
                    ->where('data_type','Apps')
                    ->update($setData);

            } else {
               $this->Storage('nexa_office')->upsert($setData);
            }
            
       return [
           'response' =>$setAtFind ?? null,
           'success' =>true,
           'timestamp' => date('Y-m-d H:i:s')
       ];
    }


   public function restful(array $data): array{
       $mapped = [
           'authorization'  => $data['authorization'] ?? null,
           'appid'          => $data['appid'] ?? null,
           'endpoint'       => $data['endpoind'] ?? $data['endpoint'] ?? null,
           'method_get'     => isset($data['GET']) ? (int)(bool)$data['GET'] : 0,
           'method_post'    => isset($data['POST']) ? (int)(bool)$data['POST'] : 0,
           'method_put'     => isset($data['PUT']) ? (int)(bool)$data['PUT'] : 0,
           'method_patch'   => isset($data['PATCH']) ? (int)(bool)$data['PATCH'] : 0,
           'method_options' => isset($data['OPTIONS']) ? (int)(bool)$data['OPTIONS'] : 0,
           'method_delete'  => isset($data['DELETE']) ? (int)(bool)$data['DELETE'] : 0,
           'expiration'     => isset($data['expiration']) ? json_encode($data['expiration']) : null,
           'build_query'    => isset($data['buildQuery']) ? json_encode($data['buildQuery']) : null,
           'description'    => $data['description'] ?? null,
           'appname'        => $data['appname'] ?? null,
           'developer'      => $data['developer'] ?? null,
           'version'        => $data['version'] ?? null,
           'development'    => $data['development'] ?? null,
           'userid'         => $this->userid() ?? null,
       ];
       $exists = $this->Storage('production')
           ->where('authorization', $mapped['authorization'])
           ->first();

       if ($exists) {
           $this->Storage('production')
               ->where('authorization', $mapped['authorization'])
               ->update($mapped);
           $action = 'updated';
       } else {
           $this->Storage('production')->insert($mapped);
           $action = 'inserted';
       }

       return [
           'response'  => $mapped,
           'action'    => $action,
           'success'   => true,
           'timestamp' => date('Y-m-d H:i:s')
       ];
    }




    public function shareID() {
        $Data= $this->Storage('nexa_office')
        ->select('id,to_id AS userid ,data_value AS data')
        ->where('to_id', $this->userid())
        ->where('data_type','share')
        ->get();
        return $Data;
    }
    public function shareDel(array $data): array{
            $this->Storage('nexa_office')
             ->where('to_id', $this->userid())
            ->where('id', $data['id'])
            ->delete();
        return [
           'success' =>true,
           'timestamp' => date('Y-m-d H:i:s')
       ];
    }

    /** Simpan struktur navigasi utama ke baris Route + sinkronkan `menu_config.json` (redJson). */
    public function upNavigation(array $params): array {
        $this->Storage('nexa_office')
            ->where('data_type','Route')
            ->update([
                'data_value'=>$params,
            ]);

        $data = $this->Storage('nexa_office')
            ->select(['data_value','navigasi','appname','icon'])
            ->where('data_type', 'Route')
            ->first();

        $brandConfig = [
            'href' => 'home',
            'logo' => $data['icon'] ?? '/assets/images/favicon.png',
            'alt'  => $data['appname'] ?? 'NexaUI',
            'text' => $data['appname'] ?? 'NexaUi',
        ];

        $nexaJon = $this->redJson();
        $nexaJon->setData([
            'type' => $data['navigasi'] ?? 'Standard',
            'menuData' => $data['data_value']['main_menu'],
            'brandConfig' => $brandConfig,
        ]);
        $nexaJon->save('menu_config.json');

        return $params;
    }


    // ---------------------------------------------------------------------
    // Kunci cari luar, agrupasi, pencarian, populate
    // ---------------------------------------------------------------------

    public function setRetFindKey($key, $name,$failed,$id=null) {
        return $this->tablesRetFindKey([$key => $name],$failed,$id);
    }

    public function setAtGroupObj(array $data): ?array {
        try {
             $tableIndex = array_keys($data['key'])[0];
              $columns=$data['columns'];
              $access=$data['access'] ?? '';
              $allTables = $this->showTables();
              $tableName = $allTables[$tableIndex];
             if ($access=="private") {
              $result=$this->Storage($tableName)
                ->select($columns)
                ->where('userid', $this->userid())
                ->groupBy($columns)
                ->limit(100)
                ->get();
             } else {
               $result=$this->Storage($tableName)
                ->select($columns)
                ->groupBy($columns)
                ->limit(100)
                ->get();
             }

            return $result;
        } catch (\Exception $e) {
           return [
            'success'=>false,
            'data'=>[]
           ];
        }
    } 

    /** GroupBy sampai 100 baris pada nama tabel resolusi `firstAtGroup`; `private` scope filter userid. */
   public function setAtGroup($key, $name, $columns,$access='') {
    try {
        $key = (int)$key;
        
        // Check if table exists
        $allTables = $this->showTables();
        if (!isset($allTables[$key])) {
            return $key;
        }
        
        // Ensure access parameter is a string, default to empty string if null
        $access = is_array($access) ? '' : (string)($access ?? '');
        
        // Use the updated firstAtGroup method that handles both string and array columns
        $result = $this->firstAtGroup([$key => $name], $columns, $access);
        
        return $result;
    } catch (\Exception $e) {
       return [
        'success'=>false,
        'data'=>[]
       ];
    }
} 


   public function searchAt(array $data,string $keyword): array{
        return $this->searchAtFind($data,$keyword);
    }

   public function searchPopulate(array $data): array{
    return $this->searchAtPopulate($data);
     
    }

   public function setPopulate(array $data): array{

     return (new Populate())->build($data);

     
    }


    /** Hasil pertama `firstAtFind` — selalu array (kosong jika tidak ada baris). */
    public function setAtFind(array $data, string $keyword): array {
        $result = $this->firstAtFind($data, $keyword);
        if (!$result) {
            return [];
        }
        return $result;
    }

    // =====================================================================
    // Analysis — query gabungan dan turunan dari hasil nestedAnalysis
    // =====================================================================

    /** Entry utama untuk builder analisis (format sesuai `Analysis::index`). */
    public function nestedAnalysis(array $bulder): array {
        $Data = new Analysis();
        return $Data->index($bulder);
    }

    /**
     * Analisis langsung dengan konfig eksplisit (where, group, order, limit, offset…).
     * Wrapper tipis atas `Analysis::directAnalysis()` / `Analysis::index()`.
     *
     * @param array $data Payload query (format sama dengan nestedAnalysis)
     * @param array $analysisConfig Konfig tambahan untuk lapisan Analysis
     * @return array Hasil struktur dari class Analysis
     */
    public function directAnalysis(array $data, array $analysisConfig = []): array {
        $Data = new Analysis();
        return $Data->directAnalysis($data, $analysisConfig);
    }

    /**
     * Lanjutan dari hasil `nestedAnalysis()` — preprocessing tambahan dan konfig baru.
     *
     * @param array $nestedResult Keluaran nestedAnalysis sebelumnya
     * @param array $originalQueryData Query mentah awal (disarankan diisi untuk konteks join)
     * @param array $analysisConfig Pengaturan where/group/order tambahan
     * @return array Hasil struktur dari class Analysis
     */
    public function fromNestedAnalysis(array $nestedResult, array $originalQueryData = [], array $analysisConfig = []): array {
        $Data = new Analysis();
        return $Data->fromNestedAnalysis($nestedResult, $originalQueryData, $analysisConfig);
    }

    /** Parsing/ekstraksi berkas pendek pada storage `Ekstrak`. */
    public function fileEkstrak(array $data): array {
        return (new Ekstrak())->index($data);
    }

    /**
     * Jalankan SELECT gabungan multi-tabel ({@see JoinTabel::joinQuery}) — satu query JOIN + pagination.
     */
    public function executeOperation(array $data) {
        $joinTabel = new JoinTabel();
        return $joinTabel->joinQuery($data);
    }

    /**
     * Mutasi berantai beberapa tabel fisik (insert/update/delete terpisah), untuk data berelasi dari layar join.
     * Bentuk payload: `{ steps: [...], userid?: mixed }` — lihat {@see JoinOprasi::run}.
     */
    public function executeJoinMutation(array $data): array {
        return JoinOprasi::run($this, $data);
    }

    /**
     * Impor baris dari berkas CSV/XLSX ke tabel utama app ({@see Importing::run}).
     *
     * @param array<string,mixed> $data tableKey, name/appId, base64|content, buildQuery?, filename?, userid?, delimiter?, maxRows?, …
     * @return array<string,mixed>
     */
    public function Import(array $data): array {
        return Importing::run($this, $data);
    }

    /** Hapus seluruh file-cache query Nexa (`sys_temp/nexacache`) — bisa dipanggil eksplisit dari admin. */
    public function clearQueryCache(): array {
        try {
            $cacheConfig = new \Phpfastcache\Drivers\Files\Config([
                'path' => sys_get_temp_dir() . '/nexacache',
            ]);
            (new \Phpfastcache\Helper\Psr16Adapter('files', $cacheConfig))->clear();
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** Untuk delegasi mutasi baris ({@see RowInsert} / {@see RowUpdate} / {@see RowDelete}); memanggil invalidateCache internal. */
    public function invalidateMutationCache(): void {
        $this->invalidateCache();
    }

    /** Membersihkan cache file query setelah mutasi CRUD (gagal diam-diam; tidak blok operasi utama). */
    private function invalidateCache(): void {
        try {
            $cacheConfig = new \Phpfastcache\Drivers\Files\Config([
                'path' => sys_get_temp_dir() . '/nexacache',
            ]);
            (new \Phpfastcache\Helper\Psr16Adapter('files', $cacheConfig))->clear();
        } catch (\Exception $e) {
            // abaikan kesalahan driver cache
        }
    }

    /**
     * Agregasi ringan SUM/COUNT dll. pada satu tabel melalui `SingleTabel::singleQuery`.
     */
    public function standaloneAt(array $bulder): array {
        return (new SingleTabel())->singleQuery($bulder);
    }


    // =====================================================================
    // Operasi DDL & baris langsung (TabelData), view, pembuatan/pemecahan bucket
    // =====================================================================

    /** Baca kumpulan baris berdasarkan payload TabelData. */
    public function tabelData(array $data): array {
        return (new TabelData())->getRows($data);
    }

    public function tabelUpdateRow(array $data): array {
        return (new TabelData())->updateRow($data);
    }

    public function tabelDeleteRow(array $data): array {
        return (new TabelData())->deleteRow($data);
    }

    public function tabelCopyTable(array $data): array {
        return (new TabelData())->copyTable($data);
    }

    public function tabelCreateTrigger(array $data): array {
        return (new TabelData())->createTrigger($data);
    }

    public function tabelListTriggers(array $data): array {
        return (new TabelData())->listTriggers($data);
    }

    public function tabelDropTrigger(array $data): array {
        return (new TabelData())->dropTrigger($data);
    }

    /** Skema kolom satu tabel. */
    public function tabelGetStructure(array $data): array {
        return (new TabelData())->getStructure($data);
    }

    public function tabelAlterColumn(array $data): array {
        return (new TabelData())->alterColumn($data);
    }

    public function tabelDropColumn(array $data): array {
        return (new TabelData())->dropColumn($data);
    }

    public function tabelOperation(array $data): array {
        return (new TabelData())->tableOperation($data);
    }

    /** SQL seleksi atas payload terstruktur dari TabelData. */
    public function tabelExecuteQuery(array $data): array {
        return (new TabelData())->executeQuery($data);
    }

    /** Definisi / materialisasi view. */
    public function createView(array $data): array {
        return (new TabelView())->buildTabelView($data);
    }

    public function delTabelView(array $data): array {
        return (new TabelView())->buildTabelDelete($data);
    }

    public function testTabelView(array $data): array {
        return (new TabelView())->testTabelView($data);
    }

    public function buckCreateTabel(array $data): array {
        return (new CreateTabel())->buildCreateTabel($data);
    }

    public function alterBuckCreateTabel(array $data): array {
        return (new CreateTabel())->alterCreateTabel($data);
    }

    public function dropBuckCreateTabel(array $data): array {
        return (new CreateTabel())->dropCreateTabel($data);
    }

    public function buckMergeTabel(array $data): array {
        return (new MergeTabel())->buildCreateTabel($data);
    }

    public function dropbuckMergeTabel(array $data): array {
        return (new MergeTabel())->dropCreateTabel($data);
    }

    /** Simpan rekaman bucket generik ke nexa_office. */
    public function bucketsSystem(array $params): array {
        $result = $this->Storage('nexa_office')->insert($params);
        return [
            'success' => (bool)$result,
            'data' => $params,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public function upBucketsSystem(array $params): array {
        $this->Storage('nexa_office')
            ->where('data_key', $params['version'])
            ->update([
                'version' => $params['version'],
                'data_value' => $params['data'],
            ]);
        return $params;
    }

    /** Konten sesuai version (nexa_office). */
    public function getBucketsSystem(array $params): array {
        return $this->Storage('nexa_office')
            ->select(['version', 'data_value'])
            ->where('version', $params['version'])
            ->first();
    }

    /**
     * Update/delete baris berdasarkan join dua tabel (federasi key).
     * `$params['mode']`: update (butuh struktur data lengkap) atau delete.
     */
    public function Buckets(array $params): array {
        $data = $params['data'] ?? [];
        $id = $params['id'] ?? null;
        $payload = $params['update'] ?? [];
        $mode = $params['mode'] ?? 'update';

        $requiredKeys = [
            'keyindexname', 'targetkey', 'keyindex', 'keytarget',
            'groupJoinType', 'groupJoinCondition', 'index', 'target',
        ];

        if ($mode === 'update') {
            foreach ($requiredKeys as $rk) {
                if (!array_key_exists($rk, $data)) {
                    throw new InvalidArgumentException("Missing required key: {$rk}");
                }
            }

            if (!is_array($payload) || empty($payload)) {
                throw new InvalidArgumentException('Update payload is empty or invalid.');
            }

            if (!empty($data['keyindex'])) {
                $sql = $this->buildBuckets($data, $id);
                $targetIndex = (int)$data['keytarget'];
                $targetTable = $this->tablesIndex($targetIndex);
                $rawTarget = $this->raw($sql);
                $targetId = isset($rawTarget[0]['id']) ? (int)$rawTarget[0]['id'] : 0;
                $this->Storage($targetTable)->where('id', $targetId)->update($payload);
            } else {
                $mainTable = $this->tablesIndex($data['key'] ?? null);
                $mainId = (int)$id;
                $this->Storage($mainTable)->where('id', $mainId)->update($payload);
            }
        } elseif ($mode === 'delete') {
            $table = $this->tablesIndex($data['key'] ?? null);
            $deleteId = (int)$id;
            $this->Storage($table)->where('id', $deleteId)->delete();
        }

        return [
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /** SQL SELECT id dari tabel target setelah JOIN — untuk Buckets memilih satu baris. */
    private function buildBuckets(array $data, ?string $id = null): string {
        $indexTable = (int)$data['keyindex'];
        $fromAlias = $data['keyindexname'];
        $baseTable = $this->tablesIndex($indexTable) . " AS {$fromAlias}";

        $joinTypeRaw = strtoupper(trim($data['groupJoinType']));
        $joinType = match ($joinTypeRaw) {
            'LEFT' => 'LEFT JOIN',
            'RIGHT' => 'RIGHT JOIN',
            'INNER' => 'INNER JOIN',
            'FULL' => 'FULL OUTER JOIN',
            default => throw new InvalidArgumentException("Unsupported join type: {$joinTypeRaw}"),
        };
        $leftField = str_replace('-', '.', $data['index']);
        $rightField = str_replace('-', '.', $data['target']);
        $joinCondition = "{$leftField} {$data['groupJoinCondition']} {$rightField}";
        $targetIndex = (int)$data['keytarget'];
        $targetAlias = $data['targetkey'];
        $targetTable = $this->tablesIndex($targetIndex) . " AS {$targetAlias}";
        $sql = "SELECT {$targetAlias}.id FROM {$baseTable} {$joinType} {$targetTable} ON {$joinCondition}";

        if (!empty($id)) {
            $sql .= " WHERE {$fromAlias}.id = '" . addslashes($id) . "'";
        }
        return $sql;
    }
}
