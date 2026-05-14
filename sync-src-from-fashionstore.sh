#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_DIR="$ROOT_DIR/Fashionstore/src/"
TARGET_DIR="$ROOT_DIR/src/"
BACKUP_DIR="$ROOT_DIR/local-backup"

usage() {
    echo "Usage: $0 --dry-run | --apply"
}

if [[ $# -ne 1 ]]; then
    usage
    exit 1
fi

MODE="$1"
RSYNC_ARGS=(
    -avh
    --delete
    --exclude=app/etc/env.php
    --exclude=auth.json
    --exclude=var/
    --exclude=generated/
    --exclude=pub/static/
    --exclude=pub/media/
    --exclude=vendor/
)

case "$MODE" in
    --dry-run)
        RSYNC_ARGS+=(-n)
        ;;
    --apply)
        ;;
    *)
        usage
        exit 1
        ;;
esac

if [[ ! -d "$SOURCE_DIR" ]]; then
    echo "Missing source directory: $SOURCE_DIR" >&2
    exit 1
fi

if [[ ! -d "$TARGET_DIR" ]]; then
    echo "Missing target directory: $TARGET_DIR" >&2
    exit 1
fi

if [[ "$MODE" == "--apply" ]]; then
    mkdir -p "$BACKUP_DIR"

    if [[ -f "$TARGET_DIR/app/etc/env.php" ]]; then
        cp "$TARGET_DIR/app/etc/env.php" "$BACKUP_DIR/env.php.bak"
    fi

    if [[ -f "$TARGET_DIR/auth.json" ]]; then
        cp "$TARGET_DIR/auth.json" "$BACKUP_DIR/auth.json.bak"
    fi
fi

rsync "${RSYNC_ARGS[@]}" "$SOURCE_DIR" "$TARGET_DIR"

if [[ "$MODE" == "--dry-run" ]]; then
    echo
    echo "Preview complete. Re-run with --apply to sync src/ from Fashionstore/src/."
else
    echo
    echo "Sync complete. Local config files were preserved and backed up in local-backup/."
fi