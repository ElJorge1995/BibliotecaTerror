import requests
import mysql.connector
from mysql.connector import Error
import time
import sys
import re

# ============================================================================
# Importador de libros desde Google Books API → BBDD librum-tenebris
# Uso manual desde terminal:
#   python importar_libros_googlebooks.py
# Requiere: pip install requests mysql-connector-python
# ============================================================================

DB_HOST = "localhost"
DB_NAME = "librum-tenebris"
DB_USER = "root"
DB_PASS = ""

TARGET_BOOKS = 100
MAX_RESULTS = 40  # Google Books permite max 40 por petición
QUERY = "subject:horror"
LANG = "en"


def get_db_connection():
    try:
        connection = mysql.connector.connect(
            host=DB_HOST,
            database=DB_NAME,
            user=DB_USER,
            password=DB_PASS
        )
        return connection
    except Error as e:
        print(f"Error conectando a MySQL: {e}")
        sys.exit(1)


def clean_description(text):
    if not text:
        return ""
    # Quita etiquetas HTML que a veces trae Google
    return re.sub(r'<.*?>', '', text)


def fetch_and_insert_books():
    connection = get_db_connection()
    cursor = connection.cursor()

    print(f"Buscando {TARGET_BOOKS} libros de terror en Google Books...")

    inserted_count = 0
    start_index = 0

    while inserted_count < TARGET_BOOKS:
        url = (
            f"https://www.googleapis.com/books/v1/volumes"
            f"?q={QUERY}&langRestrict={LANG}"
            f"&maxResults={MAX_RESULTS}&startIndex={start_index}"
        )
        print(f"Consultando startIndex={start_index}...")

        try:
            response = requests.get(url, timeout=15)
            if response.status_code != 200:
                print(f"Error de API HTTP {response.status_code}")
                time.sleep(5)
                continue

            data = response.json()
            items = data.get('items', [])

            if not items:
                print("No hay más libros disponibles.")
                break

            for book in items:
                if inserted_count >= TARGET_BOOKS:
                    break

                google_id = book.get('id')
                info = book.get('volumeInfo', {})

                title = info.get('title')
                authors = info.get('authors', [])
                author = authors[0] if authors else None
                description = info.get('description')
                image_links = info.get('imageLinks', {})
                cover = image_links.get('thumbnail') or image_links.get('smallThumbnail')
                categories = info.get('categories', [])
                category = categories[0] if categories else "Horror"
                rating = info.get('averageRating')

                # Filtros mínimos: descartar libros incompletos
                if not google_id or not title or not author or not description or not cover:
                    continue

                description = clean_description(description)

                insert_query = """
                INSERT IGNORE INTO libros
                (google_id, titulo, autor, descripcion, portada, categoria, rating)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
                """

                try:
                    cursor.execute(insert_query, (
                        google_id,
                        title,
                        author,
                        description,
                        cover,
                        category,
                        rating
                    ))

                    if cursor.rowcount > 0:
                        inserted_count += 1
                        print(f"[{inserted_count}/{TARGET_BOOKS}] Insertado: {title} — {author}")
                        connection.commit()

                except Error:
                    # ignorar duplicados u otros errores puntuales
                    pass

            start_index += MAX_RESULTS
            time.sleep(1)  # respetar rate limit de Google

        except Exception as e:
            print(f"Error durante la petición: {e}")
            time.sleep(2)

    if connection.is_connected():
        cursor.close()
        connection.close()

    print(f"\n¡Completado! Se han insertado {inserted_count} libros en la base de datos.")


if __name__ == "__main__":
    fetch_and_insert_books()
