<?php
namespace App\Models;

class OrganizerProfile {
    private $db;

    public function __construct() {
        $this->db = getConnection();
    }

    public function getByUserId($userId) {
        $stmt = $this->db->prepare("SELECT * FROM organizer_profiles WHERE user_id = ?");
        $stmt->execute([(int)$userId]);
        return $stmt->fetch();
    }

    public function create($data) {
        $columns = implode(', ', array_keys($data));
        $values = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO organizer_profiles ($columns) VALUES ($values)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));
        return $this->db->lastInsertId();
    }

    public function update($id, $data) {
        $sets = [];
        foreach ($data as $key => $value) {
            $sets[] = "$key = ?";
        }
        $sql = "UPDATE organizer_profiles SET " . implode(', ', $sets) . " WHERE id = ?";
        
        $values = array_values($data);
        $values[] = (int)$id;
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }
}