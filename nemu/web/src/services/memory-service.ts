import { api } from './api';
export type Memory = { id: string; title: string; content: string; category: string; createdAt: string; updatedAt: string; favorite?: boolean; deleted?: boolean };
export type MemoryAction = 'ask' | 'update' | 'delete';
export type ActionResult = { action: MemoryAction; ok: boolean; reply: string; memoryId: string | null; copyText?: string };

export const memoryService = {
  async list(query?: string, limit = 20): Promise<Memory[]> { const params = new URLSearchParams({ limit: String(limit) }); if (query) params.set('query', query); return api<Memory[]>(`/v1/memories?${params}`); },
  async count(): Promise<number> { return (await api<{ total: number }>('/v1/memories/count')).total; },
  async get(id: string) { return (await api<Memory[]>('/v1/memories')).find((memory) => memory.id === id); },
  async save(content: string): Promise<Memory> { return api<Memory>('/v1/memories', { method: 'POST', body: JSON.stringify({ content }) }); },
  async act(action: MemoryAction, text: string): Promise<ActionResult> { return api<ActionResult>('/v1/actions', { method: 'POST', body: JSON.stringify({ action, text }) }); },
  async update(id: string, content: string): Promise<Memory> { return api<Memory>(`/v1/memories/${id}`, { method: 'PUT', body: JSON.stringify({ content }) }); },
  async trash(id: string): Promise<{ deleted: boolean }> { return api(`/v1/memories/${id}`, { method: 'DELETE' }); },
  async restore(id: string): Promise<{ restored: boolean }> { return api(`/v1/memories/${id}/restore`, { method: 'POST', body: '{}' }); },
  async trashList(): Promise<Memory[]> { return api<Memory[]>('/v1/memories/trash'); },
  async emptyTrash(): Promise<{ deleted: number }> { return api<{ deleted: number }>('/v1/memories/trash', { method: 'DELETE' }); }
};
