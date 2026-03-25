#!/bin/bash

# Backup .cursorrules to multiple locations
# Run this regularly to ensure development guidelines are preserved

BACKUP_DIR="$HOME/dev-config-backups/$(basename $(pwd))"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Backup .cursorrules with timestamp
if [ -f ".cursorrules" ]; then
    cp .cursorrules "$BACKUP_DIR/.cursorrules_$TIMESTAMP"
    cp .cursorrules "$BACKUP_DIR/.cursorrules_latest"
    echo "✅ Backed up .cursorrules to $BACKUP_DIR"
    
    # Keep only last 10 backups
    ls -t "$BACKUP_DIR"/.cursorrules_* | tail -n +11 | xargs -r rm
    echo "📁 Cleaned old backups (keeping 10 most recent)"
else
    echo "❌ .cursorrules not found"
    exit 1
fi

# Optional: Upload to private cloud storage
# Uncomment and configure for your preferred service:
# rclone copy .cursorrules private-cloud:dev-configs/faker-local/
# aws s3 cp .cursorrules s3://your-private-bucket/dev-configs/faker-local/
# scp .cursorrules user@your-server:/backup/dev-configs/faker-local/

echo "🔒 Backup complete"
