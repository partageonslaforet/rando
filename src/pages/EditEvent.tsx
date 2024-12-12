import { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Textarea } from '../components/ui/textarea';
import { Card } from '../components/ui/card';
import { useEvents } from '../services/events';
import toast from 'react-hot-toast';
import { useAuth } from '../hooks/useAuth';
import type { Event } from '../types/event';

const eventSchema = z.object({
  title: z.string().min(3, 'Le titre doit faire au moins 3 caractères'),
  description: z.string().min(10, 'La description doit faire au moins 10 caractères'),
  date: z.string().min(1, 'La date est requise'),
  startTime: z.string().min(1, 'L\'heure de début est requise'),
  endTime: z.string().optional(),
  address: z.string().min(5, 'L\'adresse doit faire au moins 5 caractères'),
  maxParticipants: z.number().min(1, 'Le nombre de participants doit être au moins 1'),
  price: z.number().min(0, 'Le prix ne peut pas être négatif'),
  category: z.string().min(1, 'La catégorie est requise'),
});

type EventFormData = z.infer<typeof eventSchema>;

export function EditEvent() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user } = useAuth();
  const events = useEvents();
  const [isLoading, setIsLoading] = useState(false);
  const [event, setEvent] = useState<Event | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors },
    reset,
  } = useForm<EventFormData>({
    resolver: zodResolver(eventSchema),
  });

  useEffect(() => {
    if (!id) {
      navigate('/events');
      return;
    }

    const fetchEvent = async () => {
      try {
        const eventData = await events.getEvent(parseInt(id));
        setEvent(eventData);
        reset({
          title: eventData.title,
          description: eventData.description,
          date: eventData.date,
          startTime: eventData.startTime,
          endTime: eventData.endTime,
          address: eventData.address,
          maxParticipants: eventData.maxParticipants,
          price: eventData.price,
          category: eventData.category,
        });
      } catch (error) {
        console.error('Erreur lors de la récupération de l\'événement:', error);
        toast.error('Impossible de charger l\'événement');
        navigate('/events');
      }
    };

    fetchEvent();
  }, [id, reset, navigate, events]);

  const onSubmit = async (data: EventFormData) => {
    if (!id || !event) return;

    setIsLoading(true);
    try {
      await events.updateEvent(parseInt(id), {
        ...event,
        ...data,
      });
      toast.success('Événement mis à jour avec succès');
      navigate('/account');
    } catch (error) {
      console.error('Erreur lors de la mise à jour de l\'événement:', error);
      toast.error('Erreur lors de la mise à jour de l\'événement');
    } finally {
      setIsLoading(false);
    }
  };

  if (!event) {
    return (
      <div className="flex justify-center items-center min-h-screen">
        <div className="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-primary"></div>
      </div>
    );
  }

  return (
    <div className="container mx-auto px-4 py-8">
      <Card className="max-w-2xl mx-auto p-6">
        <h1 className="text-2xl font-bold mb-6">Modifier l'événement</h1>
        
        <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
          <div>
            <label className="block text-sm font-medium mb-1">
              Titre
            </label>
            <Input
              {...register('title')}
              placeholder="Titre de l'événement"
              className={errors.title ? 'border-red-500' : ''}
            />
            {errors.title && (
              <p className="text-red-500 text-sm mt-1">{errors.title.message}</p>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium mb-1">
              Description
            </label>
            <Textarea
              {...register('description')}
              placeholder="Description de l'événement"
              className={errors.description ? 'border-red-500' : ''}
            />
            {errors.description && (
              <p className="text-red-500 text-sm mt-1">{errors.description.message}</p>
            )}
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium mb-1">
                Date
              </label>
              <Input
                type="date"
                {...register('date')}
                className={errors.date ? 'border-red-500' : ''}
              />
              {errors.date && (
                <p className="text-red-500 text-sm mt-1">{errors.date.message}</p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium mb-1">
                Heure de début
              </label>
              <Input
                type="time"
                {...register('startTime')}
                className={errors.startTime ? 'border-red-500' : ''}
              />
              {errors.startTime && (
                <p className="text-red-500 text-sm mt-1">{errors.startTime.message}</p>
              )}
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium mb-1">
              Heure de fin (optionnel)
            </label>
            <Input
              type="time"
              {...register('endTime')}
              className={errors.endTime ? 'border-red-500' : ''}
            />
            {errors.endTime && (
              <p className="text-red-500 text-sm mt-1">{errors.endTime.message}</p>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium mb-1">
              Adresse
            </label>
            <Input
              {...register('address')}
              placeholder="Adresse de l'événement"
              className={errors.address ? 'border-red-500' : ''}
            />
            {errors.address && (
              <p className="text-red-500 text-sm mt-1">{errors.address.message}</p>
            )}
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium mb-1">
                Nombre maximum de participants
              </label>
              <Input
                type="number"
                {...register('maxParticipants', { valueAsNumber: true })}
                className={errors.maxParticipants ? 'border-red-500' : ''}
              />
              {errors.maxParticipants && (
                <p className="text-red-500 text-sm mt-1">{errors.maxParticipants.message}</p>
              )}
            </div>

            <div>
              <label className="block text-sm font-medium mb-1">
                Prix
              </label>
              <Input
                type="number"
                step="0.01"
                {...register('price', { valueAsNumber: true })}
                className={errors.price ? 'border-red-500' : ''}
              />
              {errors.price && (
                <p className="text-red-500 text-sm mt-1">{errors.price.message}</p>
              )}
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium mb-1">
              Catégorie
            </label>
            <Input
              {...register('category')}
              placeholder="Catégorie de l'événement"
              className={errors.category ? 'border-red-500' : ''}
            />
            {errors.category && (
              <p className="text-red-500 text-sm mt-1">{errors.category.message}</p>
            )}
          </div>

          <div className="flex justify-end space-x-4">
            <Button
              type="button"
              variant="outline"
              onClick={() => navigate('/account')}
            >
              Annuler
            </Button>
            <Button
              type="submit"
              disabled={isLoading}
            >
              {isLoading ? 'Mise à jour...' : 'Mettre à jour'}
            </Button>
          </div>
        </form>
      </Card>
    </div>
  );
}
