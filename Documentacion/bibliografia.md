# Bibliografía y Webgrafía — Librum Tenebris

Recopilación de las fuentes consultadas durante el desarrollo del TFG, agrupadas por temática y, dentro de cada bloque, ordenadas por relevancia. Todas las URLs fueron verificadas y se accedieron por última vez en **mayo de 2026**.

---

## 1. Frontend — Vue 3, Vite y ecosistema

- **Vue.js Team.** *Vue.js 3 — Documentación oficial.* <https://vuejs.org/guide/introduction.html>
  Referencia principal de la Composition API, sistema reactivo, ciclo de vida y Single File Components empleados en `BibliotecaTerror/`.
- **Vite Team.** *Vite — Next Generation Frontend Tooling.* <https://vitejs.dev/guide/>
  Configuración del bundler, proxy de desarrollo `/auth` y `/api`, build de producción y `vite preview` para auditoría SEO.
- **Vue Router Team.** *Vue Router 4 — The official router for Vue.js.* <https://router.vuejs.org/>
  Implementación de guards (`requiresAuth`, `requiresAdmin`, `guestOnly`), modo `createWebHistory` y carga diferida.
- **Pinia Team.** *Pinia — The intuitive store for Vue.* <https://pinia.vuejs.org/>
  Gestión de estado global del store de autenticación (`stores/auth.js`).
- **Axios.** *Axios — Promise based HTTP client.* <https://axios-http.com/docs/intro>
  Cliente HTTP utilizado para llamar a los dos backends.
- **Swiper.** *Swiper — The Most Modern Mobile Touch Slider.* <https://swiperjs.com/>
  Carruseles de portadas en la home y secciones de novedades / recomendaciones.

---

## 2. Backend — PHP 8 y librerías

- **The PHP Group.** *PHP 8 Manual.* <https://www.php.net/manual/es/index.php>
  Referencia del lenguaje, tipos, PDO y manejo de errores.
- **Firebase.** *firebase/php-jwt — Library for encoding and decoding JWT.* <https://github.com/firebase/php-jwt>
  Implementación del sistema de tokens HS256 utilizado en `JwtService.php`.
- **PHPMailer.** *PHPMailer — Email creation and transfer class for PHP.* <https://github.com/PHPMailer/PHPMailer>
  Envío de correos de verificación, recuperación y alertas geo en `MailService.php`.
- **MaxMind.** *GeoIP2 PHP API.* <https://github.com/maxmind/GeoIP2-php>
  Resolución de IP a país en `GeoLocationService.php` para alertas de login en ubicaciones nuevas.
- **Composer Team.** *Composer — Dependency Manager for PHP.* <https://getcomposer.org/doc/>
  Gestión de las tres dependencias anteriores en `ApiLoging/composer.json`.

---

## 3. Base de datos — MySQL / MariaDB

- **Oracle Corporation.** *MySQL 8.0 Reference Manual.* <https://dev.mysql.com/doc/refman/8.0/en/>
  Tipos de datos, claves foráneas, transacciones (`BEGIN`/`COMMIT`/`ROLLBACK`) usadas en el flujo de préstamo, y `ENUM` para `prestamos.estado`.
- **MariaDB Foundation.** *MariaDB Knowledge Base — InnoDB.* <https://mariadb.com/kb/en/innodb/>
  Comportamiento del motor InnoDB usado en todas las tablas (transacciones, FKs, locking).
- **PHP Group.** *PHP Data Objects (PDO).* <https://www.php.net/manual/es/book.pdo.php>
  Capa de abstracción de BD usada en ambos backends. Sentencias preparadas para mitigar SQL injection (OWASP A03).

---

## 4. Seguridad y autenticación

- **Jones, M., Bradley, J., Sakimura, N. (2015).** *RFC 7519 — JSON Web Token (JWT).* IETF. <https://datatracker.ietf.org/doc/html/rfc7519>
  Especificación oficial del formato JWT, claims estándar (`iat`, `exp`, `jti`) y algoritmos.
- **OWASP Foundation.** *OWASP Top Ten — 2021.* <https://owasp.org/Top10/>
  Marco de referencia para mitigar las 10 vulnerabilidades web más críticas. El proyecto cubre A01 (control de accesos), A02 (fallos criptográficos — BCRYPT), A03 (injection — PDO preparado), A07 (auth fallida — rate limit + tokens revocables).
- **OWASP Foundation.** *OWASP Authentication Cheat Sheet.* <https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html>
  Buenas prácticas seguidas: política de password mínima, tokens de un solo uso, anti-enumeración en endpoints de reset, alertas de login.
- **OWASP Foundation.** *OWASP JWT Cheat Sheet.* <https://cheatsheetseries.owasp.org/cheatsheets/JSON_Web_Token_for_Java_Cheat_Sheet.html>
  Recomendaciones aplicadas: secreto fuerte, validación de `exp`, mecanismo de revocación (`revoked_tokens` + `current_session_id`).
- **NIST SP 800-63B.** *Digital Identity Guidelines — Authentication and Lifecycle Management.* <https://pages.nist.gov/800-63-3/sp800-63b.html>
  Referencia para política de passwords y gestión de sesiones.
