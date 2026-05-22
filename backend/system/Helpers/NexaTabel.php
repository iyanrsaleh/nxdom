<?php
declare(strict_types=1);

namespace App\System\Helpers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

/**
 * NexaTabel Helper Class
 * 
 * Helper class untuk menghasilkan tabel HTML dari data project yang kompleks
 * Mendukung project summary, planning tasks, activities, dan statistics
 * 
 * Fitur HTML:
 * - Membuat laporan project summary
 * - Membuat tabel planning tasks
 * - Membuat tabel activities dengan breakdown detail
 * - Membuat statistik project
 * - Customizable CSS classes dan styling
 * 
 * Fitur Excel Export:
 * - Export full report dengan multiple worksheets
 * - Export individual sections (summary, planning, activities)
 * - Professional styling dengan borders, colors, dan formatting
 * - Auto-sized columns untuk readability
 * - Statistics calculations dengan percentage
 * 
 * Usage HTML:
 * $tableHelper = new \App\System\Helpers\NexaTabel();
 * $reportHtml = $tableHelper->fullReport($data);
 * echo $reportHtml;
 * 
 * Usage Excel Export:
 * $tableHelper = new \App\System\Helpers\NexaTabel();
 * $tableHelper->exportToExcel($data, 'filename', true); // Full report with statistics
 * $tableHelper->exportSummaryToExcel($data, 'summary'); // Summary only
 * $tableHelper->exportPlanningToExcel($data, 'planning'); // Planning tasks only
 * $tableHelper->exportActivitiesToExcel($data, 'activities'); // Activities only
 * 
 * Available HTML methods:
 * - projectSummary($data) - Generate project summary table
 * - planningTasks($data) - Generate planning tasks table
 * - activities($data) - Generate activities table with details
 * - generateStatistics($data) - Generate statistics in table format with percentages
 * - generateStatisticsCards($data) - Generate statistics in card format (alternative)
 * - generateReport($data) - Generate basic report without statistics
 * - fullReport($data) - Generate complete report with statistics (Summary -> Statistics -> Planning -> Activities)
 * - generateCompleteReport($data, $includeStatistics, $statisticsPosition) - Generate report with custom order
 * - generateScrollableTable($data, $scrollHeight) - Generate scrollable table with NexaUI scroll classes
 * - generateResponsiveTable($data) - Generate responsive table with NexaUI responsive classes
 * 
 * Available Excel Export methods:
 * - exportToExcel($data, $filename, $includeStatistics) - Export full report to Excel
 * - exportSummaryToExcel($data, $filename) - Export project summary to Excel
 * - exportPlanningToExcel($data, $filename) - Export planning tasks to Excel
 * - exportActivitiesToExcel($data, $filename) - Export activities to Excel
 * 
 * CSS Classes Used:
 * - nx-table, nx-table-striped, nx-table-bordered, nx-table-hover
 * - nx-table-scroll-200, nx-table-scroll-300, nx-table-scroll-400, nx-table-scroll-500
 * - nx-table-responsive, nx-table-wrapper
 * - nx-badge dengan variant: success, primary, warning, secondary, light, danger, info
 * 
 * Excel Features:
 * - Multiple worksheets (Project Summary, Planning Tasks, Activities, Statistics)
 * - Professional styling dengan headers berwarna
 * - Borders dan alternate row colors
 * - Auto-sized columns
 * - Merged cells untuk headers
 * - Status indicators dan percentage calculations
 * 
 * Dependencies:
 * - PhpOffice/PhpSpreadsheet untuk Excel export functionality
 * 
 * @package App\System\Helpers
 * @author Tatiye.Net
 * @version 2.0
 */
class NexaTabel
{
    private $cssClasses;
    private $tableConfig;
    
    public function __construct()
    {
        $this->cssClasses = [
            'table' => 'nx-table nx-table-striped nx-table-bordered nx-table-hover',
            'header' => 'nx-table-header-primary',
            'row' => '',
            'cell' => '',
            'status' => [
                'done' => 'nx-badge nx-badge-success',
                'approved' => 'nx-badge nx-badge-primary', 
                'in_progress' => 'nx-badge nx-badge-warning',
                'todo' => 'nx-badge nx-badge-secondary',
                'pending' => 'nx-badge nx-badge-light',
                'high' => 'nx-badge nx-badge-danger',
                'medium' => 'nx-badge nx-badge-warning',
                'low' => 'nx-badge nx-badge-info',
                'completed' => 'nx-badge nx-badge-success',
                'active' => 'nx-badge nx-badge-primary',
                'inactive' => 'nx-badge nx-badge-secondary',
                'cancelled' => 'nx-badge nx-badge-danger',
                'on_hold' => 'nx-badge nx-badge-warning'
            ]
        ];
        
        $this->tableConfig = [
            'responsive' => true,
            'striped' => true,
            'bordered' => true,
            'hover' => true
        ];
    }
    
    /**
     * Validate and sanitize input data
     */
    private function validateData($data)
    {
        // Ensure basic structure exists
        if (!isset($data['id']) || !isset($data['project']) || !isset($data['data'])) {
            throw new \InvalidArgumentException('Invalid data structure: missing required fields');
        }
        
        // Set defaults for missing fields
        $data['data']['progress'] = $data['data']['progress'] ?? 0;
        $data['data']['priority'] = $data['data']['priority'] ?? 'medium';
        $data['data']['date'] = $data['data']['date'] ?? date('d F Y');
        $data['data']['budget'] = $data['data']['budget'] ?? 0;
        $data['data']['planning'] = $data['data']['planning'] ?? [];
        $data['data']['activity'] = $data['data']['activity'] ?? [];
        
        return $data;
    }
    
    /**
     * Get status badge with fallback
     */
    private function getStatusBadge($status)
    {
        $status = strtolower(trim($status));
        $statusClass = $this->cssClasses['status'][$status] ?? 'nx-badge nx-badge-secondary';
        $statusText = ucfirst(str_replace('_', ' ', $status));
        
        return '<span class="' . $statusClass . '">' . $statusText . '</span>';
    }
    
    /**
     * Format date consistently
     */
    private function formatDate($date)
    {
        if (empty($date)) {
            return 'Not set';
        }
        
        // Try to parse different date formats
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return htmlspecialchars((string)$date); // Return as-is if can't parse
        }
        
