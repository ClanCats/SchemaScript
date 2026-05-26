import type { User } from './User';

export interface UserCollectionResponse {
  total_count: number;
  filtered_count: number;
  error?: string;
  data: User[];
}
