<template>
  <v-dialog :model-value="modelValue" max-width="420" persistent @update:model-value="emit('update:modelValue', $event)">
    <v-card rounded="lg">
      <v-card-title class="text-h6 pt-5 px-6">{{ title }}</v-card-title>

      <v-card-text class="px-6 pb-0">
        <v-radio-group v-model="scope" hide-details>
          <v-radio value="instance" label="Solo este evento" />
          <v-radio value="following" label="Este evento y los siguientes" />
          <v-radio value="all" label="Todos los eventos" />
        </v-radio-group>
      </v-card-text>

      <v-card-actions class="px-6 pb-4">
        <v-spacer />
        <v-btn variant="text" class="text-none" @click="emit('update:modelValue', false)">Cancelar</v-btn>
        <v-btn :color="danger ? 'error' : 'primary'" variant="flat" rounded="pill" class="text-none"
          @click="emit('confirm', scope)">
          Aceptar
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  title: { type: String, default: 'Evento recurrente' },
  danger: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'confirm']);

const scope = ref('instance');

watch(() => props.modelValue, (open) => {
  if (open) scope.value = 'instance';
});
</script>
