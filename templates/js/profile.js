
document.addEventListener('DOMContentLoaded', function() {
    
    const profileForm = document.getElementById('profileForm');
    
    if (profileForm) {
        profileForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            try {
                const formData = new FormData(this);
                const data = Object.fromEntries(formData.entries());

                const response = await fetch('/api/profile.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data),
                    credentials: 'include'
                });

                const result = await response.json();
                
                if (result.success) {
                    showMessage('Profil mis à jour avec succès !', 'success');
                } else {
                    throw new Error(result.message || 'Erreur lors de la mise à jour du profil');
                }
            } catch (error) {
                console.error('❌ Erreur:', error);
                showMessage(error.message, 'danger');
            }
        });
    }

    // Fonction utilitaire pour afficher les messages
    function showMessage(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
        alertDiv.setAttribute('role', 'alert');
        alertDiv.style.zIndex = '9999';
        
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
});
