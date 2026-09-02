import { buildApp } from './app.js';
import { loadConfig } from './config/env.js';

const config = loadConfig();
const app = buildApp(config);
await app.listen({ host: config.HOST, port: config.PORT });
