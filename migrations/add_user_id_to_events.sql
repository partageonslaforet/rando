-- Ajouter la colonne user_id
ALTER TABLE events ADD COLUMN user_id INT;

-- Ajouter la contrainte de clé étrangère
ALTER TABLE events ADD CONSTRAINT fk_events_user_id
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL;

-- Mettre à jour les événements existants pour les associer à l'admin
UPDATE events SET user_id = (
    SELECT id FROM users WHERE role = 'admin' LIMIT 1
);
