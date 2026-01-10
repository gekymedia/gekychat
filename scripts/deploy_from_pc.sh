#!/bin/bash

# GekyChat Deployment Script for Local PC (Linux/Mac)
# This script connects to the server and runs the deployment

# SSH Server Details
SERVER="root@gekymedia.com"
PROJECT_PATH="/home/gekymedia/web/chat.gekychat.com/public_html"

echo "🚀 Starting GekyChat deployment from PC..."
echo "📡 Connecting to server: $SERVER"

# SSH and run deployment script directly
echo ""
echo "📦 Running deployment on server..."
ssh $SERVER "cd $PROJECT_PATH && git pull origin main && bash scripts/deploy.sh"

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Deployment completed successfully!"
else
    echo ""
    echo "❌ Deployment failed. Check the error messages above."
    exit 1
fi

echo ""
echo "📋 Post-deployment checklist:"
echo "  - [ ] Verify API routes are accessible: /api/v1/auth/phone"
echo "  - [ ] Check admin panel: /admin/upload-settings"
echo "  - [ ] Test video upload limits"
echo "  - [ ] Verify database migrations ran successfully"
