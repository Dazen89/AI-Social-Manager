#!/usr/bin/env bash

set -Eeuo pipefail

deploy() {
    local git_checkout=0
    local project_root
    local maintenance_enabled=0

    project_root="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
    cd "$project_root"

    for required_command in php composer npm; do
        if ! command -v "$required_command" >/dev/null 2>&1; then
            printf 'Comando richiesto non disponibile: %s\n' "$required_command" >&2
            return 1
        fi
    done

    if command -v git >/dev/null 2>&1 && git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
        git_checkout=1

        if [[ -n "$(git status --porcelain)" ]]; then
            printf 'Deploy interrotto: la working tree contiene modifiche locali.\n' >&2
            printf 'Salvale o rimuovile esplicitamente prima di aggiornare la VPS.\n' >&2
            return 1
        fi

        if ! git rev-parse --abbrev-ref --symbolic-full-name '@{upstream}' >/dev/null 2>&1; then
            printf 'Deploy interrotto: il branch corrente non ha un upstream configurato.\n' >&2
            return 1
        fi
    else
        printf 'Checkout Git non presente: considero i file già distribuiti dal pannello hosting.\n'
    fi

    restore_application() {
        local exit_code=$?

        trap - EXIT INT TERM

        if [[ "$maintenance_enabled" -eq 1 ]]; then
            php artisan up >/dev/null 2>&1 || true
        fi

        if [[ "$exit_code" -ne 0 ]]; then
            printf 'Deploy non completato. Controlla il comando fallito prima di riprovare.\n' >&2
        fi

        exit "$exit_code"
    }

    trap restore_application EXIT
    trap 'exit 130' INT
    trap 'exit 143' TERM

    printf 'Attivo la modalità manutenzione...\n'
    maintenance_enabled=1
    php artisan down --retry=60

    if [[ "$git_checkout" -eq 1 ]]; then
        printf 'Aggiorno il repository...\n'
        git pull --ff-only
    fi

    printf 'Installo le dipendenze PHP di produzione...\n'
    composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

    printf 'Applico le migrazioni...\n'
    php artisan migrate --force

    printf 'Compilo le risorse frontend...\n'
    npm ci --no-audit --no-fund
    npm run build

    printf 'Rigenero le cache Laravel...\n'
    php artisan optimize:clear
    php artisan view:cache

    php artisan up
    maintenance_enabled=0
    trap - EXIT INT TERM

    if [[ "$git_checkout" -eq 1 ]]; then
        printf 'Deploy completato alla revisione %s.\n' "$(git rev-parse --short HEAD)"
    else
        printf 'Deploy completato sui file distribuiti dal pannello hosting.\n'
    fi
}

deploy "$@"