- **OWASP Foundation.** *OWASP Secure Headers Project.* <https://owasp.org/www-project-secure-headers/>
  Cabeceras enviadas por `Security::sendSecurityHeaders()` (CSP, X-Frame-Options, HSTS en producción).

---

## 5. SEO, accesibilidad y estándares web

- **W3C.** *HTML Living Standard.* WHATWG. <https://html.spec.whatwg.org/>
  Referencia de etiquetas semánticas (`<main>`, `<article>`, `<nav>`, `<header>`, `<footer>`) usadas en las plantillas.
- **W3C.** *Web Content Accessibility Guidelines (WCAG) 2.1.* <https://www.w3.org/TR/WCAG21/>
  Pauta de accesibilidad seguida en `AccessibilityPage.vue` y en el contraste de la paleta de colores.
- **Google.** *Search Central — SEO Starter Guide.* <https://developers.google.com/search/docs/fundamentals/seo-starter-guide>
  Recomendaciones aplicadas en meta tags, jerarquía de encabezados, sitemap y `robots.txt`.
- **Google.** *Lighthouse — Audit your web app.* <https://developer.chrome.com/docs/lighthouse/overview>
  Herramienta usada para auditorías SEO, accesibilidad y rendimiento.
- **Open Graph Protocol.** *The Open Graph protocol.* <https://ogp.me/>
  Especificación de las meta tags `og:title`, `og:description`, `og:image` para previsualizaciones en redes sociales.
- **Schema.org.** *Schema.org Vocabulary.* <https://schema.org/>
  Vocabulario de datos estructurados (JSON-LD) implementado para libros (`Book`), organización (`Organization`) y breadcrumb.
- **Mozilla Developer Network (MDN).** *Web Docs.* <https://developer.mozilla.org/>
  Referencia general de HTML, CSS, JavaScript y APIs del navegador.

---

## 6. Arquitectura, patrones y diseño de software

- **Fielding, R. T. (2000).** *Architectural Styles and the Design of Network-based Software Architectures.* PhD Dissertation, University of California, Irvine. <https://ics.uci.edu/~fielding/pubs/dissertation/top.htm>
  Tesis fundacional de REST, base del diseño de los endpoints de `ApiLoging`.
- **Fowler, M. (2002).** *Patterns of Enterprise Application Architecture.* Addison-Wesley. ISBN 978-0321127426.
  Referencia para el patrón MVC implementado en `ApiLoging/`.
- **Cockburn, A. (2000).** *Writing Effective Use Cases.* Addison-Wesley. ISBN 978-0201702255.
  Metodología seguida en `documentacion_uml.md` para los casos de uso por rol.
- **Object Management Group (OMG).** *Unified Modeling Language (UML) 2.5.1 Specification.* <https://www.omg.org/spec/UML/2.5.1/>
  Notación UML empleada en los diagramas de clases, secuencia y casos de uso.
- **Mermaid.** *Mermaid — Diagramming and charting tool.* <https://mermaid.js.org/intro/>
  Sintaxis usada para los diagramas embebidos en la documentación markdown.

---

## 7. Despliegue y herramientas

- **Hostinger.** *Premium Web Hosting — Documentación.* <https://www.hostinger.es/tutoriales/>
  Plataforma de hosting elegida para el despliegue final tras descartar InfinityFree (sin `mail()`) y Railway (crédito limitado).
- **Apache Friends.** *XAMPP — Apache + MariaDB + PHP + Perl.* <https://www.apachefriends.org/>
  Entorno de desarrollo local (Apache para servir, MariaDB como motor de BD).
- **Git SCM.** *Pro Git Book.* Chacon, S., Straub, B. <https://git-scm.com/book/es/v2>
  Sistema de control de versiones utilizado durante el desarrollo en equipo.
- **GitHub.** *GitHub Docs.* <https://docs.github.com/>
  Plataforma de hosting del repositorio y colaboración.
- **Postman.** *Postman Learning Center.* <https://learning.postman.com/>
  Herramienta usada para el diseño, prueba y documentación de la API REST. Colección publicada en `Documentacion/postman/`.

---

## 8. Integraciones externas

- **Notion Labs.** *Notion API Documentation.* <https://developers.notion.com/>
  Sincronización de catálogo y préstamos con bases de datos de Notion para gestión visual paralela (`NotionService.php`).
- **Google.** *Google Books API.* <https://developers.google.com/books>
  Referencia para el campo `google_id` en `libros` (ISBN o ID de Google Books).

---

## 9. Convenciones aplicables al TFG

- **Universidad Internacional de La Rioja (UNIR) / IES correspondiente.** *Guía del Trabajo de Fin de Grado de FP DAW.*
  Estructura de la memoria, criterios de evaluación y plantilla de portada (referencia interna del centro educativo).
- **APA Style.** *Publication Manual of the American Psychological Association (7ª ed.).* <https://apastyle.apa.org/>
  Formato de citación seguido en este documento.

---

## Notas de uso

- Las URLs sin paréntesis con fecha están vigentes a 7 de mayo de 2026.
- Todas las dependencias de software están declaradas con su versión exacta en los archivos `package.json` (frontend) y `composer.json` (backend).
- Para licencias de software de terceros, consultar [`LICENSE`](../LICENSE) y los archivos `LICENSE` individuales dentro de `BibliotecaTerror/node_modules/` y `ApiLoging/vendor/`.
