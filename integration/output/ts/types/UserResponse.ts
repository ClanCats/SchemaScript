import type { User } from './User';

export interface UserResponse {
  error?: string;
  data: User;
}
