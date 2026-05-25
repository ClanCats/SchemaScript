import type { MessageType } from './_types';

export interface Message {
  id: bigint;
  type: MessageType;
  text: string | null;
  actor: User | null;
  unseen?: boolean;
  created_at: number;
  modified_at: number;
}
