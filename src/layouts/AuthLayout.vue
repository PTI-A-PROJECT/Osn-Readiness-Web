<script setup>
import { useRoute } from 'vue-router'
import Card from 'primevue/card'

const route = useRoute()
</script>

<template>
  <main :class="route.name === 'register' ? 'min-h-screen' : 'min-h-screen flex items-center justify-center bg-gray-100 p-4'">
    <RouterView v-if="route.name === 'register'" />
    <Card v-else class="w-full max-w-md">
      <template #content>
        <RouterView />
      </template>
    </Card>
  </main>
</template>
