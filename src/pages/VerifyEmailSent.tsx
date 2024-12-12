import React, { useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useApi } from '../contexts/ApiContext';
import { Loader2 } from 'lucide-react';
import toast from 'react-hot-toast';

export function VerifyEmailSent() {
  const [isResending, setIsResending] = useState(false);
  const navigate = useNavigate();
  const location = useLocation();
  const { api } = useApi();
  const email = location.state?.email;

  const handleResendVerification = async () => {
    if (!api) {
      toast.error('Service API non disponible');
      return;
    }

    if (!email) {
      toast.error('Email non disponible');
      return;
    }

    setIsResending(true);
    try {
      const response = await api.post('/auth/resend-verification', { email });
      if (response.data?.status === 'success') {
        toast.success('Email de vérification renvoyé avec succès');
      } else {
        toast.error('Erreur lors du renvoi de l\'email de vérification');
      }
    } catch (error) {
      console.error('Erreur lors du renvoi de l\'email de vérification:', error);
      toast.error('Erreur lors du renvoi de l\'email de vérification');
    } finally {
      setIsResending(false);
    }
  };

  if (!email) {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen bg-gray-100 dark:bg-gray-900 px-4">
        <div className="text-center">
          <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-4">
            Erreur
          </h1>
          <p className="text-gray-600 dark:text-gray-400 mb-8">
            Impossible de renvoyer l'email de vérification. Email non spécifié.
          </p>
          <button
            onClick={() => navigate('/login')}
            className="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
          >
            Retour à la connexion
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="flex flex-col items-center justify-center min-h-screen bg-gray-100 dark:bg-gray-900 px-4">
      <div className="text-center">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-4">
          Email de vérification envoyé
        </h1>
        <p className="text-gray-600 dark:text-gray-400 mb-2">
          Un email de vérification a été envoyé à :
        </p>
        <p className="text-gray-900 dark:text-white font-medium mb-8">{email}</p>
        <p className="text-gray-600 dark:text-gray-400 mb-8">
          Veuillez vérifier votre boîte de réception et suivre les instructions pour activer votre compte.
        </p>
        
        <div className="space-y-4">
          <button
            onClick={handleResendVerification}
            disabled={isResending}
            className="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed w-full sm:w-auto"
          >
            {isResending ? (
              <>
                <Loader2 className="animate-spin inline-block mr-2" size={16} />
                Envoi en cours...
              </>
            ) : (
              'Renvoyer l\'email'
            )}
          </button>
          
          <button
            onClick={() => navigate('/login')}
            className="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white focus:outline-none block w-full sm:w-auto"
          >
            Retour à la connexion
          </button>
        </div>
      </div>
    </div>
  );
}