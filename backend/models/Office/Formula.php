<?php
declare(strict_types=1);

namespace App\Models\Office;

/**
 * Formula class untuk operasi aritmatika dan nested formula
 */
class Formula
{
    /**
     * Build arithmetic formula for field calculations
     * Supports field-to-field operations, field-to-value operations, and complex nested formulas
     */
    public function buildArithmeticFormula(string $fieldName, string $aggregate, array $arithmetic): string {
        $operation = strtoupper($arithmetic['operation'] ?? '');
        $value = $arithmetic['value'] ?? '';
        $field2 = $arithmetic['field2'] ?? '';
        $formula = $arithmetic['formula'] ?? '';
        $nested = $arithmetic['nested'] ?? [];
        
        // Jika ada formula custom, gunakan itu
        if (!empty($formula)) {
            return $formula;
        }
        
        // Jika ada nested operations, build complex formula
        if (!empty($nested) && is_array($nested)) {
            return $this->buildNestedFormula($fieldName, $aggregate, $nested);
        }
        
        // Build formula berdasarkan operasi
        switch ($operation) {
            case 'ADD':
                // Penjumlahan antar field
                if (!empty($field2)) {
                    return $aggregate ? "$aggregate($fieldName + $field2)" : "($fieldName + $field2)";
                }
                // Penjumlahan dengan nilai konstan
                elseif (!empty($value)) {
                    return $aggregate ? "$aggregate($fieldName + $value)" : "($fieldName + $value)";
                }
                break;
                
            case 'SUBTRACT':
                // Pengurangan antar field
                if (!empty($field2)) {
                    return $aggregate ? "$aggregate($fieldName - $field2)" : "($fieldName - $field2)";
                }
                // Pengurangan dengan nilai konstan
                elseif (!empty($value)) {
                    return $aggregate ? "$aggregate($fieldName - $value)" : "($fieldName - $value)";
                }
                break;
                
            case 'MULTIPLY':
                // Perkalian antar field
                if (!empty($field2)) {
                    return $aggregate ? "$aggregate($fieldName * $field2)" : "($fieldName * $field2)";
                }
                // Perkalian dengan nilai konstan
                elseif (!empty($value)) {
                    return $aggregate ? "$aggregate($fieldName * $value)" : "($fieldName * $value)";
                }
                break;
                
            case 'DIVIDE':
                // Pembagian antar field
                if (!empty($field2)) {
                    return $aggregate ? "$aggregate($fieldName / $field2)" : "($fieldName / $field2)";
                }
                // Pembagian dengan nilai konstan
                elseif (!empty($value)) {
                    return $aggregate ? "$aggregate($fieldName / $value)" : "($fieldName / $value)";
                }
                break;
                
            case 'MODULO':
                // Modulo antar field
                if (!empty($field2)) {
                    return $aggregate ? "$aggregate($fieldName % $field2)" : "($fieldName % $field2)";
                }
                // Modulo dengan nilai konstan
                elseif (!empty($value)) {
                    return $aggregate ? "$aggregate($fieldName % $value)" : "($fieldName % $value)";
                }
                break;
                
            case 'POWER':
                // Pangkat antar field
                if (!empty($field2)) {
                    return $aggregate ? "$aggregate(POW($fieldName, $field2))" : "POW($fieldName, $field2)";
                }
                // Pangkat dengan nilai konstan
                elseif (!empty($value)) {
                    return $aggregate ? "$aggregate(POW($fieldName, $value))" : "POW($fieldName, $value)";
                }
                break;
                
            case 'SQRT':
                return $aggregate ? "$aggregate(SQRT($fieldName))" : "SQRT($fieldName)";
            case 'ABS':
                return $aggregate ? "$aggregate(ABS($fieldName))" : "ABS($fieldName)";
            case 'ROUND':
                $decimals = $arithmetic['decimals'] ?? 2;
                return $aggregate ? "$aggregate(ROUND($fieldName, $decimals))" : "ROUND($fieldName, $decimals)";
            case 'CEIL':
                return $aggregate ? "$aggregate(CEIL($fieldName))" : "CEIL($fieldName)";
            case 'FLOOR':
                return $aggregate ? "$aggregate(FLOOR($fieldName))" : "FLOOR($fieldName)";
            case 'PERCENTAGE':
                $total = $arithmetic['total'] ?? 100;
                return $aggregate ? "$aggregate(($fieldName / $total) * 100)" : "(($fieldName / $total) * 100)";
            case 'RATIO':
                $total = $arithmetic['total'] ?? 1;
                return $aggregate ? "$aggregate($fieldName / $total)" : "($fieldName / $total)";
            default:
                return $aggregate ? "$aggregate($fieldName)" : $fieldName;
        }
        
        return $aggregate ? "$aggregate($fieldName)" : $fieldName;
    }

    /**
     * Build nested formula for complex calculations
     * Supports operations like: field_A + field_B * field_C
     */
    public function buildNestedFormula(string $fieldName, string $aggregate, array $nested): string {
        // Sort operations by priority (high priority first)
        usort($nested, function($a, $b) {
            $priorityA = $a['priority'] ?? 1;
            $priorityB = $b['priority'] ?? 1;
            return $priorityB - $priorityA; // High priority first
        });
        
        $formula = $fieldName;
        
        foreach ($nested as $operation) {
            $op = strtoupper($operation['operation'] ?? '');
            $field2 = $operation['field2'] ?? '';
            $value = $operation['value'] ?? '';
            $priority = $operation['priority'] ?? 1;
            
            if (empty($op)) continue;
            
            $operand = !empty($field2) ? $field2 : $value;
            if (empty($operand)) continue;
            
            // Build operation based on priority
            switch ($op) {
                case 'MULTIPLY':
                    if ($priority == 2) {
                        // High priority: wrap in parentheses
                        $formula = "($formula * $operand)";
                    } else {
                        $formula = "$formula * $operand";
                    }
                    break;
                    
                case 'DIVIDE':
                    if ($priority == 2) {
                        // High priority: wrap in parentheses
                        $formula = "($formula / $operand)";
                    } else {
                        $formula = "$formula / $operand";
                    }
                    break;
                    
                case 'ADD':
                    $formula = "($formula + $operand)";
                    break;
                    
                case 'SUBTRACT':
                    $formula = "($formula - $operand)";
                    break;
                    
                case 'POWER':
                    $formula = "POW($formula, $operand)";
                    break;
                    
                case 'MODULO':
                    $formula = "($formula % $operand)";
                    break;
            }
        }
        
        return $aggregate ? "$aggregate($formula)" : $formula;
    }
}
