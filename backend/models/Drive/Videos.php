<?php
namespace App\Models\Drive;
use App\System\NexaModel;

/**
 * Model Control - Mengelola manajemen controller dan akses pengguna
 * 
 * Model ini mengelola controller, hak akses pengguna, dan kontrol akses
 * untuk aplikasi. Extends NexaModel untuk operasi database.
 * 
 * @package App\Models\Role
 * @extends NexaModel
 */
class Videos extends NexaModel
{
	protected $table = 'drive';
	protected $failed = 'title';

	private function getUser($user) {

       if ($user ==1) {
        	$useUt="row";
        	$useRw=0;
        } else {
        	$useUt="userid";
        	$useRw=$user;
        }
	   $map = [
		     'failed'      => $useUt,
		     'value'     => $useRw,
	    ];
	    return $map;
	 }

	public function upload(array $data)
	{
		return $this->Storage($this->table)->insert($data);
	}

   public function manager(
        string $search,
        string $sort='DESC',
        int $page=1,
        string $failed='',
        string $categori='',
        string $user=''
      ): array {
        $failedSearch = $search;
        $failed = $this->failed;
        $uid=$this->getUser($user);
        $result = $this->Storage($this->table) 
            ->where($failed, 'LIKE', "%{$failedSearch}%")
            ->where('categori',"Video")
            ->where($uid['failed'], '=', $uid['value'])
           ->orderBy('id',$sort)
           ->paginate($page,16); // halaman 2, 15 item per halaman;
        return $result ?? [];
    }

}