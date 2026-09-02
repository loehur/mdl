import { api } from './api';
export type Plan = 'free' | 'personal' | 'pro';
export type PlanInfo = { plan: Plan; limit: number; used: number };
export const planService = { get: () => api<PlanInfo>('/v1/plan'), set: (plan: Plan) => api<PlanInfo>('/v1/plan', { method: 'PUT', body: JSON.stringify({ plan }) }) };
