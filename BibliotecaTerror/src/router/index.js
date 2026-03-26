import { createRouter, createWebHistory } from 'vue-router'
import HomePage from '../pages/HomePage.vue'
import RegisterPage from '../pages/RegisterPage.vue'
import RentalsPage from '../pages/RentalsPage.vue'
import NovedadesPage from '../pages/NovedadesPage.vue'
import RecomendacionesPage from '../pages/RecomendacionesPage.vue'
import { useAuthStore } from '../stores/auth'

/**
 * Librum Tenebris - Enrutador Principal del Frontend (Vue Router 4)
 * 
 * Configurado en modo WebHistory para evitar hashes (`#`) maliciosos en la URL.
 * La mayoría de las rutas usan carga diferida (lazy loading `() => import(...)`)
 * para fraccionar el peso del bundle JavaScript en producción.
 * Emplea guardias de navegación meta-tags para interceptar intrusos en /admin.
 */

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomePage
    },
    {
      path: '/buscar',
      name: 'search',
      component: () => import('../pages/SearchPage.vue')
    },
    {
      path: '/novedades',
      name: 'novedades',
      component: NovedadesPage
    },
    {
      path: '/recomendaciones',
      name: 'recomendaciones',
      component: RecomendacionesPage
    },
    {
      path: '/libro/:id',
      name: 'book-details',
      component: () => import('../pages/BookDetailsPage.vue')
    },
    {
      path: '/favoritos',
      name: 'favorites',
      component: () => import('../pages/FavoritesPage.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/prestamos',
      name: 'Prestamos',
      component: RentalsPage,
      meta: { requiresAuth: true }
    },
    {
      path: '/registro',
      name: 'register',
      component: RegisterPage,
      meta: { guestOnly: true }
    },
    {
      path: '/verificacion-exitosa',
      name: 'verify-email',
      component: () => import('../pages/VerifyEmailPage.vue')
    },
    {
      path: '/confirmar-cambio-correo',
      name: 'confirm-email-change',
      component: () => import('../pages/ConfirmEmailChangePage.vue')
    },
    {
      path: '/restablecer-contrasena',
      name: 'reset-password',
      component: () => import('../pages/ResetPasswordPage.vue'),
      meta: { guestOnly: true }
    },
    {
      path: '/perfil',
      name: 'profile',
      component: () => import('../pages/ProfilePage.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/admin',
      name: 'admin',
      component: () => import('../pages/AdminPage.vue'),
      meta: { requiresAuth: true, requiresAdmin: true }
    },
    {
      path: '/terminos',
      name: 'terms',
      component: () => import('../pages/TermsPage.vue')
    },
    {
      path: '/privacidad',
      name: 'privacy',
      component: () => import('../pages/PrivacyPage.vue')
    },
    {
      path: '/cookies',
      name: 'cookies',
      component: () => import('../pages/CookiesPage.vue')
    },
    {
      path: '/accesibilidad',
      name: 'accessibility',
      component: () => import('../pages/AccessibilityPage.vue')
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

export default router
