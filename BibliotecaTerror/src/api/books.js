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
  getRecientes(limit = 8) {
    return booksApi.get('/libros_api.php', {
      params: { action: 'recientes', limit }
    })
  },

  /**
   * Busca libros por título o autor.
   * @param {string} query - Término de búsqueda
   */
  buscar(query) {
    return booksApi.get('/libros_api.php', {
      params: { action: 'buscar', q: query }
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

  getAllBooks() {
    return booksApi.get('/libros_api.php', {
      params: { action: 'todos' }
    })
  },

  createBook(formData) {
    return booksApi.post('/libros_api.php?action=crear', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
  }
}
