<?php
// /home/cool5792/private/includes/Logger.php

class Logger {
    private $logFile;
    private $dateFormat = 'Y-m-d H:i:s';

    public function __construct($logFile = null) {
        $this->logFile = $logFile ?? '/home/cool5792/private/logs/api-errors.log';
        $this->ensureLogDirectoryExists();

        // Créer le dossier logs s'il n'existe pas
        $logDir = dirname($this->logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    public function error($message, array $context = []) {
        $this->log('ERROR', $message, $context);
    }

    public function info($message, array $context = []) {
        $this->log('INFO', $message, $context);
    }

    public function warning($message, array $context = []) {
        $this->log('WARNING', $message, $context);
    }

    public function debug($message, array $context = []) {
        $this->log('DEBUG', $message, $context);
    }

    private function log($level, $message, array $context = []) {
        $date = date($this->dateFormat);
        $contextJson = !empty($context) ? ' ' . json_encode($context) : '';
        
        $logMessage = sprintf(
            "[%s] [%s] %s%s\n",
            $date,
            $level,
            $message,
            $contextJson
        );

        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }
}