        return date('d M Y', $timestamp);
    }
    
    /**
     * Check if array is empty or has only empty arrays
     */
    private function hasValidData($array)
    {
        if (empty($array)) {
            return false;
        }
        
        foreach ($array as $item) {
            if (is_array($item) && !empty($item)) {
                if (isset($item['data']) && is_array($item['data'])) {
                    if (!empty($item['data'])) {
                        return true;
                    }
                } else {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Generate project summary table
     */
    public function projectSummary($data)
    {
        $data = $this->validateData($data);
        
        $html = '<div class="nx-card nx-mb-4" id="project-summary">';
        $html .= '<div class="nx-card-header"><h5 class="nx-mb-0">I RINGKASAN PROJECT</h5></div>';
        $html .= '<div class="nx-card-body">';
        
        $html .= '<div class="nx-table-wrapper">';
        $html .= '<table class="' . $this->cssClasses['table'] . '">';
        $html .= '<tbody>';
        
        $html .= '<tr>';
        $html .= '<td><strong>Project ID</strong></td>';
        $html .= '<td>' . htmlspecialchars((string)$data['id']) . '</td>';
        $html .= '</tr>';
        
        $html .= '<tr>';
        $html .= '<td><strong>Project Name</strong></td>';
        $html .= '<td>' . htmlspecialchars((string)$data['project']) . '</td>';
        $html .= '</tr>';
        
        $html .= '<tr>';
        $html .= '<td><strong>Progress</strong></td>';
        $html .= '<td>';
        $progress = max(0, min(100, (int)$data['data']['progress'])); // Ensure 0-100 range
        $progressColor = $progress >= 80 ? '#28a745' : ($progress >= 50 ? '#ffc107' : '#dc3545');
        $html .= '<div class="nx-progress" style="height: 20px; background-color: #e5e7eb; border-radius: 4px; overflow: hidden;">';
        $html .= '<div class="nx-progress-bar" style="width: ' . $progress . '%; background-color: ' . $progressColor . '; height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-size: 12px;">';
        $html .= $progress . '%';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</td>';
        $html .= '</tr>';
        
        $html .= '<tr>';
        $html .= '<td><strong>Priority</strong></td>';
        $html .= '<td>' . $this->getStatusBadge($data['data']['priority']) . '</td>';
        $html .= '</tr>';
        
        $html .= '<tr>';
        $html .= '<td><strong>Report Date</strong></td>';
        $html .= '<td>' . htmlspecialchars((string)$data['data']['date']) . '</td>';
        $html .= '</tr>';
        
        $html .= '<tr>';
        $html .= '<td><strong>Budget</strong></td>';
        $html .= '<td>';
        if ($data['data']['budget'] > 0) {
            $html .= 'Rp ' . number_format($data['data']['budget'], 0, ',', '.');
        } else {
            $html .= '<span class="nx-text-muted">Not specified</span>';
        }
        $html .= '</td>';
        $html .= '</tr>';
        
        // Add summary statistics
        $planningCount = count($data['data']['planning']);
        $activityCount = count($data['data']['activity']);
        $html .= '<tr>';
        $html .= '<td><strong>Planning Tasks</strong></td>';
        $html .= '<td>' . $planningCount . ' tasks</td>';
        $html .= '</tr>';
        
        $html .= '<tr>';
        $html .= '<td><strong>Activities</strong></td>';
        $html .= '<td>' . $activityCount . ' activities</td>';
        $html .= '</tr>';
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate planning tasks table
     */
    public function planningTasks($data)
    {
        if (empty($data['data']['planning'])) {
            return '<div class="nx-alert nx-alert-info">No planning tasks available</div>';
        }
        
        $html = '<div class="nx-card nx-mb-4" id="planning-tasks">';
        $html .= '<div class="nx-card-header"><h5 class="nx-mb-0">III PERENCANAAN</h5></div>';
        $html .= '<div class="nx-card-body">';
        
        // Add III.1 section header
        $html .= '<h6 class="nx-mb-3"><strong>III.1 ANALISIS KEBUTUHAN FITUR UTAMA</strong></h6>';
        
        $html .= '<div class="nx-table-wrapper">';
        $html .= '<table class="' . $this->cssClasses['table'] . '">';
        $html .= '<thead class="' . $this->cssClasses['header'] . '">';
        $html .= '<tr>';
        $html .= '<th>No</th>';
        $html .= '<th>Title</th>';
        $html .= '<th>Description</th>';
        $html .= '<th>Status</th>';
        $html .= '<th>Deadline</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        $planningNumber = 1;
        foreach ($data['data']['planning'] as $task) {
            $html .= '<tr>';
            $html .= '<td><strong>III.1.' . $planningNumber . '</strong></td>';
            $html .= '<td>' . htmlspecialchars((string)($task['title'] ?? '')) . '</td>';
            $html .= '<td>' . htmlspecialchars((string)($task['description'] ?? '')) . '</td>';
            $html .= '<td><span class="' . $this->cssClasses['status'][$task['status']] . '">' . 
                     ucfirst(str_replace('_', ' ', $task['status'])) . '</span></td>';
            $html .= '<td>' . htmlspecialchars((string)($task['deadline'] ?? '')) . '</td>';
            $html .= '</tr>';
            $planningNumber++;
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate activities table with detailed breakdown
     */
    public function activities($data)
    {
        if (empty($data['data']['activity'])) {
            return '<div class="nx-alert nx-alert-info">No activities available</div>';
        }
        
        $html = '<div class="nx-card nx-mb-4" id="activities">';
        $html .= '<div class="nx-card-header"><h5 class="nx-mb-0">IV AKTIVITAS PROJECT</h5></div>';
        $html .= '<div class="nx-card-body">';
        
        $activityNumber = 1;
        foreach ($data['data']['activity'] as $activity) {
            $html .= '<div class="nx-card nx-mb-3" id="activity-' . $activityNumber . '">';
            $html .= '<div class="nx-card-header" style="background-color: var(--nx-gray-25);">';
            $html .= '<h6 class="nx-mb-0"><strong>IV.' . $activityNumber . '</strong> - ' . htmlspecialchars((string)($activity['tugas'] ?? '')) . '</h6>';
            $html .= '<small class="nx-text-muted">Deadline: ' . htmlspecialchars((string)($activity['deadline'] ?? '')) . '</small>';
            $html .= '</div>';
            $html .= '<div class="nx-card-body">';
            
            if (!empty($activity['data'])) {
                $subTaskNumber = 1;
                foreach ($activity['data'] as $taskData) {
                    $html .= '<div class="nx-mb-3">';
                    $html .= '<h6><strong>IV.' . $activityNumber . '.' . $subTaskNumber . '</strong> ' . htmlspecialchars((string)($taskData['title'] ?? 'Untitled Task')) . '</h6>';
                    $html .= '<p class="nx-text-muted">' . htmlspecialchars((string)($taskData['description'] ?? 'No description')) . '</p>';
                    $html .= '<div class="nx-flex nx-justify-between nx-mb-2">';
                    $html .= '<span>Date: ' . $this->formatDate($taskData['date'] ?? '') . '</span>';
                    $html .= '<span>' . $this->getStatusBadge($taskData['status'] ?? 'pending') . '</span>';
                    $html .= '</div>';
                    
                    if (!empty($taskData['item'])) {
                        $html .= '<div class="nx-table-wrapper">';
                        $html .= '<table class="nx-table nx-table-sm nx-table-bordered">';
                        $html .= '<thead>';
                        $html .= '<tr>';
                        $html .= '<th>No</th>';
                        $html .= '<th>Task</th>';
                        $html .= '<th>Status</th>';
                        $html .= '<th>Date</th>';
                        $html .= '</tr>';
                        $html .= '</thead>';
                        $html .= '<tbody>';
                        
                        $itemNumber = 1;
                        foreach ($taskData['item'] as $item) {
                            $html .= '<tr>';
                            $html .= '<td><strong>IV.' . $activityNumber . '.' . $subTaskNumber . '.' . $itemNumber . '</strong></td>';
                            $html .= '<td>' . htmlspecialchars((string)($item['title'] ?? 'No title')) . '</td>';
                            $html .= '<td>' . $this->getStatusBadge($item['status'] ?? 'pending') . '</td>';
                            $html .= '<td>' . $this->formatDate($item['date'] ?? '') . '</td>';
                            $html .= '</tr>';
                            $itemNumber++;
                        }
                        
                        $html .= '</tbody>';
                        $html .= '</table>';
                        $html .= '</div>';
                    } else {
                        $html .= '<div class="nx-alert nx-alert-info" style="margin-top: 10px;">No sub-tasks assigned for this task</div>';
                    }
                    
                    $html .= '</div>';
                    $subTaskNumber++;
                }
            } else {
                $html .= '<div class="nx-alert nx-alert-warning">';
                $html .= '<strong>No task data available</strong><br>';
                $html .= 'This activity "' . htmlspecialchars((string)($activity['tugas'] ?? 'Unknown Task')) . '" has not been broken down into specific tasks yet.';
                $html .= '</div>';
            }
            
            $html .= '</div>';
            $html .= '</div>';
            $activityNumber++;
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate complete project report
     */
    public function generateReport($data)
    {
        $html = '<div class="nx-container-fluid">';
        $html .= '<div class="nx-row">';
        $html .= '<div class="nx-col-12">';
        
        // Project Summary
        $html .= $this->projectSummary($data);
        
        // Planning Tasks
        $html .= $this->planningTasks($data);
        
        // Activities
        $html .= $this->activities($data);
        
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate statistics summary in table format
     */
    public function generateStatistics($data)
    {
        $data = $this->validateData($data);
        
        $stats = [
            'total_planning' => count($data['data']['planning']),
            'total_activities' => count($data['data']['activity']),
            'total_tasks' => 0,
            'total_items' => 0,
            'completed_tasks' => 0,
            'approved_items' => 0,
            'planning_completed' => 0,
            'activities_with_data' => 0
        ];
        
        // Count planning task statuses
        foreach ($data['data']['planning'] as $planning) {
            if (isset($planning['status']) && in_array($planning['status'], ['done', 'completed', 'approved'])) {
                $stats['planning_completed']++;
            }
        }
        
        // Count activity and task statistics
        foreach ($data['data']['activity'] as $activity) {
            if (!empty($activity['data'])) {
                $stats['activities_with_data']++;
                $stats['total_tasks'] += count($activity['data']);
                
                foreach ($activity['data'] as $task) {
                    if (isset($task['item']) && is_array($task['item'])) {
                        $stats['total_items'] += count($task['item']);
                    }
                    
                    if (isset($task['status']) && in_array($task['status'], ['done', 'completed'])) {
                        $stats['completed_tasks']++;
                    }
                    
                    if (isset($task['item']) && is_array($task['item'])) {
                        foreach ($task['item'] as $item) {
                            if (isset($item['status']) && in_array($item['status'], ['approved', 'completed', 'done'])) {
                                $stats['approved_items']++;
                            }
                        }
                    }
                }
            }
        }
        
        // Calculate percentages
        $totalWork = $stats['total_tasks'] + $stats['total_items'];
        $completedWork = $stats['completed_tasks'] + $stats['approved_items'];
        $completionRate = $totalWork > 0 ? round(($completedWork / $totalWork) * 100, 1) : 0;
        
        $html = '<div class="nx-card nx-mb-4" id="statistics">';
        $html .= '<div class="nx-card-header"><h5 class="nx-mb-0">II STATISTIK PROJECT</h5></div>';
        $html .= '<div class="nx-card-body">';
        
        $html .= '<div class="nx-table-wrapper">';
        $html .= '<table class="' . $this->cssClasses['table'] . '">';
        $html .= '<thead class="' . $this->cssClasses['header'] . '">';
        $html .= '<tr>';
        $html .= '<th>Metric</th>';
        $html .= '<th class="text-center">Count</th>';
        $html .= '<th class="text-center">Percentage</th>';
        $html .= '<th>Status</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        // Planning Tasks Row
        $planningPercentage = $stats['total_planning'] > 0 ? round(($stats['total_planning'] / ($stats['total_planning'] + $stats['total_activities'])) * 100, 1) : 0;
        $html .= '<tr>';
        $html .= '<td><strong>Planning Tasks</strong></td>';
        $html .= '<td class="text-center"><span style="color: var(--nx-primary-dark); font-weight: bold; font-size: 1.2em;">' . $stats['total_planning'] . '</span></td>';
        $html .= '<td class="text-center">' . $planningPercentage . '%</td>';
        $html .= '<td><span class="nx-badge nx-badge-primary">Active</span></td>';
        $html .= '</tr>';
        
        // Activities Row
        $activitiesPercentage = $stats['total_activities'] > 0 ? round(($stats['total_activities'] / ($stats['total_planning'] + $stats['total_activities'])) * 100, 1) : 0;
        $html .= '<tr>';
        $html .= '<td><strong>Activities</strong></td>';
        $html .= '<td class="text-center"><span style="color: #17a2b8; font-weight: bold; font-size: 1.2em;">' . $stats['total_activities'] . '</span></td>';
        $html .= '<td class="text-center">' . $activitiesPercentage . '%</td>';
        $html .= '<td><span class="nx-badge nx-badge-info">In Progress</span></td>';
        $html .= '</tr>';
        
        // Tasks Row
        $tasksPercentage = $totalWork > 0 ? round(($stats['total_tasks'] / $totalWork) * 100, 1) : 0;
        $html .= '<tr>';
        $html .= '<td><strong>Tasks</strong></td>';
        $html .= '<td class="text-center"><span style="color: #ffc107; font-weight: bold; font-size: 1.2em;">' . $stats['total_tasks'] . '</span></td>';
        $html .= '<td class="text-center">' . $tasksPercentage . '%</td>';
        $html .= '<td><span class="nx-badge nx-badge-warning">Pending</span></td>';
        $html .= '</tr>';
        
        // Items Row
        $itemsPercentage = $totalWork > 0 ? round(($stats['total_items'] / $totalWork) * 100, 1) : 0;
        $html .= '<tr>';
        $html .= '<td><strong>Items</strong></td>';
        $html .= '<td class="text-center"><span style="color: var(--nx-gray-600); font-weight: bold; font-size: 1.2em;">' . $stats['total_items'] . '</span></td>';
        $html .= '<td class="text-center">' . $itemsPercentage . '%</td>';
        $html .= '<td><span class="nx-badge nx-badge-secondary">Assigned</span></td>';
        $html .= '</tr>';
        
        // Completed Tasks Row
        $completedTasksPercentage = $stats['total_tasks'] > 0 ? round(($stats['completed_tasks'] / $stats['total_tasks']) * 100, 1) : 0;
        $html .= '<tr>';
        $html .= '<td><strong>Completed Tasks</strong></td>';
        $html .= '<td class="text-center"><span style="color: #28a745; font-weight: bold; font-size: 1.2em;">' . $stats['completed_tasks'] . '</span></td>';
        $html .= '<td class="text-center">' . $completedTasksPercentage . '%</td>';
        $html .= '<td><span class="nx-badge nx-badge-success">Completed</span></td>';
        $html .= '</tr>';
        
        // Approved Items Row
        $approvedItemsPercentage = $stats['total_items'] > 0 ? round(($stats['approved_items'] / $stats['total_items']) * 100, 1) : 0;
        $html .= '<tr>';
        $html .= '<td><strong>Approved Items</strong></td>';
        $html .= '<td class="text-center"><span style="color: var(--nx-primary-dark); font-weight: bold; font-size: 1.2em;">' . $stats['approved_items'] . '</span></td>';
        $html .= '<td class="text-center">' . $approvedItemsPercentage . '%</td>';
        $html .= '<td><span class="nx-badge nx-badge-primary">Approved</span></td>';
        $html .= '</tr>';
        
        // Overall Completion Rate Row
        $html .= '<tr class="nx-table-success">';
        $html .= '<td><strong>Overall Completion Rate</strong></td>';
        $html .= '<td class="text-center"><span style="color: #28a745; font-weight: bold; font-size: 1.3em;">' . $completedWork . '/' . $totalWork . '</span></td>';
        $html .= '<td class="text-center"><strong>' . $completionRate . '%</strong></td>';
        $html .= '<td>';
        if ($completionRate >= 80) {
            $html .= '<span class="nx-badge nx-badge-success">Excellent</span>';
        } elseif ($completionRate >= 60) {
            $html .= '<span class="nx-badge nx-badge-primary">Good</span>';
        } elseif ($completionRate >= 40) {
            $html .= '<span class="nx-badge nx-badge-warning">Fair</span>';
        } else {
            $html .= '<span class="nx-badge nx-badge-danger">Needs Attention</span>';
        }
        $html .= '</td>';
        $html .= '</tr>';
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate statistics summary in card format (alternative)
     */
    public function generateStatisticsCards($data)
    {
        $stats = [
            'total_planning' => count($data['data']['planning']),
            'total_activities' => count($data['data']['activity']),
            'total_tasks' => 0,
            'total_items' => 0,
            'completed_tasks' => 0,
            'approved_items' => 0
        ];
        
        foreach ($data['data']['activity'] as $activity) {
            $stats['total_tasks'] += count($activity['data']);
            foreach ($activity['data'] as $task) {
                $stats['total_items'] += count($task['item']);
                if ($task['status'] === 'done') {
                    $stats['completed_tasks']++;
                }
                foreach ($task['item'] as $item) {
                    if ($item['status'] === 'approved') {
                        $stats['approved_items']++;
                    }
                }
            }
        }
        
        $html = '<div class="nx-card nx-mb-4">';
        $html .= '<div class="nx-card-header"><h5 class="nx-mb-0">Project Statistics (Cards)</h5></div>';
        $html .= '<div class="nx-card-body">';
        $html .= '<div class="nx-row">';
        
        $html .= '<div class="nx-col-md-2">';
        $html .= '<div class="nx-card text-center">';
        $html .= '<div class="nx-card-body">';
        $html .= '<h3 style="color: var(--nx-primary-dark);">' . $stats['total_planning'] . '</h3>';
        $html .= '<p class="nx-mb-0">Planning Tasks</p>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '<div class="nx-col-md-2">';
        $html .= '<div class="nx-card text-center">';
        $html .= '<div class="nx-card-body">';
        $html .= '<h3 style="color: #17a2b8;">' . $stats['total_activities'] . '</h3>';
        $html .= '<p class="nx-mb-0">Activities</p>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '<div class="nx-col-md-2">';
        $html .= '<div class="nx-card text-center">';
        $html .= '<div class="nx-card-body">';
        $html .= '<h3 style="color: #ffc107;">' . $stats['total_tasks'] . '</h3>';
        $html .= '<p class="nx-mb-0">Tasks</p>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '<div class="nx-col-md-2">';
        $html .= '<div class="nx-card text-center">';
        $html .= '<div class="nx-card-body">';
        $html .= '<h3 style="color: var(--nx-gray-600);">' . $stats['total_items'] . '</h3>';
        $html .= '<p class="nx-mb-0">Items</p>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '<div class="nx-col-md-2">';
        $html .= '<div class="nx-card text-center">';
        $html .= '<div class="nx-card-body">';
        $html .= '<h3 style="color: #28a745;">' . $stats['completed_tasks'] . '</h3>';
        $html .= '<p class="nx-mb-0">Completed</p>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '<div class="nx-col-md-2">';
        $html .= '<div class="nx-card text-center">';
        $html .= '<div class="nx-card-body">';
        $html .= '<h3 style="color: var(--nx-primary-dark);">' . $stats['approved_items'] . '</h3>';
        $html .= '<p class="nx-mb-0">Approved</p>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate full report with statistics
     */
    public function fullReport($data)
    {
        $html = '<div class="nx-container-fluid">';
        $html .= '<div class="nx-row">';
        $html .= '<div class="nx-col-12">';
        
        // Add custom CSS for better styling with NexaUI variables
        $html .= '<style>';
        $html .= '.nx-badge { font-size: 0.75em; padding: 0.25em 0.5em; border-radius: 0.25rem; }';
        $html .= '.nx-badge-success { background-color: #28a745; color: white; }';
        $html .= '.nx-badge-primary { background-color: var(--nx-primary-dark); color: white; }';
        $html .= '.nx-badge-warning { background-color: #ffc107; color: #212529; }';
        $html .= '.nx-badge-secondary { background-color: #6c757d; color: white; }';
        $html .= '.nx-badge-light { background-color: #f8f9fa; color: #212529; border: 1px solid #dee2e6; }';
        $html .= '.nx-badge-danger { background-color: #dc3545; color: white; }';
        $html .= '.nx-badge-info { background-color: #17a2b8; color: white; }';
        $html .= '.nx-progress { margin-bottom: 0; }';
        $html .= '.nx-card { box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); margin-bottom: 1rem; border: 1px solid var(--nx-gray-75); border-radius: 0.25rem; }';
        $html .= '.nx-card-header { padding: 0.75rem 1rem; background-color: var(--nx-gray-25); border-bottom: 1px solid var(--nx-gray-75); }';
        $html .= '.nx-card-body { padding: 1rem; }';
        $html .= '.nx-mb-0 { margin-bottom: 0; }';
        $html .= '.nx-mb-2 { margin-bottom: 0.5rem; }';
        $html .= '.nx-mb-3 { margin-bottom: 1rem; }';
        $html .= '.nx-mb-4 { margin-bottom: 1.5rem; }';
        $html .= '.nx-text-muted { color: var(--nx-gray-600); }';
        $html .= '.nx-flex { display: flex; }';
        $html .= '.nx-justify-between { justify-content: space-between; }';
        $html .= '.nx-row { display: flex; flex-wrap: wrap; margin: -0.5rem; }';
        $html .= '.nx-col-12 { flex: 0 0 100%; max-width: 100%; padding: 0.5rem; }';
        $html .= '.nx-col-md-2 { flex: 0 0 16.666667%; max-width: 16.666667%; padding: 0.5rem; }';
        $html .= '.text-center { text-align: center; }';
        $html .= '.nx-alert { padding: 0.75rem 1rem; margin-bottom: 1rem; border: 1px solid transparent; border-radius: 0.25rem; }';
        $html .= '.nx-alert-info { color: #0c5460; background-color: #d1ecf1; border-color: #bee5eb; }';
        $html .= '.nx-alert-warning { color: #856404; background-color: #fff3cd; border-color: #ffeaa7; }';
        $html .= '</style>';
        
        // Project Summary
        $html .= $this->projectSummary($data);
        
        // Statistics
        $html .= $this->generateStatistics($data);
        
        // Planning Tasks
        $html .= $this->planningTasks($data);
        
        // Activities
        $html .= $this->activities($data);
        
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate complete report with custom order
     */
    public function generateCompleteReport($data, $includeStatistics = true, $statisticsPosition = 'after_summary')
    {
        $html = '<div class="nx-container-fluid">';
        $html .= '<div class="nx-row">';
        $html .= '<div class="nx-col-12">';
        
        // Add custom CSS for better styling with NexaUI variables
        $html .= '<style>';
        $html .= '.nx-badge { font-size: 0.75em; padding: 0.25em 0.5em; border-radius: 0.25rem; }';
        $html .= '.nx-badge-success { background-color: #28a745; color: white; }';
        $html .= '.nx-badge-primary { background-color: var(--nx-primary-dark); color: white; }';
        $html .= '.nx-badge-warning { background-color: #ffc107; color: #212529; }';
        $html .= '.nx-badge-secondary { background-color: #6c757d; color: white; }';
        $html .= '.nx-badge-light { background-color: #f8f9fa; color: #212529; border: 1px solid #dee2e6; }';
        $html .= '.nx-badge-danger { background-color: #dc3545; color: white; }';
        $html .= '.nx-badge-info { background-color: #17a2b8; color: white; }';
        $html .= '.nx-progress { margin-bottom: 0; }';
        $html .= '.nx-card { box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); margin-bottom: 1rem; border: 1px solid var(--nx-gray-75); border-radius: 0.25rem; }';
        $html .= '.nx-card-header { padding: 0.75rem 1rem; background-color: var(--nx-gray-25); border-bottom: 1px solid var(--nx-gray-75); }';
        $html .= '.nx-card-body { padding: 1rem; }';
        $html .= '.nx-mb-0 { margin-bottom: 0; }';
        $html .= '.nx-mb-2 { margin-bottom: 0.5rem; }';
        $html .= '.nx-mb-3 { margin-bottom: 1rem; }';
        $html .= '.nx-mb-4 { margin-bottom: 1.5rem; }';
        $html .= '.nx-text-muted { color: var(--nx-gray-600); }';
        $html .= '.nx-flex { display: flex; }';
        $html .= '.nx-justify-between { justify-content: space-between; }';
        $html .= '.nx-row { display: flex; flex-wrap: wrap; margin: -0.5rem; }';
        $html .= '.nx-col-12 { flex: 0 0 100%; max-width: 100%; padding: 0.5rem; }';
        $html .= '.nx-col-md-2 { flex: 0 0 16.666667%; max-width: 16.666667%; padding: 0.5rem; }';
        $html .= '.text-center { text-align: center; }';
        $html .= '.nx-alert { padding: 0.75rem 1rem; margin-bottom: 1rem; border: 1px solid transparent; border-radius: 0.25rem; }';
        $html .= '.nx-alert-info { color: #0c5460; background-color: #d1ecf1; border-color: #bee5eb; }';
        $html .= '.nx-alert-warning { color: #856404; background-color: #fff3cd; border-color: #ffeaa7; }';
        $html .= '</style>';
        
        // Project Summary (Always first)
        $html .= $this->projectSummary($data);
        
        // Statistics (Position based on parameter)
        if ($includeStatistics && $statisticsPosition === 'after_summary') {
            $html .= $this->generateStatistics($data);
        }
        
        // Planning Tasks
        $html .= $this->planningTasks($data);
        
        // Statistics (Alternative position)
        if ($includeStatistics && $statisticsPosition === 'after_planning') {
            $html .= $this->generateStatistics($data);
        }
        
        // Activities
        $html .= $this->activities($data);
        
        // Statistics (Alternative position)
        if ($includeStatistics && $statisticsPosition === 'after_activities') {
            $html .= $this->generateStatistics($data);
        }
        
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Set custom CSS classes
     */
    public function setCssClasses($classes)
    {
        $this->cssClasses = array_merge($this->cssClasses, $classes);
        return $this;
    }
    
    /**
     * Set table configuration
     */
    public function setTableConfig($config)
    {
        $this->tableConfig = array_merge($this->tableConfig, $config);
        return $this;
    }
    
    /**
     * Generate table with scroll wrapper
     */
    public function generateScrollableTable($data, $scrollHeight = 300)
    {
        $scrollClass = match($scrollHeight) {
            200 => 'nx-table-scroll-200',
            300 => 'nx-table-scroll-300', 
            400 => 'nx-table-scroll-400',
            500 => 'nx-table-scroll-500',
            default => 'nx-table-scroll-300'
        };
        
        $html = '<div class="nx-card nx-mb-4">';
        $html .= '<div class="nx-card-header"><h5 class="nx-mb-0">Scrollable Report</h5></div>';
        $html .= '<div class="nx-card-body">';
        $html .= '<div class="' . $scrollClass . '">';
        
        // Planning Tasks Table
        if (!empty($data['data']['planning'])) {
            $html .= '<table class="' . $this->cssClasses['table'] . '">';
            $html .= '<thead class="' . $this->cssClasses['header'] . '">';
            $html .= '<tr>';
            $html .= '<th>No</th>';
            $html .= '<th>Title</th>';
            $html .= '<th>Description</th>';
            $html .= '<th>Status</th>';
            $html .= '<th>Deadline</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            
            $planningNumber = 1;
            foreach ($data['data']['planning'] as $task) {
                $html .= '<tr>';
                $html .= '<td><strong>III.1.' . $planningNumber . '</strong></td>';
                $html .= '<td>' . htmlspecialchars((string)($task['title'] ?? '')) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)($task['description'] ?? '')) . '</td>';
                $html .= '<td><span class="' . $this->cssClasses['status'][$task['status']] . '">' . 
                         ucfirst(str_replace('_', ' ', $task['status'])) . '</span></td>';
                $html .= '<td>' . htmlspecialchars((string)($task['deadline'] ?? '')) . '</td>';
                $html .= '</tr>';
                $planningNumber++;
            }
            
            $html .= '</tbody>';
            $html .= '</table>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generate responsive table
     */
    public function generateResponsiveTable($data)
    {
        $html = '<div class="nx-card nx-mb-4">';
        $html .= '<div class="nx-card-header"><h5 class="nx-mb-0">Responsive Report</h5></div>';
        $html .= '<div class="nx-card-body">';
        $html .= '<div class="nx-table-responsive">';
        
        if (!empty($data['data']['planning'])) {
            $html .= '<table class="' . $this->cssClasses['table'] . '">';
            $html .= '<thead class="' . $this->cssClasses['header'] . '">';
            $html .= '<tr>';
            $html .= '<th>ID</th>';
            $html .= '<th>Title</th>';
            $html .= '<th>Description</th>';
            $html .= '<th>Status</th>';
            $html .= '<th>Deadline</th>';
            $html .= '<th>Actions</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            
            foreach ($data['data']['planning'] as $task) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars((string)($task['id'] ?? '')) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)($task['title'] ?? '')) . '</td>';
                $html .= '<td>' . htmlspecialchars((string)($task['description'] ?? '')) . '</td>';
                $html .= '<td><span class="' . $this->cssClasses['status'][$task['status']] . '">' . 
                         ucfirst(str_replace('_', ' ', $task['status'])) . '</span></td>';
                $html .= '<td>' . htmlspecialchars((string)($task['deadline'] ?? '')) . '</td>';
                $html .= '<td>';
                $html .= '<button class="nx-btn nx-btn-sm nx-btn-primary">View</button> ';
                $html .= '<button class="nx-btn nx-btn-sm nx-btn-secondary">Edit</button>';
                $html .= '</td>';
                $html .= '</tr>';
            }
            
            $html .= '</tbody>';
            $html .= '</table>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Export data to Excel format
     * 
     * @param array $data Project data
     * @param string $filename Output filename (without extension)
     * @param bool $includeStatistics Whether to include statistics sheet
     * @return bool Success status
     */
    public function exportToExcel($data, $filename = 'project_report', $includeStatistics = true)
    {
        try {
            // Validate data
            if (!$this->validateData($data)) {
                throw new \Exception('Invalid data provided for Excel export');
            }

            $spreadsheet = new Spreadsheet();
            
            // Remove default worksheet
            $spreadsheet->removeSheetByIndex(0);
            
            // Create worksheets
            $this->createProjectSummarySheet($spreadsheet, $data);
            $this->createPlanningTasksSheet($spreadsheet, $data);
            $this->createActivitiesSheet($spreadsheet, $data);
            
            if ($includeStatistics) {
                $this->createStatisticsSheet($spreadsheet, $data);
            }
            
            // Set active sheet to first sheet
            $spreadsheet->setActiveSheetIndex(0);
            
            // Create writer and save
            $writer = new Xlsx($spreadsheet);
            $filename = $filename . '.xlsx';
            
            // Set headers for download
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            
            $writer->save('php://output');
            return true;
            
        } catch (\Exception $e) {
            error_log('Excel export error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create Project Summary worksheet
     */
    private function createProjectSummarySheet($spreadsheet, $data)
    {
        $worksheet = $spreadsheet->createSheet();
        $worksheet->setTitle('Project Summary');
        
        // Header
        $worksheet->setCellValue('A1', 'Project Summary');
        $worksheet->mergeCells('A1:B1');
        $this->styleHeader($worksheet, 'A1:B1');
        
        // Project data
        $row = 3;
        $projectData = [
            'Project ID' => $data['id'] ?? 'N/A',
            'Project Name' => $data['project'] ?? 'N/A',
            'Progress' => ($data['data']['progress'] ?? 0) . '%',
            'Priority' => ucfirst($data['data']['priority'] ?? 'medium'),
            'Date' => $data['data']['date'] ?? 'N/A',
            'Budget' => 'Rp ' . number_format($data['data']['budget'] ?? 0, 0, ',', '.')
        ];
        
        foreach ($projectData as $label => $value) {
            $worksheet->setCellValue("A{$row}", $label);
            $worksheet->setCellValue("B{$row}", $value);
            $row++;
        }
        
        // Style the data
        $dataRange = "A3:B" . ($row - 1);
        $this->styleDataTable($worksheet, $dataRange);
        
        // Auto-size columns
        $worksheet->getColumnDimension('A')->setAutoSize(true);
        $worksheet->getColumnDimension('B')->setAutoSize(true);
    }
    
    /**
     * Create Planning Tasks worksheet
     */
    private function createPlanningTasksSheet($spreadsheet, $data)
    {
        $worksheet = $spreadsheet->createSheet();
        $worksheet->setTitle('Planning Tasks');
        
        // Header
        $worksheet->setCellValue('A1', 'Planning Tasks');
        $worksheet->mergeCells('A1:F1');
        $this->styleHeader($worksheet, 'A1:F1');
        
        // Table headers
        $headers = ['ID', 'Task', 'Status', 'Priority', 'Start Date', 'End Date'];
        $col = 'A';
        foreach ($headers as $header) {
            $worksheet->setCellValue($col . '3', $header);
            $col++;
        }
        $this->styleTableHeader($worksheet, 'A3:F3');
        
        // Data rows
        $row = 4;
        $planning = $data['data']['planning'] ?? [];
        
        if (!empty($planning)) {
            foreach ($planning as $task) {
                $worksheet->setCellValue("A{$row}", $task['id'] ?? 'N/A');
                $worksheet->setCellValue("B{$row}", $task['title'] ?? 'N/A');
                $worksheet->setCellValue("C{$row}", ucfirst($task['status'] ?? 'pending'));
                $worksheet->setCellValue("D{$row}", ucfirst($task['priority'] ?? 'medium'));
                $worksheet->setCellValue("E{$row}", $this->formatDate($task['start_date'] ?? null));
                $worksheet->setCellValue("F{$row}", $this->formatDate($task['end_date'] ?? null));
                $row++;
            }
        } else {
            $worksheet->setCellValue("A{$row}", 'No planning tasks available');
            $worksheet->mergeCells("A{$row}:F{$row}");
            $row++;
        }
        
        // Style the data table
        $dataRange = "A3:F" . ($row - 1);
        $this->styleDataTable($worksheet, $dataRange);
        
        // Auto-size columns
        foreach (range('A', 'F') as $col) {
            $worksheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
    
    /**
     * Create Activities worksheet
     */
    private function createActivitiesSheet($spreadsheet, $data)
    {
        $worksheet = $spreadsheet->createSheet();
        $worksheet->setTitle('Activities');
        
        // Header
        $worksheet->setCellValue('A1', 'Project Activities');
        $worksheet->mergeCells('A1:G1');
        $this->styleHeader($worksheet, 'A1:G1');
        
        // Table headers
        $headers = ['User', 'Activity', 'Status', 'Priority', 'Progress', 'Start Date', 'End Date'];
        $col = 'A';
        foreach ($headers as $header) {
            $worksheet->setCellValue($col . '3', $header);
            $col++;
        }
        $this->styleTableHeader($worksheet, 'A3:G3');
        
        // Data rows
        $row = 4;
        $activities = $data['data']['activity'] ?? [];
        
        if (!empty($activities)) {
            foreach ($activities as $user) {
                $userName = $user['name'] ?? 'Unknown User';
                $userActivities = $user['data'] ?? [];
                
                if (!empty($userActivities)) {
                    foreach ($userActivities as $activity) {
                        $worksheet->setCellValue("A{$row}", $userName);
                        $worksheet->setCellValue("B{$row}", $activity['title'] ?? 'N/A');
                        $worksheet->setCellValue("C{$row}", ucfirst($activity['status'] ?? 'pending'));
                        $worksheet->setCellValue("D{$row}", ucfirst($activity['priority'] ?? 'medium'));
                        $worksheet->setCellValue("E{$row}", ($activity['progress'] ?? 0) . '%');
                        $worksheet->setCellValue("F{$row}", $this->formatDate($activity['start_date'] ?? null));
                        $worksheet->setCellValue("G{$row}", $this->formatDate($activity['end_date'] ?? null));
                        $row++;
                    }
                } else {
                    $worksheet->setCellValue("A{$row}", $userName);
                    $worksheet->setCellValue("B{$row}", 'No activities assigned');
                    $worksheet->mergeCells("B{$row}:G{$row}");
                    $row++;
                }
            }
        } else {
            $worksheet->setCellValue("A{$row}", 'No activities available');
            $worksheet->mergeCells("A{$row}:G{$row}");
            $row++;
        }
        
        // Style the data table
        $dataRange = "A3:G" . ($row - 1);
        $this->styleDataTable($worksheet, $dataRange);
        
        // Auto-size columns
        foreach (range('A', 'G') as $col) {
            $worksheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
    
    /**
     * Create Statistics worksheet
     */
    private function createStatisticsSheet($spreadsheet, $data)
    {
        $worksheet = $spreadsheet->createSheet();
        $worksheet->setTitle('Statistics');
        
        // Header
        $worksheet->setCellValue('A1', 'Project Statistics');
        $worksheet->mergeCells('A1:D1');
        $this->styleHeader($worksheet, 'A1:D1');
        
        // Table headers
        $headers = ['Metric', 'Count', 'Percentage', 'Status'];
        $col = 'A';
        foreach ($headers as $header) {
            $worksheet->setCellValue($col . '3', $header);
            $col++;
        }
        $this->styleTableHeader($worksheet, 'A3:D3');
        
        // Calculate statistics
        $stats = $this->calculateStatistics($data);
        
        // Data rows
        $row = 4;
        foreach ($stats as $metric => $values) {
            $worksheet->setCellValue("A{$row}", $metric);
            $worksheet->setCellValue("B{$row}", $values['count']);
            $worksheet->setCellValue("C{$row}", $values['percentage'] . '%');
            $worksheet->setCellValue("D{$row}", $values['status']);
            $row++;
        }
        
        // Overall completion
        $overallCompletion = $this->calculateOverallCompletion($data);
        $row++;
        $worksheet->setCellValue("A{$row}", 'Overall Completion');
        $worksheet->setCellValue("B{$row}", '-');
        $worksheet->setCellValue("C{$row}", $overallCompletion['percentage'] . '%');
        $worksheet->setCellValue("D{$row}", $overallCompletion['status']);
        
        // Style the data table
        $dataRange = "A3:D" . $row;
        $this->styleDataTable($worksheet, $dataRange);
        
        // Auto-size columns
        foreach (range('A', 'D') as $col) {
            $worksheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
    
    /**
     * Style header cells
     */
    private function styleHeader($worksheet, $range)
    {
        $worksheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['argb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => '4472C4']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);
    }
    
    /**
     * Style table header cells
     */
    private function styleTableHeader($worksheet, $range)
    {
        $worksheet->getStyle($range)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => '70AD47']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000']
                ]
            ]
        ]);
    }
    
    /**
     * Style data table cells
     */
    private function styleDataTable($worksheet, $range)
    {
        $worksheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000']
                ]
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ]);
        
        // Alternate row colors
        $startRow = (int)explode(':', $range)[0][1];
        $endRow = (int)explode(':', $range)[1][1];
        
        for ($row = $startRow; $row <= $endRow; $row++) {
            if ($row % 2 == 0) {
                $rowRange = 'A' . $row . ':' . explode(':', $range)[1][0] . $row;
                $worksheet->getStyle($rowRange)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'F2F2F2']
                    ]
                ]);
            }
        }
    }
    
    /**
     * Export only project summary to Excel
     */
    public function exportSummaryToExcel($data, $filename = 'project_summary')
    {
        try {
            if (!$this->validateData($data)) {
                throw new \Exception('Invalid data provided for Excel export');
            }

            $spreadsheet = new Spreadsheet();
            $spreadsheet->removeSheetByIndex(0);
            
            $this->createProjectSummarySheet($spreadsheet, $data);
            $spreadsheet->setActiveSheetIndex(0);
            
            $writer = new Xlsx($spreadsheet);
            $filename = $filename . '.xlsx';
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            
            $writer->save('php://output');
            return true;
            
        } catch (\Exception $e) {
            error_log('Excel export error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Export only planning tasks to Excel
     */
    public function exportPlanningToExcel($data, $filename = 'planning_tasks')
    {
        try {
            if (!$this->validateData($data)) {
                throw new \Exception('Invalid data provided for Excel export');
            }

            $spreadsheet = new Spreadsheet();
            $spreadsheet->removeSheetByIndex(0);
            
            $this->createPlanningTasksSheet($spreadsheet, $data);
            $spreadsheet->setActiveSheetIndex(0);
            
            $writer = new Xlsx($spreadsheet);
            $filename = $filename . '.xlsx';
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            
            $writer->save('php://output');
            return true;
            
        } catch (\Exception $e) {
            error_log('Excel export error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Export only activities to Excel
     */
    public function exportActivitiesToExcel($data, $filename = 'project_activities')
    {
        try {
            if (!$this->validateData($data)) {
                throw new \Exception('Invalid data provided for Excel export');
            }

            $spreadsheet = new Spreadsheet();
            $spreadsheet->removeSheetByIndex(0);
            
            $this->createActivitiesSheet($spreadsheet, $data);
            $spreadsheet->setActiveSheetIndex(0);
            
            $writer = new Xlsx($spreadsheet);
            $filename = $filename . '.xlsx';
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            
            $writer->save('php://output');
            return true;
            
        } catch (\Exception $e) {
            error_log('Excel export error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Export table of contents (daftar isi) to Excel
     */
    public function exportDaftarIsiToExcel($data, $filename = 'daftar_isi')
    {
        try {
            if (!$this->validateData($data)) {
                throw new \Exception('Invalid data provided for Excel export');
            }

            $spreadsheet = new Spreadsheet();
            $spreadsheet->removeSheetByIndex(0);
            
            $this->createTableOfContentsSheet($spreadsheet, $data);
            $spreadsheet->setActiveSheetIndex(0);
            
            $writer = new Xlsx($spreadsheet);
            $filename = $filename . '.xlsx';
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            
            $writer->save('php://output');
            return true;
            
        } catch (\Exception $e) {
            error_log('Excel export error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create Table of Contents worksheet
     */
    private function createTableOfContentsSheet($spreadsheet, $data)
    {
        $worksheet = $spreadsheet->createSheet();
        $worksheet->setTitle('Daftar Isi');
        
        // Header
        $worksheet->setCellValue('A1', 'DAFTAR ISI');
        $worksheet->mergeCells('A1:B1');
        $this->styleHeader($worksheet, 'A1:B1');
        
        // Table headers
        $worksheet->setCellValue('A3', 'No');
        $worksheet->setCellValue('B3', 'Judul');
        $this->styleTableHeader($worksheet, 'A3:B3');
        
        // Build table of contents items
        $tocItems = [
            [
                'number' => 'I',
                'title' => 'RINGKASAN PROJECT',
                'level' => 0
            ],
            [
                'number' => 'II',
                'title' => 'STATISTIK PROJECT',
                'level' => 0
            ],
            [
                'number' => 'III',
                'title' => 'PERENCANAAN',
                'level' => 0
            ]
        ];
        
        // Add III.1 as main planning section
        $tocItems[] = [
            'number' => 'III.1',
            'title' => 'ANALISIS KEBUTUHAN FITUR UTAMA',
            'level' => 1
        ];
        
        // Add planning tasks sub-items under III.1
        $planningCount = count($data['data']['planning'] ?? []);
        if ($planningCount > 0) {
            $subPlanningNumber = 1;
            foreach ($data['data']['planning'] as $planning) {
                $tocItems[] = [
                    'number' => 'III.1.' . $subPlanningNumber,
                    'title' => $planning['title'] ?? 'Tugas ' . $subPlanningNumber,
                    'level' => 2
                ];
                $subPlanningNumber++;
            }
        }
        
        $tocItems[] = [
            'number' => 'IV',
            'title' => 'AKTIVITAS PROJECT',
            'level' => 0
        ];
        
        // Add activity sub-items
        $activityNumber = 1;
        foreach ($data['data']['activity'] ?? [] as $activity) {
            $userTask = $activity['tugas'] ?? 'Tugas tidak diketahui';
            
            $tocItems[] = [
                'number' => 'IV.' . $activityNumber,
                'title' => $userTask,
                'level' => 1
            ];
            
            // Add sub-tasks if available
            $subTaskNumber = 1;
            foreach ($activity['data'] ?? [] as $task) {
                $taskTitle = $task['title'] ?? 'Tugas ' . $subTaskNumber;
                $taskDescription = $task['description'] ?? '';
                $displayTitle = $taskTitle;
                if (!empty($taskDescription)) {
                    $displayTitle .= ' - ' . $taskDescription;
                }
                
                $tocItems[] = [
                    'number' => 'IV.' . $activityNumber . '.' . $subTaskNumber,
                    'title' => $displayTitle,
                    'level' => 2
                ];
                
                // Add items if available
                $itemNumber = 1;
                foreach ($task['item'] ?? [] as $item) {
                    $itemTitle = $item['title'] ?? 'Item ' . $itemNumber;
                    
                    $tocItems[] = [
                        'number' => 'IV.' . $activityNumber . '.' . $subTaskNumber . '.' . $itemNumber,
                        'title' => $itemTitle,
                        'level' => 3
                    ];
                    $itemNumber++;
                }
                
                $subTaskNumber++;
            }
            
            $activityNumber++;
        }
        
        // Data rows
        $row = 4;
        foreach ($tocItems as $item) {
            // Add indentation based on level
            $indent = str_repeat('    ', $item['level']);
            
            $worksheet->setCellValue("A{$row}", $item['number']);
            $worksheet->setCellValue("B{$row}", $indent . $item['title']);
            
            // Style based on level
            if ($item['level'] == 0) {
                $worksheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true);
            } elseif ($item['level'] == 3) {
                $worksheet->getStyle("B{$row}")->getFont()->setSize(9);
                $worksheet->getStyle("B{$row}")->getFont()->getColor()->setRGB('666666');
            }
            
            $row++;
        }
        
        // Style the data table
        $dataRange = "A3:B" . ($row - 1);
        $this->styleDataTable($worksheet, $dataRange);
        
        // Auto-size columns
        $worksheet->getColumnDimension('A')->setWidth(15);
        $worksheet->getColumnDimension('B')->setAutoSize(true);
    }
    
    /**
     * Calculate statistics for Excel export
     * 
     * @param array $data Project data
     * @return array Statistics data
     */
    private function calculateStatistics($data)
    {
        $stats = [];
        
        // Planning tasks statistics
        $planning = $data['data']['planning'] ?? [];
        $planningTotal = count($planning);
        $planningCompleted = 0;
        $planningInProgress = 0;
        $planningPending = 0;
        
        foreach ($planning as $task) {
            $status = strtolower($task['status'] ?? 'pending');
            if (in_array($status, ['completed', 'done', 'finished'])) {
                $planningCompleted++;
            } elseif (in_array($status, ['in_progress', 'working', 'ongoing'])) {
                $planningInProgress++;
            } else {
                $planningPending++;
            }
        }
        
        // Activities statistics
        $activities = $data['data']['activity'] ?? [];
        $activityTotal = 0;
        $activityCompleted = 0;
        $activityInProgress = 0;
        $activityPending = 0;
        
        foreach ($activities as $user) {
            $userActivities = $user['data'] ?? [];
            foreach ($userActivities as $activity) {
                $activityTotal++;
                $status = strtolower($activity['status'] ?? 'pending');
                if (in_array($status, ['completed', 'done', 'finished'])) {
                    $activityCompleted++;
                } elseif (in_array($status, ['in_progress', 'working', 'ongoing'])) {
                    $activityInProgress++;
                } else {
                    $activityPending++;
                }
            }
        }
        
        // Build statistics array
        if ($planningTotal > 0) {
            $stats['Planning Tasks'] = [
                'count' => $planningTotal,
                'percentage' => round(($planningCompleted / $planningTotal) * 100, 1),
                'status' => $planningCompleted == $planningTotal ? 'Complete' : 'In Progress'
            ];
            
            $stats['Planning Completed'] = [
                'count' => $planningCompleted,
                'percentage' => round(($planningCompleted / $planningTotal) * 100, 1),
                'status' => 'Success'
            ];
            
            $stats['Planning In Progress'] = [
                'count' => $planningInProgress,
                'percentage' => round(($planningInProgress / $planningTotal) * 100, 1),
                'status' => 'Working'
            ];
            
            $stats['Planning Pending'] = [
                'count' => $planningPending,
                'percentage' => round(($planningPending / $planningTotal) * 100, 1),
                'status' => 'Pending'
            ];
        }
        
        if ($activityTotal > 0) {
            $stats['Total Activities'] = [
                'count' => $activityTotal,
                'percentage' => round(($activityCompleted / $activityTotal) * 100, 1),
                'status' => $activityCompleted == $activityTotal ? 'Complete' : 'In Progress'
            ];
            
            $stats['Activities Completed'] = [
                'count' => $activityCompleted,
                'percentage' => round(($activityCompleted / $activityTotal) * 100, 1),
                'status' => 'Success'
            ];
            
            $stats['Activities In Progress'] = [
                'count' => $activityInProgress,
                'percentage' => round(($activityInProgress / $activityTotal) * 100, 1),
                'status' => 'Working'
            ];
            
            $stats['Activities Pending'] = [
                'count' => $activityPending,
                'percentage' => round(($activityPending / $activityTotal) * 100, 1),
                'status' => 'Pending'
            ];
        }
        
        return $stats;
    }
    
    /**
     * Calculate overall completion for Excel export
     * 
     * @param array $data Project data
     * @return array Overall completion data
     */
    private function calculateOverallCompletion($data)
    {
        $totalTasks = 0;
        $completedTasks = 0;
        
        // Count planning tasks
        $planning = $data['data']['planning'] ?? [];
        foreach ($planning as $task) {
            $totalTasks++;
            $status = strtolower($task['status'] ?? 'pending');
            if (in_array($status, ['completed', 'done', 'finished'])) {
                $completedTasks++;
            }
        }
        
        // Count activities
        $activities = $data['data']['activity'] ?? [];
        foreach ($activities as $user) {
            $userActivities = $user['data'] ?? [];
            foreach ($userActivities as $activity) {
                $totalTasks++;
                $status = strtolower($activity['status'] ?? 'pending');
                if (in_array($status, ['completed', 'done', 'finished'])) {
                    $completedTasks++;
                }
            }
        }
        
        if ($totalTasks == 0) {
            return [
                'percentage' => 0,
                'status' => 'No Data'
            ];
        }
        
        $percentage = round(($completedTasks / $totalTasks) * 100, 1);
        
        // Determine status based on percentage
        if ($percentage >= 90) {
            $status = 'Excellent';
        } elseif ($percentage >= 70) {
            $status = 'Good';
        } elseif ($percentage >= 50) {
            $status = 'Fair';
        } else {
            $status = 'Needs Attention';
        }
        
        return [
            'percentage' => $percentage,
            'status' => $status
        ];
    }

    /**
     * Generate dynamic table of contents based on actual data
     * 
     * @param array $data Project data
     * @return string HTML table of contents
     */
    public function generateTableOfContents($data)
    {
        $data = $this->validateData($data);
        
        $html = '<div class="nx-card nx-mb-4">';
        $html .= '<div class="nx-card-header"><h5 class="nx-mb-0">📋 Daftar Isi Laporan</h5></div>';
        $html .= '<div class="nx-card-body">';
        
        // Project info
        $projectName = $data['project'] ?? 'Unknown Project';
        $projectId = $data['id'] ?? 'N/A';
        $reportDate = $data['data']['date'] ?? date('d F Y');
        
        $html .= '<div class="nx-mb-3">';
        $html .= '<h6 class="nx-text-primary">' . htmlspecialchars((string)$projectName) . '</h6>';
        $html .= '<small class="nx-text-muted">Project ID: ' . htmlspecialchars((string)$projectId) . ' | Report Date: ' . htmlspecialchars((string)$reportDate) . '</small>';
        $html .= '</div>';
        
        $html .= '<div class="nx-table-wrapper">';
        $html .= '<table class="nx-table nx-table-bordered">';
        $html .= '<thead>';
        $html .= '<tr>';
        $html .= '<th style="width: 50px;">No</th>';
        $html .= '<th>Bagian Laporan</th>';
        $html .= '<th style="width: 80px;">Jumlah</th>';
        $html .= '<th style="width: 100px;">Status</th>';
        $html .= '<th style="width: 120px;">Aksi</th>';
        $html .= '</tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        
        $sectionNumber = 1;
        
        // 1. Project Summary
        $html .= '<tr>';
        $html .= '<td class="text-center"><strong>' . $sectionNumber++ . '</strong></td>';
                 $html .= '<td><strong>Ringkasan Project</strong><br><small class="nx-text-muted">Informasi umum project, progress, dan detail</small></td>';
        $html .= '<td class="text-center">1</td>';
        $html .= '<td><span class="nx-badge nx-badge-success">Tersedia</span></td>';
        $html .= '<td><a href="#project-summary" class="nx-btn nx-btn-sm nx-btn-primary">Lihat</a></td>';
        $html .= '</tr>';
        
        // 2. Statistics
        $stats = $this->calculateBasicStats($data);
        $html .= '<tr>';
        $html .= '<td class="text-center"><strong>' . $sectionNumber++ . '</strong></td>';
                 $html .= '<td><strong>Statistik Project</strong><br><small class="nx-text-muted">Analisis progress dan performa</small></td>';
        $html .= '<td class="text-center">' . $stats['total_metrics'] . '</td>';
        $html .= '<td><span class="nx-badge nx-badge-info">Dihitung</span></td>';
        $html .= '<td><a href="#statistics" class="nx-btn nx-btn-sm nx-btn-primary">Lihat</a></td>';
        $html .= '</tr>';
        
        // 3. Planning Tasks
        $planningCount = count($data['data']['planning'] ?? []);
        $planningCompleted = 0;
        foreach ($data['data']['planning'] ?? [] as $task) {
            if (in_array(strtolower($task['status'] ?? ''), ['done', 'completed', 'finished'])) {
                $planningCompleted++;
            }
        }
        
        $html .= '<tr>';
        $html .= '<td class="text-center"><strong>' . $sectionNumber++ . '</strong></td>';
                 $html .= '<td><strong>Tugas Perencanaan</strong><br><small class="nx-text-muted">Daftar tugas perencanaan dan status</small></td>';
        $html .= '<td class="text-center">' . $planningCount . '</td>';
        if ($planningCount > 0) {
            $planningStatus = $planningCompleted == $planningCount ? 'success' : ($planningCompleted > 0 ? 'warning' : 'secondary');
            $planningText = $planningCompleted == $planningCount ? 'Selesai' : ($planningCompleted > 0 ? 'Progress' : 'Pending');
            $html .= '<td><span class="nx-badge nx-badge-' . $planningStatus . '">' . $planningText . '</span></td>';
        } else {
            $html .= '<td><span class="nx-badge nx-badge-light">Kosong</span></td>';
        }
        $html .= '<td><a href="#planning-tasks" class="nx-btn nx-btn-sm nx-btn-primary">Lihat</a></td>';
        $html .= '</tr>';
        
        // 4. Activities (broken down by user)
        $activities = $data['data']['activity'] ?? [];
        $totalActivities = 0;
        $activitiesWithData = 0;
        $totalTasks = 0;
        $totalItems = 0;
        
        foreach ($activities as $user) {
            $totalActivities++;
            $userTasks = $user['data'] ?? [];
            if (!empty($userTasks)) {
                $activitiesWithData++;
                $totalTasks += count($userTasks);
                foreach ($userTasks as $task) {
                    $totalItems += count($task['item'] ?? []);
                }
            }
        }
        
        $html .= '<tr>';
        $html .= '<td class="text-center"><strong>' . $sectionNumber++ . '</strong></td>';
                 $html .= '<td><strong>Aktivitas Project</strong><br><small class="nx-text-muted">Aktivitas per user dan detail tugas</small></td>';
        $html .= '<td class="text-center">' . $totalActivities . '</td>';
        if ($totalActivities > 0) {
            $activityStatus = $activitiesWithData == $totalActivities ? 'success' : ($activitiesWithData > 0 ? 'warning' : 'danger');
            $activityText = $activitiesWithData == $totalActivities ? 'Lengkap' : ($activitiesWithData > 0 ? 'Sebagian' : 'Kosong');
            $html .= '<td><span class="nx-badge nx-badge-' . $activityStatus . '">' . $activityText . '</span></td>';
        } else {
            $html .= '<td><span class="nx-badge nx-badge-light">Kosong</span></td>';
        }
        $html .= '<td><a href="#activities" class="nx-btn nx-btn-sm nx-btn-primary">Lihat</a></td>';
        $html .= '</tr>';
        
        // Sub-sections for activities if there are users with data
        if ($activitiesWithData > 0) {
            foreach ($activities as $user) {
                if (!empty($user['data'])) {
                    $userName = $user['name'] ?? 'Unknown User';
                    $userTasks = count($user['data']);
                    $userItems = 0;
                    $completedTasks = 0;
                    
                    foreach ($user['data'] as $task) {
                        $userItems += count($task['item'] ?? []);
                        if (in_array(strtolower($task['status'] ?? ''), ['done', 'completed', 'finished'])) {
                            $completedTasks++;
                        }
                    }
                    
                    $html .= '<tr class="nx-table-secondary">';
                    $html .= '<td class="text-center">└─</td>';
                                         $html .= '<td style="padding-left: 30px;"><strong>' . htmlspecialchars((string)$userName) . '</strong><br><small class="nx-text-muted">' . $userTasks . ' tugas, ' . $userItems . ' item</small></td>';
                    $html .= '<td class="text-center">' . $userTasks . '</td>';
                    
                    $userStatus = $completedTasks == $userTasks ? 'success' : ($completedTasks > 0 ? 'warning' : 'secondary');
                    $userText = $completedTasks == $userTasks ? 'Selesai' : ($completedTasks > 0 ? 'Progress' : 'Pending');
                    $html .= '<td><span class="nx-badge nx-badge-' . $userStatus . '">' . $userText . '</span></td>';
                    $html .= '<td><a href="#user-' . $user['id'] . '" class="nx-btn nx-btn-sm nx-btn-outline-primary">Detail</a></td>';
                    $html .= '</tr>';
                }
            }
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
        
        // Summary statistics
        $html .= '<div class="nx-row nx-mt-3">';
        $html .= '<div class="nx-col-md-3">';
        $html .= '<div class="nx-card nx-text-center">';
        $html .= '<div class="nx-card-body">';
        $html .= '<h6 style="color: var(--nx-primary-dark);">' . $planningCount . '</h6>';
        $html .= '<small>Planning Tasks</small>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '<div class="nx-col-md-3">';
        $html .= '<div class="nx-card nx-text-center">';
        $html .= '<div class="nx-card-body">';
        $html .= '<h6 style="color: #17a2b8;">' . $totalActivities . '</h6>';
        $html .= '<small>Activities</small>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '<div class="nx-col-md-3">';
        $html .= '<div class="nx-card nx-text-center">';
        $html .= '<div class="nx-card-body">';
        $html .= '<h6 style="color: #ffc107;">' . $totalTasks . '</h6>';
        $html .= '<small>Total Tasks</small>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '<div class="nx-col-md-3">';
        $html .= '<div class="nx-card nx-text-center">';
        $html .= '<div class="nx-card-body">';
        $html .= '<h6 style="color: var(--nx-gray-600);">' . $totalItems . '</h6>';
        $html .= '<small>Total Items</small>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Calculate basic statistics for table of contents
     * 
     * @param array $data Project data
     * @return array Basic statistics
     */
    private function calculateBasicStats($data)
    {
        $stats = [
            'total_metrics' => 0,
            'planning_count' => count($data['data']['planning'] ?? []),
            'activity_count' => count($data['data']['activity'] ?? []),
            'total_tasks' => 0,
            'total_items' => 0
        ];
        
        // Count tasks and items from activities
        foreach ($data['data']['activity'] ?? [] as $user) {
            foreach ($user['data'] ?? [] as $task) {
                $stats['total_tasks']++;
                $stats['total_items'] += count($task['item'] ?? []);
            }
        }
        
        // Calculate total metrics for statistics section
        $stats['total_metrics'] = 8; // Fixed number of metrics we show in statistics
        
        return $stats;
    }

    /**
     * Generate complete report with table of contents at the beginning
     * 
     * @param array $data Project data
     * @param bool $includeStatistics Whether to include statistics
     * @param string $statisticsPosition Position of statistics section
     * @return string Complete HTML report with table of contents
     */
    public function fullReportWithTableOfContents($data, $includeStatistics = true, $statisticsPosition = 'after_summary')
    {
        $html = '<div class="nx-container-fluid">';
        $html .= '<div class="nx-row">';
        $html .= '<div class="nx-col-12">';
        
        // Add custom CSS for better styling with NexaUI variables
        $html .= '<style>';
        $html .= '.nx-badge { font-size: 0.75em; padding: 0.25em 0.5em; border-radius: 0.25rem; }';
        $html .= '.nx-badge-success { background-color: #28a745; color: white; }';
        $html .= '.nx-badge-primary { background-color: var(--nx-primary-dark); color: white; }';
        $html .= '.nx-badge-warning { background-color: #ffc107; color: #212529; }';
        $html .= '.nx-badge-secondary { background-color: #6c757d; color: white; }';
        $html .= '.nx-badge-light { background-color: #f8f9fa; color: #212529; border: 1px solid #dee2e6; }';
        $html .= '.nx-badge-danger { background-color: #dc3545; color: white; }';
        $html .= '.nx-badge-info { background-color: #17a2b8; color: white; }';
        $html .= '.nx-progress { margin-bottom: 0; }';
        $html .= '.nx-card { box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075); margin-bottom: 1rem; border: 1px solid var(--nx-gray-75); border-radius: 0.25rem; }';
        $html .= '.nx-card-header { padding: 0.75rem 1rem; background-color: var(--nx-gray-25); border-bottom: 1px solid var(--nx-gray-75); }';
        $html .= '.nx-card-body { padding: 1rem; }';
        $html .= '.nx-mb-0 { margin-bottom: 0; }';
        $html .= '.nx-mb-2 { margin-bottom: 0.5rem; }';
        $html .= '.nx-mb-3 { margin-bottom: 1rem; }';
        $html .= '.nx-mb-4 { margin-bottom: 1.5rem; }';
        $html .= '.nx-mt-3 { margin-top: 1rem; }';
        $html .= '.nx-text-muted { color: var(--nx-gray-600); }';
        $html .= '.nx-text-primary { color: var(--nx-primary-dark); }';
        $html .= '.nx-flex { display: flex; }';
        $html .= '.nx-justify-between { justify-content: space-between; }';
        $html .= '.nx-row { display: flex; flex-wrap: wrap; margin: -0.5rem; }';
        $html .= '.nx-col-12 { flex: 0 0 100%; max-width: 100%; padding: 0.5rem; }';
        $html .= '.nx-col-md-2 { flex: 0 0 16.666667%; max-width: 16.666667%; padding: 0.5rem; }';
        $html .= '.nx-col-md-3 { flex: 0 0 25%; max-width: 25%; padding: 0.5rem; }';
        $html .= '.text-center { text-align: center; }';
        $html .= '.nx-alert { padding: 0.75rem 1rem; margin-bottom: 1rem; border: 1px solid transparent; border-radius: 0.25rem; }';
        $html .= '.nx-alert-info { color: #0c5460; background-color: #d1ecf1; border-color: #bee5eb; }';
        $html .= '.nx-alert-warning { color: #856404; background-color: #fff3cd; border-color: #ffeaa7; }';
        $html .= '.nx-table-secondary { background-color: var(--nx-gray-50); }';
        $html .= '.nx-btn { display: inline-block; padding: 0.375rem 0.75rem; font-size: 0.875rem; line-height: 1.5; border-radius: 0.25rem; text-decoration: none; text-align: center; vertical-align: middle; cursor: pointer; border: 1px solid transparent; }';
        $html .= '.nx-btn-sm { padding: 0.25rem 0.5rem; font-size: 0.75rem; }';
        $html .= '.nx-btn-primary { color: white; background-color: var(--nx-primary-dark); border-color: var(--nx-primary-dark); }';
        $html .= '.nx-btn-outline-primary { color: var(--nx-primary-dark); background-color: transparent; border-color: var(--nx-primary-dark); }';
        $html .= 'html { scroll-behavior: smooth; }';
        $html .= '</style>';
        
        // Table of Contents (First)
        $html .= $this->generateTableOfContents($data);
        
        // Project Summary
        $html .= $this->projectSummary($data);
        
        // Statistics (Position based on parameter)
        if ($includeStatistics && $statisticsPosition === 'after_summary') {
            $html .= $this->generateStatistics($data);
        }
        
        // Planning Tasks
        $html .= $this->planningTasks($data);
        
        // Statistics (Alternative position)
        if ($includeStatistics && $statisticsPosition === 'after_planning') {
            $html .= $this->generateStatistics($data);
        }
        
        // Activities
        $html .= $this->activities($data);
        
        // Statistics (Alternative position)
        if ($includeStatistics && $statisticsPosition === 'after_activities') {
            $html .= $this->generateStatistics($data);
        }
        
        // Back to top button
        $html .= '<div class="nx-text-center nx-mt-3">';
                 $html .= '<a href="#" class="nx-btn nx-btn-primary">Kembali ke Atas</a>';
        $html .= '</div>';
        
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Generate proper table of contents (outline structure only)
     * 
     * @param array $data Project data
     * @return string HTML table of contents outline
     */
    public function daftarIsi($data)
    {
        $data = $this->validateData($data);
        
        $html = '<div class="nx-card nx-mb-4">';
        $html .= '<div class="nx-card-header text-center">';
        $html .= '<h4 class="nx-mb-0">DAFTAR ISI</h4>';
        $html .= '</div>';
        $html .= '<div class="nx-card-body">';
        
        $html .= '<div class="nx-table-wrapper">';
        $html .= '<table class="nx-table">';
        $html .= '<tbody>';
        
        // Table of Contents Structure
        $tocItems = [
            [
                'number' => 'I',
                'title' => 'RINGKASAN PROJECT',
                'page' => '#project-summary',
                'level' => 0
            ],
            [
                'number' => 'II',
                'title' => 'STATISTIK PROJECT',
                'page' => '#statistics',
                'level' => 0
            ],
            [
                'number' => 'III',
                'title' => 'PERENCANAAN',
                'page' => '#planning-tasks',
                'level' => 0
            ]
        ];
        
        // Add III.1 as main planning section
        $tocItems[] = [
            'number' => 'III.1',
            'title' => 'ANALISIS KEBUTUHAN FITUR UTAMA',
            'page' => '#planning-tasks',
            'level' => 1
        ];
        
        // Add planning tasks sub-items under III.1
        $planningCount = count($data['data']['planning'] ?? []);
        if ($planningCount > 0) {
            $subPlanningNumber = 1;
            foreach ($data['data']['planning'] as $planning) {
                $tocItems[] = [
                    'number' => 'III.1.' . $subPlanningNumber,
                    'title' => $planning['title'] ?? 'Tugas ' . $subPlanningNumber,
                    'page' => '#planning-tasks',
                    'level' => 2
                ];
                $subPlanningNumber++;
            }
        }
        
        $tocItems[] = [
            'number' => 'IV',
            'title' => 'AKTIVITAS PROJECT',
            'page' => '#activities',
            'level' => 0
        ];
        
        // Add activity sub-items
        $activityNumber = 1;
        foreach ($data['data']['activity'] ?? [] as $activity) {
            $userTask = $activity['tugas'] ?? 'Tugas tidak diketahui';
            $taskCount = count($activity['data'] ?? []);
            
            $tocItems[] = [
                'number' => 'IV.' . $activityNumber,
                'title' => $userTask,
                'page' => '#activity-' . $activityNumber,
                'level' => 1
            ];
            
            // Add sub-tasks if available
            $subTaskNumber = 1;
            foreach ($activity['data'] ?? [] as $task) {
                $taskTitle = $task['title'] ?? 'Tugas ' . $subTaskNumber;
                $taskDescription = $task['description'] ?? '';
                $displayTitle = $taskTitle;
                if (!empty($taskDescription)) {
                    $displayTitle .= ' - ' . $taskDescription;
                }
                
                $tocItems[] = [
                    'number' => 'IV.' . $activityNumber . '.' . $subTaskNumber,
                    'title' => $displayTitle,
                    'page' => '#activity-' . $activityNumber,
                    'level' => 2
                ];
                
                // Add items if available
                $itemNumber = 1;
                foreach ($task['item'] ?? [] as $item) {
                    $itemTitle = $item['title'] ?? 'Item ' . $itemNumber;
                    
                    $tocItems[] = [
                        'number' => 'IV.' . $activityNumber . '.' . $subTaskNumber . '.' . $itemNumber,
                        'title' => $itemTitle,
                        'page' => '#activity-' . $activityNumber,
                        'level' => 3
                    ];
                    $itemNumber++;
                }
                
                $subTaskNumber++;
            }
            
            $activityNumber++;
        }
        
        // Render table of contents
        foreach ($tocItems as $item) {
            $html .= '<tr>';
            
            // Number column
            $html .= '<td style="width: 80px; vertical-align: top;">';
            $html .= str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $item['level']);
            $html .= '<strong>' . $item['number'] . '</strong>';
            $html .= '</td>';
            
            // Title column
            $html .= '<td style="vertical-align: top;">';
            $html .= str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $item['level']);
            if ($item['level'] == 0) {
                $html .= '<strong>' . $item['title'] . '</strong>';
            } elseif ($item['level'] == 3) {
                $html .= '<small class="nx-text-muted">' . $item['title'] . '</small>';
            } else {
                $html .= $item['title'];
            }
            $html .= '</td>';
            
            $html .= '</tr>';
        }
        
        $html .= '</tbody>';
        $html .= '</table>';
        $html .= '</div>';
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
}
