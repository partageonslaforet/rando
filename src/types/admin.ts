export interface User {
  id: number;
  email: string;
  name: string;
  role: 'admin' | 'user';
  isVerified: boolean;
  createdAt: string;
  lastLogin?: string;
  phone?: string;
  address?: string;
  organization?: string;
}

export interface Category {
  id: number;
  name: string;
  slug: string;
  description?: string;
  icon: string;
  color: string;
  eventsCount: number;
}

export interface EventValidation {
  id: number;
  eventId: number;
  status: 'pending' | 'approved' | 'rejected';
  adminNotes?: string;
  createdAt: string;
  updatedAt: string;
  reviewedBy?: number;
}