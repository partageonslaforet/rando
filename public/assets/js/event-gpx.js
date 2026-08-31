// Gestion des fichiers GPX
async function handleGpxUpload(input, routeIndex) {
    console.log(`🗺️ Upload GPX pour le parcours ${routeIndex}`);
    const file = input.files[0];
    
    if (!file) {
        console.log('❌ Aucun fichier sélectionné');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('gpx_file', file);
        formData.append('route_index', routeIndex);

        console.log('📤 Envoi du fichier GPX au serveur...');
        const response = await fetch('/api/events/upload-gpx.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        console.log('📥 Réponse reçue:', data);

        if (!response.ok) {
            throw new Error(data.error || 'Erreur lors de l\'upload du GPX');
        }

        if (data.success) {
            console.log('✅ GPX uploadé avec succès');
            // Stocker le chemin du fichier dans un champ caché
            const container = input.closest('.route-container');
            let hiddenInput = container.querySelector('input[name^="route_gpx_path"]');
            if (!hiddenInput) {
                hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = `route_gpx_path_${routeIndex}`;
                container.appendChild(hiddenInput);
            }
            hiddenInput.value = data.gpx_path;

            // Mettre à jour l'interface
            const fileNameDisplay = container.querySelector('.gpx-file-name');
            if (fileNameDisplay) {
                fileNameDisplay.textContent = file.name;
            }

            return data.gpx_path;
        } else {
            throw new Error(data.error || 'Erreur lors de l\'upload du GPX');
        }
    } catch (error) {
        console.error('❌ Erreur lors de l\'upload du GPX:', error);
        throw error;
    }
}
