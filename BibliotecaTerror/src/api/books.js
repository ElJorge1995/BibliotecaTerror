import axios from 'axios'

const booksApi = axios.create({
  baseURL: 'http://localhost:8080',
  headers: {
    'Content-Type': 'application/json'
  }
})

export default {
  /**
   * Obtiene los últimos libros añadidos.
   * @param {number} limit - Número máximo de libros (por defecto 8)
   */
  getRecientes(limit = 8, usuarioId = null) {
    return booksApi.get('/libros_api.php', {
      params: { action: 'recientes', limit,  ...(usuarioId && { usuario_id: usuarioId }) }
    })
  },

  /**
   * Obtiene los libros mejor valorados (Top Rated).
   */
  getRecomendaciones(limit = 32, usuarioId = null) {
    return booksApi.get('/libros_api.php', {
      params: { action: 'recomendaciones', limit, ...(usuarioId && { usuario_id: usuarioId }) }
    })
  },

  /**
   * Busca libros por título o autor.
   * @param {string} query - Término de búsqueda
   */
  buscar(query, usuarioId = null) {
    return booksApi.get('/libros_api.php', {
      params: { action: 'buscar', q: query, ...(usuarioId && { usuario_id: usuarioId }) }
    })
  },

  /**
   * Obtiene la información detallada de un libro por ID.
   * @param {number|string} id - ID del libro
   */
  getById(id) {
    return booksApi.get('/libros_api.php', {
      params: { action: 'obtener', id }
    })
  },

  checkFavorito(usuarioId, libroId) {
    return booksApi.get('/libros_api.php', {
      params: { action: 'check_favorito', usuario_id: usuarioId, libro_id: libroId }
    })
  },

  toggleFavorito(usuarioId, libroId) {
    return booksApi.post('/libros_api.php?action=toggle_favorito', {
      usuario_id: usuarioId,
      libro_id: libroId
    })
  },

  getMisFavoritos(usuarioId) {
    return booksApi.get('/libros_api.php', {
      params: { action: 'mis_favoritos', usuario_id: usuarioId }
    })
  },

  getAllBooks(usuarioId = null) {
    return booksApi.get('/libros_api.php', {
      params: { action: 'todos', ...(usuarioId && { usuario_id: usuarioId }) }
    })
  },

  createBook(formData) {
    return booksApi.post('/libros_api.php?action=crear', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
  },

  updateBook(formData) {
    return booksApi.post('/libros_api.php?action=editar_libro', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
  },

  prestarLibro(usuarioId, libroId, nombreUsuario) {
    return booksApi.post('/libros_api.php?action=prestar', {
      usuario_id: usuarioId,
      libro_id: libroId,
      nombre_usuario: nombreUsuario
    })
  },

  getMisPrestamos(usuarioId) {
    return booksApi.get('/libros_api.php', {
      params: { 
        action: 'mis_prestamos',
        usuario_id: usuarioId
      }
    })
  },

  getAllPrestamos() {
    return booksApi.get('/libros_api.php', {
      params: { action: 'todos_prestamos' }
    })
  },

  updatePrestamoStatus(prestamoId, nuevoEstado) {
    return booksApi.post('/libros_api.php?action=actualizar_prestamo', {
      prestamo_id: prestamoId,
      estado: nuevoEstado
    })
  },

  valorarPrestamo(prestamoId, rating) {
    return booksApi.post('/libros_api.php?action=valorar_prestamo', {
      prestamo_id: prestamoId,
      rating: rating
    })
  },

  adminCrearPrestamo(data) {
    return booksApi.post('/libros_api.php?action=admin_crear_prestamo', data)
  }
}
