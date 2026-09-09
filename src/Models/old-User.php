<?php
class User {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getById($id) {
        return $this->db->query("SELECT * FROM users WHERE id = ?", [$id])->fetch();
    }

    public function getByEmail($email) {
        return $this->db->query("SELECT * FROM users WHERE email = ?", [$email])->fetch();
    }

    public function create($data) {
        // Validation
        if (empty($data['email']) || empty($data['password']) || empty($data['name'])) {
            throw new Exception("Tous les champs sont requis");
        }

        // Vérifier si l'email existe déjà
        if ($this->getByEmail($data['email'])) {
            throw new Exception("Cette adresse email est déjà utilisée");
        }

        // Hasher le mot de passe
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Ajouter la date de création
        $data['created_at'] = date('Y-m-d H:i:s');
        
        return $this->db->insert('users', $data);
    }

    public function update($id, $data) {
        // Si le mot de passe est modifié, le hasher
        if (!empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        return $this->db->update('users', $data, 'id = ' . (int)$id);
    }

    public function authenticate($email, $password) {
        $user = $this->getByEmail($email);
        
        if ($user && password_verify($password, $user['password'])) {
            // Créer la session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            return true;
        }
        
        return false;
    }

    public function logout() {
        session_destroy();
    }

    public function getEvents($userId) {
        $sql = "SELECT e.* 
                FROM events e
                WHERE e.organizer_id = ?
                ORDER BY e.date DESC";
        
        return $this->db->query($sql, [$userId])->fetchAll();
    }

    public function getParticipatingEvents($userId) {
        $sql = "SELECT e.* 
                FROM events e
                JOIN event_participants ep ON e.id = ep.event_id
                WHERE ep.user_id = ?
                ORDER BY e.date DESC";
        
        return $this->db->query($sql, [$userId])->fetchAll();
    }

    public function count($filters = []) {
        $sql = "SELECT COUNT(*) FROM users";
        $params = [];
        
        $where = [];
        if (!empty($filters['status']) && $filters['status'] === 'active') {
            $where[] = "is_active = 1";
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        try {
            return $this->db->query($sql, $params)->fetchColumn();
        } catch (PDOException $e) {
            error_log("Erreur lors du comptage des utilisateurs : " . $e->getMessage());
            return 0;
        }
    }

    public function getAll($filters = []) {
        $sql = "SELECT * FROM users";
        $params = [];
        
        $where = [];
        if (!empty($filters['status']) && $filters['status'] === 'active') {
            $where[] = "is_active = 1";
        }
        
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        
        if (!empty($filters['order'])) {
            $sql .= " ORDER BY " . $filters['order'];
        }
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }
        
        try {
            return $this->db->query($sql, $params)->fetchAll();
        } catch (PDOException $e) {
            error_log("Erreur lors de la récupération des utilisateurs : " . $e->getMessage());
            return [];
        }
    }
}
