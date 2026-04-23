<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import authApi from '../api/auth'
import booksApi from '../api/books.js'
import { useAuthStore } from '../stores/auth'
import personIcon from '../assets/person.svg'
import bookIcon from '../assets/book.svg'
import calendarIcon from '../assets/calendar.svg'

const authStore = useAuthStore()

// --- Estado global de Tabs ---
const activeTab = ref('usuarios')

// --- Lógica de Usuarios ---
const users = ref([])
const loading = ref(true)
const error = ref(null)
const usersSearchQuery = ref('')

const fetchUsers = async () => {
  loading.value = true
  error.value = null
  try {
    const res = await authApi.adminGetUsers()
    users.value = res.data.users || []
  } catch (err) {
    console.error('Error cargando usuarios:', err)
    error.value = 'No se pudieron cargar los usuarios. Revisa tu conexión.'
  } finally {
    loading.value = false
  }
}

const handleRoleChange = async (user, newRole) => {
  try {
    const previousRole = user.role
    user.role = newRole
    await authApi.adminUpdateRole(user.id, newRole)
  } catch (err) {
    user.role = previousRole
    console.error('Error actualizando rol:', err)
    alert('Fallo al actualizar el rol de ' + user.username)
  }
}

const formatDate = (dateString) => {
  if (!dateString) return 'Nunca'
  return new Date(dateString).toLocaleDateString()
}

const filteredUsers = computed(() => {
  if (!usersSearchQuery.value) return users.value
  const query = usersSearchQuery.value.toLowerCase()
  return users.value.filter(user => {
    return (user.name && user.name.toLowerCase().includes(query)) ||
      (user.username && user.username.toLowerCase().includes(query)) ||
      (user.email && user.email.toLowerCase().includes(query)) ||
      (user.dni && user.dni.toLowerCase().includes(query))
  })
})

// --- Lógica de Libros (Inventario) ---
const books = ref([])
const booksLoading = ref(false)
const booksError = ref(null)
const booksSearchQuery = ref('')

const fetchBooks = async () => {
  booksLoading.value = true
  booksError.value = null
  try {
    const res = await booksApi.getAllBooks()
    books.value = res.data.data || []
  } catch (err) {
    console.error('Error cargando libros:', err)
    booksError.value = 'No se pudo cargar el inventario de libros.'
  } finally {
    booksLoading.value = false
  }
}

const filteredBooks = computed(() => {
  if (!booksSearchQuery.value) return books.value
  const query = booksSearchQuery.value.toLowerCase()
  return books.value.filter(book => {
    return (book.titulo && book.titulo.toLowerCase().includes(query)) ||
      (book.titulo_es && book.titulo_es.toLowerCase().includes(query)) ||
      (book.autor && book.autor.toLowerCase().includes(query))
  })
})

// --- Lógica de Préstamos ---
const prestamos = ref([])
const prestamosLoading = ref(false)
const prestamosError = ref(null)

const fetchPrestamos = async () => {
  prestamosLoading.value = true
  prestamosError.value = null
  try {
    const res = await booksApi.getAllPrestamos()
    prestamos.value = res.data.data || []
  } catch (err) {
    console.error('Error cargando préstamos:', err)
    prestamosError.value = 'No se pudo cargar el historial de préstamos.'
  } finally {
    prestamosLoading.value = false
  }
}

const prestamosSearchQuery = ref('')

const filteredPrestamos = computed(() => {
  if (!prestamosSearchQuery.value) return prestamos.value
  const query = prestamosSearchQuery.value.toLowerCase()
  return prestamos.value.filter(rent => {
    const userName = (rent.nombre_usuario || getUserName(rent.usuario_id)).toLowerCase()
    const bookTitle = rent.titulo ? rent.titulo.toLowerCase() : ''
    const rentId = String(rent.prestamo_id)
    return userName.includes(query) || bookTitle.includes(query) || rentId.includes(query)
  })
})

/**
 * Filtra los campos dinámicos de los préstamos mapeando la ID del dueño contra 
 * el array `users` global para incrustar su nombre en la tabla. 
 */
const getUserName = (userId) => {
  if (!userId) return 'Usuario Desconocido'
  if (!users.value || users.value.length === 0) return `User #${userId}`
  const u = users.value.find(user => user.id === userId)
  return u ? u.username : `User #${userId}`
}

/**
 * Motor matemático que computa la discrepancia actual frente a la fecha de caducidad
 * de los 14 días prestados para advertir del sobreuso mediante semáforos verdes/rojos.
 */
const getDaysRemaining = (fechaDevolucion) => {
  if (!fechaDevolucion) return 'A la espera'
  const end = new Date(fechaDevolucion)
  const now = new Date()
  const diffTime = end - now
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24))
}

/**
 * Modifica el estado comercial en la Base de Datos y actualiza la UI.
 * Si aprueba una entrega, anula la fecha esperada estableciendo una fija definitiva local.
 */
const handlePrestamoStatusUpdate = async (rent, newStatus) => {
  const previousStatus = rent.estado
  try {
    rent.estado = newStatus
    await booksApi.updatePrestamoStatus(rent.prestamo_id, newStatus)
    if (newStatus === 'devuelto' || previousStatus === 'devuelto') {
      fetchBooks() // Resincroniza stock por el fondo
    }
  } catch (err) {
    rent.estado = previousStatus
    console.error(err)
    alert('Error al actualizar el estado del préstamo.')
  }
}

watch(activeTab, (newTab) => {
  if (newTab === 'libros' && books.value.length === 0) {
    fetchBooks()
  }
  if (newTab === 'prestamos' && prestamos.value.length === 0) {
    fetchPrestamos()
  }
})

const showUserModal = ref(false)
const userLoading = ref(false)
const userError = ref(null)
const userData = reactive({
  name: '',
  username: '',
  email: '',
  dni: '',
  password: '',
  role: 'user'
})

