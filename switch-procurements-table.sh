#!/bin/bash

# Shadcn Data-Table Switcher Script
# Usage: ./switch-procurements-table.sh [shadcn|original]

set -e

PAGES_DIR="resources/js/Pages/Procurements"

case "$1" in
  shadcn)
    echo "🔄 Switching to Shadcn Data-Table..."
    cd "$PAGES_DIR"

    if [ ! -f "Index-Shadcn-DataTable.tsx" ]; then
      echo "❌ Error: Index-Shadcn-DataTable.tsx not found!"
      exit 1
    fi

    # Backup current if it's not already backed up
    if [ -f "Index.tsx" ] && [ ! -f "Index-Current.tsx" ]; then
      mv Index.tsx Index-Current.tsx
      echo "✅ Backed up current Index.tsx to Index-Current.tsx"
    fi

    # Activate shadcn version
    cp Index-Shadcn-DataTable.tsx Index.tsx
    echo "✅ Activated Shadcn Data-Table"
    echo ""
    echo "Now run: npm run build (or npm run dev)"
    ;;

  original)
    echo "🔄 Switching to Original Table..."
    cd "$PAGES_DIR"

    if [ ! -f "Index-Original-Backup.tsx" ]; then
      echo "❌ Error: Index-Original-Backup.tsx not found!"
      exit 1
    fi

    # Restore original
    cp Index-Original-Backup.tsx Index.tsx
    echo "✅ Restored Original Table"
    echo ""
    echo "Now run: npm run build (or npm run dev)"
    ;;

  *)
    echo "Usage: $0 {shadcn|original}"
    echo ""
    echo "  shadcn   - Switch to Shadcn Data-Table"
    echo "  original - Switch back to Original Table"
    echo ""
    exit 1
    ;;
esac

echo ""
echo "📁 Current files in $PAGES_DIR:"
ls -lh "$PAGES_DIR"/Index*.tsx 2>/dev/null || echo "No Index files found"
