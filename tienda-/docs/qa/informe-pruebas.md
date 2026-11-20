# Informe de pruebas: compra completa y UX en celulares

Fecha: 18 de noviembre de 2026 · Tienda: NatuFitness (copia local idéntica a https://natufitness.sevenbarber.app)

## Qué se probó

Una compra real de principio a fin en cada celular, y el dueño atendiendo esos pedidos desde su panel **Mi Tienda** en el celular, hasta entregarlos.

| Dispositivo | Cómo se probó | Compra |
| --- | --- | --- |
| **iPhone 13** | Motor WebKit (el de Safari en iOS), pantalla 390 × 844 | Proteína → envío a domicilio → pago DeUna (QR) |
| **Android 7** | Chrome, pantalla 360 × 740 (Galaxy S8) | Creatina → retiro en local → transferencia bancaria |
| **Panel del dueño** | Chrome en celular, usuario con perfil *Dueño* | Pago recibido → Enviado / Listo para retirar → Entregado |

> Son dispositivos **emulados** con los mismos motores y tamaños de pantalla; no aparatos físicos. Antes de abrir al público conviene una compra de prueba en un iPhone y un Android reales.

Pasos de cada compra: inicio → menú → búsqueda → producto → agregar al carrito → carrito → datos → dirección → envío → pago → confirmación (11 pantallas por celular, 26 en total con el panel).

## Resultado

| Revisión | iPhone 13 | Android 7 | Panel |
| --- | --- | --- | --- |
| La compra se completa | ✅ | ✅ | — |
| El pedido aparece en Mi Tienda → Pedidos | ✅ | ✅ | ✅ |
| Pago recibido → Enviado/Listo → Entregado | ✅ | ✅ | ✅ |
| Botón "Enviar mi pedido por WhatsApp" al 0983168337 con el pedido completo | ✅ | ✅ | — |
| Cuentas bancarias reales en la confirmación (transferencia) | (pagó con DeUna) | ✅ 3 cuentas | — |
| Sin scroll horizontal | ✅ | ✅ | ✅ |
| Errores de JavaScript | 0 | 0 | 0 |

Capturas de cada paso: `iphone13.jpg`, `android7.jpg`, `panel.jpg` (en esta carpeta).

## Lo que se encontró y se corrigió

1. **Íconos de cuenta y carrito muy pequeños** (22 px): ahora tienen 44 px de área para el dedo.
2. **Selector de cantidad roto en celulares**: los botones + / − quedaban debajo del número; ahora van al lado y funcionan.
3. **Ficha de producto se salía 12 px de la pantalla del iPhone** (el corazón de favoritos) y el texto "AÑADIR AL CARRITO" se cortaba: ahora entra en una línea.
4. **El carrito anunciaba pago con tarjeta (Datafast)**, que aún no está activo: ahora dice "DeUna o transferencia bancaria".
5. **Botón flotante de WhatsApp** tapaba un "Comprar" en pantallas de 360 px: más pequeño en esas pantallas.

## Pendiente antes de abrir al público

- **Titular y cédula/RUC** de las cuentas bancarias (hoy dicen PENDIENTE).
- **QR real de DeUna** (hoy se muestra un QR de ejemplo).
- **Precios reales** (el dueño los carga en Mi Tienda).
- **Correo automático** (opcional): con la contraseña de aplicación de Gmail el dueño recibe un correo por cada compra y el cliente su confirmación. Hoy el panel avisa "no se pudo enviar el correo al cliente, avísale por WhatsApp".
- **Cancelar los pedidos de prueba** de la tienda en vivo.
