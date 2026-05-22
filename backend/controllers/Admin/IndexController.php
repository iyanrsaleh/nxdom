<?php
namespace App\Controllers\Admin;
use App\System\NexaController;

/**
 * IndexController - Simplified dengan 2 method saja
 */
class IndexController extends NexaController
{




    /**
     * Default method - Show dashboard index page with role-based redirect
     */
    public function index(array $params = []): void
    {
       // Get username from params
       $username = $params['username'] ?? $this->getSession()->getUserSlug();
       
       // Get user role from database
       $userId = $this->session->getUserId();
       $userData = $this->Storage('user')
           ->select(['role'])
           ->where('id', $userId)
           ->first();
       
       $userRole = $userData['role'] ?? 'user';
       
       // Redirect based on role
       if ($userRole === 'admin') {
           $this->redirect($this->getBaseUrl() . '/' . $username . '/home');
           return;
       } else {
           $this->redirect($this->getBaseUrl() . '/' . $username . '/home');
           return;
       }
    }
    

 
} 
