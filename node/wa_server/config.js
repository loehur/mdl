/**
 * Configuration file untuk Webhook URL
 * Automatically switches between development and production based on NODE_ENV
 */

require('dotenv').config();

const WEBHOOK_URLS = {
    development: "http://localhost/mdl/api/Webhook/WA_Local/update",
    production: "https://ml.nalju.com/WH_Local/update"
};

// Set default to development if NODE_ENV not specified
const env = process.env.NODE_ENV || 'development';

// Export webhook URL based on environment
const webhookUrl = WEBHOOK_URLS[env] || WEBHOOK_URLS.development;

console.log(`[Config] Environment: ${env}`);
console.log(`[Config] Webhook URL: ${webhookUrl}`);

module.exports = webhookUrl;
