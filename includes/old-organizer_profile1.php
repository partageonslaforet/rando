<?php

class OrganizerProfile {
    private $db;
    private $user_id;

    public function __construct($db, $user_id) {
        $this->db = $db;
        $this->user_id = $user_id;
        $this->ensureTableExists();
    }

    /**
     * Assure que la table existe avec la bonne structure
     */
    public function ensureTableExists() {
        try {
            // Vérifier si la contrainte d'unicité existe et la supprimer
            $sql = "SELECT COUNT(*) 
                    FROM information_schema.TABLE_CONSTRAINTS 
                    WHERE CONSTRAINT_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'organizer_profiles' 
                    AND CONSTRAINT_NAME = 'unique_user_id'";
            
            $stmt = $this->db->query($sql);
            if ($stmt->fetchColumn() > 0) {
                error_log("🔍 [DB] Suppression de la contrainte d'unicité sur user_id");
                $this->db->exec("ALTER TABLE organizer_profiles DROP INDEX unique_user_id");
                error_log("✅ [DB] Contrainte d'unicité sur user_id supprimée");
            }

            error_log("🔍 [DB] Vérification/création de la table organizer_profiles");
            $sql = "CREATE TABLE IF NOT EXISTS organizer_profiles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                logo_path VARCHAR(255),
                storage_path VARCHAR(255),
                description TEXT,
                address TEXT,
                website VARCHAR(255),
                phone VARCHAR(20),
                email VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->db->exec($sql);
            error_log("✅ [DB] Table organizer_profiles vérifiée/créée avec succès");
            
            // Vérifier la structure de la table
            $columns = $this->db->query("SHOW COLUMNS FROM organizer_profiles")->fetchAll(PDO::FETCH_COLUMN);
            error_log("📊 [DB] Colonnes actuelles: " . implode(', ', $columns));
            
            return true;
        } catch (PDOException $e) {
            error_log("❌ [DB] Erreur lors de la création/vérification de la table: " . $e->getMessage());
            throw new Exception("Erreur lors de la configuration de la base de données: " . $e->getMessage());
        }
    }

    private function validateData($data) {
        $errors = [];
        
        // Valider le nom
        if (empty($data['name'])) {
            $errors[] = "Le nom est requis";
        }
        
        // Valider l'email
        if (!empty($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Format d'email invalide";
            }
        }
        
        // Valider le site web
        if (!empty($data['website'])) {
            if (!filter_var($data['website'], FILTER_VALIDATE_URL)) {
                $errors[] = "Format d'URL invalide";
            }
        }
        
        // Valider le téléphone
        if (!empty($data['phone'])) {
            if (!preg_match("/^[0-9+\-\s()]*$/", $data['phone'])) {
                $errors[] = "Format de téléphone invalide";
            }
        }
        
        if (!empty($errors)) {
            throw new Exception(implode(", ", $errors));
        }
        
        return true;
    }

    public function uploadLogo($file, $profile_id) {
        try {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Erreur lors du téléchargement du fichier');
            }

            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($fileInfo, $file['tmp_name']);
            finfo_close($fileInfo);

            if (!in_array($mimeType, $allowedTypes)) {
                throw new Exception('Type de fichier non autorisé. Utilisez JPG, PNG ou GIF.');
            }

            $maxFileSize = 5 * 1024 * 1024; // 5MB
            if ($file['size'] > $maxFileSize) {
                throw new Exception('Le fichier est trop volumineux (max 5MB)');
            }

            $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/organizer_logos/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'logo_' . $profile_id . '_' . uniqid() . '.' . $extension;
            $storagePath = $uploadDir . $filename;
            $logoPath = '/uploads/organizer_logos/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $storagePath)) {
                throw new Exception('Erreur lors de l\'enregistrement du fichier');
            }

