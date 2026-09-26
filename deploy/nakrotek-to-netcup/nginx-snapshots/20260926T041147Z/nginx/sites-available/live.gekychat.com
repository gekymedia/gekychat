# LiveKit WSS terminator
server {
    server_name live.gekychat.com;

    location / {
        proxy_pass         http://127.0.0.1:7880;
        proxy_http_version 1.1;
        proxy_set_header Upgrade    $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host              $host;
        proxy_set_header X-Real-IP         $remote_addr;
        proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout  3600s;
        proxy_send_timeout  3600s;
        proxy_buffering    off;
        proxy_cache        off;
    }

    listen [::]:443 ssl; # managed by Certbot
    listen 443 ssl; # managed by Certbot
    ssl_certificate /etc/letsencrypt/live/live.gekychat.com/fullchain.pem; # managed by Certbot
    ssl_certificate_key /etc/letsencrypt/live/live.gekychat.com/privkey.pem; # managed by Certbot
    include /etc/letsencrypt/options-ssl-nginx.conf; # managed by Certbot
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem; # managed by Certbot

}

server {
    if ($host = live.gekychat.com) {
        return 301 https://$host$request_uri;
    } # managed by Certbot


    listen 80;
    listen [::]:80;
    server_name live.gekychat.com;
    return 404; # managed by Certbot


}