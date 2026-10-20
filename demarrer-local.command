#!/bin/zsh
set -eu
cd "${0:A:h}"
export PATH="$HOME/.config/herd-lite/bin:$HOME/.local/share/homya/node/bin:$PATH"
export PHP_INI_SCAN_DIR="$HOME/.config/herd-lite/bin"
echo "HOMYA : http://127.0.0.1:8000/fr"
echo "Garder cette fenêtre ouverte. Ctrl+C pour arrêter."
exec php artisan serve --host=127.0.0.1 --port=8000
