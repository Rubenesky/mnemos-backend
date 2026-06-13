# Mnemos

### *Memoria abierta para las organizaciones que importan*

[![Tests](https://img.shields.io/badge/tests-291%20passed-brightgreen.svg)](#ejecutar-los-tests)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![PHP 8.2](https://img.shields.io/badge/PHP-8.2-blue.svg)](https://www.php.net/)
[![Laravel 10](https://img.shields.io/badge/Laravel-10-red.svg)](https://laravel.com/)
[![Vue 3](https://img.shields.io/badge/Frontend-Vue%203-42b883.svg?logo=vue.js)](https://github.com/rubenesky/mnemos-frontend)
[![Open Source](https://img.shields.io/badge/Open%20Source-%E2%9D%A4-brightgreen.svg)](https://github.com/rubenesky/mnemos-backend)

---

Mnemos es un sistema de archivo digital gratuito y de código abierto, construido para ONGs, fundaciones culturales, centros educativos y organizaciones comunitarias. Ofrece a tu equipo un espacio único y con motor de búsqueda para todas tus fotos, vídeos y documentos — con seguimiento de consentimiento RGPD e inteligencia artificial para accesibilidad, integrados de serie.

---

## El Problema

La mayoría de las organizaciones están perdiendo su memoria institucional ahora mismo, y no lo saben.

**1. Sin archivo estructurado**
Las fotos, vídeos y documentos están dispersos entre grupos de WhatsApp, carpetas compartidas de Google Drive e hilos de correo que se extienden por años. Cuando alguien pregunta *"¿tenemos alguna foto de la campaña de 2019?"*, la respuesta honesta suele ser *"en algún sitio, quizá"*. No hay forma de buscar, no hay nomenclatura consistente y no hay un único lugar donde mirar.

**2. Sin registro de consentimiento**
Las organizaciones publican regularmente imágenes de voluntarios, participantes de programas y menores — sin ningún registro documentado de que se obtuvo el consentimiento. Bajo el RGPD, esto no es solo un vacío procedimental: es una exposición legal. Cuando llega una auditoría o una reclamación, no hay rastro en papel que presentar.

**3. Barrera técnica**
Las herramientas profesionales de archivo digital — Canto, Bynder, Brandfolder — cuestan miles de euros al año y requieren un departamento de IT dedicado para instalarlas, configurarlas y mantenerlas. Están diseñadas para equipos de marketing en grandes corporaciones, no para un equipo de cinco personas gestionando programas extraescolares.

Mnemos elimina las tres barreras. Es gratuito, se instala con un solo comando y está diseñado para ser usado por personas sin perfil técnico.

---

## 🏛️ Un Caso de Uso Real

La Fundació Memòria Viva de Lleida lleva 20 años recopilando fotografías, historias orales y documentos manuscritos de residentes mayores — un registro insustituible de la vida rural catalana a mediados del siglo XX. Durante la mayor parte de ese tiempo, esos materiales vivían en cajas de cartón, discos duros externos y una carpeta de Dropbox compartida que nadie entendía del todo. Los voluntarios llegaban y se iban; el conocimiento institucional se marchaba con ellos.

Con Mnemos, la fundación ingiere una fotografía digitalizada y el sistema genera automáticamente una descripción accesible de su contenido mediante IA — una masía medieval al atardecer, tres mujeres seleccionando grano, un niño observando desde el umbral. Esa descripción hace que la imagen sea encontrable por cualquiera que busque "masía" o "cosecha" años más tarde. Los registros de consentimiento de cada persona viva fotografiada se gestionan directamente en el sistema, codificados por color según su estado, y bloqueados para su publicación pública hasta que estén documentados. Una URL de galería pública permite a la fundación compartir colecciones curadas con investigadores y periodistas sin necesidad de ningún inicio de sesión. Y cuando se incorpora un nuevo voluntario para el verano, recibe un rol de Voluntario temporal que expira automáticamente el día que se marcha — sin cuentas de administrador olvidadas, sin limpieza manual.

Para esto está Mnemos.

---

## ✨ Funcionalidades

**1. Galería Pública**
Comparte colecciones de recursos públicamente sin necesidad de inicio de sesión. Cada colección tiene su propia URL compartible. Ideal para compartir kits de prensa, exposiciones o archivos abiertos con el mundo exterior.

**2. 🔒 Panel RGPD de Consentimientos**
Registra el estado de consentimiento por recurso con un dashboard codificado por colores: obtenido (verde), pendiente (amarillo), denegado (rojo). Los recursos sin consentimiento documentado se bloquean automáticamente para su publicación pública. Listo para auditorías en cualquier momento.

**3. 🚀 Instalación sin conocimientos técnicos**
Un solo comando te pone en marcha: `./install.sh`. No se requieren conocimientos de configuración de servidores. Docker lo gestiona todo. Si puedes abrir una terminal y pegar un comando, puedes instalar Mnemos.

**4. Alt-text automático con IA**
Cada imagen que subes recibe automáticamente una descripción de accesibilidad generada por Google Gemini Vision. Esto hace tu archivo compatible con lectores de pantalla y mejora la búsqueda — sin ningún trabajo manual.

**5. Rol de Voluntario**
Un nivel de acceso temporal entre Visor y Editor, con una fecha de expiración configurable. Perfecto para becarios, voluntarios de proyectos puntuales o estudiantes en prácticas. El acceso desaparece automáticamente cuando finaliza el período.

**6. Multilingüe**
Soporte completo en español e inglés en toda la interfaz, impulsado por el sistema i18n de Laravel. La comunidad puede añadir más idiomas.

**7. 🔗 Solicitudes de Consentimiento por Token**
Genera un enlace compartible para cualquier registro de consentimiento pendiente y envíalo directamente a la persona cuyo consentimiento se requiere. El destinatario abre el enlace — sin necesidad de cuenta — revisa los detalles y acepta o deniega con un clic. La decisión se registra al instante y los administradores son notificados automáticamente.

**8. 🔔 Sistema de Notificaciones Interno**
El icono de campana en tiempo real en la barra superior mantiene informados a los administradores sin necesidad de correo electrónico. Las notificaciones se disparan automáticamente cuando un voluntario sube un recurso o cuando alguien responde a una solicitud de consentimiento. Contador de no leídos, marcado por elemento y "marcar todo como leído" — todo persistido en base de datos.

**9. 🧭 Incorporación Guiada**
Un modal de 3 pasos recibe a cada nuevo usuario en su primer inicio de sesión, explicando qué hace Mnemos y cómo empezar. Se muestra una sola vez y nunca más (registrado en localStorage). Sin redirección ni página separada — aparece directamente sobre el dashboard.

---

## 📸 Capturas de Pantalla

| Dashboard | Galería de Recursos | Galería Pública |
|---|---|---|
| ![Dashboard](docs/screenshots/01-dashboard.png) | ![Recursos](docs/screenshots/02-assets.png) | ![Galería Pública](docs/screenshots/06-public-gallery.png) |

| Panel RGPD de Consentimientos | Chat IA | Sala de Prensa |
|---|---|---|
| ![Consentimientos](docs/screenshots/04-consent-panel.png) | ![Chat IA](docs/screenshots/05-ai-chat.png) | ![Sala de Prensa](docs/screenshots/10-press-room.png) |

| Subida de Recurso | Kit de Emergencia | Dashboard de Impacto |
|---|---|---|
| ![Subida](docs/screenshots/03-upload.png) | ![Kit de Emergencia](docs/screenshots/11-emergency-kit.png) | ![Dashboard de Impacto](docs/screenshots/12-impact-dashboard.png) |

| Panel de Administración | Campana de Notificaciones | Formulario de Consentimiento |
|---|---|---|
| ![Admin](docs/screenshots/13-admin-panel.png) | ![Notificaciones](docs/screenshots/08-notifications.png) | ![Formulario](docs/screenshots/09-consent-form.png) |

| Modal de Bienvenida |
|:---:|
| ![Bienvenida](docs/screenshots/07-onboarding.png) |

---

## Stack Tecnológico

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.2 / Laravel 10 |
| Base de datos | MySQL 8 |
| Autenticación API | Laravel Sanctum |
| Almacenamiento de recursos y CDN | Cloudinary |
| IA (metadatos, alt-text, búsqueda, chat) | Google Gemini |
| Despliegue | Docker Compose (opcional) |
| Tests | Pest 2.x |

---

## Instalación

### Inicio rápido con Docker (recomendado)

No se requieren conocimientos técnicos previos. Necesitas Docker Desktop instalado — [descárgalo aquí](https://www.docker.com/products/docker-desktop/) — y luego ejecuta:

```bash
git clone https://github.com/rubenesky/mnemos-backend
cd mnemos-backend
chmod +x install.sh
./install.sh
```

El script de instalación configura la base de datos, genera la clave de aplicación e inicia todos los servicios automáticamente. Tu instancia de Mnemos estará disponible en `http://localhost:8000`.

### Instalación manual

Si prefieres ejecutar Mnemos sin Docker, necesitarás PHP 8.2+, Composer y MySQL instalados en tu máquina.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### Variables de entorno

Copia `.env.example` a `.env` y completa los siguientes valores:

```env
# Aplicación
APP_NAME=Mnemos
APP_URL=http://localhost:8000          # URL donde Mnemos estará accesible

# Base de datos — datos de tu conexión MySQL
DB_DATABASE=mnemos                     # Nombre de la base de datos a crear
DB_USERNAME=root                       # Tu usuario de MySQL
DB_PASSWORD=                           # Tu contraseña de MySQL (deja en blanco si no tienes)

# Cloudinary — cuenta gratuita en cloudinary.com
# Todos los archivos subidos se almacenan aquí
CLOUDINARY_CLOUD_NAME=your_cloud_name
CLOUDINARY_API_KEY=your_api_key
CLOUDINARY_API_SECRET=your_api_secret

# Google Gemini — API key gratuita en aistudio.google.com
# Impulsa la generación de alt-text, búsqueda en lenguaje natural y chat IA
GEMINI_API_KEY=your_gemini_api_key
```

**Dónde obtener las claves:**
- Cloudinary: Crea una cuenta gratuita en [cloudinary.com](https://cloudinary.com). Tus credenciales están en la página del Dashboard.
- Gemini API: Obtén una clave gratuita en [aistudio.google.com](https://aistudio.google.com). No se requiere tarjeta de crédito para el uso estándar.

---

## Resumen de la API

Mnemos expone una API REST autenticada con tokens Bearer (Laravel Sanctum). Todas las peticiones requieren una cabecera `Authorization: Bearer <token>` salvo donde se indique.

| Método | Endpoint | Auth | Descripción |
|--------|----------|------|-------------|
| POST | `/api/login` | No | Obtener un token de acceso |
| POST | `/api/logout` | Sí | Invalidar el token actual |
| GET | `/api/assets` | Sí | Listar todos los recursos (paginado) |
| POST | `/api/assets` | Sí | Subir un nuevo recurso |
| GET | `/api/assets/{id}` | Sí | Obtener un recurso específico |
| PATCH | `/api/assets/{id}` | Sí | Actualizar metadatos del recurso |
| DELETE | `/api/assets/{id}` | Sí | Eliminar un recurso |
| GET | `/api/assets/{id}/status` | Sí | Consultar estado del procesamiento IA |
| POST | `/api/search` | Sí | Búsqueda en lenguaje natural |
| POST | `/api/rag` | Sí | Chat IA sobre tu archivo |
| GET | `/api/health` | No | Health check — devuelve `{"ok":true}`, sin consulta a BD |
| GET | `/api/public/assets` | No | Lista pública de recursos, paginada (sin login) |
| GET | `/api/public/gallery` | No | Galería pública (sin login) |
| GET | `/api/consents` | Sí | Listar registros de consentimiento |
| POST | `/api/consents` | Sí | Crear un registro de consentimiento |
| PATCH | `/api/consents/{id}` | Sí | Actualizar estado de consentimiento |
| DELETE | `/api/consents/{id}` | Sí | Eliminar un registro de consentimiento |
| POST | `/api/consents/{id}/send-request` | Sí | Generar enlace de solicitud de consentimiento |
| GET | `/api/public/consents/{token}` | No | Ver formulario de consentimiento por token |
| POST | `/api/public/consents/{token}` | No | Enviar decisión de consentimiento por token |
| GET | `/api/notifications` | Sí | Listar notificaciones del usuario actual |
| POST | `/api/notifications/{id}/read` | Sí | Marcar una notificación como leída |
| POST | `/api/notifications/read-all` | Sí | Marcar todas las notificaciones como leídas |

**Ejemplo de inicio de sesión:**

```bash
curl -X POST https://your-instance.com/api/login \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@example.com", "password": "your-password"}'
```

**Ejemplo de búsqueda en lenguaje natural:**

```bash
curl -X POST https://your-instance.com/api/search \
  -H "Authorization: Bearer your-token" \
  -H "Content-Type: application/json" \
  -d '{"query": "photos from the 2023 summer campaign"}'
```

---

## Mantener el servicio activo en Render (plan gratuito)

El plan gratuito de Render detiene los servicios tras 15 minutos de inactividad. La primera petición tras una parada desencadena un arranque en frío de ~30 segundos, lo que hace que la aplicación parezca rota para los usuarios reales.

La solución es un servicio externo gratuito que hace ping al backend cada 5 minutos, evitando completamente el apagado.

### Opción 1 — UptimeRobot (recomendado, gratuito)

1. Crea una cuenta gratuita en [uptimerobot.com](https://uptimerobot.com)
2. Haz clic en **+ Add New Monitor**
3. Rellena:
   - **Monitor Type**: `HTTP(s)`
   - **Friendly Name**: `Mnemos Backend`
   - **URL**: `https://mnemos-backend-if2n.onrender.com/api/health`
   - **Monitoring Interval**: `5 minutes`
4. Haz clic en **Create Monitor**

UptimeRobot hará ping a `/api/health` cada 5 minutos. El endpoint devuelve `{"ok": true}` instantáneamente sin consultar la base de datos, por lo que no hay coste en hacerle ping con frecuencia. Como ventaja adicional, UptimeRobot te enviará un aviso por correo si el servicio cae.

### Opción 2 — cron-job.org (alternativa, también gratuito)

1. Crea una cuenta gratuita en [cron-job.org](https://cron-job.org)
2. Crea un nuevo cron job:
   - **URL**: `https://mnemos-backend-if2n.onrender.com/api/health`
   - **Schedule**: cada 14 minutos (`*/14 * * * *`)
3. Guarda

### Endpoint de health

```
GET /api/health
→ 200 {"ok": true}
```

Sin autenticación. Sin consulta a base de datos. Seguro para hacer ping a cualquier intervalo.

---

## Hoja de Ruta

Las siguientes funcionalidades están planificadas para versiones futuras. Se aceptan contribuciones.

- [ ] Integración de programación en redes sociales (publicar directamente en Instagram, LinkedIn)
- [ ] Importación masiva desde Google Drive y Dropbox
- [ ] Personalización de marca — logotipo, colores y dominio propios por organización
- [ ] Notificaciones por correo alojadas localmente para solicitudes de consentimiento y subidas
- [ ] Aplicación móvil (React Native) para equipos de campo

¿Tienes una solicitud de funcionalidad? Abre un issue y describe tu caso de uso.

---

## Contribuir

Mnemos es de código abierto y da la bienvenida a contribuciones de desarrolladores, traductores y organizaciones dispuestas a probar y dar feedback.

**Reportar incidencias**
Usa [GitHub Issues](https://github.com/rubenesky/mnemos-backend/issues). Por favor incluye: qué esperabas que ocurriera, qué ocurrió realmente y tu entorno (versión de PHP, sistema operativo, método de instalación).

**Enviar un pull request**
1. Haz un fork del repositorio
2. Crea una rama: `git checkout -b feature/nombre-de-tu-funcionalidad`
3. Realiza tus cambios
4. Ejecuta los tests: `./vendor/bin/pest`
5. Abre un PR contra `main` con una descripción clara de qué cambia y por qué

**Estándares de código**
- Formato PSR-12 (aplicado por Laravel Pint: `./vendor/bin/pint`)
- Bloques PHPDoc en todos los métodos públicos
- Las nuevas funcionalidades deben incluir tests
- Los mensajes de commit siguen Conventional Commits (`feat:`, `fix:`, `docs:`, etc.)

**Ejecutar los tests:**

```bash
./vendor/bin/pest
```

La suite de tests cubre actualmente **291 tests / 790 assertions** en autorización, seguridad, IDOR, CRUD de recursos, tokens de consentimiento, notificaciones y procesamiento IA.

---

## Sostenibilidad

Mnemos es y seguirá siendo gratuito y de código abierto bajo la licencia MIT. Para apoyar el desarrollo continuo, los siguientes servicios de pago están disponibles para organizaciones que deseen asistencia profesional:

- **Instalación y configuración alojada** — instalamos y configuramos Mnemos en tu servidor o cuenta en la nube
- **Formación personalizada** — sesiones prácticas para tu equipo, en español o inglés
- **Planes de soporte dedicado** — soporte prioritario por correo con tiempos de respuesta garantizados
- **Desarrollo de funcionalidades a medida** — funcionalidades específicas construidas para el flujo de trabajo de tu organización

Para consultas: [dcrubben25@gmail.com](mailto:dcrubben25@gmail.com)

---

## Licencia

Mnemos se publica bajo la [Licencia MIT](LICENSE). Eres libre de usarlo, modificarlo y distribuirlo para cualquier propósito, incluido el uso comercial. Se agradece la atribución, pero no es obligatoria.
