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
class files extends NexaModel
{
	protected $table = 'drive';
	protected $failed = 'title';

	private function getUser($user) {

       if ($user ==1) {
        	$useUt="row";
        	$useRw=1;
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

	public function byUpdate(array $data, int $id): array {
		$this->Storage($this->table)->where('id', $id)->update($data);
	
		return $data;
	}


    public function findById(int $id): array {
        $result = $this->Storage($this->table) 
            ->where('id', '=', $id)
            ->first();
        
        return $result ?? [];
    }



    // Update status
	  public function Library2($userid='',$id='',$status=1)
	  {
	  	 $uid=$this->getUser($userid);
        return $this->Storage($this->table)
         ->where($uid['failed'], '=', $uid['value'])
         ->where('id', $id)
         ->update(["status"=>$status]);
	  }

	public function setVideos($id='',$status='steg',$val='1')
	{
		
       $result = $this->Storage($this->table) 
              ->select(['SUM(steg) as count'])
               ->where($status, '=', $val)
              ->where('userid', '=', $id)
            ->groupBy(['steg'])
            ->first();
        
        return $result ?? [];
	}
	public function setBerkas($id='',$status='row',$val='1')
	{
		$uid=$this->getUser($id);

       $result = $this->Storage($this->table) 
              ->select(['SUM(row) as count'])
              ->where($uid['failed'], '=', $uid['value'])
               ->where($status, '=', $val)
            ->groupBy(['row'])
            ->first();
        
        return $result ?? [];
	}

	

    public function removeById($userid,$id) {
        $this->Storage($this->table)
         ->where('userid', $userid)
        ->where('id', $id)
        ->delete();
        // deleteFile
    }



	public function usedStorage($id='')
	{
       $result = $this->Storage($this->table) 
            ->where('userid', '=', $id)
            ->sum('sizefor');
        return $result;
	}

	

   public function manager(
        string $search,
        string $sort='DESC',
        int $page=1,
        string $failed='',
        string $categori='',
        string $user=''
      ): array {
        if (!empty($categori) && $categori !== 'all') {
            $failedSearch = $categori;
            $failed =$failed;
        } else {
            $failedSearch = $search;
            $failed = $this->failed;
        }
     

        if ($failed=='status') {
        	$iswhere='row';
        } else if ($failed=='categori') {
        	$iswhere='row';
        } else if ($failed=='library') {
        	$iswhere='row';

        } else {
        	$iswhere='status';
        }
        
        $uid=$this->getUser($user);
        $result = $this->Storage($this->table) 
            ->where($failed, 'LIKE', "%{$failedSearch}%")
            ->where($iswhere,1)
             ->where($uid['failed'], '=', $uid['value'])
           ->orderBy('id',$sort)
           ->paginate($page,8); // halaman 2, 15 item per halaman;
        return $result ?? [];
    }








}