const openUserModal = () => {
  userData.name = ''
  userData.username = ''
  userData.email = ''
  userData.dni = ''
  userData.password = ''
  userData.role = 'user'
  userError.value = null
  showUserModal.value = true
}

const handleAdminRegister = async () => {
  userLoading.value = true
  userError.value = null
  try {
    await authApi.adminRegister(userData)
    showUserModal.value = false
    fetchUsers()
  } catch (err) {
    console.error(err)
    userError.value = err.response?.data?.error || 'Error al registrar al usuario.'
  } finally {
    userLoading.value = false
  }
}

// --- Lógica del Borrado de Usuario ---
const showDeleteModal = ref(false)
const userToDelete = ref(null)
const deleteLoading = ref(false)
const deleteError = ref(null)

const confirmDeleteUser = (user) => {
  if (user.role === 'admin') {
    alert('No se puede eliminar a un administrador desde aquí por seguridad.')
    return
  }
  userToDelete.value = user
  deleteError.value = null
  showDeleteModal.value = true
}

const handleAdminDelete = async () => {
  if (!userToDelete.value) return
  deleteLoading.value = true
  deleteError.value = null
  try {
    await authApi.adminDeleteUser(userToDelete.value.id)
    showDeleteModal.value = false
    userToDelete.value = null
    fetchUsers()
  } catch (err) {
    console.error(err)
    deleteError.value = err.response?.data?.error || 'Error al eliminar al usuario.'
  } finally {
    deleteLoading.value = false
  }
}

// --- Menú de 3 puntitos (ban / force-logout) ---
// El menú se teletransporta al <body> para que el position:absolute no se
// quede atrapado dentro del contenedor con overflow de la tabla (eso
// generaba scroll interno, ver screenshot). Calculamos las coordenadas
// del botón y pasamos un style con top/left absolutos.
const openActionsMenuId = ref(null)
const actionsMenuStyle = ref({})
const openActionsUser = computed(() =>
  users.value.find(u => u.id === openActionsMenuId.value) || null
)

const toggleActionsMenu = async (userId, event) => {
  if (openActionsMenuId.value === userId) {
    closeActionsMenu()
    return
  }
  openActionsMenuId.value = userId
  await nextTick()
  const rect = event.currentTarget.getBoundingClientRect()
  actionsMenuStyle.value = {
    position: 'absolute',
    top: `${rect.bottom + window.scrollY + 6}px`,
    left: `${rect.right + window.scrollX - 170}px`,
    minWidth: '170px',
    zIndex: 9999,
  }
}

const closeActionsMenu = () => {
  openActionsMenuId.value = null
}

const isSelfUser = (user) => authStore.user && authStore.user.id === user.id

const handleForceLogout = async (user) => {
  closeActionsMenu()
  if (!confirm(`¿Cerrar la sesión activa de ${user.username || user.email}?`)) return
  const currentPassword = prompt('Confirma tu contraseña de admin para cerrar la sesión:')
  if (!currentPassword) return
  try {
    await authApi.adminForceLogout(user.id, currentPassword)
    alert('Sesiones del usuario cerradas.')
    fetchUsers()
  } catch (err) {
    alert(err.response?.data?.error || 'No se pudo cerrar la sesión.')
  }
}

const handleToggleBan = async (user) => {
  closeActionsMenu()
  const banning = !user.banned_at
  const verb = banning ? 'Banear' : 'Desbanear'
  if (!confirm(`¿${verb} a ${user.username || user.email}?`)) return
  const currentPassword = prompt(`Confirma tu contraseña de admin para ${verb.toLowerCase()}:`)
  if (!currentPassword) return
  try {
    await authApi.adminSetBan(user.id, banning, currentPassword)
    alert(banning ? 'Usuario baneado.' : 'Usuario desbaneado.')
    fetchUsers()
  } catch (err) {
    alert(err.response?.data?.error || 'No se pudo actualizar el baneo.')
  }
}

// Cerrar el menú al clicar fuera o hacer scroll.
const onClickOutsideActionsMenu = (e) => {
  if (!e.target.closest('.actions-trigger') && !e.target.closest('.actions-menu')) {
    closeActionsMenu()
  }
}
const onScrollCloseActionsMenu = () => closeActionsMenu()

onMounted(() => {
  document.addEventListener('click', onClickOutsideActionsMenu)
  window.addEventListener('scroll', onScrollCloseActionsMenu, true)
})
onUnmounted(() => {
  document.removeEventListener('click', onClickOutsideActionsMenu)
  window.removeEventListener('scroll', onScrollCloseActionsMenu, true)
})

// --- Lógica del Modal Nuevo Préstamo ---
const showPrestamoModal = ref(false)
const prestamoLoading = ref(false)
const prestamoError = ref(null)
const prestamoData = reactive({
  dni: '',
  libro_titulo: '',
  fecha_devolucion: ''
})

const showBookSuggestions = ref(false)

const filteredBookSuggestions = computed(() => {
  const query = prestamoData.libro_titulo.toLowerCase().trim()
  if (!query) return []
  return books.value
    .filter(book => (book.titulo_es || book.titulo).toLowerCase().includes(query))
    .slice(0, 5)
})

const openPrestamoModal = () => {
  if (books.value.length === 0) {
    fetchBooks()
  }
  prestamoData.dni = ''
  prestamoData.libro_titulo = ''
  prestamoData.fecha_devolucion = ''
  prestamoError.value = null
  showPrestamoModal.value = true
  showBookSuggestions.value = false
}

const selectBookSuggestion = (title) => {
  prestamoData.libro_titulo = title
  showBookSuggestions.value = false
}

const handleCreatePrestamo = async () => {
  if (!prestamoData.dni || !prestamoData.libro_titulo) return

  prestamoLoading.value = true
  prestamoError.value = null

  try {
    const payload = { ...prestamoData }
    if (!payload.fecha_devolucion) delete payload.fecha_devolucion

    await booksApi.adminCrearPrestamo(payload)
    showPrestamoModal.value = false
    fetchPrestamos() // Refrescar lista
  } catch (err) {
    console.error(err)
    prestamoError.value = err.response?.data?.error || 'Error al crear el préstamo.'
  } finally {
    prestamoLoading.value = false
  }
}

