import { createRouter, createWebHistory } from 'vue-router'
import HomePage from '../pages/HomePage.vue'
import RegisterPage from '../pages/RegisterPage.vue'
import { useAuthStore } from '../stores/auth'

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
