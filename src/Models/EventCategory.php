<?php
/**
 * src/Models/EventCategory.php
 * Role: Modèle de gestion des catégories d'événements.
 * Usage: new EventCategory($db)
 * Dépendances: Aucune
 */

class EventCategory {
    private PDO $db;
    private Closure $logger;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->logger = function($message) {
            error_log("[EventCategory] " . $message);
        };
    }

    /**
     * Récupère toutes les catégories actives
     */
    public function getAllActive() {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM event_categories 
                WHERE active = 1 
                ORDER BY sort_order ASC, name ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            ($this->logger)("Erreur lors de la récupération des catégories actives: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère toutes les catégories (actives et inactives)
     */
    public function getAll() {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM event_categories 
                ORDER BY sort_order ASC, name ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            ($this->logger)("Erreur lors de la récupération des catégories: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupère une catégorie par son ID
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM event_categories WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            ($this->logger)("Erreur lors de la récupération de la catégorie {$id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Vérifie si un code de catégorie existe déjà
     */
    public function codeExists($code) {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM event_categories WHERE code = ?");
            $stmt->execute([$code]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            ($this->logger)("Erreur lors de la vérification du code: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Crée une nouvelle catégorie
     */
    public function create($data) {
        try {
            // Vérifier si le code existe déjà
            if ($this->codeExists($data['code'])) {
                throw new Exception("Une catégorie avec ce code existe déjà");
            }

            ($this->logger)("Tentative de création de catégorie avec les données: " . print_r($data, true));

            $stmt = $this->db->prepare("
                INSERT INTO event_categories (code, name, icon, color, active, sort_order)
                VALUES (:code, :name, :icon, :color, :active, :sort_order)
            ");
            
            $result = $stmt->execute([
                ':code' => $data['code'],
                ':name' => $data['name'],
                ':icon' => $data['icon'] ?? null,
                ':color' => $data['color'] ?? null,
                ':active' => $data['active'] ?? true,
                ':sort_order' => $data['sort_order'] ?? 0
            ]);

            if (!$result) {
                ($this->logger)("Échec de l'insertion: " . print_r($stmt->errorInfo(), true));
                throw new Exception("Échec de la création de la catégorie");
            }

            $newId = $this->db->lastInsertId();
            ($this->logger)("Catégorie créée avec succès, ID: " . $newId);
            return $newId;

        } catch (PDOException $e) {
            ($this->logger)("Erreur PDO lors de la création de la catégorie: " . $e->getMessage());
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                throw new Exception("Une catégorie avec ce code existe déjà");
            }
            throw $e;
        }
    }

    /**
     * Met à jour une catégorie existante
     */
    public function update($id, $data) {
        try {
            ($this->logger)("Début de la mise à jour de la catégorie {$id}");
            ($this->logger)("Données reçues : " . print_r($data, true));

            // Récupérer les valeurs existantes
            $current = $this->getById($id);
            if (!$current) {
                throw new Exception("Catégorie non trouvée");
            }

            // Construire la requête dynamiquement
            $fields = [];
            $params = [':id' => $id];

            if (isset($data['code'])) {
                $fields[] = "code = :code";
                $params[':code'] = $data['code'];
            }

            if (isset($data['name'])) {
                $fields[] = "name = :name";
                $params[':name'] = $data['name'];
            }

            if (array_key_exists('icon', $data)) {
                $fields[] = "icon = :icon";
                $params[':icon'] = $data['icon'];
            }

            if (array_key_exists('color', $data)) {
                $fields[] = "color = :color";
                $params[':color'] = $data['color'];
            }

            if (isset($data['active'])) {
                $fields[] = "active = :active";
                $params[':active'] = $data['active'];
            }

            if (isset($data['sort_order'])) {
                $fields[] = "sort_order = :sort_order";
                $params[':sort_order'] = $data['sort_order'];
            }

            if (empty($fields)) {
                ($this->logger)("Aucun champ à mettre à jour");
                return true;
            }

            $query = "UPDATE event_categories SET " . implode(", ", $fields) . " WHERE id = :id";
            ($this->logger)("Requête SQL : " . $query);
            ($this->logger)("Paramètres : " . print_r($params, true));

            $stmt = $this->db->prepare($query);
            $result = $stmt->execute($params);

            ($this->logger)("Résultat de la mise à jour : " . ($result ? "succès" : "échec"));
            return $result;
        } catch (PDOException $e) {
            ($this->logger)("Erreur lors de la mise à jour de la catégorie {$id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Supprime une catégorie
     * Note: À utiliser avec précaution, vérifier d'abord qu'aucun événement n'utilise cette catégorie
     */
    public function delete($id) {
        try {
            // Vérifier si la catégorie est utilisée
            $checkStmt = $this->db->prepare("
                SELECT COUNT(*) FROM events WHERE category_id = ?
            ");
            $checkStmt->execute([$id]);
            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception("Cette catégorie est utilisée par des événements et ne peut pas être supprimée");
            }

            $stmt = $this->db->prepare("DELETE FROM event_categories WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            ($this->logger)("Erreur lors de la suppression de la catégorie {$id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Change l'état actif/inactif d'une catégorie
     */
    public function toggleActive($id, $active) {
        try {
            $stmt = $this->db->prepare("
                UPDATE event_categories 
                SET active = :active 
                WHERE id = :id
            ");
            return $stmt->execute([
                ':id' => $id,
                ':active' => $active
            ]);
        } catch (PDOException $e) {
            ($this->logger)("Erreur lors du changement d'état de la catégorie {$id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Met à jour l'ordre de tri des catégories
     */
    public function updateOrder($orderData) {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("
                UPDATE event_categories 
                SET sort_order = :sort_order 
                WHERE id = :id
            ");
            
            foreach ($orderData as $item) {
                $stmt->execute([
                    ':id' => $item['id'],
                    ':sort_order' => $item['order']
                ]);
            }
            
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            ($this->logger)("Erreur lors de la mise à jour de l'ordre des catégories: " . $e->getMessage());
            throw $e;
        }
    }
}
