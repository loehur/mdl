import type { AppConfig } from '../../config/env.js';
import { DeepSeekChatProvider, FallbackChatProvider, OpenAiChatProvider, type ChatProvider } from './chat-provider.js';

export function createChatProvider(config: AppConfig, onFallback?: () => void): ChatProvider {
  const fallback = new OpenAiChatProvider(config.OPENAI_API_KEY, config.OPENAI_MODEL);
  if (!config.DEEPSEEK_API_KEY) return fallback;
  return new FallbackChatProvider(
    new DeepSeekChatProvider(config.DEEPSEEK_API_KEY, config.DEEPSEEK_MODEL, config.DEEPSEEK_BASE_URL),
    fallback,
    onFallback
  );
}
