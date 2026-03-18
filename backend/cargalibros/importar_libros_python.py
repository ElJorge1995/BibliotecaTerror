import requests
import mysql.connector
from mysql.connector import Error
import time
import sys
import re

DB_HOST = "localhost"
DB_NAME = "librum-tenebris"
DB_USER = "root"
DB_PASS = ""

TARGET_BOOKS = 100

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
        print(f"Error connecting to MySQL: {e}")
        sys.exit(1)

def clean_description(text):
    if not text:
        return ""
    # Remove simple HTML tags that might be present
    clean = re.compile('<.*?>')
    return re.sub(clean, '', text)

def fetch_and_insert_books():
    connection = get_db_connection()
    cursor = connection.cursor()
    
    print(f"Buscando {TARGET_BOOKS} libros de terror en OpenLibrary...")
    
    inserted_count = 0
    page = 1
    
    while inserted_count < TARGET_BOOKS:
        # Usamos subject: horror en OpenLibrary Search API
        url = f"https://openlibrary.org/search.json?subject=horror&limit=50&page={page}&language=eng"
        print(f"Consultando página {page}...")
        
        try:
            response = requests.get(url, timeout=15)
            if response.status_code != 200:
                print(f"Error de API HTTP {response.status_code}")
                time.sleep(5)
                continue
                
            data = response.json()
            docs = data.get('docs', [])
            
            if not docs:
                print("No hay más libros disponibles.")
                break
                
            for book in docs:
                if inserted_count >= TARGET_BOOKS:
                    break
                    
                title = book.get('title')
                authors = book.get('author_name', [])
                author = authors[0] if authors else None
                cover_id = book.get('cover_i')
                
                if not title or not author or not cover_id:
                    continue
                
                # Para conseguir la descripcion detallada hay que hacer otro request en OpenLibrary
                # Pero consumiria mucho. Usaremos un placeholder corto o una descripcion basica si esta disponible.
                # Como alternativa, podemos extraer 'first_sentence'
                sentences = book.get('first_sentence', [])
                description = " ".join(sentences) if sentences else f"A horror book written by {author}."
                
                cover_url = f"https://covers.openlibrary.org/b/id/{cover_id}-L.jpg"
                
                # Insert directly to database
                insert_query = """
                INSERT IGNORE INTO libros (google_id, titulo, autor, descripcion, portada, categoria, rating)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
                """
                # Generamos un ID fake ("OL-xxx") ya que guardamos en la columna google_id
                fake_google_id = f"OL-{book.get('key', 'unknown').split('/')[-1]}"
                
                try:
                    cursor.execute(insert_query, (
                        fake_google_id,
                        title,
                        author,
                        description,
                        cover_url,
                        "Horror",
                        None # Rating could be generated or null
                    ))
                    
                    if cursor.rowcount > 0:
                        inserted_count += 1
                        print(f"[{inserted_count}/{TARGET_BOOKS}] Insertado: {title} por {author}")
                        connection.commit()
                        
                except Error as e:
                    # Ignore duplicate keys
                    pass
                    
            page += 1
            time.sleep(1) # Be gentle with the API
            
        except Exception as e:
            print(f"Error durante la petición: {e}")
            time.sleep(2)
            
    if connection.is_connected():
        cursor.close()
        connection.close()
        
    print(f"\n¡Completado! Se han insertado {inserted_count} libros en la base de datos.")

if __name__ == "__main__":
    fetch_and_insert_books()
