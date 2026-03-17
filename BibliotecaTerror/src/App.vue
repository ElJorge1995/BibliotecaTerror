<script setup>
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import SiteHeader from './components/SiteHeader.vue'
import SiteFooter from './components/SiteFooter.vue'
import LoginModal from './components/LoginModal.vue'

const route = useRoute()
const isLoginModalOpen = ref(false)

const openLoginModal = () => {
  isLoginModalOpen.value = true
}

const closeLoginModal = () => {
  isLoginModalOpen.value = false
}

watch(
  () => route.fullPath,
  () => {
    isLoginModalOpen.value = false
  }
)
</script>

<template>
  <div class="app-shell">
    <SiteHeader @open-login="openLoginModal" />
    <main>
      <RouterView />
    </main>
    <SiteFooter />

    <LoginModal :is-open="isLoginModalOpen" @close="closeLoginModal" />
  </div>
</template>
