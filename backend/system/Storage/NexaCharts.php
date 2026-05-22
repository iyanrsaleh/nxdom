<?php
namespace App\System\Storage;
use App\System\Storage\NexaDb;
class NexaCharts {
    private $mysqli;
    private $table;
    private $select = [];
    private $where = [];
    private $orderBy = [];
    private $groupBy = [];
    private $limit;

    public function __construct() {
        $db = new NexaDb();
        $this->mysqli = $db->connMysqli();
    }

    // Query Builder Methods
    public function table($table) {
        $this->table = $table;
        return $this;
    }

    public function select($columns) {
        if (is_string($columns)) {
            $this->select[] = $columns;
        } else if (is_array($columns)) {
            $this->select = array_merge($this->select, $columns);
        }
        return $this;
    }

    public function where($column, $operator, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        $this->where[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value
        ];
        return $this;
    }

    public function orderBy($column, $direction = 'ASC') {
        $this->orderBy[] = [
            'column' => $column,
            'direction' => strtoupper($direction)
        ];
        return $this;
    }

    public function groupBy($columns) {
        if (is_string($columns)) {
            $this->groupBy[] = $columns;
        } else if (is_array($columns)) {
            $this->groupBy = array_merge($this->groupBy, $columns);
        }
        return $this;
    }

    public function limit($limit) {
        $this->limit = $limit;
        return $this;
    }

    private function buildQuery() {
        // Build SELECT clause
        $select = !empty($this->select) 
            ? implode(', ', $this->select) 
            : '*';

        $query = "SELECT {$select} FROM {$this->table}";

        // Build WHERE clause
        if (!empty($this->where)) {
            $whereClauses = [];
            foreach ($this->where as $condition) {
                $value = is_string($condition['value']) 
                    ? "'{$condition['value']}'" 
                    : $condition['value'];
                $whereClauses[] = "{$condition['column']} {$condition['operator']} {$value}";
            }
            $query .= " WHERE " . implode(' AND ', $whereClauses);
        }

        // Build GROUP BY clause
        if (!empty($this->groupBy)) {
            $query .= " GROUP BY " . implode(', ', $this->groupBy);
        }

        // Build ORDER BY clause
        if (!empty($this->orderBy)) {
            $orderClauses = [];
            foreach ($this->orderBy as $order) {
                $orderClauses[] = "{$order['column']} {$order['direction']}";
            }
            $query .= " ORDER BY " . implode(', ', $orderClauses);
        }

        // Add LIMIT clause
        if ($this->limit) {
            $query .= " LIMIT {$this->limit}";
        }

        return $query;
    }

    private function resetQuery() {
        $this->table = null;
        $this->select = [];
        $this->where = [];
        $this->orderBy = [];
        $this->groupBy = [];
        $this->limit = null;
    }

    // Chart Methods with Builder
    public function getLineChartData($options = []) {
        $query = $this->buildQuery();
        $data = $this->processDataFromQuery($query);
        $this->resetQuery();
        
        // Return array instead of JSON string
        return [
            'type' => 'line',
            'data' => $data,
            'options' => array_merge([
                'title' => 'Line Chart',
                'xAxis' => 'X Axis',
                'yAxis' => 'Y Axis'
            ], $options)
        ];
    }

    public function getBarChartData($options = []) {
        $query = $this->buildQuery();
        $data = $this->processDataFromQuery($query);
        $this->resetQuery();
        
        return json_encode([
            'type' => 'bar',
            'data' => $data,
            'options' => array_merge([
                'title' => 'Bar Chart',
                'xAxis' => 'X Axis',
                'yAxis' => 'Y Axis'
            ], $options)
        ]);
    }

    public function getPieChartData($options = []) {
        $query = $this->buildQuery();
        $data = $this->processDataFromQuery($query, ['type' => 'pie']);
        $this->resetQuery();
        
        return json_encode([
            'type' => 'pie',
            'data' => $data,
            'options' => array_merge([
                'title' => 'Pie Chart'
            ], $options)
        ]);
    }

    // Advanced Charts Data
    public function getTimeSeriesData($options = []) {
        $query = $this->buildQuery();
        $data = $this->processDataFromQuery($query);
        $this->resetQuery();
        
        return json_encode([
            'type' => 'timeseries',
            'data' => $data,
            'options' => array_merge([
                'title' => 'Time Series',
                'xAxis' => 'Time',
                'yAxis' => 'Value',
                'timeFormat' => 'YYYY-MM-DD'
            ], $options)
        ]);
    }

    public function getHeatmapData($options = []) {
        $query = $this->buildQuery();
        $data = $this->processDataFromQuery($query);
        $this->resetQuery();
        
        return json_encode([
            'type' => 'heatmap',
            'data' => $data,
            'options' => array_merge([
                'title' => 'Heatmap'
            ], $options)
        ]);
    }

    public function getRadarData($options = []) {
        $query = $this->buildQuery();
        $data = $this->processDataFromQuery($query);
        $this->resetQuery();
        
        return json_encode([
            'type' => 'radar',
            'data' => $data,
            'options' => array_merge([
                'title' => 'Radar Chart'
            ], $options)
        ]);
    }

    public function getWaterfallData($options = []) {
        $query = $this->buildQuery();
        $data = $this->processDataFromQuery($query);
        $this->resetQuery();
        
        return json_encode([
            'type' => 'waterfall',
            'data' => $data,
            'options' => array_merge([
                'title' => 'Waterfall Chart'
            ], $options)
        ]);
    }

    // Data processing methods
    private function processDataFromQuery($query, $options = []) {
        $result = $this->mysqli->query($query);
        if (!$result) {
            throw new \Exception("Query failed: " . $this->mysqli->error);
        }

        // Process data in chunks to avoid memory issues
        $type = $options['type'] ?? 'default';
        
        if ($type === 'pie') {
            $formattedData = [];
            while ($row = $result->fetch_assoc()) {
                $formattedData[] = [
                    'label' => $row[$options['labelField'] ?? 'label'],
                    'value' => floatval($row[$options['valueField'] ?? 'value'])
                ];
            }
        } else {
            $formattedData = [
                'labels' => [],
                'datasets' => [[
                    'data' => [],
                    'label' => 'Dataset 1'
                ]]
            ];
            
            while ($row = $result->fetch_assoc()) {
                $formattedData['labels'][] = $row[$options['labelField'] ?? 'label'];
                $formattedData['datasets'][0]['data'][] = floatval($row[$options['valueField'] ?? 'value']);
            }
        }
        
        $result->free();
        return $formattedData;
    }

    private function formatData($data, $options) {
        return $data; // Data is already formatted in processDataFromQuery
    }
}
 