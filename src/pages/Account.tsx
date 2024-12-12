import { useState, useEffect } from 'react';
import { useAuth } from '../hooks/useAuth';
import { Card } from '../components/ui/card';
import { UserCircle, Calendar, Settings, Lock, Save, Plus } from 'lucide-react';
import { Switch } from '../components/ui/switch';
import { EventList } from '../components/EventList';
import { useNavigate } from 'react-router-dom';
import { useEvents } from '../services/events';
import { usePreferences } from '../services/preferences';
import { useProfile } from '../services/profile';
import toast from 'react-hot-toast';
import type { Event } from '../types/event';
import { Sidebar } from '@/components/ui/sidebar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';

const passwordSchema = z.object({
  current_password: z.string().min(1, 'Le mot de passe actuel est requis'),
  new_password: z.string()
    .min(8, 'Le nouveau mot de passe doit faire au moins 8 caractères')
    .regex(/[A-Z]/, 'Le mot de passe doit contenir au moins une majuscule')
    .regex(/[a-z]/, 'Le mot de passe doit contenir au moins une minuscule')
    .regex(/[0-9]/, 'Le mot de passe doit contenir au moins un chiffre'),
  new_password_confirmation: z.string()
}).refine((data) => data.new_password === data.new_password_confirmation, {
  message: "Les mots de passe ne correspondent pas",
  path: ["new_password_confirmation"],
});

type PasswordFormData = z.infer<typeof passwordSchema>;

const profileSchema = z.object({
  name: z.string().min(2, 'Le nom doit faire au moins 2 caractères'),
  email: z.string().email('Email invalide'),
  address: z.string().optional(),
  phone: z.string().optional(),
  organization: z.object({
    name: z.string().optional(),
    logo: z.string().optional(),
    website: z.string().url('URL invalide').optional(),
    address: z.string().optional(),
  }).optional(),
  bio: z.string().optional(),
});

type ProfileFormData = z.infer<typeof profileSchema>;

