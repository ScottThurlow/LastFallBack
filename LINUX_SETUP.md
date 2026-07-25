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
- json (for JSON handling)
- Standard file functions

## Security Notes
- The `submissions/.htaccess` file prevents direct web access to CSV logs
- Rate limiting is handled via temp files (works on Linux automatically)  
- CORS headers are properly configured for your domain

## Testing
After deployment, test the form submission to ensure:
1. CSV records are written to `~/lastfallback_data/lastfallback_org_signers.csv`
2. Rate limiting works properly

Email notifications are disabled — submissions are recorded to the CSV data store only.

## Domain Configuration
Update your domain to point to the new Linux hosting if not already done.