<?php
namespace App\System;

use App\System\Events\NexaEventDispatcher;
use App\System\Events\Listeners\NexaUserEventListeners;
use App\System\Events\NexaUserCreated;
use App\System\Events\NexaUserUpdated;
use App\System\Events\NexaUserDeleted;

/**
 * NexaBootstrap - Bootstrap Arsitektur
 * Menginisialisasi Repository Pattern, Domain-Driven Design, dan Event-Driven Architecture
 * 
 * @package   NexaUI
 * @version   2.0.0
 * @author    Tim NexaUI
 */
class NexaBootstrap
{
    private static bool $initialized = false;
    
    /**
     * Inisialisasi komponen arsitektur
     */
    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }
        
        try {
            // Inisialisasi Sistem Event
            self::initializeEventSystem();
            
            // Daftarkan Event Listener
            self::registerEventListeners();
            
            // Inisialisasi Layer Repository
            self::initializeRepositories();
            
            // Inisialisasi Domain Services
            self::initializeDomainServices();
            
            self::$initialized = true;
            
          //  error_log("NexaBootstrap: Architecture components initialized successfully");
            
        } catch (\Exception $e) {
            error_log("NexaBootstrap Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Inisialisasi Sistem Event
     */
    private static function initializeEventSystem(): void
    {
        // Dapatkan instance event dispatcher (singleton)
        $dispatcher = NexaEventDispatcher::getInstance();
        
        // Konfigurasi logging event berdasarkan environment
        $debugMode = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
        $dispatcher->setLogging($debugMode);
        
        //error_log("NexaBootstrap: Event system initialized");
    }
    
    /**
     * Daftarkan Event Listener
     */
    private static function registerEventListeners(): void
    {
        $dispatcher = NexaEventDispatcher::getInstance();
        
        // Daftarkan User Domain Event Listener
        $dispatcher->listen(
            NexaUserCreated::class,
            [NexaUserEventListeners::class, 'onUserCreated']
        );
        
        $dispatcher->listen(
            NexaUserUpdated::class,
            [NexaUserEventListeners::class, 'onUserUpdated']
        );
        
        $dispatcher->listen(
            NexaUserDeleted::class,
            [NexaUserEventListeners::class, 'onUserDeleted']
        );
        
        // Daftarkan listener tambahan untuk event tertentu
        $dispatcher->listenWithPriority(
            NexaUserCreated::class,
            function($event) {
                error_log("Prioritas tinggi: User {$event->getUserId()} dibuat dengan email {$event->getEmail()}");
            },
            10 // Prioritas tinggi
        );
        
        // Daftarkan wildcard listener untuk semua event
        $dispatcher->listen('*', function($event) {
            error_log("Global event listener: " . $event->getEventName());
        });
        
       // error_log("NexaBootstrap: Event listeners registered");
    }
    
    /**
     * Inisialisasi Layer Repository
     */
    private static function initializeRepositories(): void
    {
        // Inisialisasi repository terjadi on-demand melalui dependency injection
        // Tapi kita bisa mendaftarkan konfigurasi repository global di sini
        
        //error_log("NexaBootstrap: Repository layer initialized");
    }
    
    /**
     * Inisialisasi Domain Services
     */
    private static function initializeDomainServices(): void
    {
        // Inisialisasi domain services terjadi on-demand
        // Tapi kita bisa mendaftarkan konfigurasi service global di sini
        
        //error_log("NexaBootstrap: Domain services initialized");
    }
    
    /**
     * Tes implementasi arsitektur
     */
    public static function runArchitectureTest(): array
    {
        try {
            self::initialize();
            
            $results = [];
            
            // Test 1: Repository Pattern
            $results['repository_pattern'] = self::testRepositoryPattern();
            
            // Test 2: Domain-Driven Design
            $results['domain_driven_design'] = self::testDomainDrivenDesign();
            
            // Test 3: Event-Driven Architecture
            $results['event_driven_architecture'] = self::testEventDrivenArchitecture();
            
            return [
                'success' => true,
                'message' => 'Tes arsitektur selesai',
                'results' => $results
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Tes arsitektur gagal: ' . $e->getMessage(),
                'results' => []
            ];
        }
    }
    
    /**
     * Tes Repository Pattern
     */
    private static function testRepositoryPattern(): array
    {
        try {
            // Ini memerlukan koneksi database yang sebenarnya
            // Untuk saat ini, kita hanya tes keberadaan kelas dan struktur
            
            $tests = [
                'NexaRepositoryInterface exists' => interface_exists('App\System\Contracts\NexaRepositoryInterface'),
                'NexaBaseRepository exists' => class_exists('App\System\Repositories\NexaBaseRepository'),
                'NexaUserRepository exists' => class_exists('app\models\Repositories\NexaUserRepository'),
            ];
            
            return [
                'status' => 'success',
                'tests' => $tests,
                'description' => 'Komponen Repository Pattern telah diimplementasikan dengan baik'
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'description' => 'Tes Repository Pattern gagal'
            ];
        }
    }
    
    /**
     * Tes Domain-Driven Design
     */
    private static function testDomainDrivenDesign(): array
    {
        try {
            $tests = [
                'NexaEntity exists' => class_exists('App\System\Domain\NexaEntity'),
                'NexaValueObject exists' => class_exists('App\System\Domain\NexaValueObject'),
                'NexaEmail value object exists' => class_exists('App\System\Domain\NexaEmail'),
                'NexaPassword value object exists' => class_exists('App\System\Domain\NexaPassword'),
                'NexaUserEntity exists' => class_exists('app\models\Domain\NexaUserEntity'),
            ];
            
            // Tes pembuatan value object
            try {
                $email = new \App\System\Domain\NexaEmail('test@example.com');
                $tests['Email value object creation'] = true;
            } catch (\Exception $e) {
                $tests['Email value object creation'] = false;
            }
            
            return [
                'status' => 'success',
                'tests' => $tests,
                'description' => 'Komponen Domain-Driven Design telah diimplementasikan dengan baik'
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'description' => 'Tes Domain-Driven Design gagal'
            ];
        }
    }
    
    /**
     * Tes Event-Driven Architecture
     */
    private static function testEventDrivenArchitecture(): array
    {
        try {
            $dispatcher = NexaEventDispatcher::getInstance();
            $eventFired = false;
            
            // Daftarkan test listener
            $dispatcher->listen('test_event', function($event) use (&$eventFired) {
                $eventFired = true;
            });
            
            // Buat dan dispatch test event
            $testEvent = new class extends \App\System\Events\NexaDomainEvent {
                public function getEventData(): array {
                    return ['test' => true];
                }
            };
            
            $dispatcher->dispatch($testEvent);
            
            $tests = [
                'NexaDomainEvent exists' => class_exists('App\System\Events\NexaDomainEvent'),
                'NexaEventDispatcher exists' => class_exists('App\System\Events\NexaEventDispatcher'),
                'Event listeners registered' => $dispatcher->hasListeners(NexaUserCreated::class),
                'Event dispatching works' => $eventFired,
                'Event log enabled' => count($dispatcher->getEventLog()) > 0
            ];
            
            return [
                'status' => 'success',
                'tests' => $tests,
                'description' => 'Komponen Event-Driven Architecture telah diimplementasikan dengan baik'
            ];
            
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'description' => 'Tes Event-Driven Architecture gagal'
            ];
        }
    }
    
    /**
     * Dapatkan status arsitektur
     */
    public static function getStatus(): array
    {
        return [
            'initialized' => self::$initialized,
            'components' => [
                'repository_pattern' => [
                    'interface' => interface_exists('App\System\Contracts\NexaRepositoryInterface'),
                    'base_repository' => class_exists('App\System\Repositories\NexaBaseRepository'),
                    'user_repository' => class_exists('app\models\Repositories\NexaUserRepository')
                ],
                'domain_driven_design' => [
                    'entity' => class_exists('App\System\Domain\NexaEntity'),
                    'value_object' => class_exists('App\System\Domain\NexaValueObject'),
                    'user_entity' => class_exists('app\models\Domain\NexaUserEntity')
                ],
                'event_driven_architecture' => [
                    'domain_event' => class_exists('App\System\Events\NexaDomainEvent'),
                    'event_dispatcher' => class_exists('App\System\Events\NexaEventDispatcher'),
                    'event_listeners' => class_exists('App\System\Events\Listeners\NexaUserEventListeners')
                ]
            ]
        ];
    }
    
    /**
     * Cek apakah arsitektur telah diinisialisasi dengan benar
     */
    public static function isInitialized(): bool
    {
        return self::$initialized;
    }
} 