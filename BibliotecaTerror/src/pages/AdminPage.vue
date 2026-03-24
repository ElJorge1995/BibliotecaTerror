<script setup>
import { ref, computed, onMounted, watch } from 'vue'
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
const searchQuery = ref('')

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
  if (!searchQuery.value) return users.value
  const query = searchQuery.value.toLowerCase()
  return users.value.filter(user => {
    return (user.name && user.name.toLowerCase().includes(query)) ||
           (user.username && user.username.toLowerCase().includes(query)) ||
           (user.email && user.email.toLowerCase().includes(query))
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
  } catch(err) {
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
  } catch(err) {
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
        <button 
          :class="['tab-btn', { active: activeTab === 'usuarios' }]" 
          @click="activeTab = 'usuarios'"
        >
          <img :src="personIcon" class="tab-icon-img" alt="Usuarios" /> Usuarios
        </button>
        <button 
          :class="['tab-btn', { active: activeTab === 'libros' }]" 
          @click="activeTab = 'libros'"
        >
          <img :src="bookIcon" class="tab-icon-img" alt="Inventario" /> Inventario
        </button>
        <button 
          :class="['tab-btn', { active: activeTab === 'prestamos' }]" 
          @click="activeTab = 'prestamos'"
        >
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
        <div class="search-bar-container">
          <input 
            type="search" 
            v-model="searchQuery" 
            placeholder="Buscar por nombre, usuario o email..." 
            class="admin-search-input"
          />
        </div>
        <table class="users-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Usuario</th>
              <th>Email</th>
              <th>Verificado</th>
              <th>Rol</th>
              <th>Registro</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in filteredUsers" :key="user.id">
              <td class="cell-id">#{{ user.id }}</td>
              <td class="cell-user">
                <strong>{{ user.username }}</strong>
                <span class="full-name">{{ user.name }}</span>
              </td>
              <td class="cell-email">{{ user.email }}</td>
              <td class="cell-verified">
                <span :class="['badge', user.is_email_verified ? 'badge-success' : 'badge-warning']">
                  {{ user.is_email_verified ? 'Sí' : 'No' }}
                </span>
              </td>
              <td class="cell-role">
                <select 
                  v-model="user.role" 
                  @change="handleRoleChange(user, $event.target.value)"
                  class="role-select"
                  :disabled="user.id === authStore.user?.id"
                >
                  <option value="user">Usuario (User)</option>
                  <option value="pro">Premium (Pro)</option>
                  <option value="admin">Administrador (Admin)</option>
                </select>
              </td>
              <td class="cell-date">{{ formatDate(user.created_at) }}</td>
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
          <input 
            type="search" 
            v-model="booksSearchQuery" 
            placeholder="Buscar por título o autor..." 
            class="admin-search-input"
          />
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
                <button class="add-btn" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;" @click="openEditModal(book)">Editar</button>
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
        <div class="search-bar-container">
          <input 
            type="search" 
            v-model="prestamosSearchQuery" 
            placeholder="Buscar por usuario, libro o ID..." 
            class="admin-search-input"
          />
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
                <span 
                  class="badge" 
                  :class="{
                    'badge-success': rent.estado !== 'devuelto' && typeof getDaysRemaining(rent.fecha_devolucion) === 'number' && getDaysRemaining(rent.fecha_devolucion) >= 0,
                    'badge-danger': rent.estado !== 'devuelto' && typeof getDaysRemaining(rent.fecha_devolucion) === 'number' && getDaysRemaining(rent.fecha_devolucion) < 0,
                    'badge-warning': rent.estado !== 'devuelto' && typeof getDaysRemaining(rent.fecha_devolucion) === 'string',
                    'badge-returned': rent.estado === 'devuelto'
                  }" 
                  style="font-size: 0.9rem; padding: 0.4rem 0.8rem;"
                >
                   {{ rent.estado === 'devuelto' ? 'DEVUELTO' : getDaysRemaining(rent.fecha_devolucion) + (typeof getDaysRemaining(rent.fecha_devolucion) === 'number' ? ' días' : '') }}
                </span>
              </td>
              <td class="cell-role">
                <div class="status-controls">
                  <div v-if="rent.estado === 'devuelto'" class="return-date-text">
                    {{ rent.fecha_entregado ? 'Devuelto el ' + formatDate(rent.fecha_entregado) : 'Desconocida' }}
                  </div>
                  
                  <button 
                    v-if="rent.estado === 'pendiente'" 
                    @click="handlePrestamoStatusUpdate(rent, 'activo')"
                    class="btn-activate"
                  >Activar</button>

                  <button 
                    v-if="rent.estado === 'activo'" 
                    @click="handlePrestamoStatusUpdate(rent, 'devuelto')"
                    class="btn-return"
                  >Devolver</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
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
              <input type="file" accept="image/*" @change="handleFileChange" id="coverUpload" class="hidden-file-input" />
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
  background: #11141e;
  border-radius: 12px;
  border: 1px solid #1f2335;
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
  to { transform: rotate(360deg); }
}

