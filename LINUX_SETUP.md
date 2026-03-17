# Linux Hosting Setup Instructions

## Directory Structure
Your site should be deployed to: `~/public_html/lastfallback.org/`

## File Permissions 
After uploading, set these permissions:

```bash
# Make PHP files executable
chmod 644 *.php *.html

# Ensure submissions directory is writable by web server
chmod 755 submissions/
chmod 644 submissions/.htaccess

# Make sure main directory allows execution
chmod 755 .
```

## Required PHP Extensions
Your hosting should have these PHP extensions enabled:
- curl (for Brevo email API)
- json (for JSON handling)
- Standard file functions

## Security Notes
- The `submissions/.htaccess` file prevents direct web access to CSV logs
- Rate limiting is handled via temp files (works on Linux automatically)  
- CORS headers are properly configured for your domain

## Testing
After deployment, test the form submission to ensure:
1. CSV files are created in `/submissions/` directory
2. Emails are sent via Brevo API
3. Rate limiting works properly

## Domain Configuration
Update your domain to point to the new Linux hosting if not already done.

## Brevo API
The Brevo API key is already configured and should work on Linux hosting.