            // Mettre à jour les chemins dans la base de données
            $stmt = $this->db->prepare("UPDATE organizer_profiles SET logo_path = ?, storage_path = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$logoPath, $storagePath, $profile_id, $this->user_id]);

            return $logoPath;
        } catch (Exception $e) {
            error_log("Erreur lors du téléchargement du logo : " . $e->getMessage());
            throw $e;
        }
    }

    public function createOrUpdate($data, $profile_id = null) {
        error_log("🔍 [START] createOrUpdate - profile_id: " . ($profile_id ?? 'null'));
        error_log("📝 [DATA] Données reçues: " . print_r($data, true));
        error_log("👤 [USER] user_id: " . $this->user_id);
        
        try {
            $this->db->beginTransaction();
            
            // Valider les données
            $this->validateData($data);
            
            if ($profile_id !== null) {
                error_log("🔍 [CHECK] Vérification propriété du profil #" . $profile_id);
                $stmt = $this->db->prepare("SELECT id FROM organizer_profiles WHERE id = ? AND user_id = ?");
                $stmt->execute([$profile_id, $this->user_id]);
                
                if (!$stmt->fetch()) {
                    $this->db->rollBack();
                    error_log("❌ [ERROR] Profil #" . $profile_id . " n'appartient pas à l'utilisateur #" . $this->user_id);
                    throw new Exception("Vous n'êtes pas autorisé à modifier ce profil");
                }
                
                error_log("✅ [OK] Profil #" . $profile_id . " appartient à l'utilisateur #" . $this->user_id);
                
                // Mise à jour du profil existant
                $sql = "UPDATE organizer_profiles SET 
                        name = :name, 
                        address = :address, 
                        description = :description, 
                        website = :website, 
                        phone = :phone, 
                        email = :email 
                        WHERE id = :id AND user_id = :user_id";
                
                $stmt = $this->db->prepare($sql);
                $params = [
                    ':name' => $data['name'],
                    ':address' => $data['address'] ?? null,
                    ':description' => $data['description'] ?? null,
                    ':website' => $data['website'] ?? null,
                    ':phone' => $data['phone'] ?? null,
                    ':email' => $data['email'] ?? null,
                    ':id' => $profile_id,
                    ':user_id' => $this->user_id
                ];
                
                error_log("📝 [SQL] Exécution update avec params: " . print_r($params, true));
                
                if (!$stmt->execute($params)) {
                    $error = $stmt->errorInfo();
                    $this->db->rollBack();
                    error_log("❌ [ERROR] Échec de la mise à jour du profil #" . $profile_id . ". Code: " . $error[0] . " Message: " . $error[2]);
                    throw new Exception("Erreur lors de la mise à jour du profil: " . $error[2]);
                }
                
                error_log("✅ [OK] Profil #" . $profile_id . " mis à jour");
                $result = $profile_id;
            } else {
                error_log("📝 [INSERT] Création d'un nouveau profil");
                
                $sql = "INSERT INTO organizer_profiles 
                        (user_id, name, address, description, website, phone, email) 
                        VALUES (:user_id, :name, :address, :description, :website, :phone, :email)";
                
                $stmt = $this->db->prepare($sql);
                $params = [
                    ':user_id' => $this->user_id,
                    ':name' => $data['name'],
                    ':address' => $data['address'] ?? null,
                    ':description' => $data['description'] ?? null,
                    ':website' => $data['website'] ?? null,
                    ':phone' => $data['phone'] ?? null,
                    ':email' => $data['email'] ?? null
                ];
                
                error_log("📝 [SQL] Exécution insert avec params: " . print_r($params, true));
                
                if (!$stmt->execute($params)) {
                    $error = $stmt->errorInfo();
                    $this->db->rollBack();
                    error_log("❌ [ERROR] Échec de la création du profil. Code: " . $error[0] . " Message: " . $error[2]);
                    throw new Exception("Erreur lors de la création du profil: " . $error[2]);
                }
                
                $result = $this->db->lastInsertId();
                error_log("✅ [OK] Nouveau profil créé avec ID #" . $result);
            }

            $this->db->commit();
            error_log("✅ [SUCCESS] Transaction terminée avec succès");
            return $result;
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
                error_log("↩️ [ROLLBACK] Transaction annulée");
            }
            error_log("❌ [ERROR] " . $e->getMessage());
            throw $e;
        }
    }

    public function getAll() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM organizer_profiles WHERE user_id = ? AND is_active = TRUE ORDER BY created_at DESC");
            $stmt->execute([$this->user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des profils organisateur : " . $e->getMessage());
            return [];
        }
    }

    public function get($profile_id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM organizer_profiles WHERE id = ? AND user_id = ? AND is_active = TRUE");
            $stmt->execute([$profile_id, $this->user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération du profil organisateur : " . $e->getMessage());
            return null;
        }
    }

    public function delete($profile_id) {
        try {
            // Récupérer les chemins des fichiers
            $stmt = $this->db->prepare("SELECT storage_path FROM organizer_profiles WHERE id = ? AND user_id = ?");
            $stmt->execute([$profile_id, $this->user_id]);
            $profile = $stmt->fetch();

            // Supprimer le fichier si existe
            if ($profile && !empty($profile['storage_path']) && file_exists($profile['storage_path'])) {
                unlink($profile['storage_path']);
            }

            // Supprimer l'enregistrement
            $stmt = $this->db->prepare("UPDATE organizer_profiles SET is_active = FALSE WHERE id = ? AND user_id = ?");
            return $stmt->execute([$profile_id, $this->user_id]);
        } catch (PDOException $e) {
            error_log("Erreur lors de la suppression du profil organisateur : " . $e->getMessage());
            return false;
        }
    }
}
