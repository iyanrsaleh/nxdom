<?php
declare(strict_types=1);
namespace App\Controllers\Frontend;
use App\System\NexaController;

/**
 * AboutController - Frontend About Page
 * Example of a frontend controller using Frontend namespace
 */
class AboutController extends NexaController
{
    /**
     * About page index
     */
    public function index(array $params = []): void
    {

        $this->assignVars([
            'page_title' => 'About Us - Our Company',
            'page_description' => 'Learn more about our company and mission',
            'current_page' => 'about',
            'is_public_page' => true,
            'company_info' => [
                'name' => 'NexaUI Framework',
                'founded' => '2024',
                'mission' => 'Building modern PHP frameworks for developers',
                'vision' => 'To simplify web development with powerful tools'
            ],
            'team_members' => [
                [
                    'name' => 'John Doe',
                    'position' => 'Lead Developer',
                    'bio' => 'Experienced PHP developer with 10+ years of experience'
                ],
                [
                    'name' => 'Jane Smith',
                    'position' => 'UI/UX Designer',
                    'bio' => 'Creative designer focused on user experience'
                ]
            ]
        ]);

        // $this->dump($params);
        
    }
    
    /**
     * Team page
     */
    public function team(): void
    {
        $this->assignVars([
            'page_title' => 'Our Team - Meet the Developers',
            'page_description' => 'Meet the talented team behind NexaUI Framework',
            'current_page' => 'about',
            'current_section' => 'team',
            'is_public_page' => true,
            'team_members' => [
                [
                    'name' => 'John Doe',
                    'position' => 'Lead Developer',
                    'bio' => 'Experienced PHP developer with 10+ years of experience',
                    'skills' => ['PHP', 'JavaScript', 'MySQL', 'Laravel'],
                    'avatar' => '/assets/images/team/john.jpg'
                ],
                [
                    'name' => 'Jane Smith',
                    'position' => 'UI/UX Designer',
                    'bio' => 'Creative designer focused on user experience',
                    'skills' => ['Figma', 'Adobe XD', 'HTML/CSS', 'JavaScript'],
                    'avatar' => '/assets/images/team/jane.jpg'
                ],
                [
                    'name' => 'Mike Johnson',
                    'position' => 'Backend Developer',
                    'bio' => 'Specialized in database design and API development',
                    'skills' => ['PHP', 'MySQL', 'PostgreSQL', 'Redis'],
                    'avatar' => '/assets/images/team/mike.jpg'
                ]
            ]
        ]);
    }
    
    /**
     * Contact page
     */
    public function contact(): void
    {
        $this->assignVars([
            'page_title' => 'Contact Us - Get in Touch',
            'page_description' => 'Get in touch with our team for support or inquiries',
            'current_page' => 'about',
            'current_section' => 'contact',
            'is_public_page' => true,
            'contact_info' => [
                'email' => 'info@nexaui.com',
                'phone' => '+1 (555) 123-4567',
                'address' => '123 Framework Street, Code City, CC 12345',
                'office_hours' => 'Monday - Friday: 9:00 AM - 6:00 PM'
            ]
        ]);
    }
} 