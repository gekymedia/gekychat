# Email Configuration for GekyChat

## .env Configuration

Add the following to your `.env` file:

```env
# Mail Configuration (SMTP for sending)
MAIL_MAILER=smtp
MAIL_HOST=mail.gekychat.com
MAIL_PORT=465
MAIL_USERNAME=mail@gekychat.com
MAIL_PASSWORD="8S7}ZN;q)k|^C7Qu"
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=mail@gekychat.com
MAIL_FROM_NAME="GekyChat"

# IMAP Configuration (for fetching emails)
MAIL_IMAP_HOST=mail.gekychat.com
MAIL_IMAP_PORT=993
MAIL_IMAP_ENCRYPTION=ssl
MAIL_IMAP_USERNAME=mail@gekychat.com
MAIL_IMAP_PASSWORD="8S7}ZN;q)k|^C7Qu"
MAIL_IMAP_FOLDER=INBOX
MAIL_IMAP_VALIDATE_CERT=true

# Email Chat Settings
MAIL_DOMAIN=gekychat.com
MAIL_PREFIX=mail+
```

## Server Details

- **Webmail**: http://webmail.gekychat.com
- **Hostname**: mail.gekychat.com
- **SMTP (Sending)**: SSL/TLS on Port 465 (recommended)
- **IMAP (Receiving)**: SSL/TLS on Port 993 (recommended)

## How It Works

1. **Incoming Emails**: Emails sent to `mail+{username}@gekychat.com` are routed to the user with that username
2. **Outgoing Emails**: Replies are sent from `mail+{username}@gekychat.com` 
3. **IMAP Fetching**: A scheduled job can fetch emails from the IMAP server
4. **Webhook Support**: Emails can also be received via webhook at `/webhook/email/incoming`
