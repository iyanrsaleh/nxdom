<?php
declare(strict_types=1);

namespace App\Models;

use App\System\NexaModel;

/**
 * User Model untuk useModels() example
 */
class Wrapper extends NexaModel
{
    protected $table = 'demo';
   
    
    private $controller = null;
    
    public function setController($controller): self {
        $this->controller = $controller;
        return $this;
    }

    public function Tags($limit=5,$label='pages',$categori=''): ?array {
        $result = $this->Storage($this->table) 
            ->select(['title','deskripsi', 'thumbnails', 'slug', 'categori', 'updated_at'])
             ->where('categori', $categori)
            ->limit($limit)
            ->orderBy("id", "DESC")
            ->get();

        // Membuat sub-array untuk menyimpan data
        $pages = [];
        foreach ($result as $item) {
            $pages[$label][] = $item;
        }

        return $pages;
    }
    
    public function mySearch($limit=5,$label='pages',$failedSearch=''): ?array {
          $result = $this->Storage($this->table) 
            ->select(['title','deskripsi', 'thumbnails', 'slug', 'categori', 'updated_at'])
            ->where('title', 'LIKE', "%$failedSearch%")
            ->limit($limit)
            ->orderBy("id", "DESC")
            ->get(); 
        // Membuat sub-array untuk menyimpan data
        $pages = [];
        foreach ($result as $item) {
            $pages[$label][] = $item;
        }

        if (empty($pages)) {
            return self::pages($limit, $label);
        }
        return $pages;
    }
    



    public function pages($limit=5,$label='pages'): ?array {
        $result = $this->Storage($this->table) 
            ->select(['id','title','deskripsi', 'thumbnails', 'slug', 'categori', 'updated_at'])
            ->limit($limit)
            ->orderBy("id", "DESC")
            ->get();

        // Membuat sub-array untuk menyimpan data
        $pages = [];
        foreach ($result as $item) {
            $pages[$label][] = $item;
        }

        return $pages;
    }
    
    public function index(): ?array {
             $result = $this->Storage($this->table) 
            ->select(['title', 'thumbnails', 'slug','categori','updated_at'])
            ->limit(5)
            ->orderBy("categori", "DESC")
            ->get();
            
        return $result ?? [];
    }

     public function Trending(){
             $result = $this->Storage($this->table) 
            ->select(['title'])
            ->limit(3)
            ->orderBy("categori", "DESC")
            ->get(); 
        return $result ?? [];
    }

    public function indexFooter(): ?array {
             $result = $this->Storage($this->table) 
            ->select(['title', 'thumbnails', 'slug','categori','updated_at'])
            ->limit(2)
            ->orderBy("categori", "DESC")
            ->get();
            
        return $result ?? [];
    }
    /**
     * Get all news matching search criteria
     * Returns array of results
     * 
     * @param string $search Search keyword
     * @return array Array of news items
     */
    public function Terbaru(string $set,$limit=1): array {
             $variable =$this->Storage($this->table) 
             ->select('title,pubdate,categori,thumbnails,slug,updated_at')
             ->limit(5)
             ->orderBy(["id", "categori"], "ASC")
             ->get();
            // Menambahkan variabel tambahan ke setiap item
               $data= array();
               foreach (array_slice($variable, 0, 1) as $key => $value) {
             
                $sts = array(
                  'grid' => 1,
                  'thumbnails' => $value['thumbnails']['800x600']?? '', 
                  'class' => 'rt-post-overlay rt-post-overlay-lg ex-layout',
                ); 

                $data['terbaru_index'][] = array(
                    'title' => $value['title'],
                    'pubdate' => $value['pubdate'],
                    'grid' => $sts['grid'],  
                    'categori' => $value['categori'], 
                    'updated_at' => $value['updated_at'], 
                    'urlslung' =>$value['slug'], 
                    'thumbnails' => $sts['thumbnails'], 
                    'class' => $sts['class'],  
                );
            }
               foreach (array_slice($variable,1, 5) as $key => $value) {
                $data['terbaru_item'][] = array(
                    'title'      => $value['title'],
                    'pubdate'    => $value['pubdate'],
                    'categori'   => $value['categori'],  
                    'thumbnails' => $value['thumbnails']['800x600']?? '', 
                    'updated_at' => $value['updated_at'], 
                    'urlslung'   =>$value['slug'],  
                );
               }
            
           return $data;
    }

