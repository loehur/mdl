// Twemoji utility for consistent emoji rendering
import twemoji from 'twemoji';

/**
 * Parse text and replace emoji with Twemoji images
 * @param {string} text - Text containing emojis
 * @returns {string} - HTML string with emojis replaced by img tags
 */
export function parseEmoji(text) {
    if (!text) return '';

    return twemoji.parse(text, {
        folder: 'svg',
        ext: '.svg',
        base: 'https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/'
    });
}

/**
 * Convert single emoji to Twemoji image URL
 * @param {string} emoji - Single emoji character
 * @returns {string} - URL to Twemoji SVG
 */
export function getEmojiUrl(emoji) {
    if (!emoji) return '';

    // Get codepoints
    const codePoints = [...emoji]
        .map(char => char.codePointAt(0).toString(16))
        .join('-')
        .replace(/-fe0f/g, ''); // Remove variation selector

    return `https://cdn.jsdelivr.net/gh/twitter/twemoji@14.0.2/assets/svg/${codePoints}.svg`;
}

export default {
    parseEmoji,
    getEmojiUrl
};
