<script setup>
import { ref, computed, onMounted } from 'vue'
import authApi from '../api/auth'
import { useAuthStore } from '../stores/auth'

const authStore = useAuthStore()
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
    // Optimistic update
    user.role = newRole
    await authApi.adminUpdateRole(user.id, newRole)
  } catch (err) {
    // Revert on error
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
      <p class="admin-subtitle">Gestiona los usuarios registrados en la plataforma.</p>
    </div>

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
  border-bottom: 1px solid #2d3348;
  padding-bottom: 1.5rem;
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
}

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

.cell-user {
  display: flex;
  flex-direction: column;
}

.cell-user strong {
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
.badge-warning {
  background: rgba(245, 158, 11, 0.15);
  color: #fbbf24;
  border: 1px solid rgba(245, 158, 11, 0.3);
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
</style>
