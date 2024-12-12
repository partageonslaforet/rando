import { useState, useEffect } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import { Fragment } from 'react';
import { LoginForm } from './LoginForm';
import { RegisterForm } from './RegisterForm';
import { X } from 'lucide-react';
import { useAuth } from '../../hooks/useAuth';
import { useNavigate } from 'react-router-dom';
import type { User } from '../../types/user';

interface AuthModalProps {
  isOpen: boolean;
  onClose: () => void;
  redirectUrl?: string;
  onLogin?: (credentials: { email: string; password: string; device_name: string }) => Promise<User>;
}

export function AuthModal({ isOpen, onClose, redirectUrl, onLogin }: AuthModalProps) {
  const [mode, setMode] = useState<'login' | 'register'>('login');
  const { user } = useAuth();
  const navigate = useNavigate();

  useEffect(() => {
    if (user) {
      onClose();
      if (redirectUrl) {
        navigate(redirectUrl);
      }
    }
  }, [user, onClose, redirectUrl, navigate]);

  return (
    <Transition appear show={isOpen} as={Fragment}>
      <Dialog as="div" className="relative z-50" onClose={onClose}>
        <Transition.Child
          as={Fragment}
          enter="ease-out duration-300"
          enterFrom="opacity-0"
          enterTo="opacity-100"
          leave="ease-in duration-200"
          leaveFrom="opacity-100"
          leaveTo="opacity-0"
        >
          <div className="fixed inset-0 bg-black bg-opacity-25" />
        </Transition.Child>

        <div className="fixed inset-0 overflow-y-auto">
          <div className="flex min-h-full items-center justify-center p-4 text-center">
            <Transition.Child
              as={Fragment}
              enter="ease-out duration-300"
              enterFrom="opacity-0 scale-95"
              enterTo="opacity-100 scale-100"
              leave="ease-in duration-200"
              leaveFrom="opacity-100 scale-100"
              leaveTo="opacity-0 scale-95"
            >
              <Dialog.Panel className="w-full max-w-md transform overflow-hidden rounded-2xl bg-white dark:bg-gray-800 p-6 text-left align-middle shadow-xl transition-all">
                <div className="flex justify-between items-center mb-4">
                  <Dialog.Title
                    as="h3"
                    className="text-lg font-medium leading-6 text-gray-900 dark:text-white"
                  >
                    {mode === 'login' ? 'Connexion' : 'Inscription'}
                  </Dialog.Title>
                  <button
                    onClick={onClose}
                    className="text-gray-400 hover:text-gray-500 focus:outline-none"
                  >
                    <X className="h-6 w-6" />
                  </button>
                </div>

                {mode === 'login' ? (
                  <>
                    <LoginForm onLogin={onLogin} />
                    <div className="mt-4 text-center">
                      <button
                        type="button"
                        onClick={() => setMode('register')}
                        className="text-sm text-primary hover:text-primary-dark"
                      >
                        Pas encore de compte ? Inscrivez-vous
                      </button>
                    </div>
                  </>
                ) : (
                  <>
                    <RegisterForm onSuccess={() => setMode('login')} />
                    <div className="mt-4 text-center">
                      <button
                        type="button"
                        onClick={() => setMode('login')}
                        className="text-sm text-primary hover:text-primary-dark"
                      >
                        Déjà un compte ? Connectez-vous
                      </button>
                    </div>
                  </>
                )}
              </Dialog.Panel>
            </Transition.Child>
          </div>
        </div>
      </Dialog>
    </Transition>
  );
}
