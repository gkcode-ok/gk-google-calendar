<template>
  <v-dialog v-model="dialog" fullscreen transition="dialog-bottom-transition" persistent>
    <template v-slot:activator="{ props }">
      <ButtonCrud :tipo="tipo" @show="dialog = true" @new="dialog = true" @limpiar="dialog = false" :props="props">
        <template #button-Nuevo-text>
          Nuevo Evento
        </template>
      </ButtonCrud>
    </template>
    <div v-if="dialog" class="h-100">
      <Crud :event="event" :tipo="tipo" @close="close" class="h-100" />
    </div>
  </v-dialog>
</template>

<script setup>
import { ref } from 'vue';
import Crud from './crud.vue';

defineProps({
  event: { type: [Object, Array], default: () => ({}) },
  tipo: { type: String, default: '' },
});
const emit = defineEmits(['refresh']);

const dialog = ref(false);

function close() {
  dialog.value = false;
  emit('refresh');
}
</script>
