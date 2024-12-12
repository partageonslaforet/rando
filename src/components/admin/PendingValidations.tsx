import { useQuery } from '@tanstack/react-query';
import { Check, X } from 'lucide-react';
import type { EventValidation } from '../../types/admin';

export function PendingValidations() {
  const { data: validations = [] } = useQuery({
    queryKey: ['admin', 'pending-validations'],
    queryFn: async () => {
      // Fetch pending validations from API
      return [] as EventValidation[];
    },
  });

  const handleApprove = async (validationId: number) => {
    // Implement approval logic
    console.log('Approving validation:', validationId);
  };

  const handleReject = async (validationId: number) => {
    // Implement rejection logic
    console.log('Rejecting validation:', validationId);
  };

  return (
    <div className="space-y-4">
      {validations.map((validation) => (
        <div 
          key={validation.id}
          className="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg"
        >
          <div>
            <h3 className="font-medium dark:text-white">
              Event #{validation.eventId}
            </h3>
            <p className="text-sm text-gray-600 dark:text-gray-300">
              En attente depuis le {new Date(validation.createdAt).toLocaleDateString()}
            </p>
          </div>
          <div className="flex gap-2">
            <button
              onClick={() => handleApprove(validation.id)}
              className="p-2 text-green-600 hover:bg-green-50 rounded-full"
              aria-label="Approuver l'événement"
              title="Approuver l'événement"
            >
              <Check size={18} />
            </button>
            <button
              onClick={() => handleReject(validation.id)}
              className="p-2 text-red-600 hover:bg-red-50 rounded-full"
              aria-label="Rejeter l'événement"
              title="Rejeter l'événement"
            >
              <X size={18} />
            </button>
          </div>
        </div>
      ))}
    </div>
  );
}