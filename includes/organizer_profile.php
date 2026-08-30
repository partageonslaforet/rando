<?php

class OrganizerProfile {
    private $db;
    private $user_id;

    public function __construct($db, $user_id = null) {
        error_log("🔧 OrganizerProfile Constructor");
        error_log("📌 DB Connection: " . ($db instanceof PDO ? 'OK' : 'NON'));
        error_log("📌 User ID provided: " . ($user_id ? 'YES: ' . $user_id : 'NO'));
        
        $this->db = $db;
        $this->user_id = $user_id;
        
        // Vérifier et créer la table si nécessaire
        $this->ensureTableExists();
        
        error_log("✅ OrganizerProfile initialized with user_id: " . $this->user_id);
    }

    /**
     * Récupère tous les profils d'un utilisateur
     */
    public function getByUserId($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM organizer_profiles 
                WHERE user_id = ? 
                ORDER BY name ASC
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des profils organisateur: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère un profil par son ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM organizer_profiles 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération du profil organisateur: " . $e->getMessage());
            return null;
        }
    }

    private function validateData($data) {
        if (empty($data['name'])) {
            throw new Exception("Le nom est requis");
        }
        
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Format d'email invalide");
        }
        
        if (!empty($data['website']) && !filter_var($data['website'], FILTER_VALIDATE_URL)) {
            throw new Exception("Format d'URL invalide");
        }
        
        if (!empty($data['phone']) && !preg_match("/^[0-9+\-\s()]*$/", $data['phone'])) {
            throw new Exception("Format de téléphone invalide");
        }
        
        return true;
    }

    public function uploadLogo($file, $profile_id) {
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
    }

    public function createOrUpdate($data, $profile_id = null) {
        try {
            $this->db->beginTransaction();
            
            // Valider les données
            $this->validateData($data);
            
            if ($profile_id) {
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
                $success = $stmt->execute([
                    ':name' => $data['name'],
                    ':address' => $data['address'] ?? null,
                    ':description' => $data['description'] ?? null,
                    ':website' => $data['website'] ?? null,
                    ':phone' => $data['phone'] ?? null,
                    ':email' => $data['email'] ?? null,
                    ':id' => $profile_id,
                    ':user_id' => $this->user_id
                ]);
                
                if (!$success) {
                    throw new Exception("Erreur lors de la mise à jour du profil");
                }
                
                $result = $profile_id;
            } else {
                // Création d'un nouveau profil
                $sql = "INSERT INTO organizer_profiles 
                        (user_id, name, address, description, website, phone, email) 
                        VALUES (:user_id, :name, :address, :description, :website, :phone, :email)";
                
                $stmt = $this->db->prepare($sql);
                $success = $stmt->execute([
                    ':user_id' => $this->user_id,
                    ':name' => $data['name'],
                    ':address' => $data['address'] ?? null,
                    ':description' => $data['description'] ?? null,
                    ':website' => $data['website'] ?? null,
                    ':phone' => $data['phone'] ?? null,
                    ':email' => $data['email'] ?? null
                ]);
                
                if (!$success) {
                    throw new Exception("Erreur lors de la création du profil");
                }
                
                $result = $this->db->lastInsertId();
            }

            $this->db->commit();
            return $result;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function get($profile_id) {
        $stmt = $this->db->prepare("SELECT * FROM organizer_profiles WHERE id = ? AND user_id = ?");
        $stmt->execute([$profile_id, $this->user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAll() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM organizer_profiles WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$this->user_id]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Ajouter un commentaire HTML pour le débogage
            echo "<!-- DEBUG getAll: " . count($results) . " résultats trouvés -->";
            echo "<!-- DEBUG SQL: " . json_encode($results) . " -->";
            
            return $results;
        } catch (PDOException $e) {
            // Ajouter un commentaire HTML pour le débogage
            echo "<!-- DEBUG Error: " . $e->getMessage() . " -->";
            return [];
        }
    }

    public function delete($profile_id) {
        $stmt = $this->db->prepare("DELETE FROM organizer_profiles WHERE id = ? AND user_id = ?");
        return $stmt->execute([$profile_id, $this->user_id]);
    }

    /**
     * Recherche des organisateurs par nom
     */
    public function searchByName($term) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, name, description, distance, elevation_gain, gpx_file
                FROM organizer_profiles 
                WHERE name LIKE ?
                ORDER BY name ASC
                LIMIT 10
            ");
            $stmt->execute(["%$term%"]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur lors de la recherche des organisateurs: " . $e->getMessage());
            return [];
        }
    }

    private function ensureTableExists() {
        try {
            // Vérifier si la table existe
            $tableExists = $this->db->query("SHOW TABLES LIKE 'organizer_profiles'");
            if ($tableExists->rowCount() === 0) {
                // La table n'existe pas, on la crée
                $sql = file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/sql/create_organizer_profiles.sql');
                $this->db->exec($sql);
            }
        } catch (PDOException $e) {
            // En production, on ne fait rien
        }
    }
}