export function Account() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const events = useEvents();
  const preferences = usePreferences();
  const profile = useProfile();
  const [activeSection, setActiveSection] = useState('profile');
  const [emailNotifications, setEmailNotifications] = useState(true);
  const [isUpdatingProfile, setIsUpdatingProfile] = useState(false);
  const [isChangingPassword, setIsChangingPassword] = useState(false);

  const { register: registerProfile, handleSubmit: handleSubmitProfile, formState: { errors: profileErrors } } = useForm<ProfileFormData>({
    resolver: zodResolver(profileSchema),
    defaultValues: {
      name: user?.name || '',
      email: user?.email || '',
      address: user?.address || '',
      phone: user?.phone || '',
      organization: user?.organization || {},
      bio: user?.bio || '',
    }
  });

  const { register, handleSubmit, formState: { errors }, reset } = useForm<PasswordFormData>({
    resolver: zodResolver(passwordSchema)
  });

  useEffect(() => {
    if (user) {
      loadUserPreferences();
    }
  }, [user]);

  const loadUserPreferences = async () => {
    try {
      const preferences = await preferences.getPreferences();
      setEmailNotifications(preferences.emailNotifications);
    } catch (error) {
      console.error('Erreur lors du chargement des préférences:', error);
    }
  };

  const handleEmailNotificationsChange = async (checked: boolean) => {
    try {
      await preferences.updatePreferences({ emailNotifications: checked });
      setEmailNotifications(checked);
      toast.success('Préférences mises à jour');
    } catch (error) {
      console.error('Erreur lors de la mise à jour des préférences:', error);
      toast.error('Impossible de mettre à jour les préférences');
    }
  };

  const onProfileSubmit = async (data: ProfileFormData) => {
    setIsUpdatingProfile(true);
    try {
      await profile.updateProfile(data);
      toast.success('Profil mis à jour avec succès');
    } catch (error) {
      console.error('Erreur lors de la mise à jour du profil:', error);
      toast.error('Impossible de mettre à jour le profil');
    } finally {
      setIsUpdatingProfile(false);
    }
  };

  const onPasswordSubmit = async (data: PasswordFormData) => {
    setIsChangingPassword(true);
    try {
      await profile.changePassword({
        current_password: data.current_password,
        password: data.new_password,
        password_confirmation: data.new_password_confirmation
      });
      reset();
      toast.success('Mot de passe modifié avec succès');
    } catch (error) {
      console.error('Erreur lors du changement de mot de passe:', error);
      toast.error('Impossible de changer le mot de passe');
    } finally {
      setIsChangingPassword(false);
    }
  };

  const handleDeleteEvent = async (eventId: number) => {
    try {
      await events.deleteEvent(eventId);
      toast.success('Événement supprimé');
    } catch (error) {
      console.error('Erreur lors de la suppression:', error);
      toast.error('Impossible de supprimer l\'événement');
    }
  };

  const handleEditEvent = (eventId: number) => {
    navigate(`/edit/${eventId}`);
  };

  return (
    <div className="min-h-screen bg-background pt-16">
      <div className="container mx-auto px-4 py-8">
        <div className="mb-8">
          <h1 className="text-4xl font-bold text-secondary">
            Mon Compte
          </h1>
          <p className="mt-2 text-muted-foreground">
            Gérez votre profil et vos événements
          </p>
        </div>

        <div className="flex flex-col md:flex-row gap-6">
          <Sidebar
            items={[
              {
                value: 'profile',
                label: 'Profil',
                icon: UserCircle,
                className: 'text-primary'
              },
              {
                value: 'events',
                label: 'Mes Événements',
                icon: Calendar,
                className: 'text-primary'
              },
              {
                value: 'settings',
                label: 'Paramètres',
                icon: Settings,
                className: 'text-primary'
              },
              {
                value: 'password',
                label: 'Mot de passe',
                icon: Lock,
                className: 'text-primary'
              }
            ]}
            value={activeSection}
            onChange={setActiveSection}
          />
          
          <div className="flex-1">
            {activeSection === 'profile' && (
              <Card className="p-6">
                <div className="space-y-8">
                  <div>
                    <h2 className="text-2xl font-bold text-secondary mb-6 flex items-center gap-2">
                      <UserCircle className="h-6 w-6 text-primary" />
                      Informations Personnelles
                    </h2>
                    <form onSubmit={handleSubmitProfile(onProfileSubmit)} className="space-y-6">
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="space-y-2">
                          <label htmlFor="name" className="text-sm font-medium">
                            Nom complet
                          </label>
                          <Input
                            id="name"
                            {...registerProfile('name')}
                            className={profileErrors.name ? 'border-red-500' : ''}
                          />
                          {profileErrors.name && (
                            <p className="text-sm text-red-500">
                              {profileErrors.name.message}
                            </p>
                          )}
                        </div>

                        <div className="space-y-2">
                          <label htmlFor="email" className="text-sm font-medium">
                            Email
                          </label>
                          <Input
                            id="email"
                            type="email"
                            {...registerProfile('email')}
                            className={profileErrors.email ? 'border-red-500' : ''}
                          />
                          {profileErrors.email && (
                            <p className="text-sm text-red-500">
                              {profileErrors.email.message}
                            </p>
                          )}
                        </div>

                        <div className="space-y-2">
                          <label htmlFor="phone" className="text-sm font-medium">
                            Téléphone
                          </label>
                          <Input
                            id="phone"
                            {...registerProfile('phone')}
                          />
                        </div>

                        <div className="space-y-2">
                          <label htmlFor="address" className="text-sm font-medium">
                            Adresse
                          </label>
                          <Input
                            id="address"
                            {...registerProfile('address')}
                          />
                        </div>
                      </div>

                      <div className="border-t border-border pt-6 mt-6">
                        <h3 className="text-xl font-semibold text-secondary mb-4">
                          Information sur l'organisation
                        </h3>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                          <div className="space-y-2">
                            <label htmlFor="organization.name" className="text-sm font-medium">
                              Nom de l'organisation
                            </label>
                            <Input
                              id="organization.name"
                              {...registerProfile('organization.name')}
                            />
                          </div>

                          <div className="space-y-2">
                            <label htmlFor="organization.website" className="text-sm font-medium">
                              Site web
                            </label>
                            <Input
                              id="organization.website"
                              {...registerProfile('organization.website')}
                              className={profileErrors.organization?.website ? 'border-red-500' : ''}
                            />
                            {profileErrors.organization?.website && (
                              <p className="text-sm text-red-500">
                                {profileErrors.organization.website.message}
                              </p>
                            )}
                          </div>

                          <div className="space-y-2">
                            <label htmlFor="organization.logo" className="text-sm font-medium">
                              Logo URL
                            </label>
                            <Input
                              id="organization.logo"
                              {...registerProfile('organization.logo')}
                            />
                          </div>

                          <div className="space-y-2">
                            <label htmlFor="organization.address" className="text-sm font-medium">
                              Adresse de l'organisation
                            </label>
                            <Input
                              id="organization.address"
                              {...registerProfile('organization.address')}
                            />
                          </div>
                        </div>
                      </div>

                      <Button 
                        type="submit"
                        className="bg-secondary text-white hover:bg-secondary/90"
                      >
                        Sauvegarder les modifications
                      </Button>
                    </form>
                  </div>
                </div>
              </Card>
            )}

            {activeSection === 'events' && (
              <Card className="p-6">
                <div className="space-y-6">
                  <div className="flex items-center justify-between">
                    <h2 className="text-2xl font-bold text-black flex items-center gap-2">
                      <Calendar className="h-6 w-6 text-primary" />
                      Mes Événements
                    </h2>
                    <Button
                      type="button"
                      onClick={() => navigate('/components/ui/CreateEvent')}
                      className="bg-secondary text-white hover:bg-secondary/90"
                    >
                      <Plus className="h-4 w-4 mr-2" />
                      Créer un événement
                    </Button>
                  </div>

                  <EventList
                    events={events.getMyEvents()}
                    onDelete={handleDeleteEvent}
                    onEdit={handleEditEvent}
                  />
                  {!events.getMyEvents().length && (
                    <div className="text-center py-8">
                      <p className="text-gray-500 dark:text-gray-400">
                        Vous n'avez pas encore créé d'événements.
                      </p>
                      <Button
                        onClick={() => navigate('/components/ui/CreateEvent')}
                        className="inline-flex items-center px-4 py-2 mt-4 text-sm font-medium text-white bg-secondary rounded-md hover:bg-secondary/90"
                      >
                        Créer mon premier événement
                      </Button>
                    </div>
                  )}
                </div>
              </Card>
            )}

            {activeSection === 'settings' && (
              <Card className="p-6">
                <div className="space-y-8">
                  <div>
                    <h2 className="text-2xl font-bold text-black mb-6 flex items-center gap-2">
                      <Settings className="h-6 w-6 text-primary" />
                      Paramètres du Compte
                    </h2>
                    <div className="space-y-6">
                      <div className="flex items-center justify-between">
                        <div className="space-y-0.5">
                          <label className="text-sm font-medium">
                            Notifications par email
                          </label>
                          <p className="text-sm text-muted-foreground">
                            Recevoir des notifications par email pour les nouveaux messages
                          </p>
                        </div>
                        <Switch
                          checked={emailNotifications}
                          onCheckedChange={handleEmailNotificationsChange}
                        />
                      </div>
                    </div>
                  </div>
                </div>
              </Card>
            )}

            {activeSection === 'password' && (
              <Card className="p-6">
                <div className="space-y-8">
                  <div>
                    <h2 className="text-2xl font-bold text-black mb-6 flex items-center gap-2">
                      <Lock className="h-6 w-6 text-primary" />
                      Changer le mot de passe
                    </h2>
                    <form onSubmit={handleSubmit(onPasswordSubmit)} className="space-y-6">
                      <div className="space-y-2">
                        <label htmlFor="current_password" className="text-sm font-medium">
                          Mot de passe actuel
                        </label>
                        <Input
                          id="current_password"
                          type="password"
                          {...register('current_password')}
                          className={errors.current_password ? 'border-red-500' : ''}
                        />
                        {errors.current_password && (
                          <p className="text-sm text-red-500">
                            {errors.current_password.message}
                          </p>
                        )}
                      </div>

                      <div className="space-y-2">
                        <label htmlFor="new_password" className="text-sm font-medium">
                          Nouveau mot de passe
                        </label>
                        <Input
                          id="new_password"
                          type="password"
                          {...register('new_password')}
                          className={errors.new_password ? 'border-red-500' : ''}
                        />
                        {errors.new_password && (
                          <p className="text-sm text-red-500">
                            {errors.new_password.message}
                          </p>
                        )}
                      </div>

                      <div className="space-y-2">
                        <label htmlFor="new_password_confirmation" className="text-sm font-medium">
                          Confirmer le nouveau mot de passe
                        </label>
                        <Input
                          id="new_password_confirmation"
                          type="password"
                          {...register('new_password_confirmation')}
                          className={errors.new_password_confirmation ? 'border-red-500' : ''}
                        />
                        {errors.new_password_confirmation && (
                          <p className="text-sm text-red-500">
                            {errors.new_password_confirmation.message}
                          </p>
                        )}
                      </div>

                      <Button 
                        type="submit"
                        className="bg-secondary text-white hover:bg-secondary/90"
                      >
                        Changer le mot de passe
                      </Button>
                    </form>
                  </div>
                </div>
              </Card>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
