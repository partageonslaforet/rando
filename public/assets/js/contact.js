document.addEventListener('DOMContentLoaded', function() {
    const contactForm = document.getElementById('contactForm');
    
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(contactForm);
            
            fetch('/actions/send-contact.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert(data.message);
                    // Reset form and close modal
                    contactForm.reset();
                    bootstrap.Modal.getInstance(document.getElementById('contactModal')).hide();
                } else {
                    // Show error message
                    alert(data.error || 'Une erreur est survenue');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Une erreur est survenue lors de l\'envoi du message');
            });
        });
    }
});