// --- Lógica del Modal Add Book ---
const showModal = ref(false)
const isEditing = ref(false)
const currentEditId = ref(null)
const savingBook = ref(false)
const modalError = ref(null)
const selectedFile = ref(null)
const previewUrl = ref(null)

const emptyBook = () => ({
  google_id: '',
  titulo: '',
  autor: '',
  stock: 3,
  categoria: ''
})
const newBook = ref(emptyBook())

const openModal = () => {
  isEditing.value = false
  currentEditId.value = null
  newBook.value = emptyBook()
  selectedFile.value = null
  previewUrl.value = null
  modalError.value = null
  showModal.value = true
}

const openEditModal = (book) => {
  isEditing.value = true
  currentEditId.value = book.id
  newBook.value = {
    google_id: book.google_id || '',
    titulo: book.titulo_es || book.titulo || '',
    autor: book.autor || '',
    stock: book.stock || 1,
    categoria: book.categoria || ''
  }
  selectedFile.value = null
  previewUrl.value = book.portada || null
  modalError.value = null
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

const handleFileChange = (e) => {
  const file = e.target.files[0]
  if (file) {
    selectedFile.value = file
    previewUrl.value = URL.createObjectURL(file)
  }
}

const submitBook = async () => {
  if (!isEditing.value && !selectedFile.value) {
    modalError.value = 'Debes subir una portada.'
    return
  }
  if (!newBook.value.google_id || !newBook.value.titulo || !newBook.value.autor || !newBook.value.categoria) {
    modalError.value = 'Completa todos los campos obligatorios.'
    return
  }

  savingBook.value = true
  modalError.value = null

  const formData = new FormData()
  if (isEditing.value) {
    formData.append('id', currentEditId.value)
  }
  formData.append('google_id', newBook.value.google_id)
  formData.append('titulo', newBook.value.titulo)
  formData.append('autor', newBook.value.autor)
  formData.append('categoria', newBook.value.categoria)
  formData.append('stock', newBook.value.stock)

  if (selectedFile.value) {
    formData.append('portada', selectedFile.value)
  }

  try {
    if (isEditing.value) {
      await booksApi.updateBook(formData)
    } else {
      await booksApi.createBook(formData)
    }
    closeModal()
    fetchBooks() // Refrescar lista!
  } catch (err) {
    console.error(err)
    modalError.value = err.response?.data?.error || 'Error al guardar el libro.'
  } finally {
    savingBook.value = false
  }
}

// --- Inicialización ---
onMounted(() => {
  if (authStore.isAdmin) {
    fetchUsers()
  }
})
</script>

<template>
  <div class="admin-page">
    <div class="admin-header">
      <h1 class="admin-title">Panel de Administración</h1>
      <p class="admin-subtitle">Gestiona los usuarios y el inventario de libros de la biblioteca.</p>

      <!-- Navegación por pestañas -->
      <div class="admin-tabs">
        <button :class="['tab-btn', { active: activeTab === 'usuarios' }]" @click="activeTab = 'usuarios'">
          <img :src="personIcon" class="tab-icon-img" alt="Usuarios" /> Usuarios
        </button>
        <button :class="['tab-btn', { active: activeTab === 'libros' }]" @click="activeTab = 'libros'">
          <img :src="bookIcon" class="tab-icon-img" alt="Inventario" /> Inventario
        </button>
        <button :class="['tab-btn', { active: activeTab === 'prestamos' }]" @click="activeTab = 'prestamos'">
          <img :src="calendarIcon" class="tab-icon-img" alt="Préstamos" /> Préstamos
        </button>
      </div>
    </div>

    <!-- =============== PESTAÑA: USUARIOS =============== -->
    <div v-show="activeTab === 'usuarios'">
      <div v-if="loading" class="state-container">
        <div class="spinner"></div>
        <p>Cargando usuarios...</p>
      </div>

      <div v-else-if="error" class="state-container error-state">
        <p>{{ error }}</p>
        <button @click="fetchUsers" class="retry-btn">Reintentar</button>
      </div>

      <div v-else class="table-container">
        <div class="search-bar-container actions-bar">
          <input type="search" v-model="usersSearchQuery" placeholder="Buscar por nombre, email o DNI..."
            class="admin-search-input" />
          <button class="add-btn" @click="openUserModal">
            <span class="plus-icon">+</span> Nuevo Usuario
          </button>
        </div>
        <table class="users-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Usuario</th>
              <th>Email</th>
              <th>DNI</th>
              <th>Verificado</th>
              <th>Rol</th>
              <th>Estado</th>
              <th>Fecha Registro</th>
              <th class="col-actions" aria-label="Acciones"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in filteredUsers" :key="user.id" :class="{ 'is-banned': !!user.banned_at }">
              <td class="cell-id">#{{ user.id }}</td>
              <td class="cell-user">
                <div class="user-info">
                  <strong>{{ user.username }}</strong>
                  <span class="full-name">{{ user.name }}</span>
                </div>
              </td>
              <td class="cell-email">{{ user.email }}</td>
              <td class="cell-dni">{{ user.dni || '-' }}</td>
              <td class="cell-verified">
                <span :class="['badge', user.is_email_verified ? 'badge-success' : 'badge-warning']">
                  {{ user.is_email_verified ? 'Sí' : 'No' }}
                </span>
              </td>
              <td class="cell-role">
                <select v-model="user.role" @change="handleRoleChange(user, $event.target.value)" class="role-select"
                  :disabled="user.id === authStore.user?.id">
                  <option value="user">Usuario (User)</option>
                  <option value="pro">Premium (Pro)</option>
                  <option value="admin">Administrador (Admin)</option>
                </select>
              </td>
              <td class="cell-status">
                <span :class="['badge', user.banned_at ? 'badge-danger' : 'badge-success']">
                  {{ user.banned_at ? 'Baneado' : 'Activo' }}
                </span>
              </td>
              <td class="cell-date">{{ formatDate(user.created_at) }}</td>
              <td class="cell-actions">
                <button v-if="!isSelfUser(user)" class="actions-trigger" type="button"
                  :class="{ 'is-active': openActionsMenuId === user.id }"
                  :aria-expanded="openActionsMenuId === user.id"
                  @click.stop="toggleActionsMenu(user.id, $event)" title="Acciones">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="5" r="1.5" /><circle cx="12" cy="12" r="1.5" /><circle cx="12" cy="19" r="1.5" />
                  </svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- =============== PESTAÑA: LIBROS =============== -->
    <div v-show="activeTab === 'libros'">
      <div v-if="booksLoading" class="state-container">
        <div class="spinner"></div>
        <p>Cargando inventario...</p>
      </div>

      <div v-else-if="booksError" class="state-container error-state">
        <p>{{ booksError }}</p>
        <button @click="fetchBooks" class="retry-btn">Reintentar</button>
      </div>

      <div v-else class="table-container">
        <div class="search-bar-container actions-bar">
          <input type="search" v-model="booksSearchQuery" placeholder="Buscar por título o autor..."
            class="admin-search-input" />
          <button class="add-btn" @click="openModal">
            <span class="plus-icon">+</span> Añadir Libro
          </button>
        </div>
        <table class="users-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Título</th>
              <th>Autor</th>
              <th>Stock</th>
              <th>Disponibilidad</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="book in filteredBooks" :key="book.id">
              <td class="cell-id">#{{ book.id }}</td>
              <td class="cell-user">
                <strong>{{ book.titulo_es || book.titulo }}</strong>
              </td>
              <td class="cell-email">{{ book.autor }}</td>
              <td class="cell-id font-bold">{{ book.stock }} ud.</td>
              <td class="cell-verified">
                <span :class="['badge', book.stock > 0 ? 'badge-success' : 'badge-warning']">
                  {{ book.stock > 0 ? 'Disponible' : 'Agotado' }}
                </span>
              </td>
              <td class="cell-role">
                <button class="add-btn" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;"
                  @click="openEditModal(book)">Editar</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- =============== PESTAÑA: PRÉSTAMOS =============== -->
    <div v-show="activeTab === 'prestamos'">
      <div v-if="prestamosLoading" class="state-container">
        <div class="spinner"></div>
        <p>Cargando préstamos...</p>
      </div>

      <div v-else-if="prestamosError" class="state-container error-state">
        <p>{{ prestamosError }}</p>
        <button @click="fetchPrestamos" class="retry-btn">Reintentar</button>
      </div>

      <div v-else class="table-container">
        <div class="search-bar-container actions-bar">
          <input type="search" v-model="prestamosSearchQuery" placeholder="Buscar por usuario, libro o ID..."
            class="admin-search-input" />
          <button class="add-btn" @click="openPrestamoModal">
            <span class="plus-icon">+</span> Nuevo Préstamo
          </button>
        </div>
        <table class="users-table">
          <thead>
            <tr>
              <th>ID Préstamo</th>
              <th>Usuario</th>
              <th>Libro Reclamado</th>
              <th>Días Restantes</th>
              <th>Control de Estado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="rent in filteredPrestamos" :key="rent.prestamo_id">
              <td class="cell-id">#{{ rent.prestamo_id }}</td>
              <td class="cell-user">
                <strong>{{ rent.nombre_usuario || getUserName(rent.usuario_id) }}</strong>
              </td>
              <td class="cell-email">{{ rent.titulo }}</td>
              <td class="cell-verified">
                <span class="badge" :class="{
                  'badge-success': rent.estado !== 'devuelto' && typeof getDaysRemaining(rent.fecha_devolucion) === 'number' && getDaysRemaining(rent.fecha_devolucion) >= 0,
                  'badge-danger': rent.estado !== 'devuelto' && typeof getDaysRemaining(rent.fecha_devolucion) === 'number' && getDaysRemaining(rent.fecha_devolucion) < 0,
                  'badge-warning': rent.estado !== 'devuelto' && typeof getDaysRemaining(rent.fecha_devolucion) === 'string',
                  'badge-returned': rent.estado === 'devuelto'
                }" style="font-size: 0.9rem; padding: 0.4rem 0.8rem;">
                  {{ rent.estado === 'devuelto' ? 'DEVUELTO' : getDaysRemaining(rent.fecha_devolucion) + (typeof
                    getDaysRemaining(rent.fecha_devolucion) === 'number' ? ' días' : '') }}
                </span>
              </td>
              <td class="cell-role">
                <div class="status-controls">
                  <div v-if="rent.estado === 'devuelto'" class="return-date-text">
                    {{ rent.fecha_entregado ? 'Devuelto el ' + formatDate(rent.fecha_entregado) : 'Desconocida' }}
                  </div>

                  <button v-if="rent.estado === 'pendiente'" @click="handlePrestamoStatusUpdate(rent, 'activo')"
                    class="btn-activate">Activar</button>

                  <button v-if="rent.estado === 'activo'" @click="handlePrestamoStatusUpdate(rent, 'devuelto')"
                    class="btn-return">Devolver</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- =============== MODAL NUEVO PRÉSTAMO =============== -->
    <div v-if="showPrestamoModal" class="modal-overlay" @click.self="showPrestamoModal = false">
      <div class="modal-content">
        <h2>Crear Nuevo Préstamo</h2>
        <p style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 1.5rem;">Crea un préstamo directo asociando el DNI
          del usuario y el título exacto del libro.</p>

        <div v-if="prestamoError" class="modal-error">{{ prestamoError }}</div>

        <form @submit.prevent="handleCreatePrestamo" class="book-form">
          <div class="form-grid">
            <div class="input-group">
              <label>DNI del Usuario</label>
              <input type="text" v-model="prestamoData.dni" required placeholder="Ej. 12345678X" />
            </div>
            <div class="input-group relative">
              <label>Título del Libro</label>
              <input type="text" v-model="prestamoData.libro_titulo" required placeholder="Ej. El Resplandor"
                @focus="showBookSuggestions = true" @blur="setTimeout(() => showBookSuggestions = false, 200)" />
              <!-- Sugerencias -->
              <ul v-if="showBookSuggestions && filteredBookSuggestions.length > 0" class="suggestions-list">
                <li v-for="book in filteredBookSuggestions" :key="book.id"
                  @click="selectBookSuggestion(book.titulo_es || book.titulo)">
                  {{ book.titulo_es || book.titulo }}
                </li>
              </ul>
            </div>
            <div class="input-group">
              <label>Fecha de Devolución (Opcional)</label>
              <input type="date" v-model="prestamoData.fecha_devolucion"
                :min="new Date().toISOString().split('T')[0]" />
              <small style="color: #64748b; margin-top: 0.3rem;">Por defecto: +15 días.</small>
            </div>
          </div>

          <div class="modal-actions">
            <button type="button" class="cancel-btn" @click="showPrestamoModal = false"
              :disabled="prestamoLoading">Cancelar</button>
            <button type="submit" class="save-btn" :disabled="prestamoLoading">
              {{ prestamoLoading ? 'Creando...' : 'Crear Préstamo' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- =============== MODAL NUEVO USUARIO =============== -->
    <div v-if="showUserModal" class="modal-overlay" @click.self="showUserModal = false">
      <div class="modal-content">
        <h2>Registrar Nuevo Usuario</h2>
        <p style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 1.5rem;">Crea una cuenta de usuario directamente sin
          verificación de email.</p>

        <div v-if="userError" class="modal-error">{{ userError }}</div>

        <form @submit.prevent="handleAdminRegister" class="book-form">
          <div class="form-grid">
            <div class="input-group">
              <label>Nombre Completo</label>
              <input type="text" v-model="userData.name" required placeholder="Ej. Juan Pérez" />
            </div>
            <div class="input-group">
              <label>Nombre de Usuario (Nick)</label>
              <input type="text" v-model="userData.username" required placeholder="Ej. jperez123" />
            </div>
            <div class="input-group">
              <label>Correo Electrónico</label>
              <input type="email" v-model="userData.email" required placeholder="juan@ejemplo.com" />
            </div>
            <div class="input-group">
              <label>DNI</label>
              <input type="text" v-model="userData.dni" required placeholder="12345678X" />
            </div>
            <div class="input-group">
              <label>Contraseña Provisional</label>
              <input type="password" v-model="userData.password" required placeholder="Mín. 6 caracteres" />
            </div>
            <div class="input-group">
              <label>Rol del Usuario</label>
              <select v-model="userData.role" class="role-select" style="width: 100%; height: 42px;">
                <option value="user">Lector (Estándar)</option>
                <option value="admin">Administrador</option>
              </select>
            </div>
          </div>

          <div class="modal-actions">
            <button type="button" class="cancel-btn" @click="showUserModal = false"
              :disabled="userLoading">Cancelar</button>
            <button type="submit" class="save-btn" :disabled="userLoading">
              {{ userLoading ? 'Registrando...' : 'Registrar Usuario' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- =============== MODAL CONFIRMAR BORRADO =============== -->
    <div v-if="showDeleteModal" class="modal-overlay" @click.self="showDeleteModal = false">
      <div class="modal-content delete-modal">
        <h2 class="danger-title">¿Dar de baja usuario?</h2>
        <div class="warning-box">
          <p>Estás a punto de eliminar permanentemente la cuenta de:</p>
          <strong class="user-to-del">{{ userToDelete?.name }} ({{ userToDelete?.email }})</strong>
          <p class="warning-text">Esta acción no se puede deshacer y el usuario perderá todo acceso.</p>
        </div>

        <div v-if="deleteError" class="modal-error">{{ deleteError }}</div>

        <div class="modal-actions">
          <button class="cancel-btn" @click="showDeleteModal = false" :disabled="deleteLoading">Cancelar</button>
          <button class="save-btn delete-confirm-btn" @click="handleAdminDelete" :disabled="deleteLoading">
            {{ deleteLoading ? 'Eliminando...' : 'Confirmar Baja' }}
          </button>
        </div>
      </div>
    </div>

    <!-- =============== MODAL AÑADIR LIBRO =============== -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal-content">
        <h2>{{ isEditing ? 'Editar Libro' : 'Añadir Nuevo Libro' }}</h2>
        <div v-if="modalError" class="modal-error">{{ modalError }}</div>

        <form @submit.prevent="submitBook" class="book-form">
          <div class="form-grid">
            <div class="input-group">
              <label>ISBN (ID)</label>
              <input type="text" v-model="newBook.google_id" required placeholder="Ej. 978841..." />
            </div>
            <div class="input-group">
              <label>Título del Libro</label>
              <input type="text" v-model="newBook.titulo" required placeholder="El Resplandor" />
            </div>
            <div class="input-group">
              <label>Autor</label>
              <input type="text" v-model="newBook.autor" required placeholder="Stephen King" />
            </div>
            <div class="input-group">
              <label>Categoría</label>
              <input type="text" v-model="newBook.categoria" required placeholder="Terror, Ficción..." />
            </div>
            <div class="input-group">
              <label>Stock Total</label>
              <input type="number" v-model.number="newBook.stock" required min="1" />
            </div>
          </div>

          <!-- Subida de imagen -->
          <div class="file-upload-section">
            <label class="file-label">Portada del Libro (Imagen)</label>
            <div class="upload-area">
              <input type="file" accept="image/*" @change="handleFileChange" id="coverUpload"
                class="hidden-file-input" />
              <label for="coverUpload" class="upload-box" :class="{ 'has-image': previewUrl }">
                <div v-if="!previewUrl" class="upload-placeholder">
                  <span>📸</span>
                  <p>Añadir archivo</p>
                </div>
                <img v-else :src="previewUrl" alt="Previsualización" class="cover-preview" />
              </label>
            </div>
          </div>

          <div class="modal-actions">
            <button type="button" class="cancel-btn" @click="closeModal" :disabled="savingBook">Cancelar</button>
            <button type="submit" class="save-btn" :disabled="savingBook">
              {{ savingBook ? 'Guardando...' : (isEditing ? 'Guardar Cambios' : 'Guardar Libro') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <Teleport to="body">
    <div v-if="openActionsUser" class="actions-menu" :style="actionsMenuStyle" @click.stop>
      <button v-if="!openActionsUser.banned_at" class="action-item" @click="handleForceLogout(openActionsUser)">
        Cerrar sesión
      </button>
      <button class="action-item" :class="openActionsUser.banned_at ? 'action-success' : 'action-danger'"
        @click="handleToggleBan(openActionsUser)">
        {{ openActionsUser.banned_at ? 'Desbanear' : 'Banear' }}
      </button>
      <button class="action-item action-danger" @click="closeActionsMenu(); confirmDeleteUser(openActionsUser)">
        Dar de baja
      </button>
    </div>
  </Teleport>
</template>

<style scoped>
.admin-page {
  max-width: 1200px;
  margin: 0 auto;
  padding: 2rem;
  color: #e3e5eb;
  animation: fadeIn 0.4s ease;
}

.admin-header {
  margin-bottom: 2rem;
  padding-bottom: 0.5rem;
}

.admin-title {
  font-size: 2.5rem;
  font-weight: 800;
  margin-bottom: 0.5rem;
  color: #ffffff;
  background: linear-gradient(135deg, #ff9d00, #ed4d4d);
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
}

.admin-subtitle {
  color: #bbc2d2;
  font-size: 1.1rem;
  margin-bottom: 2rem;
}

/* Tabs */
.admin-tabs {
  display: flex;
  gap: 1rem;
  border-bottom: 1px solid #2d3348;
}

.tab-btn {
  background: transparent;
  color: #97a0b7;
  border: none;
  padding: 0.8rem 1.5rem;
  font-size: 1rem;
  font-weight: 600;
  cursor: pointer;
  position: relative;
  transition: color 0.2s;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.tab-icon-img {
  width: 1.3rem;
  height: 1.3rem;
  object-fit: contain;
  opacity: 0.7;
  transition: opacity 0.2s;
  /* Para intentar colorear SVG si es blanco/negro (opcional, filter works differently vs mask) */
}

.tab-btn:hover {
  color: #e3e5eb;
}

.tab-btn:hover .tab-icon-img {
  opacity: 1;
}

.tab-btn.active {
  color: #ed4d4d;
}

.tab-btn.active .tab-icon-img {
  opacity: 1;
}

.tab-btn.active::after {
  content: '';
  position: absolute;
  bottom: -1px;
  left: 0;
  right: 0;
  height: 2px;
  background: #ed4d4d;
  box-shadow: 0 -2px 10px rgba(237, 77, 77, 0.5);
}

/* Global table states */
.state-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  background: rgba(17, 20, 30, 0.4);
  backdrop-filter: blur(10px);
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.05);
  color: #97a0b7;
  margin-top: 1.5rem;
}

.error-state {
  color: #ff8a8a;
  border-color: rgba(237, 77, 77, 0.3);
  background: rgba(237, 77, 77, 0.05);
}

.retry-btn {
  margin-top: 1rem;
  padding: 0.5rem 1rem;
  background: #ed4d4d;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: bold;
}

.retry-btn:hover {
  background: #d64242;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid rgba(255, 157, 0, 0.2);
  border-top-color: #ff9d00;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 1rem;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.table-container {
  overflow-x: auto;
  background: rgba(17, 20, 30, 0.4);
  backdrop-filter: blur(10px);
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.05);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
  margin-top: 1.5rem;
}

.users-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

.users-table th,
.users-table td {
  padding: 0.6rem 0.5rem;
  border-bottom: 1px solid #1f2335;
  font-size: 0.8rem;
}

.search-bar-container {
  padding: 1.2rem;
  border-bottom: 1px solid #1f2335;
  background: rgba(10, 12, 18, 0.4);
}

.admin-search-input {
  width: 100%;
  max-width: 450px;
  background: #0a0c12;
  border: 1px solid #2d3348;
  color: #e3e5eb;
  padding: 0.7rem 1.2rem;
  border-radius: 8px;
  font-size: 0.95rem;
  transition: all 0.2s ease;
}

.admin-search-input::placeholder {
  color: #5c6480;
}

.admin-search-input:focus {
  outline: none;
  border-color: #ed4d4d;
  box-shadow: 0 0 0 3px rgba(237, 77, 77, 0.15);
}

.users-table th {
  background: #0b0d14;
  color: #7a839e;
  font-weight: 600;
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  position: sticky;
  top: 0;
}

.users-table tbody tr {
  transition: background 0.2s;
}

.users-table tbody tr:hover {
  background: rgba(255, 255, 255, 0.03);
}

.cell-id {
  color: #5c6480;
  font-size: 0.85rem;
  width: 50px;
}

.font-bold {
  font-weight: bold;
  color: #e3e5eb;
}

.cell-user {
  /* Retiramos display flex directo sobre td para que no rompa el border-bottom nativo de display: table-cell */
}

.cell-user strong {
  display: block;
  color: #f0f2f7;
  color: #f0f2f7;
  font-size: 1rem;
}

.full-name {
  font-size: 0.8rem;
  color: #7a839e;
  margin-top: 0.2rem;
}

.cell-email {
  color: #97a0b7;
  max-width: 140px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.badge {
  padding: 0.3rem 0.6rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
}

.badge-success {
  background: rgba(34, 197, 94, 0.15);
  color: #4ade80;
  border: 1px solid rgba(34, 197, 94, 0.3);
}

.badge-danger {
  background: rgba(237, 77, 77, 0.15);
  color: #ed4d4d;
  border-color: rgba(237, 77, 77, 0.4);
}

.badge-warning {
  background: rgba(255, 193, 7, 0.15);
  color: #ffc107;
  border-color: rgba(255, 193, 7, 0.4);
}

.badge-returned {
  background: rgba(151, 160, 183, 0.15);
  color: #97a0b7;
  border-color: rgba(151, 160, 183, 0.4);
}

/* === 3-dot actions menu (admin users row) === */
.cell-actions {
  width: 72px;
  text-align: center;
  position: relative;
}

.actions-wrap {
  position: relative;
  display: inline-block;
}

.actions-trigger {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: 1px solid transparent;
  background: transparent;
  color: #e3e5eb;
  cursor: pointer;
  transition: all 0.2s ease;
}

.actions-trigger svg {
  width: 18px;
  height: 18px;
}

.actions-trigger:hover,
.actions-trigger[aria-expanded="true"] {
  background: rgba(255, 255, 255, 0.08);
  border-color: rgba(255, 255, 255, 0.15);
}


.users-table tbody tr.is-banned td {
  opacity: 0.55;
}

.users-table tbody tr.is-banned:hover td {
  opacity: 0.75;
}

.users-table tbody tr.is-banned td.cell-actions,
.users-table tbody tr.is-banned:hover td.cell-actions {
  opacity: 1;
}

.role-select {
  background: #0a0c12;
  color: #e3e5eb;
  border: 1px solid #2d3348;
  padding: 0.4rem 0.5rem;
  border-radius: 6px;
  font-size: 0.8rem;
  outline: none;
  cursor: pointer;
  max-width: 120px;
  transition: border-color 0.2s;
}

.role-select:hover:not(:disabled) {
  border-color: #ff9d00;
}

.role-select:focus {
  outline: none;
  border-color: #ed4d4d;
}

.role-select:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.cell-dni {
  color: #97a0b7;
  width: 90px;
}

.cell-date {
  color: #5c6480;
  font-size: 0.8rem;
}

@media (max-width: 1100px) {

  .cell-date,
  th:nth-child(7) {
    display: none;
  }
}

@media (max-width: 950px) {

  .cell-email,
  th:nth-child(3) {
    display: none;
  }
}

@media (max-width: 768px) {

  .cell-dni,
  th:nth-child(4) {
    display: none;
  }
}

.badge-danger {
  background: rgba(237, 77, 77, 0.2) !important;
  color: #ff8a8a !important;
  border: 1px solid rgba(237, 77, 77, 0.3) !important;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (max-width: 768px) {
  .admin-page {
    padding: 1rem;
  }

  .users-table th,
  .users-table td {
    padding: 0.8rem;
  }

  .cell-email,
  .cell-date {
    display: none;
  }
}

/* --- ADD BUTTON --- */
.actions-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}

.add-btn {
  background: #22c55e;
  color: #fff;
  border: none;
  border-radius: 6px;
  padding: 0.7rem 1.2rem;
  font-weight: bold;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  transition: background 0.2s;
}

.add-btn:hover {
  background: #16a34a;
}

.plus-icon {
  font-size: 1.2rem;
  line-height: 1;
}

@media (max-width: 600px) {
  .actions-bar {
    flex-direction: column;
    align-items: stretch;
  }
}

/* --- MODAL --- */
.status-controls {
  display: flex;
  align-items: center;
  gap: 0.8rem;
}

.return-date-text {
  font-size: 0.8rem;
  color: #97a0b7;
  font-weight: 500;
  white-space: nowrap;
}

.status-badge {
  padding: 0.3rem 0.6rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 700;
  min-width: 80px;
  text-align: center;
}

.status-badge.pendiente {
  background: rgba(255, 193, 7, 0.15);
  color: #ffc107;
  border: 1px solid rgba(255, 193, 7, 0.3);
}

.status-badge.activo {
  background: rgba(34, 197, 94, 0.2);
  color: #4ade80;
  border: 1px solid rgba(34, 197, 94, 0.3);
}

.status-badge.devuelto {
  background: rgba(151, 160, 183, 0.2);
  color: #97a0b7;
  border: 1px solid rgba(151, 160, 183, 0.3);
}

.btn-activate {
  background: rgba(34, 197, 94, 0.15);
  color: #4ade80;
  border: 1px solid rgba(34, 197, 94, 0.4);
  padding: 0.3rem 0.6rem;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.8rem;
  transition: all 0.2s;
}

.btn-activate:hover {
  background: rgba(34, 197, 94, 0.3);
}

.delete-btn-table {
  background: transparent;
  border: 1px solid rgba(237, 77, 77, 0.3);
  padding: 0.5rem;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.delete-btn-table:hover {
  background: rgba(237, 77, 77, 0.15);
  border-color: #ed4d4d;
}

.delete-btn-table-text {
  background: rgba(237, 77, 77, 0.1);
  border: 1px solid rgba(237, 77, 77, 0.3);
  color: #ff8a8a;
  padding: 0.4rem 0.6rem;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.75rem;
  font-weight: 600;
  white-space: nowrap;
  transition: all 0.2s;
}

.delete-btn-table-text:hover {
  background: #ed4d4d;
  color: white;
  border-color: #ed4d4d;
}

.delete-modal {
  max-width: 450px;
  text-align: center;
}

.danger-title {
  color: #ff5e5e !important;
  font-weight: 800;
}

.warning-box {
  background: rgba(237, 77, 77, 0.05);
  border: 1px solid rgba(237, 77, 77, 0.2);
  padding: 1.5rem;
  border-radius: 12px;
  margin-bottom: 2rem;
}

.user-to-del {
  display: block;
  margin: 1rem 0;
  font-size: 1.1rem;
  color: #ffffff;
}

.warning-text {
  font-size: 0.85rem;
  color: #94a3b8;
  margin-top: 1rem;
  font-style: italic;
}

.delete-confirm-btn {
  background: #ed4d4d !important;
}

.delete-confirm-btn:hover {
  background: #ff3b3b !important;
  box-shadow: 0 0 15px rgba(237, 77, 77, 0.4);
}

.relative {
  position: relative;
}

.suggestions-list {
  position: absolute;
  top: 100%;
  left: 0;
  right: 0;
  background: #1a1e2e;
  border: 1px solid #2d3348;
  border-radius: 8px;
  margin: 0.25rem 0 0;
  padding: 0.5rem 0;
  list-style: none;
  z-index: 100;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.4);
}

.suggestions-list li {
  padding: 0.6rem 1rem;
  font-size: 0.9rem;
  cursor: pointer;
  color: #c6cbdb;
  transition: all 0.2s;
}

.suggestions-list li:hover {
  background: rgba(237, 77, 77, 0.15);
  color: #ff8a8a;
}

.btn-return {
  background: rgba(237, 77, 77, 0.15);
  color: #ed4d4d;
  border: 1px solid rgba(237, 77, 77, 0.4);
  padding: 0.3rem 0.6rem;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.8rem;
  transition: all 0.2s;
}

.btn-return:hover {
  background: rgba(237, 77, 77, 0.3);
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(4, 5, 8, 0.85);
  backdrop-filter: blur(5px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.modal-content {
  background: #11141e;
  border: 1px solid #1f2335;
  border-radius: 12px;
  padding: 2rem;
  width: 90%;
  max-width: 650px;
  box-shadow: 0 15px 40px rgba(0, 0, 0, 0.5);
  animation: modalIn 0.3s ease;
}

@keyframes modalIn {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.modal-content h2 {
  margin: 0 0 1.5rem;
  color: #fff;
}

.modal-error {
  background: rgba(237, 77, 77, 0.1);
  color: #ff8a8a;
  padding: 0.8rem;
  border-radius: 6px;
  margin-bottom: 1.5rem;
  border: 1px solid rgba(237, 77, 77, 0.3);
}

.book-form {
  display: flex;
  flex-direction: column;
  gap: 1.2rem;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.2rem;
}

.input-group {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
}

.input-group label,
.file-label {
  font-size: 0.9rem;
  color: #97a0b7;
}

.input-group input {
  background: #0a0c12;
  border: 1px solid #2d3348;
  color: #e3e5eb;
  padding: 0.7rem;
  border-radius: 6px;
  font-size: 0.95rem;
}

.input-group input:focus {
  outline: none;
  border-color: #ed4d4d;
}

/* Eliminar las fechas raras nativas de los inputs numéricos (Spinner) */
.input-group input[type="number"]::-webkit-outer-spin-button,
.input-group input[type="number"]::-webkit-inner-spin-button {
  -webkit-appearance: none;
  appearance: none;
  margin: 0;
}

.input-group input[type="number"] {
  -moz-appearance: textfield;
}

.file-upload-section {
  margin-top: 0.5rem;
}

.upload-area {
  margin-top: 0.5rem;
}

.hidden-file-input {
  display: none;
}

.upload-box {
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px dashed #2d3348;
  border-radius: 8px;
  height: 180px;
  width: 130px;
  background: #0a0c12;
  cursor: pointer;
  overflow: hidden;
  transition: border-color 0.2s;
}

.upload-box:hover {
  border-color: #ed4d4d;
}

.upload-box.has-image {
  border-style: solid;
  border-color: #33394b;
}

.upload-placeholder {
  text-align: center;
  color: #5c6480;
}

.upload-placeholder span {
  font-size: 2rem;
}

.upload-placeholder p {
  font-size: 0.8rem;
  margin: 0.5rem 0 0;
}

.cover-preview {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid #1f2335;
}

.cancel-btn {
  background: transparent;
  color: #97a0b7;
  border: 1px solid #2d3348;
  padding: 0.7rem 1.5rem;
  border-radius: 6px;
  cursor: pointer;
}

.cancel-btn:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.05);
}

.save-btn {
  background: #ed4d4d;
  color: #fff;
  border: none;
  padding: 0.7rem 1.5rem;
  border-radius: 6px;
  font-weight: bold;
  cursor: pointer;
}

.save-btn:hover {
  background: #ff5e5e;
}

.save-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>

<!-- Estilos no-scoped para el menú teleportado al <body>: los selectores .actions-menu
     viven fuera del árbol del componente, así que no pueden ser scoped. -->
<style>
.actions-menu {
  min-width: 170px;
  background: #1a1d25;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 8px;
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.45);
  padding: 0.3rem;
  display: flex;
  flex-direction: column;
  gap: 2px;
  animation: actionsMenuFadeIn 0.15s ease-out;
}

@keyframes actionsMenuFadeIn {
  from { opacity: 0; transform: translateY(-4px); }
  to { opacity: 1; transform: translateY(0); }
}

.actions-menu .action-item {
  background: transparent;
  border: none;
  color: #e3e5eb;
  padding: 0.5rem 0.8rem;
  font-size: 0.85rem;
  text-align: left;
  border-radius: 6px;
  cursor: pointer;
  transition: background 0.15s ease;
  font-family: inherit;
}

.actions-menu .action-item:hover {
  background: rgba(255, 255, 255, 0.08);
}

.actions-menu .action-item.action-danger {
  color: #ed4d4d;
}

.actions-menu .action-item.action-danger:hover {
  background: rgba(237, 77, 77, 0.12);
}

.actions-menu .action-item.action-success {
  color: #4ade80;
}

.actions-menu .action-item.action-success:hover {
  background: rgba(34, 197, 94, 0.12);
}
</style>