       public function Teratas(string $set,$limit=1): array {
            $variable =$this->Storage($this->table)  
             ->select('title,deskripsi,pubdate,categori,thumbnails,slug,updated_at')
              ->where('categori', $set)
             ->limit(3)
             ->orderBy("id", "DESC")
             ->get();
            // Menambahkan variabel tambahan ke setiap item
            $data= array();
            $item= array();


           foreach (array_slice($variable,1, 3) as $key => $value) {
                $item[$set.'_teratas_item'][] = array(
                    'title' => $value['title'],
                    'pubdate' => $value['pubdate'],
                    'categori' => $value['categori'],  
                    'updated_at' => $value['updated_at'], 
                    'urlslung' =>$value['slug'],  
                );
               }



               foreach (array_slice($variable, 0, 1) as $key => $value) {
                $data[$set.'_teratas_index'][] = array(
                    'title' => $value['title'],
                    'pubdate' => $value['pubdate'],
                    'categori' => $value['categori'], 
                    'deskripsi' => $value['deskripsi'], 
                    'updated_at' => $value['updated_at'], 
                    'urlslung' =>$value['slug'], 
                    'thumbnails' =>$value['thumbnails']['800x600']?? '', 
                    'item1' =>$item[$set.'_teratas_item'][0], 
                    'item2' =>$item[$set.'_teratas_item'][1], 
                    
                );
            }
           return $data;
      
  }
        public function myViews(string $set,$limit=1): array {
            $variable =$this->Storage($this->table)  
             ->select('title,categori,thumbnails,slug,updated_at')
              //->where('categori', $set)
             ->limit(5)
             ->orderBy("id", "DESC")
             ->get();
            // Menambahkan variabel tambahan ke setiap item
            $data= array();
            $item= array();

           foreach (array_slice($variable,0, 3) as $key => $value) {
                $item['public_views_item'][] = array(
                     'title' => $value['title'],
                    'thumbnails' =>$value['thumbnails']['800x600']?? '', 
                );
            }
             foreach (array_slice($variable, 3, 5) as $key => $value) {
              $data['public_views_index'][] = array(
                  'title' => $value['title'],
                  'categori' => $value['categori'],  
                  'updated_at' => $value['updated_at'], 
                  'urlslung' =>$value['slug'],  
              );
            }
           return array_merge($item,$data);
      
  }

        public function myViedos(string $set,$limit=1): array {
            $variable =$this->Storage($this->table)  
             ->select('title,categori,thumbnails,slug,updated_at')
              ->where('categori', $set)
             ->limit(4)
             ->orderBy("id", "DESC")
             ->get();
            // Menambahkan variabel tambahan ke setiap item
            $data= array();
            $item= array();


           foreach (array_slice($variable,0, 1) as $key => $value) {
                $item['public_videos_item'][] = array(
                    'title' => $value['title'],
                    'updated_at' => $value['updated_at'], 
                    'thumbnails' =>$value['thumbnails']['800x600']?? '', 
                );
            }
             foreach (array_slice($variable, 1, 4) as $key => $value) {
              $data['public_videos_index'][] = array(
                  'title' => $value['title'],
                  'categori' => $value['categori'],  
                  'updated_at' => $value['updated_at'], 
                  'urlslung' =>$value['slug'],  
              );
            }
           return array_merge($item,$data);
      
  }

    public function Populer(string $set,$limit=1): array {
        
           if ($set !=='daerah') {
               $label=$set;
               $variable = $this->Storage($this->table) 
               ->select('title,deskripsi,pubdate,categori,thumbnails,slug,updated_at')
                ->where('categori', $set)
               ->limit(5)
               ->orderBy("id", "DESC")
               ->get();
            } else {
               $label='daerah';
               $variable = $this->Storage($this->table) 
               ->select('title,deskripsi,pubdate,categori,thumbnails,slug,updated_at')
               ->limit(5)
               ->orderBy("id", "DESC")
               ->get();
            }
    

           
            // Menambahkan variabel tambahan ke setiap item
            // Menambahkan variabel tambahan ke setiap item
              $data[$label] = array();
               foreach (array_slice($variable, 0, 2) as $key => $value) {
                if ($key == 0) {
                   $sts = array(
                     'grid' => 1,
                     'thumbnails' => $value['thumbnails']['800x600']?? '', 
                     'class' => 'rt-post-overlay rt-post-overlay-lg ex-layout',
                   ); 
                } else {
                   $sts = array(
                     'thumbnails' => $value['thumbnails']['800x600']?? '', 
                     'grid' => 2,
                     'class' => 'rt-post post-md style-3',
                   );
                }
                
                $data[$label][$set.'_index'][] = array(
                    'title' => $value['title'],
                    'pubdate' => $value['pubdate'],
                    'grid' => $sts['grid'],  
                    'categori' => $value['categori'], 
                    'updated_at' => $value['updated_at'], 
                    'urlslung' =>$value['slug'], 
                    'thumbnails' => $sts['thumbnails'], 
                    'class' => $sts['class'],  
                );
            }
               foreach (array_slice($variable, 2, 5) as $key => $value) {
                $data[$label][$set.'_item'][] = array(
                    'title' => $value['title'],
                    'pubdate' => $value['pubdate'],
                    'categori' => $value['categori'],  
                    'thumbnails' => $value['thumbnails']['800x600']?? '', 
                    'updated_at' => $value['updated_at'], 
                    'urlslung' =>$value['slug'],  
                );
               }
            
           return $data[$label];




       // return $result ??[];
    }
    /**
     * Get news by ID with safe return
     * 
     * @param int $id News ID
     * @return array News data or empty array
     */
    public function findById(string $slug): array {
        $result = $this->Storage($this->table) 
            ->where('slug', '=', $slug)
            ->first();
        
        return $result ?? [];
    }
    


} 