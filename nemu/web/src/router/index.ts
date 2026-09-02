import { createRouter, createWebHistory } from 'vue-router';
import LoginView from '@/views/LoginView.vue';
import HomeView from '@/views/HomeView.vue';
import MemoriesView from '@/views/MemoriesView.vue';
import MemoryDetailView from '@/views/MemoryDetailView.vue';
import EditMemoryView from '@/views/EditMemoryView.vue';
import TrashView from '@/views/TrashView.vue';
import SettingsView from '@/views/SettingsView.vue';
import { session } from '@/services/api';

const router = createRouter({ history: createWebHistory(), routes: [
  { path: '/', component: HomeView }, { path: '/login', component: LoginView }, { path: '/memories', component: MemoriesView },
  { path: '/memories/:id', component: MemoryDetailView }, { path: '/memories/:id/edit', component: EditMemoryView },
  { path: '/trash', component: TrashView }, { path: '/settings', component: SettingsView }
] });
router.beforeEach((to) => { if (to.path !== '/login' && !session.get()) return '/login'; if (to.path === '/login' && session.get()) return '/'; });
export default router;
