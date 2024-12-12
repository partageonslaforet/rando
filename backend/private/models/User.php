<?php
class User {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function findByEmail($email) {
        try {
            $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$email]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur findByEmail: " . $e->getMessage());
            throw new Exception("Erreur lors de la recherche de l'utilisateur");
        }
    }

    public function findById($id) {
        try {
            $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur findById: " . $e->getMessage());
            throw new Exception("Erreur lors de la recherche de l'utilisateur");
        }
    }

    public function create($userData) {
        try {
            // Vérifier si l'email existe déjà
            if ($this->findByEmail($userData['email'])) {
                throw new Exception("Cet email est déjà utilisé");
            }

            // Hash du mot de passe
            $hashedPassword = password_hash($userData['password'], PASSWORD_ARGON2ID);

            $stmt = $this->db->prepare('
                INSERT INTO users (email, password, name, organization, role, is_verified, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ');

            $stmt->execute([
                $userData['email'],
                $hashedPassword,
                $userData['name'],
                $userData['organization'] ?? null,
                'user',
                0
            ]);

            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erreur create: " . $e->getMessage());
            throw new Exception("Erreur lors de la création de l'utilisateur");
        }
    }

    public function updateLastLogin($userId) {
        try {
            $stmt = $this->db->prepare('
                UPDATE users 
                SET last_login = NOW() 
                WHERE id = ?
            ');
            return $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log("Erreur updateLastLogin: " . $e->getMessage());
            throw new Exception("Erreur lors de la mise à jour de la dernière connexion");
        }
    }

    public function verifyEmail($token) {
        try {
            $stmt = $this->db->prepare('
                UPDATE users 
                SET is_verified = 1,
                    verification_token = NULL 
                WHERE verification_token = ?
            ');
            return $stmt->execute([$token]);
        } catch (PDOException $e) {
            error_log("Erreur verifyEmail: " . $e->getMessage());
            throw new Exception("Erreur lors de la vérification de l'email");
        }
    }
}