<?php

namespace App\Models;

class Organization {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create($data) {
        if (empty($data['name'])) {
            throw new \Exception("Le nom de l'organisation est requis");
        }

        return $this->db->insert('organizations', [
            'name' => $data['name'],
            'logo_url' => $data['logo_url'] ?? null,
            'website' => $data['website'] ?? null,
            'description' => $data['description'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function update($id, $data) {
        return $this->db->update('organizations', $data, 'id = ' . (int)$id);
    }

    public function getById($id) {
        return $this->db->query("SELECT * FROM organizations WHERE id = ?", [$id])->fetch();
    }

    public function getByUserId($userId) {
        return $this->db->query(
            "SELECT o.* FROM organizations o 
            JOIN user_organizations uo ON o.id = uo.organization_id 
            WHERE uo.user_id = ?", 
            [$userId]
        )->fetch();
    }
}
