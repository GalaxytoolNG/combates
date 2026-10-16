# Combates — complemento de Galaxytool NG

Visor de los informes de combate que el receptor del núcleo guarda en la tabla `combats` (los envía el script de
usuario desde la página de mensajes del juego). Para el jugador asociado al usuario muestra, por rango de fechas,
cada combate con coordenadas, atacantes, defensores, ganador (verde si gana su bando, rojo si gana el rival),
botín, pérdidas propias y ajenas y escombros, con totales; y el detalle de cada batalla con participantes,
tecnologías, unidades por ronda, escombros, honor y retirada táctica, imitando el informe del juego.

Las unidades se identifican por su id de OGame hasta que el núcleo descargue la localización del API.

## Instalación

Requiere el núcleo de [Galaxytool NG](https://github.com/GalaxytoolNG/gui) 1.0.0. No crea tablas.

```bash
cd plugins && git clone https://github.com/GalaxytoolNG/combates.git
cd .. && bin/galaxytool complementos:instalar combates && bin/galaxytool complementos:activar combates
```

Al instalar da nivel `acceso` a los usuarios con algún universo asociado.

## Niveles

| Nivel | Permite |
|---|---|
| acceso | ver los combates del jugador asociado |
| operador | ver los combates de cualquier jugador (`/combates/<dominio>/<id de jugador>`) |
| administrador | cambiar la configuración en `/admin/complementos/combates` |

## Licencia

GPL-3.0-or-later.