.table-container {
  overflow-x: auto;
  background: #11141e;
  border-radius: 12px;
  border: 1px solid #1f2335;
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
  padding: 1rem 1.2rem;
  border-bottom: 1px solid #1f2335;
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
  font-size: 0.9rem;
  width: 60px;
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
  font-size: 0.9rem;
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

.role-select {
  background: #0a0c12;
  color: #e3e5eb;
  border: 1px solid #33394b;
  padding: 0.5rem 0.8rem;
  border-radius: 6px;
  font-size: 0.9rem;
  cursor: pointer;
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

.cell-date {
  color: #7a839e;
  font-size: 0.85rem;
}

.badge-danger {
  background: rgba(237, 77, 77, 0.2) !important;
  color: #ff8a8a !important;
  border: 1px solid rgba(237, 77, 77, 0.3) !important;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 768px) {
  .admin-page {
    padding: 1rem;
  }
  .users-table th,
  .users-table td {
    padding: 0.8rem;
  }
  .cell-email, .cell-date {
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
.add-btn:hover { background: #16a34a; }
.plus-icon { font-size: 1.2rem; line-height: 1; }

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
.status-badge.pendiente { background: rgba(255, 193, 7, 0.15); color: #ffc107; border: 1px solid rgba(255, 193, 7, 0.3); }
.status-badge.activo { background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); }
.status-badge.devuelto { background: rgba(151, 160, 183, 0.2); color: #97a0b7; border: 1px solid rgba(151, 160, 183, 0.3); }

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
  top: 0; left: 0; right: 0; bottom: 0;
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
  box-shadow: 0 15px 40px rgba(0,0,0,0.5);
  animation: modalIn 0.3s ease;
}

@keyframes modalIn {
  from { opacity: 0; transform: translateY(-20px); }
  to { opacity: 1; transform: translateY(0); }
}

.modal-content h2 { margin: 0 0 1.5rem; color: #fff; }

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

.input-group label, .file-label {
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
  margin: 0;
}

.input-group input[type="number"] {
  -moz-appearance: textfield;
}

.file-upload-section { margin-top: 0.5rem; }

.upload-area {
  margin-top: 0.5rem;
}

.hidden-file-input { display: none; }

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

.upload-box:hover { border-color: #ed4d4d; }
.upload-box.has-image { border-style: solid; border-color: #33394b; }

.upload-placeholder {
  text-align: center;
  color: #5c6480;
}
.upload-placeholder span { font-size: 2rem; }
.upload-placeholder p { font-size: 0.8rem; margin: 0.5rem 0 0; }

.cover-preview {
  width: 100%; height: 100%;
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
.cancel-btn:hover { color: #fff; background: rgba(255,255,255,0.05); }

.save-btn {
  background: #ed4d4d;
  color: #fff;
  border: none;
  padding: 0.7rem 1.5rem;
  border-radius: 6px;
  font-weight: bold;
  cursor: pointer;
}
.save-btn:hover { background: #ff5e5e; }
.save-btn:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
