require('dotenv').config();
const express = require('express');
const nodemailer = require('nodemailer');
const cors = require('cors');
const bodyParser = require('body-parser');

const app = express();
const PORT = process.env.PORT || 3002;

// Middleware
app.use(cors({
    origin: function (origin, callback) {
        // Allow requests with no origin (like mobile apps or curl requests)
        if (!origin) return callback(null, true);

        // Regex to allow nalju.com and any subdomain (*.nalju.com)
        // Matches http/https and any subdomain depth
        const allowedOriginPattern = /^https?:\/\/(?:[a-zA-Z0-9-]+\.)*nalju\.com$/;

        if (allowedOriginPattern.test(origin)) {
            callback(null, true);
        } else {
            callback(new Error('Not allowed by CORS'));
        }
    }
}));
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));

// Transporter Configuration
// Create a transporter object using the default SMTP transport
const transporter = nodemailer.createTransport({
    host: process.env.SMTP_HOST,
    port: process.env.SMTP_PORT,
    secure: process.env.SMTP_SECURE === 'true', // true for 465, false for other ports
    auth: {
        user: process.env.SMTP_USER,
        pass: process.env.SMTP_PASS,
    },
});

// Verify connection configuration
transporter.verify(function (error, success) {
    if (error) {
        console.log('Error verifying transporter:', error);
    } else {
        console.log('Server is ready to take our messages');
    }
});

// Routes

/**
 * Health Check
 */
app.get('/', (req, res) => {
    res.status(200).json({ status: 'ok', message: 'Email server is running' });
});

/**
 * Send Email Endpoint
 * Expects JSON body: { to, subject, text, html (optional) }
 */
app.post('/send-email', async (req, res) => {
    const { to, subject, text, html } = req.body;

    if (!to || !subject || !text) {
        return res.status(400).json({ error: 'Missing required fields: to, subject, text' });
    }

    const mailOptions = {
        from: process.env.EMAIL_FROM,
        to: to,
        subject: subject,
        text: text,
        html: html || text, // Fallback to text if HTML not provided
    };

    try {
        const info = await transporter.sendMail(mailOptions);
        console.log('Message sent: %s', info.messageId);
        res.status(200).json({
            message: 'Email sent successfully',
            messageId: info.messageId,
            previewUrl: nodemailer.getTestMessageUrl(info)
        });
    } catch (error) {
        console.error('Error sending email:', error);
        res.status(500).json({ error: 'Failed to send email', details: error.message });
    }
});

/**
 * Send Bulk Email Endpoint (Optional utility)
 * Expects JSON body: { recipients: [], subject, text, html (optional) }
 */
app.post('/send-bulk-email', async (req, res) => {
    const { recipients, subject, text, html } = req.body;

    if (!recipients || !Array.isArray(recipients) || recipients.length === 0 || !subject || !text) {
        return res.status(400).json({ error: 'Missing or invalid fields. "recipients" must be a non-empty array.' });
    }

    const results = {
        success: [],
        failed: []
    };

    // Parallel processing with Promise.all might trigger rate limits, so we'll do it sequentially or limited concurrency if needed,
    // but for simplicity here we'll effectively do it in parallel but catch errors individually.

    // Note: For large lists, a queue system (like BullMQ) is recommended.

    const emailPromises = recipients.map(async (recipient) => {
        const mailOptions = {
            from: process.env.EMAIL_FROM,
            to: recipient,
            subject: subject,
            text: text,
            html: html || text,
        };

        try {
            const info = await transporter.sendMail(mailOptions);
            results.success.push({ email: recipient, messageId: info.messageId });
        } catch (error) {
            results.failed.push({ email: recipient, error: error.message });
        }
    });

    await Promise.all(emailPromises);

    res.status(200).json({
        message: 'Bulk email processing complete',
        results: results
    });
});

// Start Server
app.listen(PORT, () => {
    console.log(`Email server running on port ${PORT}`);
    console.log(`Health check available at http://localhost:${PORT}/`);
});
