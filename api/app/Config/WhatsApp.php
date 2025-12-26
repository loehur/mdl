<?php

namespace App\Config;

class WhatsApp
{
    /**
     * yCloud API Credentials
     */
    public static function getConfig()
    {
        return [
            'api_key' => \Env::WA_API_KEY ?? '',
            'base_url' => 'https://api.ycloud.com/v2',
            'whatsapp_number' => \Env::WA_PHONE_NUMBER ?? '+6281170706611', // Format: +62xxx
            'verify_token' => \Env::WA_VERIFY_TOKEN ?? '', // For webhook verification
            
            // Customer Service Window (CSW) duration in hours
            'csw_duration' => 23,
            
            // Logging
            'log_messages' => true,
            'log_path' => __DIR__ . '/../../logs/whatsapp',
        ];
    }
    
    /**
     * Get yCloud API Key
     */
    public static function getApiKey()
    {
        return self::getConfig()['api_key'];
    }
    
    /**
     * Get yCloud Base URL
     */
    public static function getBaseUrl()
    {
        return self::getConfig()['base_url'];
    }
    
    /**
     * Get WhatsApp Business Number
     */
    public static function getWhatsAppNumber()
    {
        return self::getConfig()['whatsapp_number'];
    }
    
    /**
     * Get CSW Duration (in hours)
     */
    public static function getCswDuration()
    {
        return self::getConfig()['csw_duration'];
    }
}
