<template>
  <section class="register-page">
    <div class="register-card">
      <h1>Crear cuenta</h1>
      <p>Unete a la Biblioteca del Terror y guarda tus lecturas favoritas.</p>

      <form class="register-form" @submit.prevent>
        <label>
          Nombre completo
          <input type="text" placeholder="Ej. Elena Salazar" required />
        </label>

        <label>
          Correo electronico
          <input type="email" placeholder="tu@email.com" required />
        </label>

        <label>
          Nombre de usuario
          <input type="text" placeholder="Usuario lector" required />
        </label>

        <label>
          Contrasena
          <input type="password" placeholder="Minimo 8 caracteres" required />
        </label>

        <label>
          Confirmar contrasena
          <input type="password" placeholder="Repite tu contrasena" required />
        </label>

        <button type="submit">Registrarme</button>
      </form>
    </div>
  </section>
</template>

<style scoped>
.register-page {
  display: flex;
  justify-content: center;
  padding: 2.3rem 0 1rem;
}

.register-card {
  width: min(620px, 100%);
  border: 1px solid #303646;
  border-radius: 16px;
  padding: 1.6rem;
  background: linear-gradient(160deg, rgba(18, 21, 30, 0.95), rgba(10, 11, 16, 0.98));
}

h1 {
  margin: 0;
  color: #f6f6f7;
}

p {
  margin: 0.5rem 0 1.2rem;
  color: #b8bece;
}

.register-form {
  display: grid;
  gap: 0.9rem;
}

label {
  display: grid;
  gap: 0.35rem;
  color: #d6daE6;
  font-size: 0.9rem;
}

input {
  border: 1px solid #32384a;
  border-radius: 10px;
  padding: 0.65rem 0.8rem;
  background: #0f121a;
  color: #f0f1f4;
}

input:focus {
  outline: 2px solid rgba(237, 77, 77, 0.5);
  border-color: #ed4d4d;
}

button {
  margin-top: 0.4rem;
  border: 1px solid #ed4d4d;
  border-radius: 10px;
  background: #ed4d4d;
  color: #1a1313;
  font-weight: 700;
  padding: 0.7rem 0.9rem;
  cursor: pointer;
}

button:hover {
  background: #f26a6a;
}
</style>
