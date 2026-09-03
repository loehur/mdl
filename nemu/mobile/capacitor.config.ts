import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.mdl.nemu',
  appName: 'Nemu',
  webDir: '../web/dist',
  server: {
    androidScheme: 'https'
  }
};

export default config;
