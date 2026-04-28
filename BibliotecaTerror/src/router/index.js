import { createRouter, createWebHistory } from 'vue-router'
import HomePage from '../pages/HomePage.vue'
import { useAuthStore } from '../stores/auth'

/**
 * Librum Tenebris - Enrutador Principal del Frontend (Vue Router 4)
 *
 * Configurado en modo WebHistory para evitar hashes (`#`) maliciosos en la URL.
 * La mayoría de las rutas usan carga diferida (lazy loading `() => import(...)`)
 * para fraccionar el peso del bundle JavaScript en producción.
 * Emplea guardias de navegación meta-tags para interceptar intrusos en /admin.
 */

const SITE_NAME = 'Librum Tenebris'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomePage,
      meta: { title: `Inicio | ${SITE_NAME}` }
    },
    {
      path: '/buscar',
      name: 'search',
      component: () => import('../pages/SearchPage.vue'),
      meta: { title: `Buscar libros | ${SITE_NAME}` }
    },
    {
      path: '/novedades',
      name: 'novedades',
      component: () => import('../pages/NovedadesPage.vue'),
      meta: { title: `Novedades | ${SITE_NAME}` }
    },
    {
      path: '/recomendaciones',
      name: 'recomendaciones',
      component: () => import('../pages/RecomendacionesPage.vue'),
      meta: { title: `Recomendaciones | ${SITE_NAME}` }
    },
    {
      path: '/libro/:id',
      name: 'book-details',
      component: () => import('../pages/BookDetailsPage.vue'),
      meta: { title: `Detalles del libro | ${SITE_NAME}` }
    },
    {
      path: '/favoritos',
      name: 'favorites',
      component: () => import('../pages/FavoritesPage.vue'),
      meta: { requiresAuth: true, title: `Mis favoritos | ${SITE_NAME}` }
    },
    {
      path: '/prestamos',
      name: 'Prestamos',
      component: () => import('../pages/RentalsPage.vue'),
      meta: { requiresAuth: true, title: `Mis préstamos | ${SITE_NAME}` }
    },
    {
      path: '/registro',
      name: 'register',
      component: () => import('../pages/RegisterPage.vue'),
      meta: { guestOnly: true, title: `Crear cuenta | ${SITE_NAME}` }
    },
    {
      path: '/verificacion-exitosa',
      name: 'verify-email',
      component: () => import('../pages/VerifyEmailPage.vue'),
      meta: { title: `Verificación de correo | ${SITE_NAME}` }
    },
    {
      path: '/confirmar-cambio-correo',
      name: 'confirm-email-change',
      component: () => import('../pages/ConfirmEmailChangePage.vue'),
      meta: { title: `Confirmar cambio de correo | ${SITE_NAME}` }
    },
    {
      path: '/confirmar-acceso',
      name: 'confirm-login-location',
      component: () => import('../pages/ConfirmarAccesoPage.vue'),
      meta: { title: `Confirmar acceso | ${SITE_NAME}` }
    },
    {
      path: '/restablecer-contrasena',
      name: 'reset-password',
      component: () => import('../pages/ResetPasswordPage.vue'),
      meta: { guestOnly: true, title: `Restablecer contraseña | ${SITE_NAME}` }
    },
    {
      path: '/perfil',
      name: 'profile',
      component: () => import('../pages/ProfilePage.vue'),
      meta: { requiresAuth: true, title: `Mi perfil | ${SITE_NAME}` }
    },
    {
      path: '/admin',
      name: 'admin',
      component: () => import('../pages/AdminPage.vue'),
      meta: { requiresAuth: true, requiresAdmin: true, title: `Panel de administración | ${SITE_NAME}` }
    },
    {
      path: '/terminos',
      name: 'terms',
      component: () => import('../pages/TermsPage.vue'),
      meta: { title: `Términos y condiciones | ${SITE_NAME}` }
    },
    {
      path: '/privacidad',
      name: 'privacy',
      component: () => import('../pages/PrivacyPage.vue'),
      meta: { title: `Política de privacidad | ${SITE_NAME}` }
    },
    {
      path: '/cookies',
      name: 'cookies',
      component: () => import('../pages/CookiesPage.vue'),
      meta: { title: `Política de cookies | ${SITE_NAME}` }
    },
    {
      path: '/accesibilidad',
      name: 'accessibility',
      component: () => import('../pages/AccessibilityPage.vue'),
      meta: { title: `Accesibilidad | ${SITE_NAME}` }
    }
  ]
})

router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  if (authStore.token && !authStore.user) {
    await authStore.fetchMe()
  }

  const isAuthenticated = authStore.isAuthenticated

  if (to.meta.requiresAuth && !isAuthenticated) {
    // We don't have a /login page anymore, just the modal.
    // We redirect to home and let the user open the modal.
    return next({ name: 'home' })
  }

  if (to.meta.requiresAdmin && !authStore.isAdmin) {
    // Redirect non-admins to their profile or home
    return next({ name: 'profile' })
  }

  if (to.meta.guestOnly && isAuthenticated) {
    return next({ name: 'profile' })
  }

  next()
})

router.afterEach((to) => {
  document.title = to.meta.title || `${SITE_NAME} · Archivo nocturno de horror y terror clásico`
})

export default router
