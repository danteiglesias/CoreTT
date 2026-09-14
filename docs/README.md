# CoreTT - API REST (uso desde el frontend o herramientas)

La API vive en `/backend/api/`. Responde JSON en UTF-8. Metodos que modifican datos requieren token.

## Autenticacion

1. `POST /backend/api/users.php?action=login` con `{ "email": "...", "password": "..." }`.
2. Respuesta: `{ "success": true, "token": "....", "user": {...} }`.
3. Enviar en cada peticion protegida: `Authorization: Bearer <token>`.

Ejemplo con `curl`:

```bash
# Login
TOKEN=$(curl -s -X POST http://localhost/backend/api/users.php?action=login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@corett.com","password":"admin1234"}' | \
  php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["token"] ?? "";')

# Listar guias
curl -s "http://localhost/backend/api/guides.php?page=1&limit=10"

# Crear una guia
curl -s -X POST http://localhost/backend/api/guides.php \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"title":"Mi guia","game":"lol","description":"...","content":"..."}'

# Votar
curl -s -X POST "http://localhost/backend/api/guides.php?action=vote" \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"id":1,"vote":1}'
```

## Respuestas de error

```json
{ "error": "mensaje" }
{ "error": "Datos invalidos", "errors": { "campo": "detalle" } }
```

Codigos: `400` peticion invalida, `401` sin autenticar, `403` sin permisos, `404` no encontrado, `422` datos invalidos, `429` rate limited, `405` metodo no permitido, `500` error interno.

## Votacion

Posterior a la creacion el primer voto suma (`vote: 1`) o resta (`vote: -1`). Volver a enviar el mismo valor anula la votacion (toggle); cambiar de signo actualiza el valor. La respuesta devuelve `votes` (total) y `myVote` (`1`, `-1` o `0`).

## Modo demo del frontend

El frontend funciona por defecto contra la API real. Para usarlo con datos de ejemplo sin servidor, agrega `?demo=1` a la URL. Las operaciones de escritura (registro, votos, crear guias) solo persisten en memoria mientras dura la pagina.