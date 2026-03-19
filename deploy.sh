#!/bin/bash
# Deploy public_html to the server.
# Run once: chmod +x deploy.sh
# Then just: ./deploy.sh

USER="ge32puf"
HOST="lehre.bpm.in.tum.de"
REMOTE_DIR="~/public_html/"
LOCAL_DIR="./public_html/"

rsync -avz --progress "$LOCAL_DIR" "$USER@$HOST:$REMOTE_DIR"
