<?php

function initLogsDirectory() {
    $logsDir = dirname(__DIR__) . '/logs';
    $result = [
        'directory' => $logsDir,
        'errors' => [],
        'success' => false
    ];
    
    // Vérifier les permissions du dossier parent
    $parentDir = dirname($logsDir);
    if (!is_writable($parentDir)) {
        $result['errors'][] = [
            'type' => 'parent_dir_not_writable',
            'path' => $parentDir,
            'current_permissions' => substr(sprintf('%o', fileperms($parentDir)), -4)
        ];
    }
    
    // Créer le dossier s'il n'existe pas
    if (!is_dir($logsDir)) {
        $mkdirResult = @mkdir($logsDir, 0755, true);
        if (!$mkdirResult) {
            $error = error_get_last();
            $result['errors'][] = [
                'type' => 'mkdir_failed',
                'path' => $logsDir,
                'error' => $error['message']
            ];
        }
    }
    
    // Vérifier les permissions du dossier de logs
    if (is_dir($logsDir) && !is_writable($logsDir)) {
        $result['errors'][] = [
            'type' => 'logs_dir_not_writable',
            'path' => $logsDir,
            'current_permissions' => substr(sprintf('%o', fileperms($logsDir)), -4)
        ];
    }
    
    // Créer le fichier de log s'il n'existe pas
    $logFile = $logsDir . '/app.log';
    if (!file_exists($logFile)) {
        $touchResult = @touch($logFile);
        if (!$touchResult) {
            $error = error_get_last();
            $result['errors'][] = [
                'type' => 'touch_failed',
                'path' => $logFile,
                'error' => $error['message']
            ];
        }
    }
    
    // Vérifier les permissions du fichier de log
    if (file_exists($logFile)) {
        if (!is_writable($logFile)) {
            $result['errors'][] = [
                'type' => 'log_file_not_writable',
                'path' => $logFile,
                'current_permissions' => substr(sprintf('%o', fileperms($logFile)), -4)
            ];
        } else {
            // Essayer d'écrire dans le fichier
            $writeTest = @file_put_contents($logFile, "Log initialization test - " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
            if ($writeTest === false) {
                $error = error_get_last();
                $result['errors'][] = [
                    'type' => 'write_test_failed',
                    'path' => $logFile,
                    'error' => $error['message']
                ];
            }
        }
    }
    
    $result['success'] = empty($result['errors']);
    $result['file'] = $logFile;
    
    // Écrire les erreurs dans le log système de PHP
    if (!empty($result['errors'])) {
        error_log("Log initialization errors: " . json_encode($result['errors']));
    }
    
    return $result;
}
