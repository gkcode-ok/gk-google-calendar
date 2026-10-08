<template>
  <v-dialog v-model="model" max-width="480" persistent>
    <v-card>
      <v-card-title class="d-flex align-center ga-2">
        <v-icon color="error">mdi-link-off</v-icon>
        Desconectar Google Calendar?
      </v-card-title>
      <v-card-text>
        Tus eventos de la agenda se conservan, pero dejaran de sincronizarse con Google Calendar hasta que vuelvas a conectarlo.
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="model = false">Cancelar</v-btn>
        <v-btn color="error" variant="flat" :loading="loading" @click="emit('confirm')">Desconectar</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
const model = defineModel({ type: Boolean, default: false });
defineProps({ loading: { type: Boolean, default: false } });
const emit = defineEmits(['confirm']);
</script>
