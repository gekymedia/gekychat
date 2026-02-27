#!/usr/bin/env python3
import re

# Read the file
with open('/etc/nginx/conf.d/domains/chat.gekychat.com.ssl.conf', 'r') as f:
    content = f.read()

# Reverb locations to add
reverb_block = '''
	location /app {
		proxy_pass http://127.0.0.1:8080;
		proxy_http_version 1.1;
		proxy_set_header Host $host;
		proxy_set_header X-Real-IP $remote_addr;
		proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
		proxy_set_header X-Forwarded-Proto $scheme;
		proxy_set_header Upgrade $http_upgrade;
		proxy_set_header Connection "upgrade";
		proxy_read_timeout 86400;
		proxy_send_timeout 86400;
	}

	location /apps {
		proxy_pass http://127.0.0.1:8080;
		proxy_http_version 1.1;
		proxy_set_header Host $host;
		proxy_set_header X-Real-IP $remote_addr;
		proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
		proxy_set_header X-Forwarded-Proto $scheme;
		proxy_set_header Upgrade $http_upgrade;
		proxy_set_header Connection "upgrade";
		proxy_read_timeout 86400;
		proxy_send_timeout 86400;
	}

'''

# Check if already added
if 'location /app {' not in content:
    # Insert before location ~ /\.
    content = content.replace('location ~ /\\.', reverb_block + 'location ~ /\\.')

# Remove proxy_hide_header Upgrade
content = re.sub(r'\s*proxy_hide_header Upgrade;\s*', '\n', content)

# Write back
with open('/etc/nginx/conf.d/domains/chat.gekychat.com.ssl.conf', 'w') as f:
    f.write(content)

print('Done')
