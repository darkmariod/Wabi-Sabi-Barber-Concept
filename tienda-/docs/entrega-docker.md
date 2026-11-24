# NatuFitness — Entrega en Docker

La tienda completa en dos imágenes de Docker:

| Imagen | Qué tiene |
|---|---|
| `natufitness-web:1.0` | PrestaShop con el tema, los módulos (Mi Tienda, DeUna, alertas), las fotos de productos y la configuración |
| `natufitness-db:1.0` | La base de datos: productos, catálogos, páginas, cuentas de banco, cuentas de acceso |

Archivos de la entrega:

- `natufitness-images-1.0.tar.gz`: las dos imágenes.
- `docker-compose.yml`: cómo se levantan.
- `.env.example`: la plantilla de configuración.
- `Caddyfile`: la configuración de HTTPS automático.
- `LEEME.md`: esta guía.

> Es privada. Las imágenes llevan las claves internas de la tienda. No subirlas a Docker Hub público ni a GitHub.

## Qué se necesita

- Un servidor Linux (Ubuntu 22.04 o 24.04) con 2 GB de RAM o más, por ejemplo Hetzner o DigitalOcean, por unos 5–7 USD al mes.
- Docker instalado (`curl -fsSL https://get.docker.com | sh`).
- El dominio, apuntando al servidor.

## Instalar

1. Copiar la carpeta de la entrega al servidor, por ejemplo a `/opt/natufitness`.
2. Cargar las imágenes:

   ```bash
   gunzip -c natufitness-images-1.0.tar.gz | docker load
   ```

3. Crear la configuración:

   ```bash
   cp .env.example .env && nano .env
   ```

   - `PS_DOMAIN`: el dominio, sin `https://`.
   - `DB_PASSWD` y `MYSQL_ROOT_PASSWORD`: contraseñas largas y nuevas.

4. Levantar la tienda.

   Con HTTPS propio (Caddy saca el certificado solo):

   ```bash
   docker compose --profile https up -d
   ```

   Ese comando necesita `PS_BIND=127.0.0.1` y los puertos 80 y 443 libres.

   Si ya hay otro proxy o un túnel de Cloudflare delante, usar:

   ```bash
   docker compose up -d
   ```

   En ese caso el proxy apunta a `http://SERVIDOR:PS_PORT`.

5. Esperar entre 2 y 5 minutos. El primer arranque carga toda la tienda y cambia el dominio solo.
6. Revisar que todo esté bien con la lista de "Comprobar" más abajo.

## Dominio con Cloudflare

- DNS: registro `A` del dominio (y de `www`) a la IP del servidor.
- Usando Caddy: SSL/TLS en **Full (strict)**.
- Usando un túnel de Cloudflare: el túnel apunta a `http://localhost:PS_PORT`, con `PS_SSL=1`.

## Comprobar

- [ ] La portada abre con los banners y las promociones.
- [ ] El menú "Nuestros productos" muestra los catálogos.
- [ ] Una categoría abre con los filtros. Un producto abre con su foto.
- [ ] El buscador encuentra productos.
- [ ] Se puede hacer una compra de prueba con transferencia, y llega a Pedidos.
- [ ] El panel abre en `https://DOMINIO/admin-nf/`.
- [ ] En Mi Tienda se puede crear, editar y subir un producto, y crear un catálogo.

## Después de instalar

- Cambiar la contraseña del dueño:

  ```bash
  docker compose exec -u www-data prestashop php /opt/natufitness/scripts/setup-owner.php --email=natufitness@gmail.com --password=-
  ```

- Conectar el correo para las notificaciones, cuando esté la contraseña de aplicación de Gmail:

  ```bash
  docker compose exec -u www-data prestashop php /opt/natufitness/scripts/setup-mail.php
  ```

- Copia diaria de la base de datos (`crontab -e`):

  ```
  30 3 * * * cd /opt/natufitness && docker compose exec -T db sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction prestashop' | gzip > /opt/natufitness/backup-$(date +\%a).sql.gz
  ```

## Cambiar de dominio más adelante

Cambiar `PS_DOMAIN` en `.env` y reiniciar:

```bash
docker compose up -d
```

La tienda se mueve sola al nuevo dominio.

## Si algo falla

- Ver qué pasa:

  ```bash
  docker compose logs -f prestashop
  ```

  y también `docker compose logs db`.
- La tienda vieja sigue funcionando: si hace falta, se vuelve el DNS al servidor anterior.
- Los pedidos, clientes y productos nuevos viven en los volúmenes `db_data` y `ps_data`. **No** correr `docker compose down -v`, porque borra la tienda.
