import { format, parseISO } from 'date-fns';
import { fr } from 'date-fns/locale';

export function formatDate(dateString: string): string {
  const date = parseISO(dateString);
  return format(date, 'EEEE d MMMM yyyy', { locale: fr });
}

export function formatTime(timeString: string): string {
  return timeString.substring(0, 5); // Retourne HH:mm
}
