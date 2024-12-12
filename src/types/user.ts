export interface User {
  id: number;
  name: string;
  email: string;
  role?: 'admin' | 'user';
  phone?: string;
  address?: string;
  organization?: {
    name: string;
    logo?: string;
    website?: string;
    address?: string;
  };
}

export interface ApiResponse<T> {
  status: 'success' | 'error';
  data: T;
  message?: string;
}

export interface LoginResponseData {
  user: User;
  token: string;
}

export type LoginResponse = ApiResponse<LoginResponseData>;

export interface LoginCredentials {
  email: string;
  password: string;
  device_name?: string;
}

export interface RegisterData extends LoginCredentials {
  name?: string;
}

export interface ResetPasswordData {
  token: string;
  password: string;
  confirmPassword: string;
}

export interface VerifyEmailData {
  token: string;
}

export interface UpdateProfileData {
  name?: string;
  currentPassword?: string;
  newPassword?: string;
  confirmNewPassword?: string;
}
