#!/usr/bin/env bash
# scripts/docker/inspection/ps.sh
# cmd_ps
# Lista rápida de TODOS los containers de las 6 instancias, filtrados
# por label `com.docker.compose.project`. Vista de una sola tabla.
# No recibe args posicionales (el dispatcher no le pasa stage/store).
cmd_ps() {
    log_cmd "/usr/bin/docker ps --filter label=com.docker.compose.project"
    docker_ps_project
}
