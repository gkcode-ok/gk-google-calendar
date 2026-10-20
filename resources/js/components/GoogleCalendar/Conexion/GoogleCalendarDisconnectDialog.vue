<template>
  <v-dialog v-model="model" max-width="460" persistent>
    <v-card rounded="xl" class="gcal-disconnect-dialog">
      <div class="pa-6 pb-4">
        <div class="d-flex align-center ga-3 mb-3">
          <div class="gcal-disconnect-icon">
            <v-icon color="#d93025" size="22">mdi-link-variant-off</v-icon>
          </div>
          <div class="text-h6 font-weight-bold">¿Desvincular Google Calendar?</div>
        </div>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Tus eventos en Google Calendar y en la agenda local se conservarán intactos, pero dejarán de sincronizarse hasta que vuelvas a conectar tu cuenta.
        </p>
      </div>
      <v-card-actions class="px-6 pb-5 pt-2">
        <v-spacer />
        <v-btn variant="text" rounded="pill" class="text-none" @click="model = false">
          Cancelar
        </v-btn>
        <v-btn
          color="#d93025"
          variant="flat"
          rounded="pill"
          class="text-none px-5"
          :loading="loading"
          @click="emit('confirm')"
        >
          Desvincular
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
const model = defineModel({ type: Boolean, default: false });
defineProps({ loading: { type: Boolean, default: false } });
const emit = defineEmits(['confirm']);
</script>

<style scoped>
.gcal-disconnect-dialog {
  font-family: 'Google Sans', Roboto, 'Segoe UI', Arial, sans-serif;
}
.gcal-disconnect-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #fce8e6;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
</style>
