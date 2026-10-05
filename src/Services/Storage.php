<?php
/**
 * src/Services/Storage.php
 * Role: Service centralisé pour la gestion des chemins, URLs et sauvegardes de fichiers.
 * Usage: Storage::getStoragePath($type, $filename), Storage::saveUploadedFile($file, $type, $filename)
 * Dépendances: config/storage.php
 */

require_once __DIR__ . '/../../logs/error.log.php';

class Storage {
    private static $config;
    
    public static function init() {
        if (!self::$config) {
            self::$config = require __DIR__ . '/../../config/storage.php';
            // error_log("✅ Configuration chargée: " . print_r(self::$config, true));
        }
    }
    
    public static function getStoragePath($type, $filename) {
        self::init();
        $basePath = self::$config['storage_base_path'];
        
        // Sélectionner le bon chemin selon le type
        if ($type === 'gpx') {
            $relativePath = self::$config['directories']['gpx']['files']['storage_path'];
        } else {
            $relativePath = self::$config['directories'][$type]['images']['storage_path'];
        }
        
        $fullPath = $basePath . $relativePath . '/' . $filename;
        // error_log("📂 Chemin de stockage généré: " . $fullPath);
        // error_log("- Base: " . $basePath);
        // error_log("- Relatif: " . $relativePath);
        // error_log("- Filename: " . $filename);
        return $fullPath;
    }
    
    public static function getPublicUrl($type, $filename) {
        self::init();
        $baseUrl = self::$config['public_base_url'];
        
        // Sélectionner le bon chemin selon le type
        if ($type === 'gpx') {
            $relativePath = self::$config['directories']['gpx']['files']['public_path'];
        } else {
            $relativePath = self::$config['directories'][$type]['images']['public_path'];
        }
        
        $fullUrl = $baseUrl . $relativePath . '/' . $filename;
        // error_log("🌐 URL publique générée: " . $fullUrl);
        return $fullUrl;
    }
    
    public static function ensureDirectoryExists($path) {
        // error_log("📁 Vérification du dossier: " . $path);
        // error_log("- Existe ? " . (file_exists($path) ? "OUI" : "NON"));
        // error_log("- Permissions actuelles: " . (file_exists($path) ? substr(sprintf('%o', fileperms($path)), -4) : "N/A"));
        
        if (!file_exists($path)) {
            // error_log("🔨 Tentative de création du dossier");
            $success = @mkdir($path, 0775, true);
            if (!$success) {
                $error = error_get_last();
                // error_log("❌ Erreur lors de la création: " . $error['message']);
                throw new Exception("Impossible de créer le dossier: " . $error['message']);
            }
            // error_log("✅ Dossier créé");
            
            // Définir les permissions et le propriétaire
            chmod($path, 0775);
            // error_log("👥 Nouvelles permissions: " . substr(sprintf('%o', fileperms($path)), -4));
        }
        
        if (!is_writable($path)) {
            // error_log("❌ Le dossier n'est pas accessible en écriture");
            // error_log("- Propriétaire actuel: " . posix_getpwuid(fileowner($path))['name']);
            // error_log("- Groupe actuel: " . posix_getgrgid(filegroup($path))['name']);
            throw new Exception("Le dossier n'est pas accessible en écriture: " . $path);
        }
        
        // error_log("✅ Le dossier est prêt pour l'écriture");
    }
    
