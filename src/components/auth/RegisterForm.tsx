import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Loader2, Mail, Lock, User } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import { useAuth } from '../../hooks/useAuth';

const registerSchema = z.object({
  name: z.string().min(2, 'Le nom doit contenir au moins 2 caractères'),
  email: z.string().email('Email invalide'),
  password: z.string().min(8, 'Le mot de passe doit contenir au moins 8 caractères'),
  password_confirmation: z.string()
}).refine((data) => data.password === data.password_confirmation, {
  message: "Les mots de passe ne correspondent pas",
  path: ["password_confirmation"],
});

type RegisterFormData = z.infer<typeof registerSchema>;

export function RegisterForm() {
  const { register: registerUser } = useAuth();
  const navigate = useNavigate();
  const [isLoading, setIsLoading] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<RegisterFormData>({
    resolver: zodResolver(registerSchema),
  });

  const onSubmit = async (data: RegisterFormData) => {
    if (isLoading) return;

    setIsLoading(true);
    
    try {
      const device_name = window.navigator.userAgent;
      const result = await registerUser({
        name: data.name,
        email: data.email,
        password: data.password,
        password_confirmation: data.password_confirmation,
        device_name,
      });
      
      console.log('[RegisterForm] Réponse complète du serveur:', result);
      
      // Afficher les informations de débogage
      if (result.debug) {
        console.log('[RegisterForm] Informations de débogage:', result.debug);
        toast((t) => (
          <div>
            <p className="font-medium">Inscription réussie !</p>
            <pre className="mt-2 text-xs bg-gray-100 p-2 rounded overflow-auto max-h-40">
              {JSON.stringify(result.debug, null, 2)}
            </pre>
          </div>
        ), { duration: 10000 });
      } else {
        toast.success('Inscription réussie ! Un email de confirmation vous a été envoyé.');
      }
      
      // Forcer une attente pour s'assurer que le toast est affiché
      await new Promise(resolve => setTimeout(resolve, 2000));
      
      // Rediriger vers la page de vérification
      console.log('[RegisterForm] Redirection vers /verify-email-sent');
      navigate('/verify-email-sent', { replace: true });
      
    } catch (error: any) {
      console.error('[RegisterForm] Erreur complète:', error);
      console.error('[RegisterForm] Réponse du serveur:', error.response?.data);
      
      // Afficher les détails de l'erreur
      toast.error((t) => (
        <div>
          <p>Erreur lors de l'inscription</p>
          <pre className="mt-2 text-xs bg-gray-100 p-2 rounded">
            {JSON.stringify(error.response?.data?.debug || error.message, null, 2)}
          </pre>
        </div>
      ), { duration: 10000 });
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
      <div className="relative py-3">
        <label htmlFor="name" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Nom
        </label>
        <div className="relative">
          <User className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
          <input
            {...register('name')}
            type="text"
            id="name"
            className="pl-10 block w-full rounded-lg border-gray-300 bg-gray-50 shadow-sm transition-colors duration-200 focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:border-primary py-2"
            placeholder="Votre nom"
          />
        </div>
        {errors.name && (
          <p className="mt-1 text-sm text-red-600 dark:text-red-400">{errors.name.message}</p>
        )}
      </div>

      <div className="relative py-3">
        <label htmlFor="email" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Email
        </label>
        <div className="relative">
          <Mail className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
          <input
            {...register('email')}
            type="email"
            id="email"
            className="pl-10 block w-full rounded-lg border-gray-300 bg-gray-50 shadow-sm transition-colors duration-200 focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:border-primary py-2"
            placeholder="votre@email.com"
          />
        </div>
        {errors.email && (
          <p className="mt-1 text-sm text-red-600 dark:text-red-400">{errors.email.message}</p>
        )}
      </div>

      <div className="relative py-3">
        <label htmlFor="password" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Mot de passe
        </label>
        <div className="relative">
          <Lock className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
          <input
            {...register('password')}
            type="password"
            id="password"
            className="pl-10 block w-full rounded-lg border-gray-300 bg-gray-50 shadow-sm transition-colors duration-200 focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:border-primary py-2"
            placeholder="••••••••"
          />
        </div>
        {errors.password && (
          <p className="mt-1 text-sm text-red-600 dark:text-red-400">{errors.password.message}</p>
        )}
      </div>

      <div className="relative py-3">
        <label htmlFor="password_confirmation" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Confirmer le mot de passe
        </label>
        <div className="relative">
          <Lock className="absolute left-3 top-1/2 transform -translate-y-1/2 h-5 w-5 text-gray-400" />
          <input
            {...register('password_confirmation')}
            type="password"
            id="password_confirmation"
            className="pl-10 block w-full rounded-lg border-gray-300 bg-gray-50 shadow-sm transition-colors duration-200 focus:border-primary focus:ring-primary dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:focus:border-primary py-2"
            placeholder="••••••••"
          />
        </div>
        {errors.password_confirmation && (
          <p className="mt-1 text-sm text-red-600 dark:text-red-400">{errors.password_confirmation.message}</p>
        )}
      </div>

      <button
        type="submit"
        disabled={isLoading}
        className="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary disabled:opacity-50 transition-all duration-200 ease-in-out transform hover:-translate-y-0.5"
      >
        {isLoading ? (
          <>
            <Loader2 className="animate-spin -ml-1 mr-2 h-5 w-5" />
            Inscription en cours...
          </>
        ) : (
          'S\'inscrire'
        )}
      </button>
    </form>
  );
}
