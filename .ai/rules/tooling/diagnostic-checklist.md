# Checklist de diagnóstico — antes de decir "no se ve" o "está roto"

> Regla load-bearing para diagnosticar el dev stack. Motivada por falsos
> positivos costosos: un 404 por curl sin header `Host`, "la web no
> muestra los cambios" cuando era caché de Astro, y previews sirviendo el
> checkout equivocado por un bind mount.

## Orden de diagnóstico (cortar apenas encuentre la causa)

1. **¿El stack está arriba?** `docker ps` — servicios `*-dev-{postgres,
   redis, backend, frontend, nginx}`. Ojo: el frontend puede figurar
   `unhealthy` y aun así servir (el healthcheck falla, el dev server no).
2. **¿El curl lleva el `Host` correcto?** Caddy enruta por vhost;
   `panda-estampados.com` y `kitsune.com` apuntan a `127.0.0.1`
   (`/etc/hosts`). Siempre:
   `curl -s -H 'Host: panda-estampados.com' http://127.0.0.1/es/...`
   Sin `Host` (o con `127.0.0.1`) devuelve **404 engañoso** aunque el
   sitio esté sano.
3. **¿Estoy mirando el contenido nuevo?** Grepear el HTML servido por un
   marcador del cambio (texto i18n nuevo, clase, href). Si el HTML es el
   viejo tras un merge: caché de Astro/Vite — fix documentado en
   `tooling/docker-stack.md` § caché (recrear `frontend` con los
   volúmenes `*_astro_cache`/`*_vite_cache` borrados).
4. **¿El container sirve MI código?** El stack sirve el checkout desde el
   que se hizo el último `up` (puede ser un worktree):
   `docker inspect panda-estampados-and-kitsune-dev-frontend --format
   '{{range .Mounts}}{{.Source}} {{end}}'`. Síntoma de desfase: el DOM
   muestra componentes viejos o archivos borrados que siguen en
   `/app/src`.
5. **¿Es caché del navegador?** Hard reload (Ctrl+Shift+R) antes de
   escalar el diagnóstico. Las capas son: HTML SSR → JS bundle → caché
   del navegador — verificar en ese orden.
6. **¿Directorios nuevos?** Los bind mounts NO propagan directorios
   creados en el host (un archivo dentro de un dir existente sí): si una
   ruta nueva responde 404 pero el archivo existe, recrear el servicio
   (`down` + `up --no-build`), no alcanza `restart`.

## Al revés: cuando el diagnóstico sea del código

- Si el HTML servido SÍ contiene el cambio y el problema es de
  comportamiento/hidratación: recién ahí ir al código (consola del
  browser, tests, `browser-logs` de Boost) — no antes de pasar 1–6.