    public static function saveUploadedFile($file, $type, $customFilename = null) {
        self::init();
        
        // error_log("🚀 Début de l'upload pour le type: " . $type);
        // error_log("📁 Fichier reçu: " . print_r($file, true));
        
        // Vérifier l'extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        // error_log("📎 Extension détectée: " . $extension);
        
        // Vérifier si c'est un fichier GPX
        if ($type === 'gpx') {
            if (!in_array($extension, self::$config['allowed_extensions']['gpx'])) {
                // error_log("❌ Extension GPX non autorisée");
                throw new Exception("Type de fichier non autorisé");
            }
            
            // Vérifier le type MIME pour les fichiers GPX (avec fallback sur le contenu XML/GPX)
            if (!in_array($file['type'], self::$config['allowed_mimes']['gpx'])) {
                $content = @file_get_contents($file['tmp_name'], false, null, 0, 1024);
                if ($content === false || (stripos($content, '<gpx') === false && stripos($content, '<?xml') === false)) {
                    // error_log("❌ Type MIME non autorisé: " . $file['type'] . " et contenu non GPX/XML");
                    throw new Exception("Type de fichier non autorisé");
                }
                // error_log("⚠️ MIME navigateur non listé mais contenu GPX/XML accepté: " . $file['type']);
            }
        } else {
            // Vérification standard pour les images
            if (!in_array($extension, self::$config['allowed_extensions']['images'])) {
                // error_log("❌ Extension non autorisée");
                throw new Exception("Type de fichier non autorisé");
            }
        }
        
        // Vérifier la taille
        if ($file['size'] > self::$config['max_file_size']) {
            // error_log("❌ Fichier trop volumineux: " . $file['size'] . " bytes");
            throw new Exception("Fichier trop volumineux");
        }
        
        // Générer un nom de fichier unique
        $filename = $customFilename ?: uniqid() . '_' . time() . '.' . $extension;
        // error_log("📄 Nom de fichier généré: " . $filename);
        
        // Créer le dossier de stockage si nécessaire
        $storagePath = dirname(self::getStoragePath($type, $filename));
        // error_log("📂 Vérification du dossier de stockage: " . $storagePath);
        self::ensureDirectoryExists($storagePath);
        
        // Déplacer le fichier
        $finalPath = self::getStoragePath($type, $filename);
        // error_log("📝 Tentative de déplacement vers: " . $finalPath);
        // error_log("- Fichier temporaire existe ? " . (file_exists($file['tmp_name']) ? "OUI" : "NON"));
        
        if (!move_uploaded_file($file['tmp_name'], $finalPath)) {
            $error = error_get_last();
            // error_log("❌ Erreur lors du déplacement: " . ($error ? $error['message'] : "Raison inconnue"));
            throw new Exception("Erreur lors du déplacement du fichier");
        }
        
        // error_log("✅ Fichier déplacé avec succès");

        // Convertir AVIF en JPEG pour une compatibilité navigateur maximale
        if ($extension === 'avif') {
            $baseName = pathinfo($filename, PATHINFO_FILENAME);
            $convertedFilename = $baseName . '.jpg';
            $convertedPath = $storagePath . '/' . $convertedFilename;

            /*
            logError('Storage::saveUploadedFile', 'Conversion AVIF demandee', [
                'original' => $finalPath,
                'target' => $convertedPath,
                'has_imagecreatefromavif' => function_exists('imagecreatefromavif'),
                'has_imagick' => class_exists('Imagick')
            ]);
            */

            $converted = self::convertToJpeg($finalPath, $convertedPath);
            if ($converted) {
                @unlink($finalPath);
                $finalPath = $convertedPath;
                $filename = $convertedFilename;
                $extension = 'jpg';
                // logError('Storage::saveUploadedFile', 'AVIF converti en JPEG', ['path' => $convertedPath]);
            } else {
                @unlink($finalPath);
                // logError('Storage::saveUploadedFile', 'Echec conversion AVIF', ['original' => $finalPath]);
                throw new Exception("Format AVIF non supporté par ce serveur pour la conversion");
            }
        }
        
        // Vérifier que le fichier a bien été créé
        if (!file_exists($finalPath)) {
            // error_log("❌ Le fichier n'existe pas après le déplacement");
            throw new Exception("Le fichier n'a pas été créé correctement");
        }
        
        // error_log("📏 Taille du fichier final: " . filesize($finalPath) . " bytes");
        
        $result = [
            'filename' => $filename,
            'storage_path' => $finalPath,
            'public_url' => self::getPublicUrl($type, $filename)
        ];
        
        // error_log("📤 Résultat final: " . print_r($result, true));
        return $result;
    }
    
    private static function convertToJpeg($sourcePath, $targetPath, $quality = 90) {
        if (function_exists('imagecreatefromavif')) {
            $src = @imagecreatefromavif($sourcePath);
            if ($src) {
                $width = imagesx($src);
                $height = imagesy($src);
                $dst = imagecreatetruecolor($width, $height);
                if ($dst) {
                    $white = imagecolorallocate($dst, 255, 255, 255);
                    imagefill($dst, 0, 0, $white);
                    imagecopy($dst, $src, 0, 0, 0, 0, $width, $height);
                    imagejpeg($dst, $targetPath, $quality);
                    imagedestroy($dst);
                }
                imagedestroy($src);
                return file_exists($targetPath);
            }
        }

        if (class_exists('Imagick')) {
            try {
                $imagick = new Imagick($sourcePath);
                $imagick->setImageFormat('jpeg');
                $imagick->setImageCompressionQuality($quality);
                $imagick->writeImage($targetPath);
                $imagick->clear();
                $imagick->destroy();
                return file_exists($targetPath);
            } catch (Exception $e) {
                // error_log("❌ Erreur Imagick AVIF -> JPEG: " . $e->getMessage());
                return false;
            }
        }

        return false;
    }

    public static function deleteFile($type, $filename) {
        $path = self::getStoragePath($type, $filename);
        if (file_exists($path)) {
            unlink($path);
            // error_log("🗑️ Fichier supprimé: " . $path);
        }
    }
}
