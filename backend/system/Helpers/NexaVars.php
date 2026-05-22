<?php
namespace App\System\Helpers;
class NexaVars {
    private array $_nexadom = [
        '.' => [] // Root level storage
    ];

    public function __construct() {
        // Constructor no longer needs to initialize NexaFilter
    }

    /**
     * Sets variables at root level
     * @param array $vararray Variables to set
     * @param bool $bAppend If true, appends to existing values instead of overwriting
     */
    public function nexa_vars(array $vararray, bool $bAppend = false): bool {
        foreach ($vararray as $key => $val) {
            // Handle nested arrays
            if (is_array($val)) {
                if ($bAppend && isset($this->_nexadom['.'][$key]) && is_array($this->_nexadom['.'][$key])) {
                    $this->_nexadom['.'][$key] = array_merge($this->_nexadom['.'][$key], $val);
                } else {
                    $this->_nexadom['.'][$key] = $val;
                }
                continue;
            }

            // Handle scalar values
            if ($bAppend && isset($this->_nexadom['.'][$key])) {
                $this->_nexadom['.'][$key] = (string)$this->_nexadom['.'][$key] . (string)$val;
            } else {
                $this->_nexadom['.'][$key] = $val;
            }
        }
        return true;
    }

    /**
     * Gets a variable value
     * @param string $key Variable name
     * @return mixed Variable value or null if not found
     */
    public function getVar(string $key) {
        $varName = trim($key);
        return $this->_nexadom['.'][$varName] ?? null;
    }

    /**
     * Gets a block of variables
     * @param string $blockname Block name
     * @return mixed Block value or false if not found
     */
    public function get_block(string $blockname) {
        return $this->getVar($blockname);
    }

    /**
     * Processes template variables in output buffer
     * Replaces {varname} with their values
     */
    public function outputHandler(): void {
        ob_start(function($buffer) {
            return preg_replace_callback('/{([^}]+)}/', function($matches) {
                $fullKey = trim($matches[1]);
                $value = $this->getVar($fullKey);
                return $value !== null ? (string)$value : $matches[0];
            }, $buffer);
        });
    }
    
    /**
     * Flush output buffer to process template variables
     */
    public function flushOutput(): void {
        if (ob_get_level()) {
            ob_end_flush();
        }
    }
}
