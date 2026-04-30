# BibliotecaTerror — Frontend de Librum Tenebris

Frontend SPA de **Librum Tenebris** (TFG del FP DAW). Construido con
**Vue 3 + Vite**.

## Stack

- **Vue 3** (`<script setup>`) + **Vue Router** + **Pinia**
- **Vite** como build tool y servidor de desarrollo
- **Axios** para llamadas HTTP a los backends
- **Swiper** para carruseles del catálogo

## Backends que consume

| Backend | URL desarrollo | Función |
|---|---|---|
| ApiLoging | `http://localhost:8000` | Registro, login, JWT, perfil |
| libros_api | `http://localhost:8080` | Catálogo, fichas y portadas |

Ambos deben estar corriendo (ver el [README raíz](../README.md) del
proyecto).

## Desarrollo

```bash
npm install   # solo la primera vez
npm run dev
```

Abre [http://localhost:5173](http://localhost:5173).

## Build de producción

```bash
npm run build      # genera dist/
npm run preview    # sirve dist/ localmente para verificar
```

## Estructura

```
BibliotecaTerror/
├── public/         # assets estáticos servidos en raíz
├── src/
│   ├── assets/     # CSS, fuentes
│   ├── components/ # componentes reutilizables
│   ├── img/        # imágenes
│   ├── layouts/    # layouts de página
│   ├── media/      # vídeos / multimedia
│   ├── views/      # vistas asociadas a rutas
│   ├── App.vue
│   └── main.js     # entry point
├── index.html
├── package.json
└── vite.config.js
```

Para arrancar el ecosistema completo (frontend + ambos backends + MySQL),
consulta el [README del proyecto](../README.md).
