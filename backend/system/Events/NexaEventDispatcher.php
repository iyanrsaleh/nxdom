<?php
namespace App\System\Events;

use Exception;

/**
 * NexaEventDispatcher - Sistem Event Dispatcher
 * Menangani dispatching event dan manajemen listener
 * 
 * @package   NexaUI
 * @version   2.0.0
 * @author    Tim NexaUI
 */
class NexaEventDispatcher
{
    private static ?NexaEventDispatcher $instance = null;
    private array $listeners = [];
    private array $eventLog = [];
    private bool $loggingEnabled = true;
    
    /**
     * Konstruktor private untuk singleton
     */
    private function __construct()
    {
        $this->loggingEnabled = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
    }
    
    /**
     * Dapatkan instance singleton
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Dispatch sebuah event
     */
    public function dispatch(NexaDomainEvent $event): void
    {
        try {
            $eventType = $event->getEventType();
            
            // Log event jika logging diaktifkan
            if ($this->loggingEnabled) {
                $this->logEvent($event);
            }
            
            // Dapatkan listener untuk tipe event ini
            $listeners = $this->getListenersForEvent($eventType);
            
            // Eksekusi setiap listener
            foreach ($listeners as $listener) {
                $this->executeListener($listener, $event);
            }
            
        } catch (Exception $e) {
            $this->logError("Kesalahan dispatch event: " . $e->getMessage(), $event);
        }
    }
    
    /**
     * Tambahkan event listener
     */
    public function listen(string $eventType, callable $listener): void
    {
        if (!isset($this->listeners[$eventType])) {
            $this->listeners[$eventType] = [];
        }
        
        $this->listeners[$eventType][] = [
            'listener' => $listener,
            'priority' => 0
        ];
        
        // Urutkan berdasarkan prioritas (prioritas tinggi pertama)
        $this->sortListenersByPriority($eventType);
    }
    
    /**
     * Tambahkan event listener dengan prioritas
     */
    public function listenWithPriority(string $eventType, callable $listener, int $priority = 0): void
    {
        if (!isset($this->listeners[$eventType])) {
            $this->listeners[$eventType] = [];
        }
        
        $this->listeners[$eventType][] = [
            'listener' => $listener,
            'priority' => $priority
        ];
        
        // Urutkan berdasarkan prioritas (prioritas tinggi pertama)
        $this->sortListenersByPriority($eventType);
    }
    
    /**
     * Hapus event listener
     */
    public function forget(string $eventType, callable $listener = null): void
    {
        if ($listener === null) {
            // Hapus semua listener untuk tipe event ini
            unset($this->listeners[$eventType]);
        } else {
            // Hapus listener tertentu
            if (isset($this->listeners[$eventType])) {
                $this->listeners[$eventType] = array_filter(
                    $this->listeners[$eventType],
                    function($existingListener) use ($listener) {
                        $actualListener = is_array($existingListener) && isset($existingListener['listener']) 
                            ? $existingListener['listener'] 
                            : $existingListener;
                        return $actualListener !== $listener;
                    }
                );
            }
        }
    }
    
    /**
     * Urutkan listener berdasarkan prioritas (prioritas tinggi pertama)
     */
    private function sortListenersByPriority(string $eventType): void
    {
        if (!isset($this->listeners[$eventType])) {
            return;
        }
        
        usort($this->listeners[$eventType], function($a, $b) {
            $priorityA = is_array($a) && isset($a['priority']) ? $a['priority'] : 0;
            $priorityB = is_array($b) && isset($b['priority']) ? $b['priority'] : 0;
            return $priorityB - $priorityA;
        });
    }
    
    /**
     * Dapatkan listener untuk tipe event
     */
    private function getListenersForEvent(string $eventType): array
    {
        $listeners = [];
        
        // Listener langsung
        if (isset($this->listeners[$eventType])) {
            $listeners = array_merge($listeners, $this->listeners[$eventType]);
        }
        
        // Listener wildcard
        if (isset($this->listeners['*'])) {
            $listeners = array_merge($listeners, $this->listeners['*']);
        }
        
        return $listeners;
    }
    
    /**
     * Eksekusi sebuah listener
     */
    private function executeListener($listener, NexaDomainEvent $event): void
    {
        try {
            $actualListener = is_array($listener) && isset($listener['listener']) 
                ? $listener['listener'] 
                : $listener;
            
            if (is_callable($actualListener)) {
                call_user_func($actualListener, $event);
            } else {
                $this->logError("Listener tidak valid untuk event: " . $event->getEventType(), $event);
            }
        } catch (Exception $e) {
            $this->logError("Kesalahan mengeksekusi listener: " . $e->getMessage(), $event);
        }
    }
    
    /**
     * Log event
     */
    private function logEvent(NexaDomainEvent $event): void
    {
        $this->eventLog[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event->toArray()
        ];
        
        // Simpan hanya 100 event terakhir untuk mencegah masalah memory
        if (count($this->eventLog) > 100) {
            $this->eventLog = array_slice($this->eventLog, -100);
        }
    }
    
    /**
     * Log kesalahan
     */
    private function logError(string $message, NexaDomainEvent $event): void
    {
        $errorInfo = [
            'error' => $message,
            'event' => $event->toArray(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        error_log("NexaEventDispatcher Error: " . json_encode($errorInfo));
    }
    
    /**
     * Dapatkan log event
     */
    public function getEventLog(): array
    {
        return $this->eventLog;
    }
    
    /**
     * Bersihkan log event
     */
    public function clearEventLog(): void
    {
        $this->eventLog = [];
    }
    
    /**
     * Dapatkan semua listener yang terdaftar
     */
    public function getListeners(): array
    {
        return $this->listeners;
    }
    
    /**
     * Dapatkan jumlah listener untuk tipe event
     */
    public function getListenerCount(string $eventType): int
    {
        return count($this->getListenersForEvent($eventType));
    }
    
    /**
     * Cek apakah tipe event memiliki listener
     */
    public function hasListeners(string $eventType): bool
    {
        return $this->getListenerCount($eventType) > 0;
    }
    
    /**
     * Aktifkan/nonaktifkan logging event
     */
    public function setLogging(bool $enabled): void
    {
        $this->loggingEnabled = $enabled;
    }
}

/**
 * Fungsi helper global untuk dispatching event
 */
function nexaEvent(NexaDomainEvent $event): void
{
    NexaEventDispatcher::getInstance()->dispatch($event);
}

/**
 * Fungsi helper global untuk mendengarkan event
 */
function nexaListen(string $eventType, callable $listener): void
{
    NexaEventDispatcher::getInstance()->listen($eventType, $listener);
} 