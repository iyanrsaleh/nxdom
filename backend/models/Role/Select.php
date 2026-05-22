<?php
namespace App\Models\Role;
use App\System\NexaModel;

/**
 * User Model untuk useModels() example
 */
class Select extends NexaModel
{
    
    public function getFormattedControllers($categori = 'news') {
        try {
            // Ambil data package dari controllers
            $select = $this->Storage('controllers')
                ->select(['keywords'])
                ->where('categori', $categori)->first();
                
            // Format data seperti contoh sebelumnya
            $formattedSelect = [];
            if ($select && isset($select['keywords'])) {
                $keywords = $select['keywords'];
                
                if (isset($keywords['label']) && isset($keywords['value'])) {
                    for ($i = 0; $i < count($keywords['label']); $i++) {
                        $formattedSelect[] = [
                            'label' => $keywords['label'][$i],
                            'value' => $keywords['value'][$i]
                        ];
                    }
                }
            }
            
            return $formattedSelect;
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Static method untuk mendapatkan data yang sudah diformat
     * 
     * @param string $categori
     * @return array
     */
    public  function option($categori = 'news') {
        try {
            return $this->getFormattedControllers($categori);
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}
