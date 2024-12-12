import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useAuth } from '../../hooks/useAuth';
import { z } from 'zod';
import { Loader2, Mail, Lock } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import type { User } from '../../types/user';

const loginSchema = z.object({
  email: z.string().email('Email invalide'),
  password: z.string().min(8, 'Le mot de passe doit contenir au moins 8 caractères'),
});

type LoginFormData = z.infer<typeof loginSchema>;

interface LoginFormProps {
  onLogin?: (credentials: { email: string; password: string; device_name: string }) => Promise<User>;
  onSuccess?: () => void;
}

export function LoginForm({ onLogin, onSuccess }: LoginFormProps) {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [isLoading, setIsLoading] = useState(false);

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
  });

  const onSubmit = async (data: LoginFormData) => {
    if (isLoading) return;

    setIsLoading(true);
    
    try {
      const device_name = window.navigator.userAgent;
      const credentials = { email: data.email, password: data.password, device_name };
      
      let user: User;
      if (onLogin) {
        user = await onLogin(credentials);
      } else {
        user = await login(credentials);
      }
      
      if (onSuccess) {
        onSuccess();
      }

      // Redirection en fonction du rôle
      if (user.role === 'admin') {
        navigate('/admin');
      } else {
        navigate('/');
      }
      
    } catch (error: any) {
      toast.error(
        error.response?.data?.message || 
        'Erreur lors de la connexion. Veuillez réessayer.'
      );
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
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

      <button
        type="submit"
        disabled={isLoading}
        className="w-full flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary disabled:opacity-50 disabled:cursor-not-allowed"
      >
        {isLoading ? (
          <>
            <Loader2 className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" />
            Connexion en cours...
          </>
        ) : (
          'Se connecter'
        )}
      </button>
    </form>
  );
}
