import { api } from './api';
export type Memory = { id: string; title: string; content: string; category: string; createdAt: string; updatedAt: string; favorite?: boolean; deleted?: boolean };
export type MemoryAction = 'ask' | 'update' | 'delete';
export type ActionResult = { action: MemoryAction; ok: boolean; reply: string; memoryId: string | null };

export const memoryService = {
  async list(): Promise<Memory[]> { return api<Memory[]>('/v1/memories'); },
  async get(id: string) { return (await api<Memory[]>('/v1/memories')).find((memory) => memory.id === id); },
  async save(content: string): Promise<Memory> { return api<Memory>('/v1/memories', { method: 'POST', body: JSON.stringify({ content }) }); },
  async act(action: MemoryAction, text: string): Promise<ActionResult> { return api<ActionResult>('/v1/actions', { method: 'POST', body: JSON.stringify({ action, text }) }); },
  async update(id: string, content: string): Promise<Memory> { return api<Memory>(`/v1/memories/${id}`, { method: 'PUT', body: JSON.stringify({ content }) }); },
  async trash(id: string): Promise<{ deleted: boolean }> { return api(`/v1/memories/${id}`, { method: 'DELETE' }); },
  async restore(id: string): Promise<{ restored: boolean }> { return api(`/v1/memories/${id}/restore`, { method: 'POST', body: '{}' }); },
  async trashList(): Promise<Memory[]> { return api<Memory[]>('/v1/memories/trash'); }
};
